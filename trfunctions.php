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

//Server input scrubber
function scrubInput($data)
{
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}//END scrubInput


function buildDiv($divID, $dbTableName, $fields)
{
    //this function will arbitraily build div elements from passed-in arguments
    //	$divID will be the ID of the div created
    //	$divID+"Table" will the be ID of the table within the new div element
    //	$dbTableName is the MySQL table you are selecting from
    //	$fields is an array of fields that you want to select from the DB
    // *RETURN VALUE*: string of HTML which contains a div element and a table composed of
    //		 returned values from the given query

    //need an ID that isn't the same as the div for the table element
    $htmlTableID = $divID . "Table";

    //Create our Database Handler, $dbh
    $dbh = getDatabaseHandle();

    $fieldString = "";
    //Assemble list of fields to retrieve in our select statement (last field can't have a comma!)
    for ($count=0; $count < count($fields); $count++)
    {
	//is this the last element? NO COMMA!
	if ($count == (count($fields) - 1))
        {
            $fieldString = $fieldString . $fields[$count];
        }
	else //slap a comma on that shit
        {
            $fieldString = $fieldString . $fields[$count] . ", ";
        }
    }//END field assembly

    //now that we have a string of fields, assemble the query 
    $stmnt = "SELECT " . $fieldString . " FROM " . $dbTableName;# . " WHERE cid = " . $_SESSION["CID"];
    $results = $dbh->query($stmnt);
    //create a div and html table with our query results
    $newDiv = "<div id='$divID' >
			   <table id='$htmlTableID' class='table'>
			   <tr>";
    //create column headers based on $fields
    for ($counter = 0; $counter < count($fields); $counter++) //assemble each column of the new row
    {
        $newDiv = $newDiv . "<th>$fields[$counter]</th>";
    }
    
    $newDiv = $newDiv . "</tr>";
    //fill in the table with the results from the query
    foreach ($results as $row) //go row-by-row through returned query
    {
        $newRow = "<tr>";
        for ($counter = 0; $counter < count($fields); $counter++) //assemble each column of the new row
        {
            $newRow = $newRow . "<td>$row[$counter]</td>";
        }
        $newDiv = $newDiv . $newRow . "</tr>";
    }
    //close the table and div tags!
    $newDiv = $newDiv . "</table></div>";

    return $newDiv;
}//END buildDiv

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
            $_SESSION['bh_client_id'] = $queryResult["bh_user_affiliation"];
            $_SESSION["timer"]= time();
            if ($queryResult["bh_user_force_password"]) {
                header("Location:http://". $_SERVER['SERVER_NAME'] ."/blackholesun/changepass.php");    
            } elseif ($queryResult["bh_user_role"] == 4) {
                header("Location:http://". $_SERVER['SERVER_NAME'] ."/blackholesun/management.php");    
            } else {
                header("Location:http://". $_SERVER['SERVER_NAME'] ."/blackholesun/mainpage.php");
            }
            //die();
            return 0; //return 0 to notify password match success
        }
    }
}//END logIn


