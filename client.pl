#!/usr/bin/perl

=begin comment
# Copyright (c) 2019 The Board of Trustees of Carnegie Mellon University.
#
#  Authors: Chris Rapier <rapier@psc.edu>
#
# Licensed under the Apache License, Version 2.0 (the "License");
# you may not use this file except in compliance with the License.
# You may obtain a copy of the License at
#
#   http://www.apache.org/licenses/LICENSE-2.0
#
# Unless required by applicable law or agreed to in writing, software
# distributed under the License is distributed on an "AS IS" BASIS,
# WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
# See the License for the specific language governing permissions and
# limitations under the License. *
=end comment
=cut

# this is started as a test client but it became
# the basis of the processing engine.
# Long story short - this is the process that parses
# incoming requests from the WebUI and turns them into actions
# that modify the database, sends requests to the ExaBGP interface
# and parses any response from there

use strict;
use warnings;
use IO::Socket::INET;
use IO::Socket::Timeout;
use CryptX;
use Crypt::PK::RSA;
use Crypt::PK::DH qw(dh_shared_secret);
use Crypt::Mode::CTR;
use Crypt::Random qw(makerandom);
use Config::Tiny;
use Getopt::Std;
use Data::Dumper;
use JSON;
use Switch;
use DBI;
use Net::Netmask;
use Scalar::Util qw(looks_like_number);
use App::Genpass;      # create random password
use Email::Sender::Simple qw(sendmail);
use Email::Sender::Transport::SMTP;
use Email::Simple;
use Email::Simple::Creator;

# this is to ensure that the password
# functions in the php side match what
# we generate here
use PHP::Functions::Password qw(:all);
use Log::Log4perl;

my %options  = ();
my $config   = Config::Tiny->new();
my $cfg_path = "/usr/local/etc/client.cfg";
my $logger;    # this is the object for the logger it is instantianted later
my $transport; #SMTP transport options 

sub readConfig {
    if ( !-e $cfg_path ) {
	print STDERR "Config file not found at $cfg_path. Exiting.\n";
	exit;
    } else {
	$config = Config::Tiny->read($cfg_path);
	my $error = $config->errstr();
	if ( $error ne "" ) {
	    print STDERR "Error: $error. Exiting.\n";
	    exit;
	}
    }
}

# we need to make sure that all of the configuration
# variables exist and validates them as best we can.
sub validateConfig {
    #check key data
    if ( !defined $config->{'keys'}->{'client_private_rsa'} ) {
	print STDERR
	    "Missing client's private RSA key location in config file. Exiting.\n";
	exit;
    }
    if ( !defined $config->{'keys'}->{'server_public_rsa'} ) {
	print STDERR
	    "Missing server's public RSA key infomation in config file. Exiting.\n";
	exit;
    }

    #check interface data
    if ( !defined $config->{'interface'}->{'host'} ) {
	print STDERR "ExaBGP interface host not defined. Exiting.\n";
	exit;
    }
    if ( !defined $config->{'interface'}->{'port'} ) {
	print STDERR "ExaBGP interface port not defined. Exiting.\n";
	exit;
    }

    #check server data
    if ( !defined $config->{'server'}->{'host'} ) {
	print STDERR "Server host not defined. Exiting.\n";
	exit;
    }
    if ( !defined $config->{'server'}->{'port'} ) {
	print STDERR "Server port not defined. Exiting.\n";
	exit;
    }

    #test key files
    if ( !-e $config->{'keys'}->{'client_private_rsa'} ) {
	print STDERR
	    "The client's private RSA file is missing or not readable. Exiting.\n";
	exit;
    }
    if ( !-e $config->{'keys'}->{'server_public_rsa'} ) {
	print STDERR
	    "The server's public RSA file is missing or not readable. Exiting.\n";
	exit;
    }

    #check the database information
    if ( !defined $config->{'database'}->{'host'} ) {
	print STDERR "Database host not defined. Exiting.\n";
	exit;
    }
    if ( !defined $config->{'database'}->{'port'} ) {
	print STDERR "Database port not defined. Exiting.\n";
	exit;
    }
    if ( !defined $config->{'database'}->{'user'} ) {
	print STDERR "Database user not defined. Exiting.\n";
	exit;
    }
    if ( !defined $config->{'database'}->{'password'} ) {
	print STDERR "Database password not defined. Exiting.\n";
	exit;
    }

    # check the duration mix/max for bh requests
    if ( !defined $config->{'duration'}->{'min'} ) {
	$config->{'duration'}->{'min'} = 0;
    }
    if ( !defined $config->{'duration'}->{'max'} ) {
	$config->{'duration'}->{'max'} = 2160;
    }

    #check the email transport information
    if (defined $config->{'email'}->{'host'} && defined $config->{'email'}->{'port'}) {
	$transport = Email::Sender::Transport::SMTP->new
	    ({host => $config->{'email'}->{'host'},
	      port => $config->{'email'}->{'port'}
	     });
    }
    if (defined $config->{'email'}->{'host'} && !defined $config->{'email'}->{'port'}) {
	$transport = Email::Sender::Transport::SMTP->new
	    ({host => $config->{'email'}->{'host'}
	     });
    }
}

#start the authentication process.
sub authorize {
    my $socket     = shift;
    my $authorized = -1;

    #send the auth request to the server
    $logger->debug("ST: sending auth");
    print $socket "auth\n";

    #we will get two responses from the server
    $logger->debug("ST waiting on sig_srv");
    my $sig_srv = <$socket>;
    chomp $sig_srv;
    $logger->debug("ST waiting on dh_public_srv");
    my $dhpublic_srv = <$socket>;
    chomp $dhpublic_srv;

    # convert them both back to the binary format
    $sig_srv      = pack( qq{H*}, $sig_srv );
    $dhpublic_srv = pack( qq{H*}, $dhpublic_srv );

    #verify the signature
    my $rsapub = Crypt::PK::RSA->new( $config->{'keys'}->{'server_public_rsa'} );
    if ( !$rsapub->verify_message( $sig_srv, $dhpublic_srv ) ) {
	print STDERR
	    "Could not verify the RSA signature for the server. Exiting.\n";
	$logger->error(
	    "Could not verify the RSA signature for the server. Exiting.");
	exit;
    }

    #We verifed the servers DH key so load our keys.
    my $pk = Crypt::PK::DH->new();
    $pk->generate_key(128);

    my $dhprivate_cli = Crypt::PK::DH->new( \$pk->export_key('private') );
    my $dhpublic_cli  = $pk->export_key('public');

    #load the client's private RSA key
    my $rsaprivate = Crypt::PK::RSA->new( $config->{'keys'}->{'client_private_rsa'} );

    #compute the signature of the private key
    my $sig_client = $rsaprivate->sign_message($dhpublic_cli);

    #convert the signature in to a text string
    $sig_client = unpack( qq{H*}, $sig_client );

    #convert the public dh key to text
    $dhpublic_cli = unpack( qq{H*}, $dhpublic_cli );

    $dhpublic_srv = Crypt::PK::DH->new( \$dhpublic_srv );

    #generate the shared secret
    my $clientsecret = dh_shared_secret( $dhprivate_cli, $dhpublic_srv );

    #again we convert that to text for ease of transmission
    $clientsecret = unpack( qq{H*}, $clientsecret );

    $logger->debug("Got the secret $clientsecret");

    # we now have all three elements that the server is waiting on
    # so send it over to them

    my $keydata = "$sig_client:$dhpublic_cli:$clientsecret\n";
    $logger->debug("ST: sending key data");
    print $socket $keydata;

    #wait until we get an auth response from the server
    $logger->debug("ST Waiting on auth");

    $authorized = <$socket>;
    chomp $authorized;

    $authorized = decrypt($authorized);
        
    $logger->debug("authorized = $authorized");

    return $authorized;
}


