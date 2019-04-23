#!/usr/bin/perl
# this is a test client that will be the basis of the processing engine
use strict;
use warnings;
use IO::Socket::SSL qw(inet4);
use IO::Socket::INET;
use IO::Socket::Timeout;
use CryptX;
use Crypt::PK::RSA;
use Crypt::PK::DH qw(dh_shared_secret); 
use Try::Tiny;
use Config::Tiny;
use Getopt::Std;
use Data::Dumper;
use JSON;
use Switch;
use DBI;
use App::Genpass; # create random password 
use MIME::Lite;
# this is to ensure that the password
# functions in the php side match what
# we generate here
use PHP::Functions::Password qw(:all);;


my %options = ();
my $config = Config::Tiny->new();
my $cfg_path = "./private/client.cfg";

sub readConfig {
    if (! -e $cfg_path) {
        print "Config file not found at $cfg_path. Exiting.\n";
        exit;
    } else {
        $config = Config::Tiny->read($cfg_path);
        my $error = $config->errstr();
        if ($error ne "") {
            print "Error: $error. Exiting.\n";
            exit;
        }
    }
}    

# we need to make sure that all of the configuration 
# variables exist and validates them as best we can.
sub validateConfig {
#check key data
    if (! defined $config->{'keys'}->{'client_private_rsa'}) {
        print "Missing client's private RSA key location in config file. Exiting.\n";
        exit;
    }
    if (! defined $config->{'keys'}->{'server_public_rsa'}) {
        print "Missing server's public RSA key infomation in config file. Exiting.\n";
        exit;
    }
#check interface data
    if (! defined $config->{'interface'}->{'host'}) {
	print "ExaBGP interface host not defined. Exiting.\n";
	exit;
    }
    if (! defined $config->{'interface'}->{'port'}) {
	print "ExaBGP interface port not defined. Exiting.\n";
	exit;
    }
#check server data
    if (! defined $config->{'server'}->{'host'}) {
	print "Server host not defined. Exiting.\n";
	exit;
    }
    if (! defined $config->{'server'}->{'port'}) {
	print "Server port not defined. Exiting.\n";
	exit;
    }
#test key files
    if (! -e $config->{'keys'}->{'client_private_rsa'}) {
	print "The client's private RSA file is missing or not readable. Exiting.\n";
	exit;
    }
    if (! -e $config->{'keys'}->{'server_public_rsa'}) {
	print "The server's public RSA file is missing or not readable. Exiting.\n";
	exit;
    }
#check the database information
    if (! defined $config->{'database'}->{'host'}) {
	print "Database host not defined. Exiting.\n";
	exit;
    }
    if (! defined $config->{'database'}->{'port'}) {
	print "Database port not defined. Exiting.\n";
	exit;
    }
    if (! defined $config->{'database'}->{'user'}) {
	print "Database user not defined. Exiting.\n";
	exit;
    }
    if (! defined $config->{'database'}->{'password'}) {
	print "Database password not defined. Exiting.\n";
	exit;
    }
}

# get the command line options
getopts ("f:h", \%options);
if (defined $options{h}) {
    print "client usage\n";
    print "\client.pl [-f] [-h]\n";
    print "\t-f path to configuration file. Defaults to /usr/local/etc/client.cfg\n";
    print "\t-h this help text\n";
    exit;
}

# make sure that the user specified config file exists
if (defined $options{f}) {
    if (-e $options{f}) {
        $cfg_path = $options{f};
    } else {
        printf "Configuration file not found at $options{f}. Exiting.\n";
        exit;
    }
}

