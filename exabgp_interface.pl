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
#check db data
    if (! defined $config->{'server'}->{'listen'}) {
        print STDERR "Listen port not defined in config file. Exiting\n";
	$logger->error("Listen port not defined in config file. Exiting");
        exit;
    }
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


}

sub authorize {
    # get the client socket
    my $socket = shift;
    
    # the default is to fail so explicitly set the return value
    my $authorized = -1;

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
    $logger->debug("Checking Secrets");
    if ($srvsecret eq $clientsecret) {
	$authorized = 1;
	print $socket "Authorized\n";
    }

    $logger->info("Client authorized");
    
    return $authorized;
}

sub processInput {
    my $cli_socket = shift; # client socket
    my $client_input = shift;
    my $response;
    my %request = decode_json($client_input);

    #request{action}
    #request{route}

    if ($request{'action'} == "add") {
	$response = &addBHroute($request{'route'});
    }

    if ($request{'action'} == "dump") {
	$response = &dumpRoutes();
    }
    
    if ($request{'action'} == "del") {
	$response = &withdrawRoutes($request{'route'});
    }

    print $cli_socket $response;
}
 
sub addBHRoute {
    my $route = shift;
    
    # the template for each blackhole route configuration in the config
    # file in the format of template_n so step through each and replace
    # the route keyword with the route to blackhole
    $logger->debug("In addBHRoute with $route and  $config->{templates}->{total_templates} templates");
    for (my $i = 1; $i <= $config->{'templates'}->{'total_templates'}; $i++) {
	my $templateNum = "template_" . $i;
	my $template = $config->{'templates'}->{$templateNum};
	$logger->debug("template is $template for $templateNum");
	$template =~ s/_route_/$route/;
	$logger->debug("Transformed template is $template");
	# we just print to STDOUT to send it to the ExaBGP process
	print STDOUT $template ."\n";
	$logger->debug("sending $template to ExaBGP")
    }
    return;
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
    }
    return $data;
}

sub withdrawRoutes {
    my $route = shift;
    for (my $i = 1; $i <= $config->{'templates'}->{'total_templates'}; $i++) {
	my $templateNum = "template_" . $i;
	my $template = $config->{'templates'}->{$templateNum};
	$logger->debug("template is $template for $templateNum");
	$template =~ s/announce/withdraw/;
	$template =~ s/_route_/$route/;
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
    my $server = IO::Socket::INET->new(LocalAddr => '0.0.0.0',
				       LocalPort => $config->{'server'}->{'listen'},
				       Listen => 5,
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
	    $child->read_timeout(5);
	    $child->write_timeout(5);
	    if ($pid == 0) {
		my $response = <$child>;
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
			chomp $buf;
			if ($buf =~ /quit/) {
			    print $child "Quitting\n";
			    close ($child);
			}
			processInput($child, $buf);
		    }
		    exit(0); # Child process exits when it is done.
		} else {
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