# for various dumb reasons I'm going to use makrandom to generate
# a string of characers (the key and IV in the AES encrypt/decrypt needs to be
# a string and not a number and quoting the variable isn't making it work better
# so we are doing this. Recursively generate a string of a desired length using
# printable ASCII characters.
# as a note: this isn't necessary the best way to do this
# I could have just had a loop that loaded the random numbers into an array
# and then gone through that array to convert it into chars. Doing it
# recursively was just more fun
# input length - int - length of string
#       string - char - generated string
#       note : when you initially call genRandomString 'string' should
#                be null
#output char (string of desired length)
sub genRandomString {
    my $length = shift @_;
    my $string = shift @_;
    my $num = makerandom(Size=>7, Strength =>0); #2^7 = 128
    
    if ($num < 33 or $num == 127) {
	#anything lower than 33 is nonprintable 127 is del
        &genRandomString($length, $string);
    }
    $string .= chr($num);
    if (length($string) == $length) {
        return $string;
    } else {
        &genRandomString($length, $string);
    }
}

# we need the server's public key in order to encrypt things
# because of the way we set up the server we need to send this as a single
# string of text. As such, we have to unpack it before we return
# input : char string
# output : char string
sub encrypt {
    my $enclear = shift @_;
    chomp $enclear;
    my $srv_public;
    my $cryptkey;
    my $ciphertext;
    
    # create a key to use with the AES encryption 16 bytes
    my $key = genRandomString(16); 
    
    #create an initialization vector 16 bytes
    my $iv = genRandomString(16);
    
    #load the server's public key
    eval {$srv_public = Crypt::PK::RSA->new($config->{'keys'}->{'server_public_rsa'})};
    if ($@) {
	$logger->error("Error loading server's public key: $@");
	return -1;
    }
    
    #encrypt the AES key with the public key
    eval {$cryptkey = unpack(qq{H*}, $srv_public->encrypt($key))};
    if ($@) {
	$logger->error("Error loading server's public key: $@");
	return -1;
    }

    #instantiate AES 
    my $AES = Crypt::Mode::CTR->new('AES');

    #encrypt the data with the unencrypted AES key and the iv
    eval {$ciphertext = unpack(qq{H*}, $AES->encrypt($enclear, $key, $iv))};
    if ($@) {
	$logger->error("Error loading server's public key: $@");
	return -1;
    }

    #prepend the data with the iv and the encrypted aes key 
    $ciphertext = $iv . $cryptkey . $ciphertext;

    return $ciphertext;
}

# we need our private key in order to decrypt things
# because we are unpacking the binary crypto stream into a char string
# we need to pack it back into a binary before we can decrypt it
# input : char string
# output: char string
sub decrypt {
    my $ciphertext = shift @_;
    my $enclear;
    my $key;
    my $cli_private;
    $logger->debug("Decrypt: Received $ciphertext\n");
    
    #we need to grab the 1st 16 bytes as the iv
    my $iv = substr($ciphertext, 0, 16);
    
    # we need to grab the next 512 bytes as the encrypted key
    # Even though the initial key is 16 bytes it's RSA encrypted so we
    # end up with 512 bytes
    my $cryptkey = substr($ciphertext, 16, 512);

    # pack it back into a binary representation
    $cryptkey = pack(qq{H*}, $cryptkey);
    
    # now put the rest of the data back into ciphertext
    $ciphertext = substr($ciphertext, 528);
    
    # pack it back into a binary representation
    $ciphertext = pack(qq{H*}, $ciphertext); 
    
    #load the client's private RSA key
    eval {$cli_private = Crypt::PK::RSA->new($config->{'keys'}->{'client_private_rsa'})};
    if ($@) {
	$logger->error("Error loading client key: $@");
	return -999;
    }

    #decrypt the AES key
    eval {$key = $cli_private->decrypt($cryptkey)};
    if ($@) {
	$logger->error("Error decrypting key: $@");
	return -999;
    }
    
    #instantiate AES 
    my $AES = Crypt::Mode::CTR->new('AES');

    #encrypt the data with the unencrypted AES key and the iv
    eval {$enclear = $AES->decrypt($ciphertext, $key, $iv)};
    if ($@) {
	$logger->error("Error decrypting message: $@");
	return -999;
    }
    return $enclear;
}


#open a connection to the server
sub openExaSocket {
    $logger->debug(
	"About to open ExaSocket: $config->{'interface'}->{'host'}, $config->{'interface'}->{'port'}"
	);
    my $sock; 
    eval {$sock = IO::Socket::INET->new(
	      PeerAddr => $config->{'interface'}->{'host'},
	      PeerPort => $config->{'interface'}->{'port'},
	      Proto    => 'tcp'
	      )};
    if ($@) {
	$logger->error("Could not open socket to ExaBGP interface: $@");
	return -1;
    }
    return $sock;
}

#this creates the local listening socket to handle incoming
#requests from the web app
sub instantiateServer {
    my $sock = IO::Socket::INET->new(
	LocalAddr => $config->{'server'}->{'host'},
	LocalPort => $config->{'server'}->{'port'},
	Listen    => SOMAXCONN,
	Proto     => 'tcp',
	Reuse     => 1,
	Timeout   => 1,
	);
    die "Socket could not be created. Reason: $!\n" unless ($sock);
    return $sock;
}

sub mainloop {
    my $parent = shift;
    
    $SIG{CHLD} = sub { wait() };
    while (1) {
	while ( my $child = $parent->accept() ) {
	    my $pid = fork();
	    if ( !defined($pid) ) {
		print STDERR "Cannot fork child: $!\n";
		close($child);
	    }
	    IO::Socket::Timeout->enable_timeouts_on($child);
	    $child->read_timeout(0.1);
	    $child->write_timeout(0.1);
	    
	    # Child process
	    while ( defined( my $buf = <$child> ) ) {
		chomp $buf;
		$logger->debug("buffer is $buf");
		
		#inbound request is in json - no new lines/indents though
		my $json = validateJson($buf);
		if ( $json != -1 ) {
		    $logger->debug("About to process inbound request");
		    my $status = processInboundRequests( $child, $json );
		    $logger->debug("I got $status");
		    print $child "$status\n";
		} else {
		    print $child "Invalid JSON\n";
		}
		exit(0);    # Child process exits when it is done.
	    }    # else 'tis the parent process, which goes back to accept()
	}
    }
}

# json format is (so far)
# {'action' => 'string',
#  'request' => 'string'}

#validate the inbound json request
sub validateJson {
    my $jsonstring = shift;
    my $text;

    eval {$text = decode_json($jsonstring)};
    if ($@) {
	$logger->error("Invalid JSON passed to client");
	return -1;
    }    
    return $text;
}

# some basic input validation 
sub validateBHInput {
    my $json = shift;

    # we are validating the input at the WebUI but we might as well maintain
    # it here
    my $block = Net::Netmask->new2( $json->{bh_route} );
    if ( !$block ) {
	#invalid ip address
	return -1;
    }

    # verify that duration is a number greater than the configured
    # min and max (0 and 2160 by default)
    if ( !looks_like_number( $json->{bh_lifespan} ) ) {
	return -2;
    }

    #check to see if they are making an immortal blackhole
    if ( $json->{bh_lifespan} == 9999 ) {
	return 1;
    }
    # ensure the time period is inbounds
    if ($json->{bh_lifespan} < $config->{'duration'}->{'min'}
	|| $json->{bh_lifespan} > $config->{'duration'}->{'max'}) {
	return -3;
    }
    return 1;
}

