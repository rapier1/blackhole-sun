<?php
/*
 * Copyright (c) 2019 The Board of Trustees of Carnegie Mellon University.
 *
 * Authors: Chris Rapier <rapier@psc.edu>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License. *
 */

/*
 * this is the user and group managment interface for the
 * black hole project. I'm still nailing down the various
 * portions that really matter but we need to start with
 * some basics - username, password, class of user, contact
 * information, etc.
 */
?>
<!DOCTYPE html>
<head>
    <?php
    session_start ();
    include './functions.php';
    include './customer_functions.php';
    if (empty ( $_SESSION ["username"] )) {
	header ( "Location: http://" . $_SERVER ['SERVER_NAME'] . "/blackholesun/login.php" );
	die ();
    }
    sessionTimer ();
    if ($_SESSION ["bh_user_role"] != 4)
	// they don't have appropriate access priveliges. Bounce them to the main page
    {
	header ( "Location: http://" . $_SERVER ['SERVER_NAME'] . "/blackholesun/routes.php" );
	die ();
    }
    $page_id = "customers";
    ?>

    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- The above 3 meta tags *must* come first in the head; any other head content must come *after* these tags -->
    <meta name="description" content="BlackHole Sun">
    <meta name="author" content="Pittsburgh Supercompuing Center">
    <link rel="icon" href="./icons/favicon.ico">
    <title>BlackHole Sun - Customer Management</title>
    <link href="jquery/datatables.css" rel="stylesheet">
    <!-- Bootstrap core CSS -->
    <link href="bootstrap/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- IE10 viewport hack for Surface/desktop Windows 8 bug -->
    <link href="bootstrap/assets/css/ie10-viewport-bug-workaround.css"
		rel="stylesheet">
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
    <script type="text/javascript">
     $(document).ready(function(){
	 $(':checkbox').bind('change', function() {
	     $('input[type="checkbox"]').not(this).prop('checked', false);
	 });
     });
    </script>
</head>

<body>
    <?php
    include ("./modals.php");
    include ("./navbar.php");
    $errFlag = "";
    $errMsg = "";
    $url = "";
    // prewrap ($_POST);
    // prewrap ($_SESSION);
    // if the user wants to edit a customer from the list of customers
    // the action value will be edit and we'll know what customer they want to edit
    // so load a form specifically for that
    if (($_POST ['action'] == "edit") && (isset ( $_POST ['editcustomer'] ))) {
	// we lose the editcustomer value in the post and if there is an error
	// we can't reload the form so store it in a session variable
	$_SESSION ['editcustomer'] = $_POST ['editcustomer'];
	// we want to edit a customer so implement that here
	list ( $error, $form ) = loadCustomerForm ( $_POST ['editcustomer'], 0 );
	print "<table align='center'> <tr><td>";
	print $form;
	print "<br>";
	print deleteCustomerWidget ( $_POST ['editcustomer'] );
	print "</td></tr></table>";
    } elseif ($_POST ['action'] == "updateCustomer") {
	// customer edit has been submitted. handle it
	// prewrap ($_POST);

	if (! isset ( $_POST ['customer-asns'] )) {
	    $_POST ['customer-asns'] = "";
	}
	$_POST ['customer-asns'] = normalizeListInput ( $_POST ['customer-asns'] );

	if (! isset ( $_POST ['customer-vlans'] )) {
	    $_POST ['customer-vlans'] = "";
	}
	$_POST ['customer-vlans'] = normalizeListInput ( $_POST ['customer-vlans'] );

	// first thing we need to do is validate the submitted
	// IP addresses and CIDR blocks. I want to provide feedback on
	// individual bad IPs as opposed to the entire submission
	// so we split the incoming set of IP/CIDR and run it through a validator

	if (! isset ( $_POST ['customer-blocks'] )) {
	    $errFlag = 1;
	    $errMsg = "No customer address blocks defined!";
	    goto error;
	}

	$_POST ['customer-blocks'] = normalizeListInput ( $_POST ['customer-blocks'] );
	foreach ( explode ( ",", $_POST ['customer-blocks'] ) as $cidr ) {
	    $cidr = trim ( $cidr );
	    if (validateCIDR ( $cidr ) == - 1) {
		$errFlag = 1;
		$errMsg = "Invalid IP/CIDR found at $cidr";
	    }
	}
	if ($errFlag != 1) {
	    $json = json_encode ( $_POST ) . "\n";
	    $response = sendToProcessingEngine ( $json );
	    if (preg_match ( "/Success/", $response )) {
		$errFlag = 0;
		$errMsg = "Customer Updated Successfully";
		goto listcustomers;
	    } else {
		$errFlag = 1;
		$errMsg = "There has been an error handling this request: $response";
	    }
	}
	// we have an error flag from somwhere so reload the form with the user input
	error:
				     if ($errFlag == 1) {
					 list ( $error, $form ) = loadCustomerForm ( $_POST, 1 );
					 print $form;
				     }
    } elseif ($_REQUEST ['action'] == "deleteCustomer") {
	$form = "<form id='confirmDeleteCustomer' role='form' action='" . htmlspecialchars ( $_SERVER ["PHP_SELF"] ) . "' method='post'>\n";
	$form .= "<input type='hidden' name='action' value='confirmDeleteCustomer' />\n";
	$form .= "<input type='hidden' name='bh_customer_id' value='" . $_POST ['bh_customer_id'] . "' />\n";
	$form .= "<input type='radio' name='confirm' value='1'> Yes </input><br>\n";
	$form .= "<input type='radio' name='confirm' value='0'> No </input><br>\n";
	$form .= "<button type='submit' class='btn btn-lg btn-danger'>Confirm Delete Customer</button></form>";
	print "<table align='center'> <tr><td>";
	print $form;
	print "</td></tr></table>";
    } elseif ($_REQUEST ['action'] == "confirmDeleteCustomer") {
	if ($_POST ['confirm'] == 0) {
	    goto listcustomers;
	}
	$json = json_encode ( $_POST ) . "\n";
	$response = sendToProcessingEngine ( $json );
	if (preg_match ( "/Success/", $response )) {
	    $errFlag = 0;
	    $errMsg = "Customer Deleted";
	    goto listcustomers;
	} else {
	    $errFlag = 1;
	    $errMsg = "There was a problem deleting the user: $response";
	}
    } else {
	listcustomers:
	$customer_table = listCustomers ();
	// we need to be able to edit individual customer. by wrapping the list in a
	// form we can do that
	print "<form id='customerForm' name='customerForm' class='form-horizontal col-5'
                 action='" . htmlspecialchars ( $_SERVER ['PHP_SELF'] ) . "'
                 method='post' role='form' class='form-horizontal'>";
	print $customer_table;
	print "<button type='submit' name='action' value='edit' class='btn btn-success'>Edit Selcted</button>";
	print "</form>";
    }
    ?>

    <!-- modals handler -->

    <script>
     <?php
     // This has to be kept in the footers as we don't have the variable data yet.
     // by the way, what we are doing here is using php to write javascript.
     // dirty!
     print "modalSetFormSrc(\"customers\");\n";
     print "customersFormInfo(" . $errFlag . ", \"" . $errMsg . "\", \"" . $url . "\" );\n";
     ?>
    </script>
</body>
