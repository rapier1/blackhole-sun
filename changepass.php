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
    <link rel="icon" href="../../favicon.ico">
    <title>BlackHole Sun</title>
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

    <?php
    session_start();
    if (empty($_SESSION["username"]))
    {
        header("Location: http://". $_SERVER['SERVER_NAME']. "/blackholesun/login.php");
        die();
    }
    include("./functions.php");

    function changePasswordWidget () {
    	$form  = "<form id='updatePassword' role='form' class='form-horizontal col-8' action='"  .
    			htmlspecialchars($_SERVER["PHP_SELF"]) . "' method='post'>\n";
    	$form .= "<input type='hidden' name='form_src' value='userManagement' />\n";
    	$form .= "<input type='hidden' name='action' value='changePassword' />\n";
    	$form .= "<input type='hidden' name='bh_user_id' value='" . $_SESSION['bh_user_id'] . "' />\n";
    	$form .= "<div class='form-group'><label for='cpass'> Current Password:</label><input type='password' name='cpass' class='form-control' value='' required></div>\n";
    	$form .= "<div class='form-group'><label for='npass1'> New Password:</label><input type='password' name='npass1' class='form-control' value='' required></div>\n";
    	$form .= "<div class='form-group'><label for='npass2'> Confirm Password:</label><input type='password' name='npass2' class='form-control' value='' required></div>\n";
    	$form .= "<button type='submit' class='btn btn-lg btn-success'>Update Password</button></form>";
    	return($form);
    }
    ?>
</head>

<body>
    <?php include ("./modals.php"); ?>
    <nav class="navbar navbar-inverse navbar-fixed-top">
        <div class="container">
            <div class="navbar-header">
                <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#navbar" aria-expanded="false" aria-controls="navbar">
                    <span class="sr-only">Toggle navigation</span>
                    <span class="icon-bar"></span>
                    <span class="icon-bar"></span>
                    <span class="icon-bar"></span>
                </button>
		<div class="navbar-brand">BlackHole Sun</div>
            </div>
            <div id="navbar" class="collapse navbar-collapse">
                <ul class="nav navbar-nav">
		    <li><a id="menu-home" href="http://<?php echo $_SERVER['SERVER_NAME']?>/blackholesun/about.php">About</a></li>
		    <li><a id="menu-faq" href="http://<?php echo $_SERVER['SERVER_NAME']?>/blackholesun/faq.php">FAQ</a></li>
                </ul>
		<p class="navbar-right navbar-btn"><button id="logout" onClick="window.location='http://<?php echo $_SERVER['SERVER_NAME']?>/blackholesun/login.php'"  type="button" class="btn btn-sm btn-primary">Logout</button></p>
            </div><!--/.nav-collapse -->
	</div> <!-- END nav container -->
    </nav>
    <table align="center" width="33%"><tr><td>
	You need to change your password before proceeding. After changing your password you'll need to authenticate again
    </td>
    </tr>
    <?php
    $errFlag="";
    $errMsg="";
    $url = "";
    if ($_POST['action'] == "changePassword") {
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
    $form = changePasswordWidget();
    print "<tr><td>";
    print $form;
    print "</td></tr>";
    ?>

    <!-- modals handler -->
    <script>
     <?php
     // This has to be kept in the footers as we don't have the variable data yet.
     // by the way, what we are doign here is using php to write javascript.
     // dirty!
     print "modalSetFormSrc(\"changePass\");\n";
     print "changePassFormInfo(" . $errFlag . ", \"" . $errMsg . "\", \"" . $url . "\" );\n";
     ?>
    </script>
    </table>
</body>