# handle the incoming requests from the inbound client here
# these requests are in valid json format
sub processInboundRequests {
    my $child = shift;    # this is the interface to the webapp
    my $json  = shift;    # keep in mind that this is not a json object
    # but the decoded json object. Yeah, nomenclature
    # is annoying

    # a gigantic switch statement is probably the wrong way to do this
    # but it's working effectively at this point. I shoudl refactor this
    # to use a more managable method
    # eg - have a hash of case names with the value of a function
    # %case = ("foo" => "bar");
    # $case{$json->{'action'})();
    # would call the function bar if $json->action is foo
    # the problem is that passing arguments would be realtive opaque
    # in terms of maintenance. 
    switch ( $json->{'action'} ) {
	case /clibeat/i {
	    return 1;
	}
	case /exabeat/i {
	    my $status = sendtoExaBgpInt( "", "", "exabeat" );
	    return $status;
	}
	case /bgpbeat/i {
	    my $status = sendtoExaBgpInt( "", "", "bgpbeat" );
	    return $status;
	}
	case /quit/i {
	    print $child "Closed connection\n";
	    close($child);
	    return 1;
	}
	case /blackhole/i {
	    # verify if the IP address/mask is valid.
	    # note date and time is validated at user input
	    my $valid = validateBHInput($json);
	    if ( $valid != 1 ) {
		return $valid;
	    }
	    
	    # add it to the DB first in case there is an error adding it
	    $logger->debug("About to add route to database");
	    my $db_stat = &addRouteToDB($json);
	    if ( $db_stat != 1 ) {
		return "Error adding route to Database: $db_stat";
	    }
	    
	    $logger->debug("About to interface with ExaBGP");
	    my $status = sendtoExaBgpInt( $child, $json, "add" );
	    
	    # add route to the database
	    if ( $status == -1 ) {
		return "Error interfacing with ExaBGP";
	    }
	    if ($status == -2 ) {
		return "Problems encountered with encrypting the request";
	    }
	    if ($status == -3) {
		return "Problems encountered with decrypting the response";
	    }
	    return 1;
	}
	case /listexisting/i {
	    
	    #get a list of the existing blackhole routes from the DB
	    my $listing = &listExistingBH(
		"all",
		$json->{'bh_customer_id'},
		$json->{'bh_user_role'}
		);    #returns a json object
	    return $listing;
	}
	case /listactive/i {
	    
	    # get a list of only active BH routes from the DB
	    my $listing = &listExistingBH(
		"active",
		$json->{'bh_customer_id'},
		$json->{'bh_user_role'}
		);    #returns json
	    return $listing;
	}
	case /edit/i {
	    
	    # edit the route in the DB
	    my $status = &editRouteInDB($json);
	    my $result = encode_json( { "results" => $status } ) . "\n";
	    return $result;
	}
	case /updateUser/i {
	    
	    # edit the user information in the database
	    my $status = &updateUser($json);
	    return $status;
	}
	case /resetPassword/i {
	    my $status = &resetPassword($json);
	    return $status;
	}
	case /changePassword/i {
	    my $status = &changePassword($json);
	    return $status;
	}
	case /addUser/i {
	    my $status = &addUser($json);
	    return $status;
	}
	case /confirmDeleteUser/i {
	    my $status = &deleteUser($json);
	    return $status;
	}
	case /updateCustomer/i {
	    my $status = &updateCustomer($json);
	    return $status;
	}
	case /addCustomer/i {
	    my $status = &addCustomer($json);
	    return $status;
	}
	case /confirmDeleteCustomer/i {
	    my $status = &deleteCustomer($json);
	    return $status;
	}
	case /pushchanges/i {
	    
	    # this forces the db to push all pending changes out to the
	    # exabgp server. Essentially it normalizes the bh database to the
	    # exabgp server.
	    my $status = &pushChanges( $child, $json );
	    return $status;
	}
	#am I even using this? 
	case /deleteselection/i {
	    #delete one blackhole route
	    my $status = sendtoExaBgpInt( $child, $json, "del" );
	    if ( $status == -1 ) {
		return "Error interfacing with ExaBGP";
	    }
	    if ($status == -2 ) {
		return "Problems encountered with encrypting the request";
	    }
	    if ($status == -3) {
		return "Problems encountered with decrypting the response";
	    }
	    
	    # set route to inactive route to the database
	    my $db_stat = &inactivateRouteInDB($json);
	    return $status;
	}
	case /email/i {
	    my $status = &routeModNotification($json);
	    return $status;
	}		    
	# return a list of extant routes as reported by ExaBGP
	case /getexaroutes/i {
	    my $status = &listExaRoutes($child, $json);
	    return $status;
	}
    }
}

# get a list of routes as reported by the exabgp server
# filter the routes to ensure that the user can only see routes
# that they have access to
sub listExaRoutes {
    my $child = shift @_;
    my $request = shift @_;
    #request will hold bh_customer_id
    #                  bh_user_role
    # we need to grab the list of routes in the exabgp server

    my $results = sendtoExaBgpInt( $child, "", "dump" );
    
    my %exablocks;           #blocks from the server
    my %dbblocks;            # blocks from the database
    my %protected;           # blocks from the protected config information
    
    # these are the full lines returned by the exabgp server
    my @exaroutes = split( /\n/, $results );
    
    #we really only need the routes from it though
    # line format is
    # neighbor ip proto unicast route nexthop target
    # so we can split the line and just grab the routes and drop them into
    # an array
    
    foreach my $exaroute (@exaroutes) {
	# data is whitespace separated.
	my @split_route = split( / +/, $exaroute );
	$exablocks{ $split_route[4] } = 1;
    }
    # if they aren't admin/staff then remove any route
    # they don't control from the struct
    if ( $request->{'bh_user_role'} == 1 ) {
	my $exablocks_ref =
	    &customerOnlyRoutes( \%exablocks, $request->{'bh_customer_id'} );
	%exablocks = %$exablocks_ref;
    }
    return encode_json(\%exablocks)
}

# send the requested route or action to the ExaBGP server
# via the ExaBGP Interface
sub sendtoExaBgpInt {
    my $child   = shift;
    my $request = shift;
    my $action  = shift;
    
    #delete one blackhole route
    my $exa_socket = &openExaSocket;    #create ExaBGP interface socket
    if ($exa_socket == -1) {
	print $child "Could not connect to ExaBGP interface\n";
    }
    if ( !&authorize($exa_socket) ) {   #authorize this process
	$logger->error("ExaBGP authorization failed");
	print $child "Authorization failed\n";
	close($exa_socket);
	close($child);
	return;
    }
    my $status = &blackHole( $exa_socket, $request, $action );
    my $unpacked_request = objectToString($request);
    $logger->debug("I sent $unpacked_request and $action and received $status");
    close($exa_socket);
    return $status;
}

# grab a list of the current blackhole routes from the database
# turn them into a json object
# return the object to the browser for display
# action = "active"
sub listExistingBH {
    # get the database handle
    my $action      = shift @_;      # to access different subsets of data
    my $customer_id = shift @_;
    my $user_role   = shift @_;
    my $dbh         = &DBSocket();
    my $query;
    my $sth;
    my $result_json;
    my @name_array;
    my @results;

    if ( $dbh == -1 ) {
	$dbh->disconnect();
	return "Could not connect to database";
    }

    # build an array that lets us translate the customer_id into the
    # customer_name
    $query = "SELECT bh_customer_id, bh_customer_name
              FROM bh_customers";
    $sth = $dbh->prepare($query);
    $sth->execute();
    while ( my @row = $sth->fetchrow_array() ) {
	$name_array[ $row[0] ] = $row[1];
    }
    $sth->finish();
    
    # determine what filters we are going to use
    if ( $user_role == 1 ) {
	if ( $action eq "active" ) {
	    
	    #this is regular user so we need to limit their view
	    $query = "SELECT * 
                          FROM bh_routes 
                          WHERE bh_customer_id = ?
                          AND bh_active = 1";
	} else {
	    $query = "SELECT * 
                          FROM bh_routes 
                          WHERE bh_customer_id = ?";
	}
    } else {
	# they are staff or BH manager
	$query = "SELECT * FROM bh_routes";
	if ( $action eq "active" ) {
	    $query .= " WHERE bh_active = 1";
	}
    }
    $sth = $dbh->prepare($query);
    if ( $user_role == 1 ) {
	$sth->bind_param( 1, $customer_id );
    }
    $sth->execute();
    
    #loop through the results and build the array of hashrefs
    #and see if the user has the rights to edit the route
    while ( my $result = $sth->fetchrow_hashref() ) {
	$result->{'bh_customer_name'} = $name_array[ $result->{'bh_customer_id'} ];
	$result->{'bh_owner_name'} = $name_array[ $result->{'bh_owner_id'} ];
	
	# if the customer_id is not the same as the owner id they don't have rights
	if ( $result->{'bh_customer_id'} == $result->{'bh_owner_id'} ) {
	    $result->{'editable'} = 1;
	} else {
	    $result->{'editable'} = -1;
	}
	push @results, $result;
    }
    
    $sth->finish();
    $dbh->disconnect();
    
    #convert to json
    $result_json = encode_json \@results;
    return $result_json;
}

