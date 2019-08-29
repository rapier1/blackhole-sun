#!/usr/bin/perl

=begin comment
  Copyright (c) 2018 The Board of Trustees of Carnegie Mellon University.
 
   Authors: Chris Rapier <rapier@psc.edu> 
 
  Licensed under the Apache License, Version 2.0 (the "License");
  you may not use this file except in compliance with the License.
  You may obtain a copy of the License at
 
    http://www.apache.org/licenses/LICENSE-2.0
 
  Unless required by applicable law or agreed to in writing, software 
  distributed under the License is distributed on an "AS IS" BASIS,
  WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
  See the License for the specific language governing permissions and
  limitations under the License. *
=end comment
=cut 

# This application acts as an interface between ExaBGP and the processing
# engine for the blackhole sun project.

# Goal: Provide a secure and authenticated way for the processing engine to
# submit black hole change requests to the ExaBGP server.

# Method: This script is configured in ExaBGP as a processing service. Since ExaBGP
# doesn't provide a remote access method it's necessary to create your own. Examples
# using REST interfaces exist but these lack any sort of authentciation or security
# as such we'll be used RSA signed DH key exchange to authenticate the requests from
# the processed in engine. Once the engine has been authenticated the engine will
# submit the request to ExaBGP via STDOUT. The response to the request will be captured
# by this script and parsed for sucess or failure and, additionally capture any other
# requested information (such as the list of black hole routes known by the ExaBGP service)

# Components: forking TCP server ?
#             RSA and DH methods
#             SSL methods

use strict;
use warnings;
use JSON;
use Sys::Syslog qw(:standard :macros);
use IO::Socket::SSL qw(inet4);
use IO::Socket::INET;
use IO::Socket::Timeout;
use CryptX;
use Crypt::PK::RSA;
use Crypt::PK::DH qw(dh_shared_secret);
use Crypt::Mode::CTR;
use Crypt::Random qw(makerandom);
use Try::Tiny;
use Config::Tiny;
use Getopt::Std;
use Data::Dumper;
use Capture::Tiny qw(capture);
use Log::Log4perl;

my %options = ();
my $config = Config::Tiny->new();
my $cfg_path = "/usr/local/etc/exabgp_interface.cfg";
my $logger; # this is the object for the logger it is intantianted later

sub readConfig {
    if (! -e $cfg_path) {
	print STDERR "Config file not found at $cfg_path. Exiting.\n";
        exit;
    } else {
        $config = Config::Tiny->read($cfg_path);
        my $error = $config->errstr();
        if ($error ne "") {
	    print STDERR "Error loading config file: $error. Exiting.\n";
            exit;
        }
    }
}    

# we need to make sure that all of the configuration 
# variables exist and validates them as best we can.
sub validateConfig {
    if (! defined $config->{'server'}->{'listen'}) {
        print STDERR "Listen port not defined in config file. Exiting\n";
	$logger->error("Listen port not defined in config file. Exiting");
        exit;
    }
    if (! defined $config->{'server'}->{'host'}) {
        print STDERR "Host not defined in config file. Exiting\n";
	$logger->error("Host port not defined in config file. Exiting");
        exit;
    }
#check db data
    if (! defined $config->{'keys'}->{'server_private_rsa'}) {
        print STDERR "Missing DB host infomation in config file. Exiting\n";
	$logger->error("Missing DB host infomation in config file. Exiting");
        exit;
    }
    if (! defined $config->{'keys'}->{'client_public_rsa'}) {
        print STDERR "Missing DB password infomation in config file. Exiting\n";
	$logger->error("Missing DB password infomation in config file. Exiting");
        exit;
    }
    if (! -e $config->{'keys'}->{'server_private_rsa'}) {
	print STDERR "The server's private RSA file is missing or not readable. Exiting.\n";
	$logger->error("The server's private RSA file is missing or not readable. Exiting.");
        exit;
    }
    if (! -e $config->{'keys'}->{'client_public_rsa'}) {
	print STDERR "The client's public RSA file is missing or not readable. Exiting.\n";
	$logger->error("The client's public RSA file is missing or not readable. Exiting.");
        exit;
    }
    if (! defined $config->{'exabgppipe'}->{'pipein'}) {
        print STDERR "ExaBGP in pipe file location not defined in config file. Exiting\n";
	$logger->error("ExaBGP in pipe file location not defined in config file. Exiting");
        exit;
    }
    if (! defined $config->{'exabgppipe'}->{'pipeout'}) {
        print STDERR "ExaBGP out pipe file location not defined in config file. Exiting\n";
	$logger->error("ExaBGP out pipe file location not defined in config file. Exiting");
        exit;
    }
}

