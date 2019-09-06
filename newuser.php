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
    include './functions.php';
    include './user_functions.php';
    if (empty($_SESSION["username"]))    {
        header("Location: http://". $_SERVER['SERVER_NAME']. "/blackholesun/login.php");
        die();
    }
    sessionTimer();
    if ($_SESSION["bh_user_role"] != 4) {
        // they don't have appropriate access priveliges. Bounce them to the main page
        header("Location: http://". $_SERVER['SERVER_NAME']. "/blackholesun/routes.php");
        die();
    }
    $page_id = "new_user";
    ?>

    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- The above 3 meta tags *must* come first in the head; any other head content must come *after* these tags -->
    <meta name="description" content="BlackHole Sun">
    <meta name="author" content="Pittsburgh Supercompuing Center">
    <link rel="icon" href="./icons/favicon.ico">
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
    <!-- bootstrap javascript -->
    <script src="bootstrap/dist/js/bootstrap.min.js"></script>
    <!-- modals code -->
    <script src="./trmodals.js"></script>
</head>

<body>
    <?php include ("./modals.php"); ?>
    <?php include ("./navbar.php"); ?>
    <?php
    $errFlag= -1;
    $errMsg="";
    $url = "";
    if ($_POST['action'] == "addUser") {
        $json = json_encode($_POST);
        $response = sendToProcessingEngine($json);
        /* print "response is $response"; */
        if (preg_match("/Success/", $response)) {
            $url =  "/blackholesun/usermanagement.php";
            $errFlag = 0;
            $errMsg = "New User Successfully Added. Initial Password Sent.";
        } else {
            $url =  "/blackholesun/newuser.php";
            $errFlag = 1;
            $errMsg = "Failed to add user: $response";
        }
    }
    $form = newUserForm();
    print "<table align='center'><tr><td>\n";
    print $form;
    print "</tr></td>\n";
    /* cancel button */
    print "<tr><td><br></td></tr>\n";
    print "<tr><td>";
    print "<input action=\"action\" onclick=\"window.location = './usermanagement.php';
           return false;\" type=\"button\" value=\"Cancel\" class=\"btn btn-lg btn-danger\"/>\n";
    print "</td></tr>\n";
    print "</table>\n";
    ?>

    <!-- modals handler -->

    <script>
     <?php
     // This has to be kept in the footers as we don't have the variable data yet.
     // by the way, what we are doing here is using php to write javascript.
     // dirty!
     print "modalSetFormSrc('newUser');\n";
     print "newUserFormInfo($errFlag, '$errMsg', '$url');\n";
     ?>
    </script>
</body>