#start the authentication process.
sub authorize {
    my $socket = shift;
    my $authorized = -1;
    
    #send the auth request to the server
    print "ST: sending auth\n";
    print $socket "auth\n";

    #we will get two responses from the server
    print "ST waiting on sig_srv\n";
    my $sig_srv = <$socket>;
    chomp $sig_srv;
    print "ST waiting on dh_public_srv\n";
    my $dhpublic_srv = <$socket>;
    chomp $dhpublic_srv;

    # convert them both back to the binary format
    $sig_srv = pack (qq{H*}, $sig_srv);
    $dhpublic_srv = pack (qq{H*}, $dhpublic_srv);

    #verify the signature
    my $rsapub = Crypt::PK::RSA->new($config->{'keys'}->{'server_public_rsa'});
    if (! $rsapub->verify_message($sig_srv, $dhpublic_srv)) {
	print "Could not verify the RSA signature for the server. Exiting.\n";
	exit;
    }    
    
    #We verifed the servers DH key so load our keys.
    my $pk = Crypt::PK::DH->new();
    $pk->generate_key(128);

    my $dhprivate_cli = Crypt::PK::DH->new(\$pk->export_key('private'));
    my $dhpublic_cli = $pk->export_key('public');
    
    #load the client's private RSA key
    my $rsaprivate = Crypt::PK::RSA->new($config->{'keys'}->{'client_private_rsa'});

    #compute the signature of the private key
    my $sig_client = $rsaprivate->sign_message($dhpublic_cli);

    #convert the signature in to a text string
    $sig_client = unpack (qq{H*}, $sig_client);

    #convert the public dh key to text
    $dhpublic_cli = unpack (qq{H*}, $dhpublic_cli);

    $dhpublic_srv = Crypt::PK::DH->new(\$dhpublic_srv); 

    #generate the shared secret
    my $clientsecret = dh_shared_secret($dhprivate_cli, $dhpublic_srv);

    #again we convert that to text for ease of transmission
    $clientsecret = unpack (qq{H*}, $clientsecret);

    print "Got the secret $clientsecret\n";

    # we now have all three elements that the server is waiting on
    # so send it over to them
    
    my $keydata = "$sig_client:$dhpublic_cli:$clientsecret\n";
    print "ST: sending key data\n";
    print $socket $keydata;
    
    #wait until we get an auth response from the server
    print "ST Waiting on auth\n";
    $authorized = <$socket>;
    chomp $authorized;

    print "authorized = $authorized\n";

    return $authorized;
}



#open a connection to the server
sub openExaSocket {
    print "About to open ExaSocket\n";
    my $sock = IO::Socket::INET->new(PeerAddr => $config->{'interface'}->{'host'},
					   PeerPort => $config->{'interface'}->{'port'},
					   Proto    => 'tcp') or die "Cannot connect: $!\n";
    print "Socket opened\n";
    return $sock;
}

#this creates the local listening socket to handle incoming
#requests from the web app
sub instantiateServer {
    my $sock = IO::Socket::INET->new(LocalAddr => $config->{'server'}->{'host'},
				       LocalPort => $config->{'server'}->{'port'},
				       Listen => 5,
				       Proto => 'tcp',
				       Reuse => 1,
				       Timeout => 1,
	);
    die "Socket could not be created. Reason: $!\n" unless ($sock);
    return $sock;
}