// time the user out after a certain period of inactivity. 
function sessionTimer() {
    $login_session_duration = 10*60; // ten minutes of inactivity 
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



function logOut()
{
    unset($_SESSION["username"]);
    unset($_SESSION["CID"]);
    unset($_SESSION["UID"]);
    session_unset();
    session_destroy();
}//END logOut()

function getDatabaseHandle () {
    //Create our Database Handler, $dbh
    // the DB variables are in the trfunctions.cfg file. Not included in the git
    // but it's just wrapper of php tags around the definitions for these variables.
    include './private/trfunctions.cfg';
    ini_set('display_errors',1);
    try {
	$dbh = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME", $DB_USERNAME, $DB_PASSWORD);
    } catch (Exception $e) {
	print "Failed to connect $e->getMessage()";
    };
    return $dbh;
}

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

function listUsers()
{
    // get a list of the users on the system and provide admins with a method
    // to edit them
    $dbh = getDatabaseHandle();
    $query = "SELECT bh_user_id,
                     bh_user_name,
                     bh_user_fname,
                     bh_user_lname,
                     bh_user_email,
                     bh_user_affiliation,
                     bh_user_role,
                     bh_user_active
              FROM bh_users";
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

    // build an array that lets us convert the numeric value of the user affiliation into a
    // textual value
    
    $query = "SELECT bh_client_id,
                     bh_client_name
              FROM   bh_clients";
    $sth = $dbh->prepare($query);
    $sth->execute();
    $client_result = $sth->fetchall(PDO::FETCH_ASSOC);
    foreach ($client_result as $line) {
        $names[$line['bh_client_id']] = $line['bh_client_name'];
    }

    // we have some results. Place them into a table structure
    // the fields are
    // bh_user_id (int)
    // bh_user_name (char)
    // bh_user_fname (char)
    // bh_user_lname (char)
    // bh_user_email (char)
    // bh_user_affiliation (char)
    // bh_user_community (char)
    // bh_user_role (int)
    // bh_user_active (int|bool)
    $role[1] = "User";
    $role[2] = "PSC Admin";
    $role[4] = "BHS Admin";    
    $table_header = "<tr><th>Username</th><th>Name</th><th>Email</th><th>Affiliation</th><th>Role</th><th>Active</th><th>Edit</th></tr>";
    $table_body = "";
    foreach ($result as $line) {
        $active = "no";
        if ($line['bh_user_active'] == 1) {
            $active = "yes";
        }
        $user_role = $role[$line['bh_user_role']];
        $table_body .=  "<tr><td>" . $line['bh_user_name'] .
			"</td><td>" . $line['bh_user_fname'] .
                        " " . $line['bh_user_lname'] .
			"</td><td>" . $line['bh_user_email'] .
			"</td><td>" . $names[$line['bh_user_affiliation']] .
			"</td><td>" . $user_role .
			"</td><td>" . $active .
			"</td><td><input type='checkbox' name='edituser' value='" . $line['bh_user_id'] . "' />" . 
			"</td></tr>";
    }
    $table = "<table id='user_list' class='table'>" . $table_header .  $table_body . " </table>";
    return $table;
}

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


/* we need to get a list of available institutions. This allows us to limit
 * access to a specific set of address blocks asscoiated with that institution.
 * this is found in the tabel bh_clients 
 */
function userAffiliationWidget($affiliation) {
    $dbh = getDatabaseHandle();
    $query = "SELECT bh_client_id,
                     bh_client_name
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
        print "No results were returned. The clients data tables might be empty.";
        return 0;    
    }
    #build the drop down widget
    $user_affiliation = "<select name='user-affiliation' class='form-control' required>\n";
    $user_affiliation .= "<option value=''>---</option>\n";
    foreach ($result as $line) {
        $selected = "";
        if ($affiliation == $line['bh_client_id']) {
            $selected = "SELECTED";
        }
        $user_affiliation .= "<option $selected value='" . $line['bh_client_id'] . "'>" . $line['bh_client_name'] . "</option>\n";
    }
    $user_affiliation .= "</select>";
    return $user_affiliation;
}

function newUserForm () {
    $user_affiliation = userAffiliationWidget("");
    $roles = array("1" => "User", "2" => "PSC Staff", "4" => "BHS Admin");
    $form  = "<form id='addUserForm' role='form' class='form-horizontal col-8' action='" .
             htmlspecialchars($_SERVER["PHP_SELF"]) . "' method='post'>\n";
    $form .= "<input type='hidden' name='action' value='addUser' />\n";
    $form .= "<div class='form-group'><label for='user-username'> Username:</label><input type='text' name='user-username' class='form-control' value='' required></div>\n";
    $form .= "<div class='form-group'><label for='user-fname'> First name:</label><input type='text' name='user-fname' class='form-control' value='' required></div>\n";
    $form .= "<div class='form-group'><label for='user-lname'> Last name:</label><input type='text' name='user-lname' class='form-control' value='' required></div>\n";
    $form .= "<div class='form-group'><label for='user-email'> Email:</label><input type='text' name='user-email' class='form-control' value='' required></div>\n";
    $form .= "<div class='form-group'><label for='user-affiliation'> Affiliation:</label>" . $user_affiliation . "</div>\n";
    $form .= "<div class='form-group'>
                        <label for='user-role'>Role:</label>
                        <div class='form-control'>
                          <select name='user-role' id='user-role'>";
    foreach (array_keys($roles) as $key) {
        $selected = "";
        if ($key == 1) {
            $selected = "selected";
        }
        $form .= "<option value=$key $selected>$roles[$key]</option>";
    }
    $form .= "</select></div></div>";
    $form .= "<div class ='form-group'>
                          <label for='user-active'>Active:</label>
                          <div class = 'form-control'>
                              <input type='radio' name='user-active' value='1' checked> Active 
                              <input type='radio' name='user-active' value='0'> Inactive
                          </div>
               </div>";
    $form .= "<button type='submit' class='btn btn-lg btn-success'>Add User</button></form>";
    return $form;
}