# simply get a socket to the database. 
sub DBSocket {
    my $dbh;
    my $data_source =
	"DBI:mysql:database="
	. $config->{database}->{database}
        . ";host="
	. $config->{database}->{host}
        . ";port="
	. $config->{database}->{port};
    eval {$dbh = DBI->connect($data_source, $config->{database}->{user},
			      $config->{database}->{password},
			      {'RaiseError' => 0, 'PrintError' => 0})};
    if ($@) {
	$logger->error("Could not open socket to database: $@");
	return -1;
    }
    return $dbh;
}

# create, delete, or modify black holes
# the socket in question is the exabgp interface socket
# input:
#       srv_socket = socket to exabgp interface
#       route      = route to blackhole (may be null)
#       action     = What they are looking to do
# output:
#       char = could be a list of routes or a success label
#       -1   = unknown failure from exabgp
#       -2   = local decrypt failure
#       -3   = local encrypt failure
#       -4   = remote encrypt failure
#       -5   = remote decrypt failure
sub blackHole {
    my $srv_socket = shift;    # socket to the exabgp_interface
    my $route      = shift;    # the blackhole route
    my $action     = shift;    # what we want the exabgp interface to do
    # 'add' = add route to black hole
    # 'del' = remove route
    # 'dump' = dump list of all routes in exabgp
    my $status;    # this is what we get back from the exabgp interface
    my %request_struct;
    
    # build a json structure to send to the Exa Interface
    $request_struct{'action'} = $action;
    $request_struct{'route'}  = $route;
    my $request = encode_json \%request_struct;

    #encrypt the request and make sure that we did not run into problems
    $request = encrypt($request);

    # we need to use looks_like_number to differentiate between
    # an encrypted string and a numerical error code.
    # TODO: You should set this up so that all communication between client
    # and server have an error field, error message field, and payload.
    # On second thought - thats not a good idea as it will leak information
    # you can specifically probe the communications to determine which
    # actions produce what kind of error. Unlikley that anyone would
    # be able to effectively exploit it but why be sloppy? 
    if (looks_like_number($request)) {
	if ($request == -1) {
	    return -3;
	}
    }
    
    # everything worked so send the request
    print $srv_socket $request . "\n";

    # wait for a result
    $status = <$srv_socket>;
    chomp $status;
    
    # we can get multiple responses
    # -2 = encrypt failure on remote
    # -3 = decrypt failure on remote
    # char string = encrypted message (what we want to get)
    # check to see if we got a bare value back. This indicates a crypto failure on the
    # remote end

    if (looks_like_number($status)) {
	if ($status == "-2") {
	    return -4;
	}
	if ($status == "-3") {
	    return -5;
	}
    }
    $logger->debug("Received $status");
    
    #decrypt whatever we received
    $status = decrypt($status);
    chomp $status;

    # -999 indicates a local crypto failure while decrypting the response
    # why -999? Because we can get other negative values as valid responses
    if (looks_like_number($status)) {
	if ($status == "-999") {
	    return -2;
	}
    }
    
    $logger->debug("status in function blackHole is $status");
    
    # if we are dumping routes we'll just get a blob of text
    # this isn't the best way to do this because if we are dumping
    # and we get an error then we will miss it. 
    # TODO: Fix this
    if ( $action eq "dump" ) {
	return $status;
    }
    
    # for other requests we get a success status
    if ( $status eq "Success" ) {
	return 1;
    }
    #something unexpected happened
    return -1;
}

# the user has made a change to a route using the table edit functionality
# There are a few thing they may have done
# 1: Set the route to inactive
# 2: changed the route
# 3: changed the duration
# if 1 then withdraw route
# if 2 withdraw route and send new route
# if 3 only update the db
# if 1 && 2 then then doesn't make sense but we need to
# plan for it anyway in that case we withdraw the route
# and only update the db
sub editRouteInDB {
    my $json = shift @_;
    $logger->debug("about to edit route in DB");
    
    # if for some reason we don't have a valid bh_index
    # and it is equal to NULL then the update fails
    if ( !defined $json->{'bh_index'} ) {
	return -4;
    }
    
    #validate the route and duration
    my $valid = validateBHInput($json);
    if ( $valid != 1 ) {
	return $valid;
    }
    
    # get a socket to the database
    my $dbh   = &DBSocket();
    if ($dbh == -1) {
	$dbh->disconnect();
	return "Could not access the database.";
    }

    my $query = "SELECT bh_route FROM bh_routes WHERE bh_index = ?";
    my $sth   = $dbh->prepare($query);
    $sth->bind_param( 1, $json->{'bh_index'} );
    $sth->execute();
    if ( $sth->err() ) {
	my $error = $sth->errstr();
	$sth->finish();
	$dbh->disconnect();
	return ($error);
    }
    if ( $sth->rows != 1 ) {
	$sth->finish();
	$dbh->disconnect();
	return "No results for this route index";
    }
    my @route_orig = $sth->fetchrow_array();
    
    # get the original route based on the index number
    
    my %route;
    if ( $json->{'bh_active'} == 0 ) {
	#withdraw the route
	$route{'bh_route'} = $route_orig[0];
	sendtoExaBgpInt( "", \%route, "del" );
    }
    
    $logger->debug( "Inbound json object is" . objectToString($json) );
    if ( $json->{'bh_active'} == 1 ) {
	
	# if the new route is different than the old route then
	# withdraw it first
	if ( $json->{'bh_route'} ne $route_orig[0] ) {
	    $route{'bh_route'} = $route_orig[0];
	    sendtoExaBgpInt( "", \%route, "del" );
	}
	#now add the new route
	sendtoExaBgpInt( "", $json, "add" );
    }
    
    #local $dbh->{TraceLevel} = "3|SQL";
    
    # update the database with the new information
    $query = "UPDATE bh_routes
	      SET    bh_route = ?,
		     bh_lifespan = ?,
		     bh_active = ?,
		     bh_owner_id = ?,
		     bh_comment = ?			 
	      WHERE  bh_index = ?";
    $sth = $dbh->prepare($query);
    $sth->bind_param( 1, $json->{'bh_route'} );
    $sth->bind_param( 2, $json->{'bh_lifespan'} );
    $sth->bind_param( 3, $json->{'bh_active'} );
    $sth->bind_param( 4, $json->{'bh_owner_id'} );
    $sth->bind_param( 5, $json->{'bh_comment'} );
    $sth->bind_param( 6, $json->{'bh_index'} );
    $sth->execute();
    
    if ( $sth->err() ) {
	my $error = $sth->errstr();
	$sth->finish();
	$dbh->disconnect();
	$logger->error($error);
	return ($error);
    }
    $sth->finish();
    $dbh->disconnect();
    return "Success";
}

