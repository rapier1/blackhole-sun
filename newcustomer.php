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

/* this is the customer managment interface for the
 * black hole project. Basically, we can create a customer
 * that woudl correspond to an admintsrative network domain
 * and define what networks this domain owns. This is used to
 * ensure that users have the right the BH a route.
 */
?>
<!DOCTYPE html>
<head>
    <?php
    session_start();
    include './functions.php';
    include './customer_functions';
    if (empty($_SESSION["username"]))
    {
        header("Location: http://". $_SERVER['SERVER_NAME']. "/blackholesun/login.php");
        die();
    }
    sessionTimer();
    if ($_SESSION["bh_user_role"] != 4)
        // they don't have appropriate access priveliges. Bounce them to the main page
    {
        header("Location: http://". $_SERVER['SERVER_NAME']. "/blackholesun/routes.php");
        die();
    }
    ?>

    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- The above 3 meta tags *must* come first in the head; any other head content must come *after* these tags -->
    <meta name="description" content="BlackHole Sun">
    <meta name="author" content="Pittsburgh Supercompuing Center">
    <link rel="icon" href="../../favicon.ico">
    <title>BlackHole Sun - New Customer</title>
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
	    </div> <!--navbar-header -->
	    <div id="navbar" class="collapse navbar-collapse">
		<ul class="nav navbar-nav">
		    <li><a id="menu-home" href="http://<?php echo $_SERVER['SERVER_NAME']?>/blackholesun/about.php">About</a></li>
		    <li><a id="menu-faq" href="http://<?php echo $_SERVER['SERVER_NAME']?>/blackholesun/faq.php">FAQ</a></li>
		</ul>
		<p class="navbar-right navbar-btn"><button id="userManagment" onClick="window.location='http://<?php echo $_SERVER['SERVER_NAME']?>/blackholesun/usermanagement.php'" type="button" class="btn btn-sm btn-primary">Users</button></p>
		<p class="navbar-right navbar-btn"><button id="Customers" onClick="window.location='http://<?php echo $_SERVER['SERVER_NAME']?>/blackholesun/customers.php'" type="button" class="btn btn-sm btn-primary">Customers</button></p>
	    </div> <!-- navbar -->
	</div> <!-- END nav container -->
    </nav>

    <?php
    $errFlag="";
    $errMsg="";
    $url = "";
    if ($_POST['action'] == "addCustomer") {
	if (!isset($_POST['customer-asns'])) {
	    $_POST['customer-asns'] = "";
	}
	$_POST['customer-asns'] = normalizeListInput($_POST['customer-asns']);

	if (!isset($_POST['customer-vlans'])) {
	    $_POST['customer-vlans'] = "";
	}
	$_POST['customer-vlans'] = normalizeListInput($_POST['customer-vlans']);

	// first thing we need to do is validate the submitted
	// IP addresses and CIDR blocks. I want to provide feedback on
	// individual bad IPs as opposed to the entire submission
	// so we split the incoming set of IP/CIDR and run it through a validator

	if(!isset($_POST['customer-blocks'])) {
	    $errFlag = 1;
	    $errMsg = "No customer address blocks defined!";
	} else {
	    $_POST['customer-blocks'] = normalizeListInput($_POST['customer-blocks']);
	    foreach (explode(",", $_POST['customer-blocks']) as $cidr) {
		$cidr = trim($cidr);
		if (validateCIDR($cidr) == -1) {
		    $errFlag = 1;
		    $errMsg = "Invalid IP/CIDR found at $cidr";
		}
	    }
	}

	if ($errFlag != 1) {
            $json = json_encode($_POST);
            $response = sendToProcessingEngine($json);
            /* print "response is $response"; */
            if (preg_match("/Success/", $response)) {
		$url =  "/blackholesun/customers.php";
		$errFlag = 0;
		$errMsg = "New Customer Successfully Added.";
            } else {
		$url =  "/blackholesun/newcustomer.php";
		$errFlag = 1;
		$errMsg = "Failed to add customer: $response";
            }
	}
    }
    $form = newCustomerForm($_POST); /* send post data in case this is a reload due to error */
    print "<table align='center'><tr><td>";
    print $form;
    print "</td></tr></table>";
    ?>

    <!-- modals handler -->

    <script>
     <?php
     // This has to be kept in the footers as we don't have the variable data yet.
     // by the way, what we are doing here is using php to write javascript.
     // dirty!
     print "modalSetFormSrc(\"addCustomer\");\n";
     print "addCustomerFormInfo(" . $errFlag . ", \"" . $errMsg . "\", \"" . $url . "\" );\n";
     ?>
    </script>
</body>