/* add a new client (address blocks etc) to the database */
function newClientForm ($data) {
    $form  = "<form id='addClientForm' role='form' class='form-horizontal col-8' action='" .
             htmlspecialchars($_SERVER["PHP_SELF"]) . "' method='post'>\n";
    $form .= "<input type='hidden' name='action' value='addClient' />\n";
    $form .= "<div class='form-group'><label for='client-name'> Name:</label><input type='text' name='client-name' 
               class='form-control' value='" . $data['client-name'] . "' required></div>\n";
    $form .= "<div class='form-group'><label for='client-asns'> ASNs:</label><input type='text' name='client-asns' 
               class='form-control' value='" . $data['client-asns'] . "'></div>\n";
    $form .= "<div class='form-group'><label for='client-vlans'> VLANs:</label><input type='text' name='client-vlans' 
               class='form-control' value='" . $data['client-valns'] . "'></div>\n";
    $form .= "<div class='form-group'><label for='client-blocks'> Blocks:</label><textarea 
               rows='4' columns='40' name='client-blocks' 
               class='form-control' value='" . $data['client-blocks'] . "' required></textarea></div>\n";
    $form .= "<button type='submit' class='btn btn-lg btn-success'>Add Client</button></form>";
    return $form;
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
        $vlans = $client_data['vlans'];
        $blocks = $client_data['client-blocks']; 
    }
    
    $form  = "<form id='updateUserForm' role='form' class='form-horizontal col-8' action='" .
             htmlspecialchars($_SERVER["PHP_SELF"]) . "' method='post'>\n";
    $form .= "<input type='hidden' name='action' value='updateClient' />\n";
    $form .= "<input type='hidden' name='bh_client_id' value='$client_id' />\n";
    $form .= "<div class='form-group'><label for='client-name'> Name:</label><input type='text' name='client-name' class='form-control' value='$name' required></div>\n";
    $form .= "<div class='form-group'><label for='client-asns'> ASNs:</label><input type='text' name='client-asns' class='form-control' value='$ASNs'></div>\n";
    $form .= "<div class='form-group'><label for='client-vlans'> VLANs:</label><input type='text' name='client-vlans' class='form-control' value='$vlans'></div>\n";
    $form .= "<div class='form-group'><label for='client-blocks'> Blocks:</label><input type='text' name='client-blocks' class='form-control' value='$blocks' required></div>\n";
    $form .= "<button type='submit' class='btn btn-lg btn-success'>Update Client</button></form>";
    return array(0, $form);
}


/* user_id corresponds to bh_user_id
 * user_class is the role that the calling user has and 
 * is used ot modify the form elements. eg a normal user
 * cannot modfiy their own active state. 
 * user_id_session no on but a user can change their own password
 * an admin can only generate a new password notice that is sent to the user
 */