# get the incoming request and insert it in to the database
# this comes after the request has been sent ot the exabgp server
# I may want to change this so that instead of writin from here to the
# ex server I write the the DB and then trigger a process that
# normalizes the ex server to the DB.
sub addRouteToDB {
    my $json = shift @_;
    my $dbh  = &DBSocket();
    my $query;
    my $sth;
    my $status = 1; #assume that it will work

    #make sure we can access the DB
    if ($dbh == -1) {
	$dbh->disconnect();
	return "Could not access the database.";
    }
    
    #    local $dbh->{TraceLevel} = "3|SQL";
    $query = "INSERT INTO bh_routes
			  (bh_route,
			   bh_lifespan,
			   bh_starttime,
			   bh_requestor,
			   bh_customer_id,
			   bh_owner_id,
			   bh_comment,
			   bh_active)
              VALUES      (?,?,?,?,?,?,?,1);";
    $sth = $dbh->prepare($query);
    my $datetime = $json->{'bh_startdate'} . " " . $json->{'bh_starttime'};
    $sth->bind_param( 1, $json->{'bh_route'} );
    $sth->bind_param( 2, $json->{'bh_lifespan'} );
    $sth->bind_param( 3, $datetime );
    $sth->bind_param( 4, $json->{'bh_requestor'} );
    $sth->bind_param( 5, $json->{'bh_customer_id'} );
    $sth->bind_param( 6, $json->{'bh_owner_id'} );
    $sth->bind_param( 7, $json->{'bh_comment'} );
    $sth->execute();
    
    #need better error checking
    if ( $sth->err() ) {
	#it didn't work so over write the assumption made above
	$status = $sth->errstr();
    }
    $sth->finish();
    $dbh->disconnect();
    
    return $status;
}

