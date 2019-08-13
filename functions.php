<?php
/*
 * Copyright (c) 2019 The Board of Trustees of Carnegie Mellon University.
 *
 *  Authors: Chris Rapier <rapier@psc.edu>
 *          Nate Robinson <nate@psc.edu>
 *          Bryan Learn <blearn@psc.edu>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License. *
 */

/* time the user out after a certain period of inactivity.
 * TODO: set the inactivity time to a configurable value
 * used in multiple php files */

/* TODO: prior to distribution any references to the private directory needs to be
 * shifted.
 */

include_once './private/functions.cfg';

function sessionTimer() {
	$login_session_duration = $DURATION_TIMER*60; // DURATION_TIMER defined in functions.cfg
	$current_time = time();
	if(isset($_SESSION['timer'])){
		if(((time() - $_SESSION['timer']) > $login_session_duration)){
			header("Location:http://". $_SERVER['SERVER_NAME'] . "/blackholesun/timeout.php");
		}
		// update the time
		$_SESSION["timer"] = time();
		return;
	}
	// Somehow the session time isn't set at all. Bounce them to the timeout page anyway
	header("Location:http://". $_SERVER['SERVER_NAME'] . "/blackholesun/timeout.php");
}

/* generate a database handle */
function getDatabaseHandle () {
	//Create our Database Handler, $dbh
	// the DB variables are in the functions.cfg file. Not included in the git
	// but it's just wrapper of php tags around the definitions for these variables.
	ini_set('display_errors',1);
	try {
		$dbh = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME", $DB_USERNAME, $DB_PASSWORD);
	} catch (Exception $e) {
		/* TODO: need better reporting here. */
		print "Failed to connect $e->getMessage()";
	};
	return $dbh;
}

/* confirm user input against the db value */
	function confirmPass($password) {
	$dbh = getDatabaseHandle();
	$stmnt = $dbh->prepare("SELECT bh_user_pass
                            FROM bh_users
                            WHERE bh_user_id = :bhsid");
    $stmnt->bindParam(":bhsid", $_SESSION["bh_user_id"], PDO::PARAM_STR);
    $stmnt->execute();
    $result = $stmnt->fetch(PDO::FETCH_ASSOC);
    return (password_verify($password, $result["bh_user_pass"]));
}

function prewrap($text) {
	print("<tr><td align='left'><table border=1><tr><td valign='top' align='left'><pre>");
    var_dump ($text);
    print("</pre></td></tr></table></td></tr>");
}

/* we need to do some sanity checking on the addresses being sent to us
* so we're just going to make sure each of the address are valid ipv4 or ipv6
* and that the cidr block isn't insane
*/
function validateCIDR($cidr) {
	list ($address, $mask) = explode ("/", $cidr);

	# make sure we have *something* here
	if (!isset($address)) {
		return -1;
	}
	/* they may not supply a mask so we need to assume that if they
	* don't then it's a single address 32 for v4 & 128 for v6*/
	if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
		if (!isset($mask)) {
			$mask = 32;
		}
		if ($mask >= 0 && $mask <= 32) {
			return 1;
		}
	}
	if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
		if (!isset($mask)) {
			$mask = 128;
		}
		if ($mask >= 0 && $mask <= 128) {
			return 1;
		}
	}
	return -1;
}

/* a user might enter a CSV list with extra commas or spaces or
 * even mix and match them on the same line so fix it for them
 */
function normalizeListInput ($list) {
	$nospaces = preg_replace("/\s+/", ",", $list);
	$noextracommas = preg_replace("/,+/", ",", $nospaces);
	$notrailingcomma = preg_replace("/,+$/", "", $noextracommas);
	return $noextracommas;
}


/* need to open a socket to the server
 * send the request
 * and get anything back for display
 */
function sendToProcessingEngine ($request) {
    /* open the socket */
    if (!($sock = socket_create(AF_INET, SOCK_STREAM, 0))) {
        $errorcode = socket_last_error();
        $errormsg = socket_strerror($errorcode);
        print "I cowardly refused to create a socket: [$errorcode], $errormsg\n";
        exit;
    }
    /* connect to the local processing engine (client side interface to exabgp)*/
    if (! socket_connect($sock, $EXASERVER_CLIENTSIDE, $EXASERVER_CLIENTPORT)) {
        $errorcode = socket_last_error();
        $errormsg = socket_strerror($errorcode);
        print "Could not connect to processing engine: [$errorcode], $errormsg\n";
        exit;
    }
    /* send the data */
    if (! socket_send($sock, $request, strlen($request), 0)) {
        $errorcode = socket_last_error();
        $errormsg = socket_strerror($errorcode);
        print "Could not send data: [$errorcode] $errormsg \n";
        exit;
    }
    /* read the response */
    /* TEMPNOTE i Need to have the processing
     * engine spit some back to test that this works
     * Might want to just take the inbound json (from here)
     * reencode it, and spit it back 10/12/2018*/
    if (!($buf = socket_read($sock, $EXASERVER_CLIENTBUFSIZ, PHP_NORMAL_READ))) {
        $errorcode = socket_last_error();
        $errormsg = socket_strerror($errorcode);
        die("Could not receive data: [$errorcode] $errormsg \n");
    }
    return $buf;
}
?>