function loadUserForm ($user_id, $user_class, $user_id_session)
{    
    // take an incoming user id and build a form that will allow the user or an admin
    // to modify their information
    $dbh = getDatabaseHandle();
    $query = "SELECT bh_user_id,
                     bh_user_name,
                     bh_user_fname,
                     bh_user_lname,
                     bh_user_email,
                     bh_user_affiliation,
                     bh_user_role,
                     bh_user_active
              FROM bh_users
              where bh_user_id = :userid";
    try{
        $sth = $dbh->prepare($query);
        $sth->bindParam(':userid', $user_id, PDO::PARAM_STR);        
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
    $active_widget = "";
    // standard user class is 1, 2 is for the NOC, 4 is for the BHS admin, I don't know what 3 is for
    if ($user_class >= 2) {
        if ($result['bh_user_active'] == 1) {
            $yes = "checked";
            $no = "";
        } else {
            $yes = "";
            $no = "checked";
        }
        $active_widget = "<div class ='form-group'>
                          <label for='user-active'>Active:</label>
                          <div class = 'form-control'>
                              <input type='radio' name='user-active' value='1' $yes> Active 
                              <input type='radio' name='user-active' value='0' $no> Inactive
                          </div>
               </div>";
    } // end active widget creation
    // we need a widget to change the role
    $role_widget = "";
    $delete_widget = "";
    if ($user_class == 4) {
        // they have to be a BHS admin to change roles
        $role_widget = "<div class='form-group'>
                        <label for='user-role'>Role:</label>
                        <div class='form-control'>
                          <select name='user-role' id='user-role'>";
        $roles = array("1" => "User", "2" => "PSC Staff", "4" => "BHS Admin");
        foreach (array_keys($roles) as $key) {
            $selected = '';
            if ($key == $result['bh_user_role']) {
                $selected = "selected";
            }
            $role_widget .= "<option value=$key $selected>$roles[$key]</option>";
        }
        $role_widget .= "</select></div></div>";
    }

    $user_affiliation = userAffiliationWidget($result['bh_user_affiliation']);
    
    $form  = "<form id='updateUserForm' role='form' class='form-horizontal col-8' action='" .
             htmlspecialchars($_SERVER["PHP_SELF"]) . "' method='post'>\n";
    $form .= "<input type='hidden' name='action' value='updateUser' />\n";
    $form .= "<input type='hidden' name='bh_user_id' value='$user_id' />\n";
    $form .= "<div class='form-group'><label for='user-username'> Username:</label><input type='text' name='user-username' class='form-control' value='$result[bh_user_name]' required></div>\n";
    $form .= "<div class='form-group'><label for='user-fname'> First name:</label><input type='text' name='user-fname' class='form-control' value='$result[bh_user_fname]' required></div>\n";
    $form .= "<div class='form-group'><label for='user-lname'> Last name:</label><input type='text' name='user-lname' class='form-control' value='$result[bh_user_lname]' required></div>\n";
    $form .= "<div class='form-group'><label for='user-email'> Email:</label><input type='text' name='user-email' class='form-control' value='$result[bh_user_email]' required></div>\n";
    $form .= "<div class='form-group'><label for='user-affiliation'> Affiliation:</label>" . $user_affiliation . "</div>\n";
    $form .= $role_widget;
    $form .= $active_widget;
    $form .= "<button type='submit' class='btn btn-lg btn-success'>Update Account</button></form>";
    if ($user_id_session == $user_id) {
	$form .= "<a href='#' onclick='toggle_vis(\"np\");'>Change Password</a></p>";
	$form .= "<div id='np' style='display: none;'>";
	$form .= changePasswordWidget();
	$form .= "</div>";

    }
    //    $form .= "</div>";
    return array (0, $form);
}

function changePasswordWidget () {
    $form  = "<form id='updatePassword' role='form' class='form-horizontal col-8' action='"  .
             htmlspecialchars($_SERVER["PHP_SELF"]) . "' method='post'>\n";
    $form .= "<input type='hidden' name='form_src' value='management' />\n";
    $form .= "<input type='hidden' name='action' value='changePassword' />\n";
    $form .= "<input type='hidden' name='bh_user_id' value='" . $_SESSION['bh_user_id'] . "' />\n";
    $form .= "<div class='form-group'><label for='cpass'> Current Password:</label><input type='password' name='cpass' class='form-control' value='' required></div>\n";
    $form .= "<div class='form-group'><label for='npass1'> New Password:</label><input type='password' name='npass1' class='form-control' value='' required></div>\n";
    $form .= "<div class='form-group'><label for='npass2'> Confirm Password:</label><input type='password' name='npass2' class='form-control' value='' required></div>\n";
    $form .= "<button type='submit' class='btn btn-lg btn-success'>Update Password</button></form>";
    return($form);    
}

// create a wdiget that we can use to reset the users password automatically
function passwordResetWidget($user_id) {
    $form  = "<form id='resetPassword' role='form' action='" .
             htmlspecialchars($_SERVER["PHP_SELF"]) . "' method='post'>\n";
    $form .= "<input type='hidden' name='action' value='resetPassword' />\n";
    $form .= "<input type='hidden' name='bh_user_id' value='$user_id' />\n";
    $form .= "<button type='submit' class='btn btn-lg btn-success'>Reset Password</button></form>";
    return($form);
}

function deleteUserWidget  ($user_id) {
    $form  = "<form id='deleteUser' role='form' action='" .
             htmlspecialchars($_SERVER["PHP_SELF"]) . "' method='post'>\n";
    $form .= "<input type='hidden' name='action' value='deleteUser' />\n";
    $form .= "<input type='hidden' name='bh_user_id' value='$user_id' />\n";
    $form .= "<button type='submit' class='btn btn-lg btn-danger'>Delete User</button></form>";
    return($form);
}

function deleteClientWidget  ($client_id) {
    $form  = "<form id='deleteClient' role='form' action='" .
             htmlspecialchars($_SERVER["PHP_SELF"]) . "' method='post'>\n";
    $form .= "<input type='hidden' name='action' value='deleteClient' />\n";
    $form .= "<input type='hidden' name='bh_client_id' value='$client_id' />\n";
    $form .= "<button type='submit' class='btn btn-lg btn-danger'>Delete Client</button></form>";
    return($form);
}

function prewrap($text) {
    print("<tr><td align='left'><table border=1><tr><td valign='top' align='left'><pre>");
    if (is_array($text)) {
        print_r($text);
    } else {
        print($text);
    }
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

/* we want to ensure that all submissions have a mask on them
 * in case the user doesn't add one then we assume it's a single
 * ip and add either a /32 for v4 or /128 for v6
 */
function normalizeRoute ($route) {
    list ($address, $mask) = explode ("/", $route);
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
        return ($address . "/" . $mask);
    }
    if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
        if (!isset($mask)) {
            $mask = 128;
        }
        return ($address . "/" . $mask);
    }
    return -1;
}

