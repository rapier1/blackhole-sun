<?php
/*
 * Copyright (c) 2019 The Board of Trustees of Carnegie Mellon University.
 *
 *  Authors: Chris Rapier <rapier@psc.edu>
 *           Nate Robinson <nate@psc.edu>
 *           Bryan Learn <blearn@psc.edu>
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

/* load the variables */
include_once './functions.cfg.php';

/* this automatically expires a user session if they haven't loaded a page
 * in some period of time. This has to be included on every page 
 * after the functions.php include is loaded
 */
function sessionTimer() {
	$login_session_duration = DURATION_TIMER * 60; // DURATION_TIMER defined in functions.cfg
	$current_time = time();
	if(isset($_SESSION['timer'])){
		if ((time() - $_SESSION['timer']) > $login_session_duration) {
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
	// Create our Database Handler, $dbh
	// the DB variables are in the functions.cfg file. Not included in the git
	// but it's just wrapper of php tags around the definitions for these variables.
	ini_set('display_errors',1);
	try {
		$dbh = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USERNAME, DB_PASSWORD);
	}
    catch (Exception $e) {
		$msg = "Failed to connect to database with error:" . $e->getMessage() . ". ";
        $msg .= "The database process might not be running.";
        print "<script type='text/javascript'>\n";
        if (empty ($_SESSION)) {
            /* this is likely happening from the login page */
            print "alert('$msg');";
            print "self.location='login.php';";
        } else {
            /* everywhere else a modal message shoudl work */
            print "modalMessage('error', '" . $msg . "');";
        }
        print "</script>\n";
        /* we have to exit now or php will try to process the dead handle */
        exit;
	}
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

/* this is a debug routine that automagically wraps the 
 * var_dump in <pre> tags in a table
 */
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

/* we want to be able to alert people when a route is added or 
 * modified. The goal is to alert everyone with an email address
 * associated with the customer who owns that particular route. We also
 * need to send email to staff/admin contacts 
 * we take the incoming user id and use that to determine the affiliated
 * customer. We then use that to extract all email addresses on that account
 * we then extract all email addresses with a non-user role (3 and 4)
 * we bundles all of those up and send the update out
 * NOTE: Almost all of this happens in the client interface and
 * not here. this is just an entry method
 * inputs: route_info (stringified json)
 * return: 1 on success -1 and errmsg on failure       
 */

function emailNotification ($route_info) {
    #convert the stringified json back into an object
    $json = json_decode($route_info);
    # change the action
    $json->{'action'} = "email";
    # re-encode it. Might be easier to do a regex replace on blackhole/email
    # but this way we absolutely ensure that we only modify the action parameter
    $route_info = json_encode($json);
    $response = sendToProcessingEngine($route_info);
    if (!preg_match("/Success/", $response)) {
        return array(-1, $response);
    }
    return array(1, NULL);
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
    /* in order to confirm who we are and maintain security
     * we are going to cryptographically sign each message sent with
     * a local private key. So we need to send the request to another 
     * funtion that will load the key, sign the message,
     * and append the message to the json struct
     * along with an identifier of the client. This identifier
     * informs the client as to which public key it shoudl be using
     * to verify the signature
     */
    $request = signMessage($request);
    if ($request == -1) {
        return "Could not load private key to sign message";
    } elseif ($request == -2) {
        return "Could not sign the message with the private key";
    } elseif ($request == -3) {
        return "Could not encode outbound JSON request";
    }
    /* that worked so send out the request */

    /* open the socket */
    if (!($sock = socket_create(AF_INET, SOCK_STREAM, 0))) {
        $errorcode = socket_last_error();
        $errormsg = socket_strerror($errorcode);
        return "Unable to create a socket for the processing engine: [$errorcode], $errormsg\n";
    }
    /* connect to the local processing engine (client side interface to exabgp)*/
    if (! socket_connect($sock, EXASERVER_CLIENTSIDE, EXASERVER_CLIENTPORT)) {
        $errorcode = socket_last_error();
        $errormsg = socket_strerror($errorcode);
        return "Could not connect to processing engine: [$errorcode], $errormsg\n";
    }
    /* send the data */
    if (! socket_send($sock, $request, strlen($request), 0)) {
        $errorcode = socket_last_error();
        $errormsg = socket_strerror($errorcode);
        return "Could not send data: [$errorcode] $errormsg \n";
    }
    /* read the response */
    if (!($buf = socket_read($sock, EXASERVER_CLIENTBUFSIZ, PHP_NORMAL_READ))) {
        $errorcode = socket_last_error();
        $errormsg = socket_strerror($errorcode);
        return "Could not receive data: [$errorcode] $errormsg \n";
    }
    return $buf;
}

/* sign the outbound message request with a crypto signature
 * request is in JSON format so we need to add to the end of it without
 * breaking the json structure. We'll be using a private key to sign the
 * contents. We also need to append a unique identifier to the message
 * so the client knows what key to look at
 */
function signMessage ($request) {
    /* get the private key - ideally this could be cached using memcache
     * but that's for later and after a security review */
    $key_path = "file://" . PRIVATE_SIGNATURE_KEY;
    $private = openssl_pkey_get_private($key_path);
    if ($private === FALSE) {
        return -1;
    }
    if (openssl_sign($request, $signature, $private, OPENSSL_ALGO_SHA256) === FALSE) {
        return -2;
    }

    /* at this point we've signed the request but we are going to create a new
     * json object where the request is one member and the signature and uuid
     * are other members
     */

    $outbound_json['request'] = $request;
    $outbound_json['signature'] = bin2hex($signature);
    $outbound_json['uuid'] = UI_UUID;

    $signed_request = json_encode ($outbound_json);
    if ($signed_request === FALSE) {
        return -3;
    }

    return $signed_request;
}

/* this creates the widget used to change the user password
 * normally it is hidden but is displayed when the user clicks on the
 * change password button
 */
function changePasswordWidget () {
    $form  = "<form id='updatePassword' role='form' class='form-horizontal col-8' action='"  .
           htmlspecialchars($_SERVER["PHP_SELF"]) . "' method='post'>\n";
    $form .= "<input type='hidden' name='form_src' value='userManagement' />\n";
    $form .= "<input type='hidden' name='action' value='changePassword' />\n";
    $form .= "<input type='hidden' name='bh_user_id' value='" . $_SESSION['bh_user_id'] . "' />\n";
    $form .= "<div class='form-group'><label for='cpass'> Current Password:</label>
              <input type='password' name='cpass' class='form-control' value='' required></div>\n";
    $form .= "<div class='form-group'><label for='npass1'> New Password:</label>
              <input type='password' name='npass1' class='form-control' value='' required></div>\n";
    $form .= "<div class='form-group'><label for='npass2'> Confirm Password:</label>
              <input type='password' name='npass2' class='form-control' value='' required></div>\n";
    $form .= "<button type='submit' class='btn btn-lg btn-success'>Update Password</button></form>";
    return($form);
}

?>
