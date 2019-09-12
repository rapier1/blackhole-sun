<?php
/*
 * Copyright (c) 2018 The Board of Trustees of Carnegie Mellon University.
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

/* a user is being forced to change their password.
 * this happens because the password was reset or because they've
 * not logged in before
 */

?>
<!DOCTYPE html>
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- The above 3 meta tags *must* come first in the head; any other head content must come *after* these tags -->
    <meta name="description" content="BlackHole Sun">
    <meta name="author" content="Pittsburgh Supercompuing Center">
    <link rel="icon" href="./icons/favicon.ico">
    <title>BlackHole Sun Account Management</title>
    <link href="jquery/datatables.css" rel="stylesheet">
    <!-- Bootstrap core CSS -->
    <link href="bootstrap/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- IE10 viewport hack for Surface/desktop Windows 8 bug -->
    <link href="bootstrap/assets/css/ie10-viewport-bug-workaround.css" rel="stylesheet">
    <!-- Custom styles for this template -->
    <link href="trstylesheet.css" rel="stylesheet">
    <!-- HTML5 shim and Respond.js for IE8 support of HTML5 elements and media queries -->
    <!--[if lt IE 9]>
        <script src="https://oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js"></script>
        <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
    <![endif]-->

    <!-- load jquery -->
    <script type="text/javascript" src="./jquery/jquery.min.js"></script>
    <!-- load tabledit js library -->
    <script type="text/javascript" src="./jquery/jquery.tabledit.js"></script>
    <!-- bootstrap javascript -->
    <script src="bootstrap/dist/js/bootstrap.min.js"></script>
    <!-- modals code -->
    <script src="./trmodals.js"></script>
</head>
<body>

    <?php
    session_start();
    if (empty($_SESSION["username"])) {
        header("Location: http://". $_SERVER['SERVER_NAME']. "/blackholesun/login.php");
        die();
    }
    $page_id = "account";
    include './functions.php';
    include './user_functions.php';
    include ("./modals.php");
    include ("./navbar.php");
    sessionTimer();
    $errFlag= -1;
    $errMsg="";
    $url = "";
    if ($_POST['action'] == "updateUser") {
        // current password is good and the new ones match
        $json = json_encode($_POST) . "\n";
        $response = sendToProcessingEngine($json);
        if (preg_match("/Success/", $response)) {
            $errFlag = 0;
            $errMsg = "Your account has been updated";
	    $url = "";
        } else {
            $errFlag = 1;
            $errMsg = "There was an error updating your account: $response";
        }
    } elseif ($_POST['action'] == "changePassword") {
        if (confirmPass($_REQUEST["cpass"]) === FALSE) {
            // bad current password
            $errFlag = 1;
            $errMsg = "The password supplied does not match our records";
        } elseif ($_REQUEST["npass1"] != $_REQUEST["npass2"]) {
            //the old password is true but these don't match
            $errFlag = 1;
            $errMsg = "Your new passwords do not match. Please try again.";
        } else {
            // current password is good and the new ones match
            $json = json_encode($_POST) . "\n";
            $response = sendToProcessingEngine($json);
            if (preg_match("/Success/", $response)) {
                $errFlag = 0;
                $errMsg = "Password Changed Successfully";
		$url = "/blackholesun/login.php";
            } else {
                $errFlag = 1;
                $errMsg = "There was a problem changing the password in the database: $response";
	    }
	}
    }
    list($error, $form) = loadUserForm($_SESSION['bh_user_id'], $_SESSION['bh_user_role'], $_SESSION['bh_user_id']);
    if ($error == -1) {
	$errFlag = 1;
	$errMsg = "There has been an error : $form";
    } else {
	print "<table align='center' width='33%'>";
        print "<tr><td>";
        print $form;
        print "<tr><td><br></td></tr>\n";
        print "<tr><td>";
        if ($_SESSION['bh_user_role'] == 4) {
            print "<input action=\"action\" onclick=\"window.location = './usermanagement.php';
           return false;\" type=\"button\" value=\"Cancel\" class=\"btn btn-lg btn-danger\"/>\n";
    } else {
        print "<input action=\"action\" onclick=\"window.location = './routes.php';
           return false;\" type=\"button\" value=\"Cancel\" class=\"btn btn-lg btn-danger\"/>\n";
    }
    print "</td></tr>\n";
    print "</td></tr>";
    }
    ?>

    <!-- modals handler -->
    <script>
     <?php
     // This has to be kept in the footers as we don't have the variable data yet.
     print "accountMgmtFormInfo($errFlag, '$errMsg', '$url');\n";
     ?>
    </script>
    </table>
</body>