sub authorize {
    # get the client socket
    my $socket = shift;

    # we generate a key and send it to them.
    # they respond with their key and the shared secret
    
    #new dh key
    my $pk = Crypt::PK::DH->new();
    $pk->generate_key(128);
    # you need to create a new dhkey struct based on the exported pub/priv key
    # the ->new requires a deref (the \$) in order to take the key from a buffer
    my $dhprivate_srv = Crypt::PK::DH->new(\$pk->export_key('private'));

    # don't create this one yet as we need to send the raw key and not a struct. 
    my $dhpublic_srv = $pk->export_key('public');

    #load the server's private RSA key
    my $rsaprivate = Crypt::PK::RSA->new($config->{'keys'}->{'server_private_rsa'});

    #compute the signature of the private key
    my $sig_srv = $rsaprivate->sign_message($dhpublic_srv);

    #convert the signature in to a text string
    $sig_srv = unpack (qq{H*}, $sig_srv);

    #convert the public dh key to text
    $dhpublic_srv = unpack (qq{H*}, $dhpublic_srv);
    
    #send server sig to client
    print $socket $sig_srv . "\n";
    #send server public dh key to client
    print $socket $dhpublic_srv . "\n";

    # now we get the response from the client
    # they are all text strings so they need to be repacked into binary
    my $keydata = <$socket>;
    chomp $keydata;
    (my $clientsig, my $dhpublic_cli, my $clientsecret) = split (":", $keydata);
    
    $clientsig = pack (qq{H*}, $clientsig);
    $dhpublic_cli = pack (qq{H*}, $dhpublic_cli);
    $clientsecret = pack (qq{H*}, $clientsecret);
    
    #verify the key
    my $rsapub = Crypt::PK::RSA->new($config->{'keys'}->{'client_public_rsa'});
    if (! $rsapub->verify_message($clientsig, $dhpublic_cli)) {
	print $socket "RSA Verification Failed\n";
	$logger->warn("RSA verifivation failed");
    }

    $dhpublic_cli = Crypt::PK::DH->new(\$dhpublic_cli);
    #compute shared secret
    my $srvsecret = dh_shared_secret($dhprivate_srv, $dhpublic_cli);
    if ($srvsecret eq $clientsecret) {
	$logger->info("Client authorized");
	my $cryptdata = encrypt("Authorized");
	print $socket "$cryptdata\n";
	return 1;
    }

    $logger->info("Client failed authorization");
    return -1;
}

