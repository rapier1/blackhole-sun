<?php 
/*
 * Copyright (c) 2017 The Board of Trustees of Carnegie Mellon University.
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
?>
<?php

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
    $stmnt = $dbh->prepare('SELECT bh_user_pass, 
                                   bh_user_active, 
                                   bh_user_community, 
                                   bh_user_role, 
                                   bh_user_fname, 
                                   bh_user_lname, 
                                   bh_user_id
					FROM bh_users
					WHERE bh_user_name = :username');
    $stmnt->bindParam(':username', $username, PDO::PARAM_STR);
    $stmnt->execute();
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
            $_SESSION["bh_user_community"] = $queryResult["bh_user_community"];
            $_SESSION["bh_user_role"] = $queryResult["bh_user_role"];
            $_SESSION["fname"] = $queryResult["bh_user_fname"];
            $_SESSION["lname"] = $queryResult["bh_user_lname"];
            $_SESSION["bh_user_id"] = $queryResult["bh_user_id"];
            header("Location:http://". $_SERVER['SERVER_NAME'] ."/blackholesun/mainpage.php");
            //die();
            return 0; //return 0 to notify password match success
        }
    }
}//END logIn

function generateUserInfo() 
{ 
    //we could populate this more. With what, idk yet -N
    $welcomeMessage = "Greetings, ". $_SESSION["username"]."!";
    $assocStr = $_SESSION["inst_name"] . " Test Rig";
    $welcomeDiv = '<div id="welcomeDiv">'. $welcomeMessage . '<br>'
		. $assocStr . '</div>';
    
    return $welcomeDiv;
    
}//END generateUserInfo()

function logOut()
{

    unset($_SESSION["username"]);
    unset($_SESSION["CID"]);
    unset($_SESSION["UID"]);
    session_unset();
    session_destroy();
    header("Location: http://". $_SERVER['SERVER_NAME']. "/index.php");
    die();
}//END logOut()

function getDatabaseHandle () {
    //Create our Database Handler, $dbh
    // the DB variables are in the trfunctions.cfg file. Not included in the git
    // but it's just wrapper of php tags around the definitions for these variables.
    include './private/trfunctions.cfg';
    ini_set('display_errors',1);
    $dbh = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME", $DB_USERNAME, $DB_PASSWORD);
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
                     bh_user_community,
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
			"</td><td>" . $line['bh_user_affiliation'] .
			"</td><td>" . $user_role .
			"</td><td>" . $active .
			"</td><td><input type='checkbox' name='edituser' value='" . $line['bh_user_id'] . "' />" . 
			"</td></tr>";
    }
    $table = "<table id='user_list' class='table'>" . $table_header .  $table_body . " </table>";
    return $table;
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
                     bh_user_community,
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
        return array(0, $error);
    }
    if (! isset($result)) {
        // The DB didn't return a result or there was an error
        $error = "No results were returned. There may be a problem with the database.";
        return array (0, $error);
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
    if ($user_class ==4) {
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
    $form  = "<form id='updateUserForm' role='form' class='form-horizontal col-8' action='" .
              htmlspecialchars($_SERVER["PHP_SELF"]) . "' method='post'>\n";
    $form .= "<input type='hidden' name='action' value='updateUser' />\n";
    $form .= "<input type='hidden' name='bh_user_id' value='$user_id' />\n";
    $form .= "<div class='form-group'><label for='user-username'> Username:</label><input type='text' name='user-username' class='form-control' value='$result[bh_user_name]' required></div>\n";
    $form .= "<div class='form-group'><label for='user-fname'> First name:</label><input type='text' name='user-fname' class='form-control' value='$result[bh_user_fname]' required></div>\n";
    $form .= "<div class='form-group'><label for='user-lname'> Last name:</label><input type='text' name='user-lname' class='form-control' value='$result[bh_user_lname]' required></div>\n";
    $form .= "<div class='form-group'><label for='user-email'> Email:</label><input type='text' name='user-email' class='form-control' value='$result[bh_user_email]' required></div>\n";
    $form .= "<div class='form-group'><label for='user-affiliation'> Affiliation:</label><input type='text' name='user-affiliation' class='form-control' value='$result[bh_user_affiliation]' required></div>\n";
    $form .= $role_widget;
    $form .= $active_widget;
	if ($user_id_session == $user_id) {
	    $form .= changePasswordWidget();
	}
    $form .= "<button type='submit' class='btn btn-lg btn-success'>Update Account</button></form>";
    //    $form .= "</div>";
    return array (1, $form);
}

function changePasswordWidget () {
    $form = "<a href='#' onclick='toggle_vis(\"np\");'>Change Password</a></p>";
    $form .= "<div id='np' style='display: none;'>";
    $form .= "<form id='updatePassword' role='form' class='form-horizontal col-8' action='"  .
              htmlspecialchars($_SERVER["PHP_SELF"]) . "' method='post'>\n";
    $form .= "<input type='hidden' name='form_src' value='management' />\n";
    $form .= "<input type='hidden' name='action' value='changePassword' />\n";
    $form .= "<div class='form-group'><label for='cpass'> Current Password:</label><input type='password' name='cpass' class='form-control' value='' required></div>\n";
    $form .= "<div class='form-group'><label for='npass1'> New Password:</label><input type='password' name='npass1' class='form-control' value='' required></div>\n";
    $form .= "<div class='form-group'><label for='npass2'> Confirm Password:</label><input type='password' name='npass2' class='form-control' value='' required></div>\n";
    $form .= "<button type='submit' class='btn btn-lg btn-success'>Update Password</button></form>";
    $form .= "</div>";
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
    


function generateAdminForm()
    {
	//This panel is where the user can update their info that is saved into the database. They will need
	// to provide their password in order for any changes to take effect

	//Admin Panel variables
    $AdminInputs = array(
        'fName' => "",
        'lName' => "",
        'email' => "",
        'testRigUsername' => "",
        'testRigPassword' => "",
        'phoneNumber' => "",
        'instName' => "",
        'scpPubKey' => "",
        'scpPrivKey' => "",
        'scpDstIp' => "",
        'scpHostPath' => "",
        'scpUsername' => "",
        'rtEmailAddress' => "",
        'scpHostPath' => "",
        'scpPrivKey' => "");
    
    $AdminInputErrors = array(
        'fName' => "",
        'lName' => "",
        'email' => "",
        'testRigUsername' => "",
        'testRigPassword' => "",
        'phoneNumber' => "",
        'instName' => "",
        'scpPubKey' => "",
        'scpPrivKey' => "",
        'scpDstIp' => "",
        'scpHostPath' => "",
        'scpUsername' => "",
        'rtEmailAddress' => "",
        'scpHostPath' => "",
        'scpPrivKey' => "",
        'admin-testRigPassword' => "");
    
    $url = htmlspecialchars($_SERVER["PHP_SELF"]);
    $errFlag = 0;
    $errMsg = "";
    $type = 0;
    
    $dbh = getDatabaseHandle();
    
    //let's see if the user wants to update their shit
    
    if ($_REQUEST["a"] == "ud") { //did they submit something?
        $type = 1;
        // scrub our inputs
        foreach ($_REQUEST as $key => $value) {
            if (preg_match ("/admin/", $key)) {
                $_REQUEST[$key] = scrubInput($value);
            }
        }
        
        $stmnt = $dbh->prepare("UPDATE customer 
    SET inst_name = :iname,
    contact_fname = :fname,
    contact_lname = :lname, 
    contact_phone = :phone,
    contact_email = :email, 
    inst_data_host = :host,
    inst_host_uname = :uname, 
    inst_host_path = :path,
    tt_system = :tt 
    WHERE cid = :CID");
        $stmnt->bindParam(':iname', $_REQUEST['admin-instName'], PDO::PARAM_STR); 
        $stmnt->bindParam(':fname', $_REQUEST['admin-fName'], PDO::PARAM_STR); 
        $stmnt->bindParam(':lname', $_REQUEST['admin-lName'], PDO::PARAM_STR); 
        $stmnt->bindParam(':phone', $_REQUEST['admin-phoneNumber'], PDO::PARAM_STR); 
        $stmnt->bindParam(':email', $_REQUEST['admin-email'], PDO::PARAM_STR); 
        $stmnt->bindParam(':host',  $_REQUEST['admin-scpDstIp'], PDO::PARAM_STR); 
        $stmnt->bindParam(':uname', $_REQUEST['admin-scpUsername'], PDO::PARAM_STR); 
        $stmnt->bindParam(':path',  $_REQUEST['admin-scpHostPath'], PDO::PARAM_STR); 
        $stmnt->bindParam(':tt',    $_REQUEST['admin-rtEmailAddress'], PDO::PARAM_STR); 
        $stmnt->bindParam(':CID',   $_SESSION['CID'], PDO::PARAM_STR);
        $stmnt->execute();
    }
    // Do they want to change their password?
    if ($_REQUEST["a"] == "np") { //did they submit something?
        $type = 2;
        if (confirmPass($_REQUEST["cpass"])) {
            if ($_REQUEST["npass1"] == $_REQUEST["npass2"]) {
                $hash = password_hash($_REQUEST["npass1"], PASSWORD_BCRYPT);
                $stmnt = $dbh->prepare("UPDATE customer
    SET tr_password = :hash
    WHERE cid = :CID");
                $stmnt->bindParam(':hash', $hash, PDO::PARAM_STR); 
                $stmnt->bindParam(':CID',  $_SESSION["CID"], PDO::PARAM_STR);
                $stmnt->execute();
            } else {
                //passwords do not match
            $errFlag = 1;
            $errMsg = "Your new passwords do not match. Please try again.";
            }
        } else {
            // bad current password
            $errFlag = 1;
            $errMsg = "The password supplied does not match our records";
        }
    }
            
    // Do they want a new SCP key?
    if ($_REQUEST["a"] == "nk") { //did they submit something?
        $type = 3;
        if (confirmPass($_REQUEST["cpass"])) {
             // get the users email address so we can mail them the new public key
            $stmnt = $dbh->prepare("SELECT contact_email 
    FROM customer 
    WHERE cid = :CID");
            $stmnt->bindParam(':CID',  $_SESSION["CID"], PDO::PARAM_STR);
            $stmnt->execute();
            $queryResult = $stmnt->fetch(PDO::FETCH_ASSOC); //returns FALSE if empty result
            if (!$queryResult) {
                print "NO RESPONSE!";
                // insert error here because we didn't get a result;
            }
            // generate the keys
            list ($scpPrivKey, $scpPubKey) = generateSSHKeys();

             // insert the new keys
            $stmnt = $dbh->prepare("UPDATE customer
    SET inst_pub_key = :pub,
    inst_priv_key = :priv
    WHERE cid = :CID");
            $stmnt->bindParam(':pub',  $scpPubKey, PDO::PARAM_STR);
            $stmnt->bindParam(':priv', $scpPrivKey, PDO::PARAM_STR);
            $stmnt->bindParam(':CID',  $_SESSION["CID"], PDO::PARAM_STR);
            $stmnt->execute();
            // email the new public key
            emailPubKey($scpPubKey, $queryResult["contact_email"]);
        } else {
            // bad current password
            $errFlag = 1;
            $errMsg = "The password supplied does not match our records";
        }
    }
    
    // lets get the initial values for this form. This has to be done
    // after any changes ot the DB or they won't be reflected. 
    $stmnt = $dbh->prepare("SELECT inst_name, contact_fname,
    contact_lname, contact_phone,
    contact_email, inst_data_host,
    inst_host_uname, inst_host_path,
    tt_system 
    FROM customer WHERE cid = :CID");
    $stmnt->bindParam(':CID', $_SESSION["CID"], PDO::PARAM_STR);
    $stmnt->execute();
    $queryResult = $stmnt->fetch(PDO::FETCH_ASSOC); //returns FALSE if empty result
    if (!$queryResult) {
        // insert error here because we didn't get a result;
    }
     
    //We have to assemble to form in a funky way because php does NOT like dealing with the 'for' HTML attribute. Escape the quotes!
    $adminForm = "<a href='#' onclick='toggle_vis(\"ud\");'>Change Account Information</a></p>";
    $adminForm .= "<div id='ud' style='display: none;'>";
    $adminForm .= "<form id='updateContactInformation' role='form' class='form-horizontal col-8' action='$url?a=ud' method='post'>\n";
    $adminForm .= "<input type='hidden' name='form_src' value='admin' />\n";
    $adminForm .= "<div class='form-group'><label for='admin-fName'> First Name:</label><input type='text' name='admin-fName' class='form-control' value='$queryResult[contact_fname]' required></div>\n";
    $adminForm .= "<div class='form-group'><label for='admin-lName'> Last Name:</label><input type='text' name='admin-lName' class='form-control' value='$queryResult[contact_lname]' required ></div>\n";
    $adminForm .= "<div class='form-group'><label for='admin-email'> Email Address:</label><input type='email' name='admin-email' class='form-control' value='$queryResult[contact_email]' required></div>\n";
    $adminForm .= "<div class='form-group'><label for='admin-phoneNumber'>Phone Number:</label><input type='text' name='admin-phoneNumber' class='form-control'  value='$queryResult[contact_phone]' required></div>\n";
    $adminForm .= "<div class='form-group'><label for='admin-instName'>Institution Name:</label><input type='text' name='admin-instName' class='form-control' value='$queryResult[inst_name]' required ></div>\n";
    $adminForm .= "<div class='form-group'><label for='admin-rtEmailAddress'>RT Email Address:</label><input type='text' name='admin-rtEmailAddress' id='admin-rtEmailAddress' class='form-control' value='$queryResult[tt_system]'></div>";
    $adminForm .= "<div class='form-group'><label for='admin-scpUsername'>SCP Username:</label><input type='text' name='admin-scpUsername' id='admin-scpUsername' class='form-control' value='$queryResult[inst_host_uname]' required></div>";
    $adminForm .= "<div class='form-group'><label for='admin-scpDstIp'>SCP Host:</label><input type='text' name='admin-scpDstIp' id='admin-scpDstIp' class='form-control' value='$queryResult[inst_data_host]' required></div>";
    $adminForm .= "<div class='form-group'><label for='admin-scpHostPath'>SCP Destination (Absolute Path):</label><input type='textarea' name='admin-scpHostPath' id='admin-scpHostPath' class='form-control' value='$queryResult[inst_host_path]' required></div>";
    $adminForm .= "<button type='submit' class='btn btn-lg btn-success'>Update Account</button></form>";
    $adminForm .= "</div>";
    
    $adminForm .= "<a href='#' onclick='toggle_vis(\"np\");'>Change Password</a></p>";
    $adminForm .= "<div id='np' style='display: none;'>";
    $adminForm .= "<form id='updatePassword' role='form' class='form-horizontal col-8' action='$url?a=np' method='post'>\n";
    $adminForm .= "<input type='hidden' name='form_src' value='admin' />\n";
    $adminForm .= "<div class='form-group'><label for='cpass'> Current Password:</label><input type='password' name='cpass' class='form-control' value='' required></div>\n";
    $adminForm .= "<div class='form-group'><label for='npass1'> New Password:</label><input type='password' name='npass1' class='form-control' value='' required></div>\n";
    $adminForm .= "<div class='form-group'><label for='npass2'> Confirm Password:</label><input type='password' name='npass2' class='form-control' value='' required></div>\n";
    $adminForm .= "<button type='submit' class='btn btn-lg btn-success'>Update Password</button></form>";
    $adminForm .= "</div>";
   
    $adminForm .= "<a href='#' onclick='toggle_vis(\"nk\");'>Generate New Keys</a>";
    $adminForm .= "<div id='nk' style='display: none;'>";
    $adminForm .= "<form id='updateKey' role='form' class='form-horizontal col-8' action='$url?a=nk' method='post'>\n";
    $adminForm .= "<input type='hidden' name='form_src' value='admin' />\n";
    $adminForm .= "<div class='form-group'><label for='cpass'> Password:</label><input type='password' name='cpass' class='form-control' value='' required></div>\n";
    $adminForm .= "<button type='submit' class='btn btn-lg btn-success'>Request New SCP key</button></form>";
    $adminForm .= "</div>";

    #$errMsg = implode("<br>", array_filter($AdminInputErrors));
    return [$adminForm, $errFlag, $errMsg, $type];
}


function runPSLocateJson($ip, $count) // ip: IP address to lookup, count: number of test hosts to return.
{
    $args = " -i '".$ip."' -c '".$count."'";
    $jsonResponse = shell_exec('ps_locate '.$args);
    return $jsonResponse;
}

// generate the public and private keys for use with the ISO
function generateSSHKeys() {
// generate private key
    $privKey = openssl_pkey_new(array(
        'private_key_bits' => 2048,
        'private_key_type' => OPENSSL_KEYTYPE_RSA
    ));
    openssl_pkey_export($privKey, $pem); // convert to pem encoding
    $pubKey = sshEncodePublicKey($privKey);
    return array($pem, $pubKey);
}

// convert the private key into a openssh format publickey
function sshEncodePublicKey($privKey)
{
    $keyInfo = openssl_pkey_get_details($privKey);

    $buffer  = pack("N", 7) . "ssh-rsa" . 
               sshEncodeBuffer($keyInfo['rsa']['e']) . 
               sshEncodeBuffer($keyInfo['rsa']['n']);

    return "ssh-rsa " . base64_encode($buffer); 
}

function sshEncodeBuffer($buffer)
{
    $len = strlen($buffer);
    if (ord($buffer[0]) & 0x80) {
        $len++;
        $buffer = "\x00" . $buffer;
    }

    return pack("Na*", $len, $buffer);
}

function emailPubKey($pubKey, $email) {
    $message = "Hello TestRig 2.0 Customer!\r\n\r\n";
    $message .= "At the end of this message you will find a public openssh format key.\r\n";
    $message .= "You will need to copy this key into a file named authorized_keys and place it\r\n";
    $message .= "a directory named .ssh in the top level directory of the account\r\n";
    $message .= "that will recieve the completed TestRig 2.0 diagnostic datasets.\r\n";
    $message .= "\r\n\r\nOpenSSH Public Key:\r\n";
    $message .= "$pubKey";
    $subject = "TestRig 2.0 Public Key Enclosed";
    $headers = 'From: testrig@psc.edu' . "\r\n" .
    'Reply-To: testrig@psc.edu' . "\r\n" .
					       'X-Mailer: PHP/' . phpversion();

    mail($email, $subject, $message, $headers);
}