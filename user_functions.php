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

	$query = "SELECT bh_customer_id,
                     bh_customer_name
              FROM   bh_customers";
	$sth = $dbh->prepare($query);
	$sth->execute();
	$customer_result = $sth->fetchall(PDO::FETCH_ASSOC);
	foreach ($customer_result as $line) {
		$names[$line['bh_customer_id']] = $line['bh_customer_name'];
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
	$role[2] = "BHS Staff";
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


function newUserForm () {
	$user_affiliation = userAffiliationWidget("");
	$roles = array("1" => "User", "2" => "BHS Staff", "4" => "BHS Admin");
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

/* we need to get a list of available institutions. This allows us to limit
 * access to a specific set of address blocks asscoiated with that institution.
 * this is found in the tabel bh_customers
 */
function userAffiliationWidget($affiliation) {
	$dbh = getDatabaseHandle();
	$query = "SELECT bh_customer_id,
                     bh_customer_name
              FROM bh_customers";
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
		print "No results were returned. The customers data tables might be empty.";
		return 0;
	}
	#build the drop down widget
	$user_affiliation = "<select name='user-affiliation' class='form-control' required>\n";
	$user_affiliation .= "<option value=''>---</option>\n";
	foreach ($result as $line) {
		$selected = "";
		if ($affiliation == $line['bh_customer_id']) {
			$selected = "SELECTED";
		}
		$user_affiliation .= "<option $selected value='" . $line['bh_customer_id'] . "'>" . $line['bh_customer_name'] . "</option>\n";
	}
	$user_affiliation .= "</select>";
	return $user_affiliation;
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
	return array(-1, $error);
    }
    if (! isset($result)) {
	// The DB didn't return a result or there was an error
	$error = "No results were returned. There may be a problem with the database.";
	return array (-1, $error);
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
        $roles = array("1" => "User", "2" => "BHS Staff", "4" => "BHS Admin");
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
	$form .= "<P><P><input action=\"action\" onclick=\"javascript:toggle_vis('np');\";
           return false;\" type=\"button\" value=\"Change Password\" class=\"btn btn-lg btn-success\"/>\n";
	$form .= "<div id='np' style='display: none;'>";
	$form .= changePasswordWidget();
	$form .= "</div>";
    }
    //    $form .= "</div>";
    return array (1, $form);
}

?>
