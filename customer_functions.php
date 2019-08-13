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

function listClients()
{
	// get a list of the users on the system and provide admins with a method
	// to edit them
	$dbh = getDatabaseHandle();
	$query = "SELECT bh_client_id,
                     bh_client_name,
                     bh_client_blocks
              FROM bh_clients";
	try{
		$sth = $dbh->prepare($query);
		$sth->execute();
		$result = $sth->fetchall(PDO::FETCH_ASSOC);
	}     catch(PDOException $e) {
		// TODO need beter exception message passing here
		print "<h1>Something went wrong while interacting with the database:</h1> <br>"
				. $e->getMessage();
		return 0;
	}
	if (! isset($result)) {
		// The DB didn't return a result or there was an error
		print "No results were returned. This shouldn't happen as an initial user is created on install.";
		return 0;
	}
	// we have some results. Place them into a table structure
	// the fields are
	// bh_client_id (int)
	// bh_client_name (char)
	// bh_client_blocks (json)
	// the json structrure is
	//   name: char (same as client name)
	//   ASNs: array of ints
	//   vlans: array of ints
	//   blocks: array of chars (format CIDR address blocks)

	$table_header = "<tr><th>Client ID</th><th>Client Name</th><th>ASNs</th><th>VLANs</th><th>Blocks</th><th>Edit</th></tr>";
	$table_body = "";
	foreach ($result as $line) {
		$block = json_decode($line['bh_client_blocks']);
		$asns = implode(", ", $block->{'ASNs'});
		$vlans = implode(", ", $block->{'vlans'});
		$addresses = implode (", ", $block->{'blocks'});
		$table_body .=  "<tr><td>" . $line['bh_client_id'] .
		"</td><td>" . $line['bh_client_name'] .
		"</td><td>" . $asns .
		"</td><td>" . $vlans .
		"</td><td>" . $addresses .
		"</td><td><input type='checkbox' name='editclient' value='" . $line['bh_client_id'] . "' />" .
		"</td></tr>";
	}
	$table = "<table id='client_list' class='table'>" . $table_header .  $table_body . " </table>";
	return $table;
}

/* client_id is the id of the client in the bhsun database table bh_clients */
function loadClientForm ($client_data, $postFlag) {
	if ($postFlag == 0) {
		$client_id = $client_data; /*stupid but it keeps the nomenclature more reasonable */
		// take the incoming id and grab the required structure out of the database
		$dbh = getDatabaseHandle();
		$query = "SELECT bh_client_id,
                     bh_client_name,
                     bh_client_blocks
              FROM   bh_clients
              WHERE  bh_client_id = :clientid";
		try{
			$sth = $dbh->prepare($query);
			$sth->bindParam(':clientid', $client_id, PDO::PARAM_STR);
			$sth->execute();
			$result = $sth->fetch(PDO::FETCH_ASSOC);
		}
		catch(PDOException $e) {
			// TODO need beter exception message passing here
			$error =  "Something went wrong while interacting with the database:"
					. $e->getMessage();
			return array(1, $error);
		}
		if (! isset($result)) {
			// The DB didn't return a result or there was an error
			$error = "No results were returned. There may be a problem with the database.";
			return array (1, $error);
		}
		// we have a client now
		// bh_client_id = int
		// bh_client_name = char
		// bh_client_blocks = json
		//    name char
		//    vlans array of ints
		//    ASNs array of ints
		//    blocks array of CIDR address blocks
		$name = $result['bh_client_name'];

		// grab the json
		$clientobj = json_decode($result['bh_client_blocks']);

		// convert the arrays in to strings
		$ASNs = implode (", ", $clientobj->{'ASNs'});
		$vlans = implode (", ", $clientobj->{'vlans'});
		$blocks = implode (", ", $clientobj->{'blocks'});
	} else {
		// we've be sent data in the form of a post structure
		$name = $client_data['client-name'];
		$client_id = $client_data['bh_client_id'];
		$ASNs = $client_data['client-asns'];
		$vlans = $client_data['client-vlans'];
		$blocks = $client_data['client-blocks'];
	}

	$form  = "<form id='updateUserForm' role='form' class='form-horizontal col-8' action='" .
			htmlspecialchars($_SERVER["PHP_SELF"]) . "' method='post'>\n";
	$form .= "<input type='hidden' name='action' value='updateClient' />\n";
	$form .= "<input type='hidden' name='bh_client_id' value='$client_id' />\n";
	$form .= "<div class='form-group'><label for='client-name'> Name:</label><input type='text' name='client-name' class='form-control' value='$name' required></div>\n";
	$form .= "<div class='form-group'><label for='client-asns'> ASNs:</label><input type='text' name='client-asns' class='form-control' value='$ASNs'></div>\n";
	$form .= "<div class='form-group'><label for='client-vlans'> VLANs:</label><input type='text' name='client-vlans' class='form-control' value='$vlans'></div>\n";
	$form .= "<div class='form-group'><label for='client-blocks'> Blocks:</label><textarea rows='4' columns='40' name='client-blocks' class='form-control' required>" . $blocks . "</textarea></div>\n";
	$form .= "<button type='submit' class='btn btn-lg btn-success'>Update Client</button></form>";
	$form .="<p><p>";
	$form .= "<input action=\"action\" onclick=\"window.location = './customers.php';
           return false;\" type=\"button\" value=\"Cancel\" class=\"btn btn-lg btn-danger\"/>\n";

	return array(0, $form);
}

/* add a new client (address blocks etc) to the database */
function newCustomerForm ($data) {
	$form  = "<form id='addClientForm' role='form' class='form-horizontal col-8' action='" .
			htmlspecialchars($_SERVER["PHP_SELF"]) . "' method='post'>\n";
	$form .= "<input type='hidden' name='action' value='addClient' />\n";
	$form .= "<div class='form-group'><label for='client-name'> Name:</label><input type='text' name='client-name'
               class='form-control' value='" . $data['client-name'] . "' required></div>\n";
	$form .= "<div class='form-group'><label for='client-asns'> ASNs:</label><input type='text' name='client-asns'
               class='form-control' value='" . $data['client-asns'] . "'></div>\n";
	$form .= "<div class='form-group'><label for='client-vlans'> VLANs:</label><input type='text' name='client-vlans'
               class='form-control' value='" . $data['client-vlans'] . "'></div>\n";
	$form .= "<div class='form-group'><label for='client-blocks'> Blocks:</label><textarea
               rows='4' columns='40' name='client-blocks'
               class='form-control' value='" . $data['client-blocks'] . "' required></textarea></div>\n";
	$form .= "<button type='submit' class='btn btn-lg btn-success'>Add Client</button></form>";
	$form .= "<P><P>";
	$form .= "<input action=\"action\" onclick=\"window.location = './customers.php';
           return false;\" type=\"button\" value=\"Cancel\" class=\"btn btn-lg btn-danger\"/>\n";

	return $form;
}

function deleteClientWidget  ($client_id) {
	$form  = "<form id='deleteClient' role='form' action='" .
			htmlspecialchars($_SERVER["PHP_SELF"]) . "' method='post'>\n";
	$form .= "<input type='hidden' name='action' value='deleteClient' />\n";
	$form .= "<input type='hidden' name='bh_client_id' value='$client_id' />\n";
	$form .= "<button type='submit' class='btn btn-lg btn-danger'>Delete Client</button></form>";
	return($form);
}
?>