/* a user might enter a CSV list with extra commas or spaces or
 * even mix and match them on the same line so fix it for them
 */
function normalizeListInput ($list) {
    $nospaces = preg_replace("/\s+/", ",", $list);
    $noextracommas = preg_replace("/,+/", ",", $nospaces);
    return $noextracommas;
}

/* we need to ensure that the route requested lives in side 
 * of one of the address blocks owned by the customer
 * so load the approved route blocks from the database
 * if the incoming route is a /32 we just see if it's
 * in one of those blocks
 * if it is not a /32 we need to find the
 * upper and lower bounds of the network and ensure that 
 * each of them exists within a block 
 * $route is the addess supplied by the user
 * $client is the client/customer id as retreived from the user profile
 */

function validateRoute ($route, $clientid) {
    /* we are importing a class to handle this but only
     * include it if we are goign to be using it. Ya know?
     */
    include_once("./CIDR.php");

    /* first we are going to ensure that the supplied route is actually 
     * a valid ip address
     */
    if (validateCIDR($route) == -1) {
        return array (-1, "This address is not valid IPv4 or IPv6", null);
    }
    
    /* next by get the route blocks from the bh_clients table */
    $dbh = getDatabaseHandle();
    $query = "SELECT bh_client_blocks 
              FROM bh_clients 
              WHERE bh_client_id = :clientid";
    try{
        $sth = $dbh->prepare($query);
        $sth->bindParam(':clientid', $clientid, PDO::PARAM_STR);        
        $sth->execute();
        $result = $sth->fetch(PDO::FETCH_ASSOC);
    }
    catch(PDOException $e) {
        // TODO need beter exception message passing here
        $error =  "Something went wrong while interacting with the database:"
               . $e->getMessage();
        return array(-1, $error, null);
    }
    if (! isset($result)) {
        // The DB didn't return a result or there was an error
        $error = "No results were returned. There may be a problem with the database.";
        return array (-1, $error, null);
    }
    $blocks = json_decode ($result['bh_client_blocks'], true);

    /* now that we have the blocks we need to get the upper and lower
     * ranges of the user submitted route if they have a cidr mask */
    list ($upper, $lower) = explodeAddress($route);
    
    /* we now have the upper and lower bounds
     * right now i'm just going to brute force this and
     * not figure out if they are the same address
     * I'll just run both under the assumption that 
     * it won't bog things down too much as opposed to 
     * spending the time to write an elegant but of logic to 
     * handle it properly. I need more coffee. This is obvious
     */

    $cidrtest = new CIDR();

    foreach ($blocks['blocks'] as $block) {
        $uppertest = $cidrtest->match($upper, $block);
        $lowertest = $cidrtest->match($lower, $block);
        if ($uppertest === true and $lowertest === true) {
            return array (1, null, normalizeRoute($route));
        }
    }
    /* no matches*/
    return array (-1, "The IP address is not in range of any address blocks you control", null);   
}

