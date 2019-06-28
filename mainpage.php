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

/* the goal is to create a service where the user can
 * enter a route that will be blackholed and have it 
 * propagated to the exbgp server. 
 * so the intial pass will simply take a route to be blackholed
 * and pass it to the processing engine*/

/* the first pass is just going to consist of displaying a textbox
 * and then processing the form, getting the results, and displaying them
 * I'm likely going to want to use AJAX for this. 
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

    <?php
    session_start();
    if (empty($_SESSION["username"]))
    {
        header("Location: http://". $_SERVER['SERVER_NAME']. "/blackholesun/login.php");
        die();
    }
    include("./trfunctions.php");
    include("./functions.php");
    ?>

</head>

<body>
    <nav class="navbar navbar-inverse navbar-fixed-top">
        <div class="container">
            <div class="navbar-header">
		<div class="navbar-brand">BlackHole Sun</div>
            </div>
            <div id="navbar" class="collapse navbar-collapse">
                <ul class="nav navbar-nav">
		    <li><a id="menu-home" href="http://<?php echo $_SERVER['SERVER_NAME']?>/blackholesun/about.php">About</a></li>
		    <li><a id="menu-faq" href="http://<?php echo $_SERVER['SERVER_NAME']?>/blackholesun/faq.php">FAQ</a></li>
                </ul>
		<p class="navbar-right navbar-btn"><button id="logout"
							   onClick="window.location=
                                                           'http://<?php echo $_SERVER['SERVER_NAME']?>/blackholesun/accountmgmt.php'"
							   type="button"
							   class="btn btn-sm btn-primary">Account</button></p>
		<?php
		if ($_SESSION['bh_user_role'] == 4) {
		    print "<p class='navbar-right navbar-btn'><button id='management' 
                           onClick=\"window.location='http://" .
			  $_SERVER['SERVER_NAME'] .
			  "/blackholesun/management.php'\"  type='button' 
                           class='btn btn-sm btn-primary'>Managment</button></p>";
		}
		?>
		<p class="navbar-right navbar-btn">
		    <button id="logout"
			    onClick="window.location='http://<?php echo $_SERVER['SERVER_NAME']?>/blackholesun/login.php'"
			    type="button"
			    class="btn btn-sm btn-primary">Logout</button></p>
            </div><!--/.nav-collapse -->
	</div> <!-- END nav container -->
    </nav>

    <div class="container-fluid text-left" > <!-- indent the main body -->
	<div class="row content">
	    <div class="col-sm-2 sidenav">
		<form action="<?=$_SERVER['PHP_SELF']?>" method='POST'>
		    <input type="hidden" name="request" value="listexisting">
		    <input type="hidden" name="action" value="listexisting">
		    <input type="submit" name="submit" value="Show All Routes">
		</form>
		<form action="<?=$_SERVER['PHP_SELF']?>" method='POST'>
		    <input type="hidden" name="request" value="listactive">
		    <input type="hidden" name="action" value="listactive">
		    <input type="submit" name="submit" value="Active Routes">
		</form>
		<form action="<?=$_SERVER['PHP_SELF']?>" method='POST'>
		    <input type="hidden" name="request" value="pushchanges">
		    <input type="hidden" name="action" value="pushchanges">
		    <input type="submit" name="submit" value="Push Changes">
		</form>
	    </div>
	    <div class="col-sm-8 text-left">
		Enter Route to Blackhole
		<form action="<?=$_SERVER['PHP_SELF']?>" method='POST'>
		    <table>
			<thead>
			    <tr>
				<th>Route</th><th>Duration</th><th>Start Date</th><th>Time</th>
			    </tr>
			</thead>
			<tr>
			    <td><input type="text" id="bh_route" name="bh_route"></td>
			    <td><input type="text" id="bh_lifespan" name="bh_lifespan" value="72"></td>
			    <td><input type="date" id="bh_startdate" name="bh_startdate"></td>
			    <td><input type="time" id="bh_starttime" name="bh_starttime"></td>
			</tr>
		    </table>
		    <input type="hidden" name="action" value="blackhole">
		    <input type="submit" name="submit" value="Add BH Route">
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
/*I'm doing this whole thing wrong. I can't do this as a single 
 * monolithic page for any number of reasons. However, the most important
 * is that that it gets unweidly and makes it nearly imposible to use the various
 * javascipt libraries. We need to break all of the functions down into 
 * sub pages and make use of ajax as appropriate. 
 */

if (isset($_POST['submit'])) {
    /*We have form data*/
    /* send the command to a function that will 
     * determine the next set of routines to run in
     * order to get the data. 
     */
    
    $request = json_encode($_POST) . "\n";
    $response = sendToProcessingEngine($request);
    /*hopefully we have a reponse */
    parseResponse($_POST['action'], $response);
}

/* we have a response. Now we need to parse it out
 * how we parse and display it is based on the type of
 * request that was sent */