#for various dumb reasons I'm goint to use makrandom to generate
#a sting of characers (the key and IV in the AES encrypt/decrypt needs to be
#a string and not a number and quoting the variable isn't making it work better
#so we are doing this. Recursively generate a string of a desired length using
#printable ASCII characters.
#input length - int - length of string
#      string - char - generated string
#         note : when you initially call genRandomString 'string' should
#                be null
#output char (string of desired length)
sub genRandomString {
    my $length = shift @_;
    my $string = shift @_;
    my $num = makerandom(Size=>6, Strength =>0);
    
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

# we need the client's public key in order to encrypt things
# because of the way we set up the server we need to send this as a single
# string of text. As such, we have to unpack it before we return
# input : char string
# output : char string
sub encrypt {
    my $enclear = shift @_;
    chomp $enclear;

    my $cli_public;
    my $cryptkey;
    my $ciphertext;
    
    my $key = genRandomString(16);
    
    #create an initialization vector
    my $iv = genRandomString(16);
    
    #load the client's public key
    eval {$cli_public = Crypt::PK::RSA->new($config->{'keys'}->{'client_public_rsa'})};
    if ($@) {
	$logger->error("Error loading client public key: $@");
	return -1;
    }
    
    #encrypt the AES key with the public key
    eval {$cryptkey = unpack(qq{H*}, $cli_public->encrypt($key))};
    if ($@) {
	$logger->error("Error encrypting AES key: $@");
	return -1;
    }
    
    #instantiate AES 
    my $AES = Crypt::Mode::CTR->new('AES');
    #encrypt the data with the unencrypted AES key and the iv

    eval {$ciphertext = unpack(qq{H*}, $AES->encrypt($enclear, $key, $iv))};
    if ($@) {
	$logger->error("Error in AES encryption of message: $@");
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
    my $srv_private;
    my $key;
    my $enclear;
    
    #we need to grab the 1st 16 bytes as the iv
    my $iv = substr($ciphertext, 0, 16);

    #we need to grab the next 512 bytes as the encrypted key
    my $cryptkey = substr($ciphertext, 16, 512);

    $cryptkey = pack(qq{H*}, $cryptkey);
   
    # now put the rest of the data back into ciphertext
    $ciphertext = substr($ciphertext, 528);

    $ciphertext = pack(qq{H*}, $ciphertext); 
    
    #load the servers private RSA key
    eval {$srv_private = Crypt::PK::RSA->new($config->{'keys'}->{'server_private_rsa'})};
    if ($@) {
	$logger->error("Error loading private key: $@");
	return -1;
    }    

    #decrypt the AES key
    eval {$key = $srv_private->decrypt($cryptkey)};
    if ($@) {
	$logger->error("Error decrypting AES key: $@");
	return -1;
    }
    
    #instantiate AES 
    my $AES = Crypt::Mode::CTR->new('AES');

    #encrypt the data with the unencrypted AES key and the iv
    eval {$enclear = $AES->decrypt($ciphertext, $key, $iv)};
    if ($@) {
	$logger->error("Error in AES decryption of message: $@");
	return -1;
    }

    return $enclear;
}

sub processInput {
    use Data::Dumper;
    my $cli_socket = shift; # client socket
    my $client_input = shift;
    # $client_input may be -1 if the decryption failed
    # if it is then the json_decode will fail and we will
    # fall through these if statements to the final return
    my $response = "";
    my $request = decode_json($client_input);

    $logger->debug("in processInput with route: " . $request->{'route'} . " and action: " . $request->{'action'});
    if ($request->{'action'} eq "add") {
	$response = encrypt(&addBHRoute($request->{'route'}));
	print $cli_socket $response;
	return;
    }

    if ($request->{'action'} eq "dump") {
	$response = encrypt(&dumpRoutes());
	print $cli_socket $response;
	return;
    }
    
    if ($request->{'action'} eq "del") {
	$response = encrypt(&withdrawRoutes($request->{'route'}));
	print $cli_socket $response;
	return;
    }

    if ($request->{'action'} eq "exabeat") {
	$response = encrypt("Success");
	print $cli_socket $response;
	return;
    }

    if ($request->{'action'} eq "bgpbeat") {
	if ( (-e $config->{'exabgppipe'}->{'pipein'}) &&
	     (-e $config->{'exabgppipe'}->{'pipeout'}) {
	    $response = encrypt("Success");
	    print $cli_socket $response;
	    return;
	} 
    }

    #There are a few ways we can end up here
    #1 The json is malformed
    #2 The action doesn't match anything
    #3 the decrypt routine crapped out
    # 1 and 2 are dealt with in the same way
    # encrypt -2 and send it back out
    # 3 indicates a problem with encrytption as a whole and
    # we probably shouldn't try to use it to send a value back
    if (looks_like_number($client_input)) {
	if ($client_input == -1) {
	    $response = -3;
	}
    } else {
	$response = encrypt("-2");
    }
    print $cli_socket $response;
}
 
sub addBHRoute {
    my $route = shift;

    # the template for each blackhole route configuration in the config
    # file in the format of template_n so step through each and replace
    # the route keyword with the route to blackhole
    $logger->debug("In addBHRoute with $route->{bh_route} and  $config->{templates}->{total_templates} templates");
    for (my $i = 1; $i <= $config->{'templates'}->{'total_templates'}; $i++) {
	my $templateNum = "template_" . $i;
	my $template = $config->{'templates'}->{$templateNum};
	$logger->debug("template is $template for $templateNum");
	$template =~ s/_route_/$route->{bh_route}/;
	$logger->debug("Transformed template is $template");
	# we just print to STDOUT to send it to the ExaBGP process
	print STDOUT $template ."\n";
	$logger->debug("sending $template to ExaBGP")
    }
    return "Success";
}
    
sub dumpRoutes {
    # this is a test to see if we can grab the data from exabgp.
    # this isn't the way I'd like to do it but it seems to work effectively
    # so I'm not going to complain too much right now. 
    my $data;
    my $stderr;
    my $exit;
    ($data, $stderr, $exit) = capture {
	system ("/usr/local/bin/exabgpcli show adj-rib out");
    };
    return $data;
}

sub withdrawRoutes {
    my $route = shift;
    for (my $i = 1; $i <= $config->{'templates'}->{'total_templates'}; $i++) {
	my $templateNum = "template_" . $i;
	my $template = $config->{'templates'}->{$templateNum};
	$logger->debug("template is $template for $templateNum");
	$template =~ s/announce/withdraw/;
	$template =~ s/_route_/$route->{'bh_route'}/;
	$logger->debug("Transformed template is $template");
	# we just print to STDOUT to send it to the ExaBGP process
	print STDOUT $template ."\n";
	$logger->debug("sending $template to ExaBGP")
    }    
    return;
}

sub startServer {
    my $authorized = -1;
    $SIG{CHLD} = sub {wait ()};
    my $server = IO::Socket::INET->new(LocalAddr => $config->{'server'}->{'host'},
				       LocalPort => $config->{'server'}->{'listen'},
				       Listen => SOMAXCONN,
				       Proto => 'tcp',
				       Reuse => 1,
				       Timeout => 1,
	);
    die "Socket could not be created. Reason: $!\n" unless ($server);

    $logger->info("Server started");
    my $child;
    while (1) {
	while ($child = $server->accept()) {
	    my $pid = fork();
	    if (! defined ($pid)) {
		$logger->error("Cannot fork child: $!");
		close ($child);
	    }
	    IO::Socket::Timeout->enable_timeouts_on($child);
	    $child->read_timeout(1);
	    $child->write_timeout(1);
	    if ($pid == 0) {
		my $response = <$child>;
		#pre auth do not decrypt
		if ($response =~ /auth/i) {
		    $authorized = &authorize($child);
		} else {
		    $logger->warn("Bad auth request");
		    #if the first request from the client isn't to
		    #authorize then close the client out
		}		    
		# Child process
		if ($authorized == 1) {
		    while (defined (my $buf = <$child>)) {
			# the input is a json object
			$buf = decrypt($buf);
			# if encryption fails $buf is -1
			# pass that to processInput and deal with it there
			chomp $buf;
			
			if ($buf =~ /quit/) {
			    print $child encrypt("Quitting"). "\n";
			    close ($child);
			}
			$logger->debug("Sending $buf to processInput");
			processInput($child, $buf);
		    }
		    exit(0); # Child process exits when it is done.
		} else {
		    # do not encrypt this as it is preauth failure. 
		    print $child "Authorization failure\n";
		    $logger->warn("Authorization failure");
		    close ($child);
		}
	    } # else 'tis the parent process, which goes back to accept()
	}
    }
}

############# MAIN ###################

# get the command line options
getopts ("f:h", \%options);
if (defined $options{h}) {
    print "exabgp_interface usage\n";
    print "\texabgp_interface.pl [-f] [-h]\n";
    print "\t-f path to configuration file. Defaults to /usr/local/etc/exabgp_interface.cfg\n";
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

#get the config information
readConfig();

#start the logger
Log::Log4perl::init($config->{'logconfig'}->{'path'});
$logger = Log::Log4perl->get_logger('logger');

# make sure the config isn't full of stupid
validateConfig();

#start the main server loop
startServer();