# get an incoming request to update a customer
# TODO: updateCustomer and addCustomer can be reduced into
# one function if we pass a flag
sub updateCustomer {
    my $json = shift;    #incoming json
    my $update;          # json string
    my %block_hash;
    
    # get the socket

    my $dbh = &DBSocket;
    if ($dbh == -1) {
	$dbh->disconnect();
	return "Could not access the database.";
    }

    # we need to convert the CSV values to arrays
    my @asns = split( ",", $json->{'customer-asns'} );
    map { s/^\s+|\s+$//g; } @asns;
    my @vlans = split( ",", $json->{'customer-vlans'} );
    map { s/^\s+|\s+$//g; } @vlans;
    my @blocks = split( ",", $json->{'customer-blocks'} );
    map { s/^\s+|\s+$//g; } @blocks;
    
    # build the hash of block data
    $block_hash{'name'}   = $json->{'customer-name'};
    $block_hash{'ASNs'}   = [@asns];
    $block_hash{'vlans'}  = [@vlans];
    $block_hash{'blocks'} = [@blocks];
    
    # convert the hash into a json string
    $update = encode_json \%block_hash;
    
    #we have all of the necessary structures so now we can build the query
    my $query = "UPDATE     bh_customers
		 SET   	    bh_customer_name = ?,
                  	    bh_customer_blocks = ?
		 WHERE      bh_customer_id = ?";
    my $sth = $dbh->prepare($query);
    $sth->bind_param( 1, $json->{'customer-name'} );
    $sth->bind_param( 2, $update );
    $sth->bind_param( 3, $json->{'bh_customer_id'} );
    $sth->execute();
    
    if ( $sth->err() ) {
	my $error = $sth->errstr() . "\n";
	$sth->finish();
	$dbh->disconnect();
	return ($error);
    }
    return "Success\n";
}

# add a customer to the database
# incoming data is in json format
# return the word 'Success' on success ;)
# really need to convert these magic words into
# numerical values.
sub addCustomer {
    my $json = shift;    #incoming json
    my $update;          # json string
    my %block_hash;
    
    # get the socket
    my $dbh = &DBSocket;

    if ($dbh == -1) {
	$dbh->disconnect();
	return "Could not access the database.";
    }
    
    # we need to convert the CSV values to arrays
    my @asns = split( ",", $json->{'customer-asns'} );
    map { s/^\s+|\s+$//g; } @asns;
    my @vlans = split( ",", $json->{'customer-vlans'} );
    map { s/^\s+|\s+$//g; } @vlans;
    my @blocks = split( ",", $json->{'customer-blocks'} );
    map { s/^\s+|\s+$//g; } @blocks;
    
    # build the hash of block data
    $block_hash{'name'}   = $json->{'customer-name'};
    $block_hash{'ASNs'}   = [@asns];
    $block_hash{'vlans'}  = [@vlans];
    $block_hash{'blocks'} = [@blocks];
    
    # convert the hash into a json string
    $update = encode_json \%block_hash;
    
    #we have all of the necessary structures so now we can build the query
    my $query = "INSERT INTO bh_customers
			     (bh_customer_name, bh_customer_blocks)
		 VALUES      (?,?)";
    my $sth = $dbh->prepare($query);
    $sth->bind_param( 1, $json->{'customer-name'} );
    $sth->bind_param( 2, $update );
    $sth->execute();
    if ( $sth->err() ) {
	my $error = $sth->errstr() . "\n";
	$sth->finish();
	$dbh->disconnect();
	return ($error);
    }
    return "Success\n";
}

# get an incoming request to update a user and update the database.
sub updateUser {
    my $json = shift;
    my $role_insert;
    my $role_active;
    my $dbh = &DBSocket();
    if ($dbh == -1) {
	$dbh->disconnect();
	return "Could not access the database.";
    }

    my $query = "UPDATE bh_users 
	     	 SET    bh_user_name = ?,
                        bh_user_fname = ?,
                        bh_user_lname = ?, 
                        bh_user_email = ?,
                        bh_user_affiliation = ?
                 WHERE  bh_user_id = ?";
    my $sth = $dbh->prepare($query);
    $sth->bind_param( 1, $json->{'user-username'} );
    $sth->bind_param( 2, $json->{'user-fname'} );
    $sth->bind_param( 3, $json->{'user-lname'} );
    $sth->bind_param( 4, $json->{'user-email'} );
    $sth->bind_param( 5, $json->{'user-affiliation'} );
    $sth->bind_param( 6, $json->{'bh_user_id'} );
    $sth->execute();
    
    if ( $sth->err() ) {
	my $error = $sth->errstr();
	$sth->finish();
	$dbh->disconnect();
	return ($error);
    }
    $sth->finish();
    
    # we need to do the active and role as seperate queries because
    # there are no named placeholders in this engine
    
    if ( defined( $json->{'user-role'} ) ) {
	$query = "UPDATE bh_users
                  SET    bh_user_role = ?
                  WHERE  bh_user_id = ?";
	$sth = $dbh->prepare($query);
	$sth->bind_param( 1, $json->{'user-role'} );
	$sth->bind_param( 2, $json->{'bh_user_id'} );
	$sth->execute();
	if ( $sth->err() ) {
	    my $error = $sth->errstr();
	    $sth->finish();
	    $dbh->disconnect();
	    return ($error);
	}
	$sth->finish();
    }
    if ( defined( $json->{'user-active'} ) ) {
	$query = "UPDATE bh_users 
		  SET    bh_user_active = ?
		  WHERE  bh_user_id = ?";
	$sth = $dbh->prepare($query);
	$sth->bind_param( 1, $json->{'user-active'} );
	$sth->bind_param( 2, $json->{'bh_user_id'} );
	$sth->execute();
	if ( $sth->err() ) {
	    my $error = $sth->errstr();
	    $sth->finish();
	    $dbh->disconnect();
	    return ($error);
	}
	$sth->finish();
    }
    $dbh->disconnect();
    return "Success";
}

# add a new user to the database
sub addUser {
    my $json        = shift;
    my $genpass     = App::Genpass->new();
    my $newPassword = $genpass->generate(1);    #initial password for user
    my $passhash = password_hash( $newPassword, PASSWORD_BCRYPT );
    my $dbh = &DBSocket();
    if ($dbh == -1) {
	$dbh->disconnect();
	return "Could not access the database.";
    }

    $logger->debug( "AddUser inbound data: " . objectToString($json) );
    my $query = "INSERT INTO bh_users 
		             (bh_user_name, bh_user_fname, bh_user_lname, 
                              bh_user_email, bh_user_affiliation, bh_user_role,
                              bh_user_active, bh_user_pass)
		 VALUES      (?,?,?,?,?,?,?,?)";
    my $sth = $dbh->prepare($query);
    $sth->bind_param( 1, $json->{'user-username'} );
    $sth->bind_param( 2, $json->{'user-fname'} );
    $sth->bind_param( 3, $json->{'user-lname'} );
    $sth->bind_param( 4, $json->{'user-email'} );
    $sth->bind_param( 5, $json->{'user-affiliation'} );
    $sth->bind_param( 6, $json->{'user-role'} );
    $sth->bind_param( 7, $json->{'user-active'} );
    $sth->bind_param( 8, $passhash );
    $sth->execute();
    
    if ( $sth->err() ) {
	my $error = $sth->errstr();
	$sth->finish();
	$dbh->disconnect();
	return ($error);
    }
    $logger->info( "User " . $json->{'user-username'} . " added!" );
    $sth->finish();
    $dbh->disconnect();
    
    # the user exists in the database so lets let them know what their
    # initial password is
    
    my $text = "Hello, your new one time password for the Black Hole Service at 3ROX is\n";
    $text .= "found below. You will need to change your password the next time you\n";
    $text .= "log into the service.\n\n";
    $text .= "Thank you,\n The BHS Team at 3ROX\n\n\n";
    $text .= "One time password: $newPassword\n\n";
    my $target_email = $json->{'user-email'};
    my $email        = Email::Simple->create(
	header => [
	    To      => $target_email,
	    From    => '"BlackHole Sun Server" <blackholesun@psc.edu>',
	    Subject => "Initial Password for Blackhole Sun at 3ROX",
	],
	body => $text,
	);
    eval { sendmail($email, {transport => $transport}) };
    if ($@) {
	$logger->error("Problem sending email: $@");
    }
    $logger->info("In addUser - Sent mail to $target_email");
    return "Success";
}

sub resetPassword {
    my $json        = shift @_;
    my $genpass     = App::Genpass->new();
    my $newPassword = $genpass->generate(1);
    
    my $dbh = &DBSocket();
    if ($dbh == -1) {
	$dbh->disconnect();
	return "Could not access the database.";
    }
    
    # this essentially confirms that the user exists before we try to
    # do any changes to the database
    my $query = "SELECT bh_user_email FROM bh_users WHERE bh_user_id = ?";
    my $sth   = $dbh->prepare($query);
    $sth->bind_param( 1, $json->{'bh_user_id'} );
    $sth->execute();
    if ( $sth->err() ) {
	my $error = $sth->errstr();
	$sth->finish();
	$dbh->disconnect();
	return ($error);
    }
    if ( $sth->rows != 1 ) {
	$sth->finish();
	$dbh->disconnect();
	return "No results for this user id";
    }
    my @result = $sth->fetchrow_array();
    $sth->finish();
    my $target_email = $result[0];
    
    # we have the email now update the password
    my $passhash = password_hash( $newPassword, PASSWORD_BCRYPT );
    $query = "UPDATE bh_users
              SET    bh_user_pass = ?,
		     bh_user_force_password = 1			       
	      WHERE  bh_user_id = ?";
    $sth = $dbh->prepare($query);
    $sth->bind_param( 1, $passhash );
    $sth->bind_param( 2, $json->{'bh_user_id'} );
    $sth->execute();
    
    if ( $sth->err() ) {
	my $error = $sth->errstr();
	$sth->finish();
	$dbh->disconnect();
	return ($error);
    }
    $sth->finish();
    $dbh->disconnect();
    $logger->info("Updated password in DB for user $json->{bh_user_id}");
    
    # we have updated the password. Now send the new password to the user
    my $text = "Hello, your one time recovery password for the Black Hole Service at 3ROX is\n";
    $text .= "found below. You will need to change your password the next time you\n";
    $text .= "log into the service.\n\n";
    $text .= "Thank you,\n The BHS Team at 3ROX\n\n\n";
    $text .= "One time password: $newPassword\n\n";
    my $email = Email::Simple->create(
	header => [
	    To      => $target_email,
	    From    => '"BlackHole Sun Server" <blackholesun@psc.edu>',
	    Subject => "Recovery Password for Blackhole Sun at 3ROX",
	],
	body => $text,
	);
    eval { sendmail($email, {transport => $transport}) };
    if ($@) {
	$logger->error("Problem sending email: $@");
    }
    $logger->info("Supposedly sent the mail to $email");
    return "Success";
}

# we set force_password to 0 because a user will only be changing their
# password if it is already set to 0 or if they are doing an initial
# password change which requires it to be set ot 0
sub changePassword {
    my $json     = shift @_;
    my $passhash = password_hash( $json->{'npass1'}, PASSWORD_BCRYPT );
    my $dbh      = &DBSocket();
    if ($dbh == -1) {
	$dbh->disconnect();
	return "Could not access the database.";
    }
    
    # $dbh->trace(1); #enable tracing for debug purposes
    my $query = "UPDATE bh_users
      		 SET 	bh_user_pass = ?,
             	 	bh_user_force_password = 0
	      	 WHERE  bh_user_id = ?";
    my $sth = $dbh->prepare($query);
    $sth->bind_param( 1, $passhash );
    $sth->bind_param( 2, $json->{'bh_user_id'} );
    $sth->execute();
    if ( $sth->err() ) {
	my $error = $sth->errstr();
	$sth->finish();
	$dbh->disconnect();
	return ($error);
    }
    $sth->finish();
    $dbh->disconnect();
    return "Success";
}

# the following two functions
# deleteUser and deleteCustomer can also be
# collapsed with the use of flags.
sub deleteUser {
    my $json  = shift @_;
    my $dbh   = &DBSocket();
    if ($dbh == -1) {
	$dbh->disconnect();
	return "Could not access the database.";
    }

    my $query = "DELETE FROM bh_users
		 WHERE       bh_user_id = ?";
    my $sth = $dbh->prepare($query);
    $sth->bind_param( 1, $json->{'bh_user_id'} );
    $sth->execute();
    if ( $sth->err() ) {
	my $error = $sth->errstr();
	$sth->finish();
	$dbh->disconnect();
	return ($error);
    }
    $sth->finish();
    $dbh->disconnect();
    return "Success";
}

sub deleteCustomer {
    my $json  = shift @_;
    my $dbh   = &DBSocket();
    if ($dbh == -1) {
	$dbh->disconnect();
	return "Could not access the database.";
    }

    my $query = "DELETE FROM bh_customers
		 WHERE       bh_customer_id = ?";
    my $sth = $dbh->prepare($query);
    $sth->bind_param( 1, $json->{'bh_customer_id'} );
    $sth->execute();
    if ( $sth->err() ) {
	my $error = $sth->errstr();
	$sth->finish();
	$dbh->disconnect();
	return ($error);
    }
    $sth->finish();
    $dbh->disconnect();
    return "Success";
}

# normalize the exabgp server to the database
sub pushChanges {
    my $child = shift @_;
    my $json  = shift @_;    #should contain customer_id and user_role
    
    # we need to grab the list of routes in the exabgp server
    my $results = sendtoExaBgpInt( $child, "", "dump" );
    my %exablocks;           #blocks from the server
    my %dbblocks;            # blocks from the database
    my %protected;           # blocks from the protected config information
    
    # these are the full lines returned by the exabgp server
    my @exaroutes = split( /\n/, $results );
    
    #we really only need the routes from it though
    # line format is
    # neighbor ip proto unicast route nexthop target
    # so we can split the line and just grab the routes and drop them into
    # an array
    
    foreach my $exaroute (@exaroutes) {
	
	# data is whitespace separated.
	my @split_route = split( / +/, $exaroute );
	$exablocks{ $split_route[4] } = 1;
    }
    
    # if they aren't admin/staff then remove any route
    # they don't control from the struct
    if ( $json->{'bh_user_role'} == 1 ) {
	my $exablocks_ref =
	    &customerOnlyRoutes( \%exablocks, $json->{'bh_customer_id'} );
	%exablocks = %$exablocks_ref;
    }
    
    # we now have all of the exabgp routes in exablocks
    # get the list of *active* routes from the database
    my $sth;
    my $dbh = &DBSocket;
    if ($dbh == -1) {
	$dbh->disconnect();
	return "Could not access the database.";
    }

    # local $dbh->{TraceLevel} = "3|SQL";
    my $query = "SELECT bh_route 
                 FROM   bh_routes
                 WHERE  bh_active = 1";

    # if they aren't an admin/staff then only grab the ones they have control over
    if ( $json->{'bh_user_role'} == 1 ) {
	$query .= " AND  bh_customer_id = ?";
	$sth = $dbh->prepare($query);
	$sth->bind_param( 1, $json->{'bh_customer_id'} );
    } else {
	$sth = $dbh->prepare($query);
    }
    
    $sth->execute();
    if ( $sth->err() ) {
	$logger->error("Error in updateloop: $sth->errstr()");
    }

    while ( my @result = $sth->fetchrow_array() ) {
	$dbblocks{ $result[0] } = 1;
    }
    $sth->finish();
    
    #we now have two hashes of blocks
    #if there is a block in the exabgp set that is
    #not in the db set then we want to delete it
    #conversely, if it is the db set and not exabgp then we
    #want to add it
    
    # there may be routes that must be maintained even if they aren't in the
    # the database. That's a configuration option so we need to ensure we
    # don't test those
    if ( defined $config->{'protected_routes'}->{'routes'} ) {
	#this may be a CSV list so we need to turn it into an array
	%protected = map { trim($_) => 1 } split( ",", $config->{'protected_routes'}->{'routes'} );
    }
    
    my %route;
    foreach my $exa ( keys %exablocks ) {
	
	#check any protected routes
	if ( defined $protected{$exa} ) {
	    next;
	}
	if ( !defined $dbblocks{$exa} ) {
	    $logger->info("$exa is not in database: deleting");
	    $route{'bh_route'} = $exa;
	    sendtoExaBgpInt( "", \%route, "del" );
	}
    }
    
    foreach my $db ( keys %dbblocks ) {
	if ( !defined $exablocks{$db} ) {
	    $logger->info("$db is not in exabgp: adding");
	    $route{'bh_route'} = $db;
	    sendtoExaBgpInt( "", \%route, "add" );
	}
    }
    return 1;
}

# take an incoming list of routes
# determine if they are in the set of routes that the
# customer has control over. Elide everything else
sub customerOnlyRoutes {
    my $routes_ref  = shift @_;
    my $customer_id = shift @_;
    
    #first we grab the set of routes that the customer has control over
    my $dbh   = &DBSocket();
    if ($dbh == -1) {
	$dbh->disconnect();
	return "Could not access the database.";
    }

    my $query = "SELECT bh_customer_blocks
                 FROM 	bh_customers
		 WHERE 	bh_customer_id = ?";
    my $sth = $dbh->prepare($query);
    $sth->bind_param( 1, $customer_id );
    $sth->execute();
    if ( $sth->err() ) {
	$logger->error("Error in customerOnlyRoutes: $sth->errstr()");
    }
    
    #we should only have one result
    my $json;
    $sth->bind_col( 1, \$json );
    $sth->fetch();
    my $blocks = decode_json($json);
    
    # this part is annoying because we have to take every single
    # route passed to us by exabgp and determine if it's in a control
    # block owned by the customer.
    
    my $delete_flag;
    
    #go through each of the routes given to use by exabgp
    foreach my $exaroute ( keys %$routes_ref ) {
	$delete_flag = 0;
	
	# now go through each of the ones from the database
	foreach my $dbroute ( @{ $blocks->{'blocks'} } ) {
	    
	    #get the ip address
	    #we are ignoring the mask
	    my ( $ip, $mask ) = split( "/", $exaroute );
	    my $netblock = Net::Netmask->new2($dbroute);
	    
	    #if the ip is not in the netblock then
	    #set the delete flag to 1. This may happen
	    #multiple times. No big deal because if we do get a match
	    #then we exit the inner loop after resetting the flag to 0
	    if ( !$netblock->match($ip) ) {
		$delete_flag = 1;
	    } else {
		$delete_flag = 0;
		last;
	    }
	}
	
	# we only delete the route if the flag is true
	if ( $delete_flag == 1 ) {
	    delete %$routes_ref{$exaroute};
	}
    }
    
    #what remains should *only* be the routes on the exabgp server
    #that our customer controls. Since it was passed by reference
    #we should be good to go without explicitly returning the hash reference
    return $routes_ref;
}

# strip leading and trailing whitespace
sub trim {
    my $s = shift;
    $s =~ s/^\s+|\s+$//g;
    return $s;
}

sub updateloop {
    
    # loop forever!
    my $pid = fork();
    if ( $pid == 0 ) {
	my $dbh = &DBSocket;
	#this isn't seen by the user so just skip it
	if ($dbh == -1) {
	    $dbh->disconnect();
	    return -1;
	}
	
	# this query finds all of the active routes where the difference
	# between the start time and the current time is greater than the
	# desired life of the route
	my $query = "SELECT bh_index, bh_route, bh_lifespan 
		     FROM   bh_routes 
		     WHERE  (timestampdiff(hour, bh_starttime, current_timestamp()) > bh_lifespan)
                     AND    bh_active=1";
	my $sth = $dbh->prepare($query);
	# we only have to prepare the query once
	my $route_json;
	while (1) {
	    
	    #every minute find any routes that have expired
	    $sth->execute();
	    if ( $sth->err() ) {
		
		#need to write this to a log
		$logger->error("Error in updateloop: $sth->errstr()");
	    }
	    while ( my $result = $sth->fetchrow_hashref() ) {
		
		# if the lifespan is set to the magic number then
		# never expire it
		if ( $result->{'bh_lifespan'} == 9999 ) {
		    next;
		}
		my $updateQuery = "UPDATE bh_routes
				   SET    bh_active=0
				   WHERE  bh_index = ?";
		my $updatesth = $dbh->prepare($updateQuery);
		$updatesth->bind_param( 1, $result->{'bh_index'} );
		$updatesth->execute();
		if ( $updatesth->err() ) {
		    $logger->error("Error in updateloop: $updatesth->error()");
		}
		# we now have to fire off something to exaBGP to tell it to
		# eliminate those routes. We don't need to specify the first
		# argument as it's only used to print errors to STDIN
		my %route;
		$route{'bh_route'} = $result->{'bh_route'};
		$logger->info("route $result->bh_index has expired");
		sendtoExaBgpInt( "", \%route, "del" );
	    }
	    sleep 60;
	}
	exit(0);
    }
    return;
}

#
# Copyright 2006 NetMesh Inc.
#
# Licensed under the Apache License, Version 2.0 (the "License");
# you may not use this file except in compliance with the License.
# You may obtain a copy of the License at
#
#     http://www.apache.org/licenses/LICENSE-2.0
#
# Unless required by applicable law or agreed to in writing, software
# distributed under the License is distributed on an "AS IS" BASIS,
# WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
# See the License for the specific language governing permissions and
# limitations under the License.

use overload;

#####
# Convert a hierarchic data structure into a string. Does not detect loops.

sub objectToString {
    my $obj    = shift;    # object to convert to string
    my $indent = shift;    # indentation level. Defaults to 0.
    
    $indent = 0 unless ( defined($indent) );
    return 'undef' unless ( defined($obj) );
    
    my $ret = '';
    
    my $strval = overload::StrVal($obj);
    
    my ( $realpack, $realtype, $id ) =
	( $strval =~ /^(?:(.*)\=)?([^=]*)\(([^\(]*)\)$/ );
    $realpack = '' unless ($realpack);
    $realtype = '' unless ($realtype);
    $id       = '' unless ($id);
    
    if ( $realpack eq "Math::BigInt" ) {
	$ret .= ref($obj) . " { ";
	$ret .= $obj->bstr;
	$ret .= " }";
    } elsif ( $realtype eq "ARRAY" ) {
	$ret .= ref($obj) . " {\n";
	
	# check whether this is a pseudo-hash
	if ( @{$obj} && overload::StrVal( @{$obj}[0] ) =~ m!^pseudohash=! ) {
	    my $pseudo = @{$obj}[0];
	    foreach my $k ( keys %{$pseudo} ) {
		$ret .= indent( $indent + 1 );
		$ret .= sprintf( "%-32s => %s\n",
				 $k,
				 objectToString( @{$obj}[ $pseudo->{$k} ], $indent + 1 ) );
	    }
	} else {
	    foreach my $o ( @{$obj} ) {
		$ret .= indent( $indent + 1 );
		if ( ref($o) eq "HASH" || ref($o) eq "ARRAY" ) {
		    $ret .= objectToString( $o, $indent + 1 ) . "\n";
		} else {
		    if ( defined($o) ) {
			$ret .= objectToString( $o, $indent + 1 ) . "\n";
		    } else {
			$ret .= "<<undef>>\n";
		    }
		}
	    }
	}
	$ret .= indent($indent) . "}\n";
	
    } elsif ( $realtype eq "HASH" ) {
	$ret .= ref($obj) . " {\n";
	foreach my $k ( keys %{$obj} ) {
	    $ret .= indent( $indent + 1 );
	    $ret .= sprintf( "%-32s => %s\n",
			     $k, objectToString( $obj->{$k}, $indent + 1 ) );
	}
	$ret .= indent($indent) . "}";
    } else {
	$ret .= $obj;
    }
    return $ret;
}

#####
# indent so many times
sub indent {
    my $indent = shift;
    
    $indent = 0 unless ( defined($indent) );
    my $ret = '';
    for ( my $i = 0 ; $i < $indent ; ++$i ) {
	$ret .= '    ';
    }
    return $ret;
}

# the incoming route object has all of the data that we need to
# pull the necessary email addresses out of the database
sub routeModNotification {
    my $route = shift @_;
    my @emails;
    my $email;
    my $dbh = &DBSocket();
    if ($dbh == -1) {
	$dbh->disconnect();
	return "Cannot access the database";
    }
    
    $logger->error("In routeModNotification");
    
    my $query = "SELECT bh_user_email
                 FROM   bh_users
		 WHERE  bh_user_affiliation = ?";
    my $sth = $dbh->prepare($query);

    $sth->bind_param(1, $route->{'bh_customer_id'});
    $sth->execute();
    $sth->bind_col(1, \$email);

    if ( $sth->err() ) {
	$logger->error("Error in routeModNotification: $sth->errstr()");
	return $sth->errstr();
    }

    # this puts all of the emails of the associated customer into an array
    while (my $result = $sth->fetch()) {
	push(@emails, $email)
    }

    # now we have to get the email of all of the people with a user_role of 3 or 4
    $query = "SELECT bh_user_email
              FROM   bh_users
	      WHERE  bh_user_role = 3 OR bh_user_role = 4";
    $sth = $dbh->prepare($query);
    $sth->execute();
    $sth->bind_col(1, \$email);

    if ( $sth->err() ) {
	$logger->error("Error in routeModNotification: $sth->errstr()");
	return $sth->errstr();
    }
    
    while (my $result = $sth->fetch()) {
	push(@emails, $email)
    }

    #we now have a full list of emails but there may be duplicates
    #we want to eliminate all of them
    my %temphash = map {$_ => 1} @emails;
    @emails = keys %temphash;        
    
    my $subject = "Route added/modified via Blackhole Sun";
    my $text = "To whom it may concern,\n";
    $text   .= "\n\nAn ExaBGP route had been added or modified via Blackhole Sun.\n";
    $text   .= "Details are as follows:\n";
    $text   .= "Requestor: " . $route->{'bh_requestor'} . "\n";
    $text   .= "Customer: " . customerLookup($route->{'bh_customer_id'}) . "\n";
    $text   .= "Owner: " . customerLookup($route->{'bh_owner_id'}) . "\n";    
    $text   .= "Route: " . $route->{'bh_route'} . "\n";
    $text   .= "Active: " . $route->{'active'} . "\n";
    $text   .= "Start time: " . $route->{'bh_startdate'} . " " . $route->{'bh_starttime'} . "\n";
    $text   .= "Lifespan: " . $route->{'bh_lifespan'} . "\n";
    $text   .= "Comments: " . $route->{'bh_comment'} . "\n";
    $text   .= "\n\nBlackhole sun notification service over and out\n";
    my $addresses = join(", ", @emails);

    $logger->error("addresses are $addresses");

    $logger->error("text is\n$text");
    
    my $notice        = Email::Simple->create(
	header => [
	    To      => $addresses,
	    From    => '"BlackHole Sun Server" <blackholesun@psc.edu>',
	    Subject => $subject,
		],
	body => $text,
	);
    eval { sendmail($notice, {transport => $transport}) };
    if ($@) {
	$logger->error("Problem sending email: $@");
    }
    $logger->info("In modRouteNotify - Sent route notification email");
    return "Success";
}

# return the name associated with a customer_id
# TODO: add exception handling
sub customerLookup {
    my $customer_id = shift @_;
    my $name;
    my $dbh = &DBSocket();
    if ($dbh == -1) {
	$dbh->disconnect();
	return -1;
    }

    my $query = "SELECT bh_customer_name 
                 FROM   bh_customers
		 WHERE   bh_customer_id = ?";
    my $sth = $dbh->prepare($query);
    $sth->bind_param(1, $customer_id);
    $sth->execute();
    $sth->bind_col(1, \$name);
    $sth->fetch();
    return $name;
}


# get the command line options
getopts( "f:h", \%options );
if ( defined $options{h} ) {
    print "client usage\n";
    print "\client.pl [-f] [-h]\n";
    print "\t-f path to configuration file. Defaults to /usr/local/etc/client.cfg\n";
    print "\t-h this help text\n";
    exit;
}

# make sure that the user specified config file exists
if ( defined $options{f} ) {
    if ( -e $options{f} ) {
	$cfg_path = $options{f};
    } else {
	print STDERR "Configuration file not found at $options{f}. Exiting.\n";
	exit;
    }
}

#now that we have a config file lets read validate the thing. 
&readConfig();
&validateConfig();

#start the logger
if ( !-e $config->{'logconfig'}->{'path'} ) {
	die "canot find log config file";
}
Log::Log4perl::init( $config->{'logconfig'}->{'path'} );
$logger = Log::Log4perl->get_logger('logger');

my $server = &instantiateServer();    #get the server socket

# we need a loop that will, every minute or so, query the db
# to update any route information in terms of the routes expiring.
&updateloop($server);
&mainloop($server);
