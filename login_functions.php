<?php
/*
 * Copyright (c) 2019 The Board of Trustees of Carnegie Mellon University.
 *
 *  Authors: Chris Rapier <rapier@psc.edu>
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

//Server input scrubber
function scrubInput($data)
{
	$data = trim($data);
	$data = stripslashes($data);
	$data = htmlspecialchars($data);
	return $data;
}//END scrubInput

function logIn($username, $password)
{
	//Create our Database Handler, $dbh
	$dbh = getDatabaseHandle();
	$stmnt = $dbh->prepare("SELECT bh_user_pass,
                                   bh_user_active,
                                   bh_user_role,
                                   bh_user_fname,
                                   bh_user_lname,
                                   bh_user_id,
                                   bh_user_affiliation,
                                   bh_user_force_password
		            FROM bh_users
			    WHERE bh_user_name = :username");
	$stmnt->bindParam(':username', $username, PDO::PARAM_STR);
	try {
		$stmnt->execute();
	} catch (Exception $e) {
		print "Failed to execute $e->GetMessage()";
	}
	$queryResult = $stmnt->fetch(PDO::FETCH_ASSOC); //returns FALSE if empty result
	if (!$queryResult) //Did we find a match to the submitted username?
	{
	return 1;
	}
	else //found a username match, time to see if the password is correct
	{
	if (!password_verify($password, $queryResult["bh_user_pass"]) || ($queryResult["bh_user_active"] == 0)) //fail
	{
			return 1; //return 1 to notify password match failed
	}
	else //pass!
	// Not sure I like all of this being set here. It hides the process from the login page.
	// that said, the alternative would be to return all of this data and that's overly complicated.
	{
	//load relevant user info into the current session
	$_SESSION["username"] = $username;
		$_SESSION["bh_user_role"] = $queryResult["bh_user_role"];
		$_SESSION["fname"] = $queryResult["bh_user_fname"];
		$_SESSION["lname"] = $queryResult["bh_user_lname"];
		$_SESSION["bh_user_id"] = $queryResult["bh_user_id"];
				$_SESSION["bh_customer_id"] = $queryResult["bh_user_affiliation"];
				list($error, $_SESSION["bh_customer_name"]) = getName($queryResult["bh_user_affiliation"]);
						if ($error == -1) {
                print "Error: " . $_SESSION['bh_customer_name'] . " . Halting.";
	}
                		$_SESSION["timer"]= time();
				if ($queryResult["bh_user_force_password"]) {
				header("Location:http://". $_SERVER['SERVER_NAME'] ."/blackholesun/changepass.php");
				} elseif ($queryResult["bh_user_role"] == 4) {
						header("Location:http://". $_SERVER['SERVER_NAME'] ."/blackholesun/usermanagement.php");
            } else {
            header("Location:http://". $_SERVER['SERVER_NAME'] ."/blackholesun/routes.php");
            }
            //die();
            		return 0; //return 0 to notify password match success
            }
            }
}//END logIn

function getName($customer_id) {
	$dbh = getDatabaseHandle();
	$query = "SELECT bh_customer_name
              FROM bh_customers
              WHERE bh_customer_id = :customer_id";
	try{
		$sth = $dbh->prepare($query);
		$sth->bindParam(':customer_id', $customer_id, PDO::PARAM_STR);
		$sth->execute();
		$result = $sth->fetch();
	} catch(PDOException $e) {
		// TODO need beter exception message passing here
		return array (-1, "Something went wrong while interacting with the database:<br>"
				. $e->getMessage());
	}
	if (! isset($result)) {
		// The DB didn't return a result or there was an error
		return array(-1, "No results were returned. The customers data tables might be empty.");
	}
	return array (1, $result[0]);
}

function logOut()
{
	unset($_SESSION["username"]);
	unset($_SESSION["CID"]);
	unset($_SESSION["UID"]);
	session_unset();
	session_destroy();
}//END logOut()

?>