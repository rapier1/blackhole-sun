<?php
/*
 * Copyright (c) 2018 The Board of Trustees of Carnegie Mellon University.
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
 * the goal is to create a service where the user can
 * enter a route that will be blackholed and have it
 * propagated to the exbgp server.
 * so the intial pass will simply take a route to be blackholed
 * and pass it to the processing engine
 */

/*
 * TODO
 * DONE: Add a comments field so people can explain why they are entering a route
 * DONE: Add a customer field built form the DB so a higher level user or admin can assign route
 * visbility to another customer
 * DONE: Add an initiator field to the database and as a value for routes. This would allow
 * an admin type to create a route for another customer. This will give us the opportunity
 * to create read only routes. The customer can see the route but not edit it.
 * DONE: Add an option to make a route last forever. Need some special value for this.
 * Add email notifcations for when routes are added. This would go an email defined by the
 * admin and any users that are asscoiated with that specific customer
 * DONE: cancel button on all sections
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
<!-- load tabledit js library -->
<script type="text/javascript" src="./jquery/jquery.tabledit.js"></script>
<!-- bootstrap javascript -->
<script src="bootstrap/dist/js/bootstrap.min.js"></script>
<!-- modals code -->
<script src="./trmodals.js"></script>
<link href="jquery/tablesorter-master/dist/css/theme.default.min.css"
	rel="stylesheet">
<script src="jquery/tablesorter-master/dist/js/jquery.tablesorter.js"></script>
<script
	src="jquery/tablesorter-master/dist/js/jquery.tablesorter.widgets.js"></script>
<!-- Tablesorter: optional -->
<link rel="stylesheet"
	href="jquery/tablesorter-master/addons/pager/jquery.tablesorter.pager.css">
<script
	src="jquery/tablesorter-master/addons/pager/jquery.tablesorter.pager.js"></script>


    <?php
    session_start ();
    if (empty ( $_SESSION ["username"] )) {
    	header ( "Location: http://" . $_SERVER ['SERVER_NAME'] . "/blackholesun/login.php" );
    	die ();
    }
    include ("./functions.php");
    include ("./routeentry_functions.php");
    ?>

</head>

<body>
    <?php include ("./modals.php"); ?>
    <nav class="navbar navbar-inverse navbar-fixed-top">
		<div class="container">
			<div class="navbar-header">
				<div class="navbar-brand">BlackHole Sun</div>
			</div>
			<div id="navbar" class="collapse navbar-collapse">
				<ul class="nav navbar-nav">
					<li><a id="menu-home"
						href="http://<?php echo $_SERVER['SERVER_NAME']?>/blackholesun/about.php">About</a></li>
					<li><a id="menu-faq"
						href="http://<?php echo $_SERVER['SERVER_NAME']?>/blackholesun/faq.php">FAQ</a></li>
				</ul>
				<p class="navbar-right navbar-btn">
					<button id="account"
						onClick="window.location=
                                                           'http://<?php echo $_SERVER['SERVER_NAME']?>/blackholesun/accountmgmt.php'"
						type="button" class="btn btn-sm btn-primary">Account</button>
				</p>
		<?php
		$_SESSION ['errFlag'] = 0;
		$_SESSION ['errMsg'] = "";
		if ($_SESSION ['bh_user_role'] == 4) {
			print "<p class='navbar-right navbar-btn'><button id='userManagement'
                           onClick=\"window.location='http://" . $_SERVER ['SERVER_NAME'] . "/blackholesun/usermanagement.php'\"  type='button'
                           class='btn btn-sm btn-primary'>Users</button></p>";
			print "<p class='navbar-right navbar-btn'><button id='customers'
                           onClick=\"window.location='http://" . $_SERVER ['SERVER_NAME'] . "/blackholesun/customers.php'\"  type='button'
                           class='btn btn-sm btn-primary'>Customers</button></p>";
		}
		?>
		<p class="navbar-right navbar-btn">
					<button id="logout"
						onClick="window.location='http://<?php echo $_SERVER['SERVER_NAME']?>/blackholesun/login.php'"
						type="button" class="btn btn-sm btn-primary">Logout</button>
				</p>
				<p class="navbar-text"><?php echo $_SESSION['username'] . " @ " . $_SESSION['bh_client_name']?>
		</p>

			</div>
			<!--/.nav-collapse -->
		</div>
		<!-- END nav container -->
	</nav>

	<div class="container-fluid text-left">
		<!-- indent the main body -->
		<div class="row content">
			<div class="col-sm-2 sidenav">
				<form action="<?=$_SERVER['PHP_SELF']?>" method='POST'>
					<input type="hidden" name="request" value="listexisting"> <input
						type="hidden" name="action" value="listexisting"> <input
						type="submit" name="submit" value="Show All Routes">
				</form>
				<form action="<?=$_SERVER['PHP_SELF']?>" method='POST'>
					<input type="hidden" name="request" value="listactive"> <input
						type="hidden" name="action" value="listactive"> <input
						type="submit" name="submit" value="Active Routes">
				</form>
				<form action="<?=$_SERVER['PHP_SELF']?>" method='POST'>
					<input type="hidden" name="user_role"
						value="<?php echo $_SESSION['user_role']?>"> <input type="hidden"
						name="client_id" value="<?php echo $_SESSION['client_id']?>"> <input
						type="hidden" name="request" value="pushchanges"> <input
						type="hidden" name="action" value="pushchanges"> <input
						type="submit" name="submit" value="Normalize ExaBGP">
				</form>
			</div>
			<div class="col-sm-9 text-left">
				Enter Route to Blackhole
				<form action="<?=$_SERVER['PHP_SELF']?>" method='POST'>
					<table>
						<thead>
							<tr>
								<th>Route</th>
								<th>Duration</th>
								<th>Start Date</th>
								<th>Time</th>
								<th>Customer</th>
								<th>Comments</th>
							</tr>
							</tr>
						</thead>
						<tr valign="top">
							<td><input type="text" id="bh_route" name="bh_route" required></td>
							<td><input type="text" id="bh_lifespan" name="bh_lifespan"
								value="72" required></td>
							<td><input type="date" id="bh_startdate" name="bh_startdate"
								required></td>
							<td><input type="time" id="bh_starttime" name="bh_starttime"
								required></td>
			    <?php
			    /*
			    * the following include loads all of the functions
			    * specific to this page
			    */
			    if ($_SESSION ['bh_user_role'] != 1) {
			    	list ( $error, $list ) = buildCustomerList ( NULL );
			    	if ($error != - 1) {
			    		print "<td>$list</td>";
			    	} else {
			    		$errFlag = - 1;
			    		$errMsg = $list;
			    	}
			    } else {
			    	list ( $error, $name ) = getClientNameFromID ( $_SESSION ['bh_client_id'] );
			    	if ($error != - 1) {
			    		print "<td>$name</td>";
			    		print "<input type=hidden name='bh_client_id' value='" . $_SESSION ['bh_client_id'] . "'/>";
			    	} else {
			    		$errFlag = - 1;
			    		$errMsg = $name;
			    	}
			    }
			    ?>
			    <td><textarea rows="2" columns="40" name="bh_comment"
									id="bh_comment"></textarea></td>
						</tr>
					</table>
					<input type="hidden" name="bh_requestor"
						value="<?php echo $_SESSION['username'];?>"> <input type="hidden"
						name="action" value="blackhole"> <input type="submit"
						name="submit" value="Add BH Route">
				</form>
				<script>
		    	document.getElementById("bh_startdate").valueAsDate = new Date();
			    var hours = new Date().getHours();
			    var minutes = new Date().getUTCMinutes();
			    if (minutes < 10) {
				    minutes = "0" + minutes;
				}
				time = hours + ":" + minutes;
				document.getElementById("bh_starttime").value = time;
				</script>
				<?php
				/* set this to your local timezone */
				/* this is needed for the time/date calcs */
				date_default_timezone_set ( 'America/New_York' );

				if (isset ( $_POST ['submit'] )) {
					/* We have form data */
					/*
					 * Many of the actions here require passing along the users
					 * affiliation and role so append it to the POST data
					 * the owner_id is the institution that created the route
					 * the client_id is the institution that the route applies to
					 * most of the time these should be the same but if an admin or
					 * the like creates the route we want to ensure that the client
					 * can't modfy the route.
					 */
					$_POST ['bh_owner_id'] = $_SESSION ['bh_client_id'];
					$_POST ['bh_client_id'] = $_SESSION ['bh_client_id'];
					$_POST ['bh_user_role'] = $_SESSION ['bh_user_role'];

					if ($_POST ['action'] == 'blackhole') {
						list ( $validRouteFlag, $message, $normalized ) =
							validateRoute ( $_POST ['bh_route'], $_SESSION ['bh_client_id'] );
						if ($validRouteFlag != 1) {
							$_SESSION ['errFlag'] = - 1;
							$_SESSION ['errMsg'] = $message;
						}
						$_POST ['bh_route'] = $normalized;
					}
					if ($_SESSION ['errFlag'] != - 1) {
						/*
						* send the command to a function that will
						* determine the next set of routines to run in
						* order to get the data.
						*/
						$request = json_encode ( $_POST ) . "\n";
						$response = sendToProcessingEngine ( $request );
						/* hopefully we have a reponse */
						parseResponse ( $_POST ['action'], $response);
					}
				}
				?>
			</div>
		</div>
	</div>
	<!-- modals handler -->
	<script type="text/javascript">
     <?php
     // This has to be kept in the footers as we don't have the variable data yet.
     // by the way, what we are doing here is using php to write javascript.
     // dirty!
     print "modalSetFormSrc(\"mainpage\");\n";
     print "mainpageFormInfo(" . $_SESSION ['errFlag'] . ", \"" . $_SESSION ['errMsg'] . "\");\n";
     $_SESSION ['errFlag'] = 0;
     $_SESSION ['errMsg'] = "";
     ?>
     </script>
</body>
