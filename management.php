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

/* this is the user and group managment interface for the 
 * black hole project. I'm still nailing down the various 
 * portions that really matter but we need to start with 
 * some basics - username, password, class of user, contact
 * information, etc. 
 */

?>
<!DOCTYPE html>
<head>
    <?php
    session_start();
    if (empty($_SESSION["username"]))
    {
        header("Location: https://". $_SERVER['SERVER_NAME']. "/blackholesun/login.php");
        die();
    }
    if ($_SESSION["bh_user_role"] != 4)
        // they don't have appropriate access priveliges. Bounce them to the main page
    {
        header("Location: https://". $_SERVER['SERVER_NAME']. "/blackholesun/mainpage.php");
        die();
    }
    include("./trfunctions.php");
    include("./functions.php");
    ?>

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
    <script type="text/javascript">
     $(document).ready(function(){
	 $(':checkbox').bind('change', function() {
	     $('input[type="checkbox"]').not(this).prop('checked', false);
	 });
     });
    </script>

    <!-- bootstrap javascript -->
    <script src="bootstrap/dist/js/bootstrap.min.js"></script>
    <!-- modals code -->
    <script src="trmodals.js"></script>
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

                <a class="navbar-brand" href="http://<?php echo $_SERVER['SERVER_NAME']?>/blackholesun/index.php">BlackHole Sun; an EXABGP Interface</a>
                <a class="navbar-brand" href="http://<?php echo $_SERVER['SERVER_NAME']?>/blackholesun/login.php">Logout</a>
            </div>
            <div id="navbar" class="collapse navbar-collapse">
                <ul class="nav navbar-nav">
		    <!--                                  <li><a id="menu-home" href="https://<?php echo $_SERVER['SERVER_NAME']?>/blackholesun/index.php">About</a></li>
			 <li><a id="menu-faq" href="https://<?php echo $_SERVER['SERVER_NAME']?>/blackholesun/faq.php">FAQ</a></li>
                         </ul>
			 <p class="navbar-right navbar-btn"><button id="logout" onClick="window.location='https://<?php echo $_SERVER['SERVER_NAME']?>/signup.php'"  type="button" class="btn btn-sm btn-primary">Sign Up</button></p> -->
            </div><!--/.nav-collapse -->
        </div> <!-- END nav container -->
    </nav>

    <?php
    $errFlag = "";
    $errMsg = "";
    $passbutton = "";
    if (($_POST['action'] == "edit") && (isset($_POST['edituser']))) {
        // generate a form to edit a specific user
        list($error, $form) = loadUserForm($_POST['edituser'], $_SESSION['bh_user_role'], $_SESSION['bh_user_id']);
        $_SESSION['edituser'] = $_POST['edituser'];
        // only include the password reset button if they are an BHS admin
        if ($_SESSION['bh_user_role'] == 4) {
            $passbutton = passwordResetWidget($_POST['edituser']);
        }
        print "<table align='center'> <tr><td>";
        print $form;
        print $passbutton;
        print "</td></tr></table>";
    } elseif ($_POST['action'] == "updateUser") {
        // user edit has been submitted. handle it
        $json = json_encode($_POST) . "\n";
        $response = sendToProcessingEngine($json);
        if (preg_match("/Success/", $response)) {
            $errFlag = 0;
            $errMsg = "User Updated Successfully";
            goto listusers;
        } else {
            $errFlag = 1;
            $errMsg = "There has been an error handling this request: $response";
            // reload the user edit form
            list($error, $form) = loadUserForm($_SESSION['edituser'], $_SESSION['bh_user_role'], $_SESSION['bh_user_id']);
            if ($_SESSION['bh_user_role'] == 4) {
                $passbutton = passwordResetWidget($_POST['edituser']);
            }
            print "<table align='center'> <tr><td>";
            print $form;
            print "</td></tr></table>";
        }
    } elseif ($_POST['action'] == "resetPassword") {
        $json = json_encode($_POST) . "\n";
        $respose = sendToProcessingEngine($json);
        if (preg_match("/Success/", $response)) {
            $errFlag = 0;
            $errMsg = "Password Reset Successful";
            goto listusers;
        } else {
            $errFlag = 1;
            $errMsg = "There has been an error handling this request: $response";
            // reload the user edit form
            list($error, $form) = loadUserForm($_SESSION['edituser'], $_SESSION['bh_user_role'], $_SESSION['bh_user_id']);
            if ($_SESSION['bh_user_role'] == 4) {
                $passbutton = passwordResetWidget($_POST['edituser']);
            }
            print "<table align='center'> <tr><td>";
            print $form;
            print "</td></tr></table>";
        }
    } elseif ($_POST['action'] == "changePassword") {
        if (confirmPass($_REQUEST["cpass"]) === FALSE) {
            // bad current password
            $errFlag = 1;
            $errMsg = "The password supplied does not match our records";
            // reload the user edit form
            list($error, $form) = loadUserForm($_SESSION['edituser'], $_SESSION['bh_user_role'], $_SESSION['bh_user_id']);
            print "<table align='center'> <tr><td>";
            print $form;
            print "</td></tr></table>";
        } elseif ($_REQUEST["npass1"] != $_REQUEST["npass2"]) {
            //the old password is true but these don't match
            $errFlag = 1;
            $errMsg = "Your new passwords do not match. Please try again.";
            // reload the user edit form
            list($error, $form) = loadUserForm($_SESSION['edituser'], $_SESSION['bh_user_role'], $_SESSION['bh_user_id']);
            print "<table align='center'> <tr><td>";
            print $form;
            print "</td></tr></table>";
        } else {
            // current password is good and the new ones match
            $json = json_encode($_POST) . "\n";
            $response = sendToProcessingEngine($json);
            if (preg_match("/Success/", $response)) {
                $errFlag = 0;
                $errMsg = "Password Changed Successfully";
                goto listusers;
            } else {
                $errFlag = 1;
                $errMsg = "There was a problem changing the password in the database";
            }
        }
    } else {
        listusers:
        // generate the list of current users
        $user_table = listUsers();
        // we need to be able to edit individual users. by wrapping the list in a
        // form we can do that
        print "<form id='loginForm' name='loginForm' class='form-horizontal col-6' action='" . htmlspecialchars($_SERVER['PHP_SELF']) . "' method='post' role='form' class='form-horizontal'>";     
        print $user_table;
        print "<button type='submit' name='action' value='edit' class='btn btn-success'>Edit Selcted</button>";
        print "</form>";
    }
    ?>

    <!-- modals handler -->
    <script>
     <?php
     // This has to be kept in the footers as we don't have the variable data yet.
     // by the way, what we are doign here is using php to write javascript. 
     // dirty!
     print "modalSetFormSrc(\"management\");";
     print "managementFormInfo(".$errFlag.", \"".$errMsg."\");";
     ?>   
    </script>
    

</body>