function parseResponse ($action, $data) {
    switch ($action) {
	case 'blackhole':
	    confirmBH($data);
	    break;
	case 'listexisting':
	    formatList($data);
	    break;
	case 'listactive':
	    formatList($data);
	    break;
	case 'deleteselection':
	case 'confirmbhdata':
	case 'quit':
	    break;
    }
}

function confirmBH($data) {
    if ($data == -1) {
        print "<script>alert('Invalid Route/IP Entered')</script>";    
        return;
    }
    if ($data == -2) {
        print "<script>alert('Non Numeric Duration Entered')</script>";    
        return;
    }
    if ($data == -3) {
        print "<script>alert('Duration Out Of Range (must be between 0 and 2160)')</script>";    
        return;
    }
    print "<script>alert('$data')</script>";    
}

function formatList($request) {
    $table_data = json_decode($request, true);
    // the ajax can't use the client process
    // so we need to update the database in a different function
    // in this case editdb.php. I dislike breaking up the
    // database access functions like this but if I want to
    // use the table edit function I need to go this route. 
    print <<< EOL
    <script type="text/javascript">   
    $(document).ready(function(){
	$('#bhTable').Tabledit({
	    url: './editdb.php', 
	    editButton: true,
	    deleteButton: false,
	    onSuccess: function (data, textStatus, jqXHR) {
		obj = JSON.parse(data);
		if (obj.results == "Success") {
		    alert("Route updated");
		} else {
            if (obj.results == "-1") {
                error = "Invalid route";
            } else if (obj.results == "-2") {
                error = "Non numeric duration";
            } else if (obj.results == "-3") {
                error = "Duration out of bounds";
            } else {
                error = "Unknown error";
            }
            // on a failure we should find some way to revert to the prior values
		    alert("Update failure: " + error);
		}
		return;
	    },
	    // on a failure we shoudl find some way to revert to the prior values
	    onFail(jqXHR, textStatus, errorThrown) {
		alert("fail! " + errorThrown  + " : " + textStatus);
		return;
	    },
	    columns: {
		identifier: [0, 'id'],                    
		editable: [[1, 'bh_active', '{"0": "no", "1": "yes"}'],
			   [2, 'bh_route'],
			   [3, 'bh_lifespan'],
			   [7, 'bh_index']]
	    }
	});
    });        
    </script>
    <hr>
    <table id="bhTable" class="display" width="100%" align="center" cellspacing="0">
    <thead>
    <tr></tr>
    <tr>
    <th>Index</th>
    <th>Active</th>
    <th>Route</th>
    <th>Duration</th>
    <th>Start Time</th>
    <th>Remaining</th>
    <th>Requestor</th>
    </tr>
    </thead>
EOL;
    foreach ($table_data as $row) {
	$remaining = findRemainingTime($row[bh_starttime], $row[bh_lifespan]);
	$active = "no";
	if ($row[bh_active] == 1) {
	    $active = "yes";
	}
	print "<tr>";
	print "<td id='identifier' name='identifier'>" . $row[bh_index] . "</td>";
	print "<td id='bh_active' name='bh_active'>" . $active . "</td>";
	print "<td id='bh_route' name='bh_route'>" . $row[bh_route] . "</td>";
	print "<td id='bh_lifespan' name='bh_lifespan'>" . $row[bh_lifespan] . "</td>";
	print "<td>" . $row[bh_starttime] . "</td>";
	print "<td>" . $remaining . "</td>";
	print "<td>" . $row[bh_requestor] . "</td>";
	# we hide this cell - we need to be able to pass along the index value but we
	# don't want them to edit it. This works without modifying the tabledit js code. 
	print "<td style='display:none' id='bh_index' name='bh_index'>" . $row[bh_index] . "</td>"; 
	print "</tr>";
    }
    print "</table>";
}

function findRemainingTime ($start, $life) {
    $currentdate = new DateTime(date('m/d/Y h:i:s'));
    $startdate = new DateTime($start);
    $diff = $currentdate->diff($startdate);
    if ($diff < 0) {
	return "$life:00";
    }
    $hours = $diff->days*24 + $diff->h + $diff->i/60;
    $remaining = $life - $hours;
    $fraction = $remaining - intval($remaining);
    $timeleft = intval($remaining). ":" . $fraction*60;
    if ($timeleft < 0) {
	return "00:00";
    }
    return $timeleft;
}

/*sanity check to make sure it's actually json data */
function validateJSON ($json=null) {
    /* is it a string? */
    if (is_string($json)) {
	/*decode the json. the @ supresses any errors/warnings
	 *and fills the last_error struct*/
	@json_decode($json);
	/*returns true if the last_error is no error*/
	return (json_last_error() === JSON_ERROR_NONE);
    }
    /* bad json. no biscuit. */
    return false;
}

function printFooter () {
    exit;
}
?>
	    </div>
	</div>
    </div>
</body>