function explodeAddress ($route) {
    list($address, $mask) = explode ("/", $route);

    /* if the mask is not set then it's a single address and not a range */
    if (!isset($mask)) {
        return array ($address, $address);
    }

    /* we need to determine if its an ipv4 or ipv6 */
    if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
        if ($mask < 0 or $mask > 32) {
            return array(-1, "Inavlid CIDR mask for IPv4");
        }
        /* get and return the network and broadcast */
        $network = long2ip((ip2long($address)) & ((-1 << (32 - (int)$mask))));
        $broadcast = long2ip((ip2long($network)) + pow(2, (32 - (int)$mask)) - 1);        
        return array($network, $broadcast);
    }

    if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
        if ($mask < 0 or $mask > 128) {
            return array(-1, "Invalid CIDR mask for IPv6");
        }
        /* the following is take from Sander Steffan at 
         * https://stackoverflow.com/questions/10085266/php5-calculate-ipv6-range-from-cidr-prefix
         * because copypasta from stackoverflow is how we get things done
         */

        // Parse the address into a binary string
        $firstaddrbin = inet_pton($address);

        // Convert the binary string to a string with hexadecimal characters
        # unpack() can be replaced with bin2hex()
        # unpack() is used for symmetry with pack() below
        $firstaddrhex = reset(unpack('H*', $firstaddrbin));

        // Overwriting first address string to make sure notation is optimal
        $firstaddrstr = inet_ntop($firstaddrbin);

        // Calculate the number of 'flexible' bits
        $flexbits = 128 - $mask;

        // Build the hexadecimal string of the last address
        $lastaddrhex = $firstaddrhex;

        // We start at the end of the string (which is always 32 characters long)
        $pos = 31;
        while ($flexbits > 0) {
            // Get the character at this position
            $orig = substr($lastaddrhex, $pos, 1);
            
            // Convert it to an integer
            $origval = hexdec($orig);
            
            // OR it with (2^flexbits)-1, with flexbits limited to 4 at a time
            $newval = $origval | (pow(2, min(4, $flexbits)) - 1);
            
            // Convert it back to a hexadecimal character
            $new = dechex($newval);
            
            // And put that character back in the string
            $lastaddrhex = substr_replace($lastaddrhex, $new, $pos, 1);
            
            // We processed one nibble, move to previous position
            $flexbits -= 4;
            $pos -= 1;
        }
        
        // Convert the hexadecimal string to a binary string
        # Using pack() here
        # Newer PHP version can use hex2bin()
        $lastaddrbin = pack('H*', $lastaddrhex);
        
        // And create an IPv6 address from the binary string
        $lastaddrstr = inet_ntop($lastaddrbin);
        return array($firstaddrstr, $lastaddrstr);
    }    
}

?>