sub mainloop {
    my $parent = shift;

    $SIG{CHLD} = sub {wait ()};
    while (1) {
	while (my $child = $parent->accept()) {
	    my $pid = fork();
	    if (! defined ($pid)) {
		print STDERR "Cannot fork child: $!\n";
		close ($child);
	    }
	    IO::Socket::Timeout->enable_timeouts_on($child);
	    $child->read_timeout(5);
	    $child->write_timeout(5);
	    # Child process
	    while (defined (my $buf = <$child>)) {
		chomp $buf;
		print "buffer is $buf\n";
		#inbound request is in json - no new lines/indents though
		my $json = validateJson($buf);
		print Dumper ($json);
		if ($json != -1) {
		    print "About to process inbound request\n";
		    my $status = processInboundRequests($child, $json);
		    print "I got $status\n";
		    print $child $status;
		} else {
		    print $child "Invalid JSON\n";
		}		       
		exit(0); # Child process exits when it is done.
	    } # else 'tis the parent process, which goes back to accept()
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
#    try {
    print $jsonstring;
	$text = decode_json($jsonstring);
#    } catch {
#	return -1
#    }
    return $text;
}

# handle the incoming requests from the inbound client here
# these requests are in valid json format
sub processInboundRequests {
    my $child = shift; # this is the interface to the webapp
    my $json = shift; # keep in mind that this is not a json object
                      # but the decoded json object. Yeah, nomenclature
                      # is annoying

    switch ($json->{'action'}) {
	case /quit/i {
	    print $child "Closed connection\n";
	    close ($child);
	    return;
	}	    
	case /blackhole/i {
	    print "about to open socket to exabgp interface\n";
	    my $exa_socket = &openExaSocket; #create ExaBGP interface socket
	    if (! &authorize($exa_socket)) { #authorize this process 
		print "Validation failed\n";
		print $child "Authorization failed\n";
		close ($exa_socket);
		close ($child);
		return;
	    }
	    my $status = &blackHole($exa_socket, $json);
	    close ($exa_socket);
	    # add route to the database
	    my $db_stat = &addRouteToDB($json);
	    return $status;
	}
	case /listexisting/ {
	    #get a list of the existing blackhole routes from the DB
	    my $listing = &listExistingBH("all"); #returns a json object
	    return $listing . "\n";
	}
	case /listactive/ {
	    # get a list of only active BH routes from the DB
	    my $listing = &listExistingBH("active"); #returns json
	    return $listing . "\n";
	}
	case /edit/ {
	    # edit the route in the DB
	    my $status = &editRouteInDB($json);
	    my $result = encode_json({"results" => $status}) . "\n";
	    return $result;
	}
	case /updateUser/ {
	    # edit the user information in the database
	    my $status = &updateUser($json);
	    return $status;
	}
	case /resetPassword/ {
	    my $status = &resetPassword($json);
	    return $status;
	}
	case /changePassword/ {
	    my $status = &changePassword($json);
	    return $status;
	}
	case /pushchanges/ {
	    # this forces the db to push all pending changes out to the
	    # exabgp server
	}
	case /deleteselection/ {
	    #delete one or more blackhole routes
	}
	case /confirmbhdata/ {
	    #compare bh data from db to exabgp
	}
	else {
	}
    }
}

# grab a list of the current blackhole routes from the database
# turn them into a json object
# return the object to the browser for display
# action = "active"
sub listExistingBH {
    # get the database handle
    my $action = shift @_; # to access different subsets of data
    my $dbh = &DBSocket();
    my $result_json;
    my @results;
    if ($dbh =~ /Err/) {
	return "$dbh\n";
    }
    # prepare the query
    my $query = "SELECT * FROM bh_routes";
    if ($action eq "active") {
	$query .= "  WHERE bh_active = 1";
    }
    my $sth = $dbh->prepare($query);
    $sth->execute();
    #loop through the results and build the array of hashrefs
    while (my $result = $sth->fetchrow_hashref()) {
	push @results, $result;
    }
    $sth->finish();
    $dbh->disconnect();
    #convert to json
    $result_json = encode_json \@results;
    return $result_json;
}

sub DBSocket {
    my $dbh;
    my $data_source = "DBI:mysql:database=" . $config->{database}->{database} .
	                           ";host=" . $config->{database}->{host} .
	                           ";port=" . $config->{database}->{port};
    print $data_source . "\n";
    $dbh = DBI->connect($data_source, 
 		        $config->{database}->{user}, 
		        $config->{database}->{password},
	                {'RaiseError' => 0, 'PrintError' => 0}) or die "This failed";

    return $dbh;
}

# create, delete, or modify black holes
# the socket in question is the exabgp interface socket
sub blackHole {
    my $socket = shift; # socket to the exabgp_interface
    my $json = shift; # the blackhole request structure
    my $status; # this is what we get back from the exabgp interface

    # I don't know the EXABGP format yet but in the meantime we'll fake it
    my $request = $json->{'bh_route'} . " " . $json->{'bh_lifespan'} .
	" " . $json->{'bh_community'};
    
    print $socket $request . "\n";

    $socket->read($status,4096);
    $status = "Got to blackhole subroutine: $status";
    
    return $status;
}    


sub editRouteInDB {
    my $json = shift @_;
    my $dbh = &DBSocket();
    my $query = "UPDATE bh_routes
                 SET bh_route = ?,
                     bh_lifespan = ?,
		     bh_community = ?,
                     bh_active = ?
                 WHERE 
		     bh_index = ?";
    my $sth=$dbh->prepare($query);
    $sth->bind_param(1, $json->{'bh_route'});
    $sth->bind_param(2, $json->{'bh_lifespan'});
    $sth->bind_param(3, $json->{'bh_community'});
    $sth->bind_param(4, $json->{'bh_active'});
    $sth->bind_param(5, $json->{'bh_index'});
    $sth->execute();
    if ($sth->err()) {
	return $sth->errstr();
    }
    $sth->finish();
    $dbh->disconnect();
    return ("Success");
}		 

# get the incoming request and insert it in to the database
# his comes after the request has been sent ot the exabgp server
# I may want to change this so that instead of writin from here to the
# ex server I write the the DB and then trigger a process that
# normalizes the ex server to the DB. 
sub addRouteToDB {
    my $json = shift;
    my $dbh = &DBSocket();
    my $query = "INSERT INTO bh_routes
			     (bh_route,
			      bh_lifespan,
			      bh_starttime,
			      bh_community,
			      bh_requestor,
			      bh_active)
                        VALUES
			     (?,?,?,?,?,1);";
    my $sth = $dbh->prepare($query);
    my $datetime = $json->{'bh_startdate'} . " " . $json->{'bh_starttime'};
    $sth->bind_param(1, $json->{'bh_route'});
    $sth->bind_param(2, $json->{'bh_lifespan'});
    $sth->bind_param(3, $datetime);
    $sth->bind_param(4, $json->{'bh_community'});
    $sth->bind_param(5, "rapier");
    $sth->execute();
    #need error checking 
    $sth->finish();
    $dbh->disconnect();
    return;
}

# get an incoming request to update a user and update the database.
sub updateUser {
    my $json = shift;
    my $role_insert;
    my $role_active;
    my $dbh = &DBSocket();
    print "I have a socket!\n";
    if ($json->{'user-role'}) {
    }
    if ($json->{'user-active'}) {
	$role_active = "bh_user_active = :active,";
    }
    my $query = "UPDATE bh_users 
		 SET bh_user_name = ?,
                     bh_user_fname = ?,
                     bh_user_lname = ?, 
                     bh_user_email = ?,
                     bh_user_affiliation = ?
                 WHERE
		     bh_user_id = ?";
    my $sth=$dbh->prepare($query);
    $sth->bind_param(1, $json->{'user-username'});
    $sth->bind_param(2, $json->{'user-fname'});
    $sth->bind_param(3, $json->{'user-lname'});
    $sth->bind_param(4, $json->{'user-email'});
    $sth->bind_param(5, $json->{'user-affiliation'});
    $sth->bind_param(6, $json->{'bh_user_id'});
    $sth->execute();
    if ($sth->err()) {
	return $sth->errstr();
    }
    $sth->finish();
    # we need to do the active and role as seperate queries because
    # there are no named placeholders in this engine
    
    if (defined($json->{'user-role'})) {
	$query = "UPDATE bh_users 
		     SET bh_user_role = ?
		     WHERE bh_user_id = ?";
	$sth=$dbh->prepare($query);
	$sth->bind_param(1, $json->{'user-role'});
	$sth->bind_param(2, $json->{'bh_user_id'});
	$sth->execute();
	if ($sth->err()) {
	    return $sth->errstr();
	}
	$sth->finish();
    }
    if (defined($json->{'user-active'})) {
	$query = "UPDATE bh_users 
		     SET bh_user_active = ?
		     WHERE bh_user_id = ?";
	$sth=$dbh->prepare($query);
	$sth->bind_param(1, $json->{'user-active'});
	$sth->bind_param(2, $json->{'bh_user_id'});
	$sth->execute();
	if ($sth->err()) {
	    return $sth->errstr();
	}
	$sth->finish();
    }
    $dbh->disconnect();
    return ("Success\n");   
}
		      
sub resetPassword {
    my $json = shift @_;
    my $genpass = App::Genpass->new();
    my $newPassword = $genpass->generate(1);
    print "New pass is $newPassword\n";
    my $dbh = &DBSocket();
    # this essentially confirms that the user exists before we try to
    # do any changes to the database
    my $query = "SELECT bh_user_email FROM bh_users WHERE bh_user_id = ?";
    my $sth = $dbh->prepare($query);
    $sth->bind_param(1, $json->{'bh_user_id'});
    $sth->execute();
    if ($sth->err()) {
	return $sth->errstr();
    }    
    if ($sth->rows != 1) {
	return "No results for this user id";
    }
    my @result = $sth->fetchrow_array();
    $sth->finish();
    my $email = $result[0];
    print "The email is $email\n";
    # we have the email now update the password 
    my $passhash = password_hash($newPassword, PASSWORD_BCRYPT);
    $query = "UPDATE bh_users
              SET bh_user_pass = ?
	      WHERE bh_user_id = ?";
    $sth = $dbh->prepare($query);
    $sth->bind_param(1, $passhash);
    $sth->bind_param(2, $json->{'bh_user_id'});
    $sth->execute();
    if ($sth->err()) {
	return ($sth->errstr());
    }    
    print "Updated password in DB\n";
    # we have updated the password. Now send the new password to the user
    my $text  = "Hello, your new one time password for the Black Hole Service at 3ROX is\n";
    $text .= "found below. You will need to change your password the next time you\n";
    $text .= "log into the service.\n\n";
    $text .= "Thank you,\n The BHS Team at 3ROX\n\n\n";
    $text .= "One time password: $newPassword\n\n";
    my $msg = MIME::Lite->new (
	From => "blackholesun\@psc.edu",
	To   => $email,
	CC   => "rapier\@psc.edu",
	Subject => "Password reset for Black Hole Sun",
	Data => $text
	);
    try {
        $msg->send("sendmail", "/usr/sbin/sendmail -t -oi -oem", Timeout=>5, Debug=>1);
    } catch {
	return ("Failed to send email to user\n");
    };
    print "Supposedly sent the mail\n";
    return ("Success\n");
}

sub changePassword {
    my $json = shift @_;
    my $passhash = password_hash($json->{'npass1'}, PASSWORD_BCRYPT);
    my $dbh = &DBSocket();
    my $query = "UPDATE bh_users
	      SET bh_user_pass = ?
	      WHERE bh_user_id = ?";
    my $sth = $dbh->prepare($query);
    $sth->bind_param(1, $passhash);
    $sth->bind_param(2, $json->{'bh_user_id'});
    $sth->execute();
    if ($sth->err()) {
	return ($sth->errstr());
    }
    return "Success\n";
}

&readConfig();
&validateConfig();
my $server = &instantiateServer(); #get the server socket
&mainloop ($server);