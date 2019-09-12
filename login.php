<?php
/*
 * Copyright (c) 2018 The Board of Trustees of Carnegie Mellon University.
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
include './functions.php';
include './login_functions.php';

logOut(); /* making sure we clear all session data */
session_start();
session_unset();

//GLOBAL VARIABLE DECLARATIONS
$inputs = array(
    "bhUsername" => "",
    "bhPassword" => "");
$inputErrors = array(
    "bhUsername" => "",
    "bhPassword" => "");
$errFlag = -1;
$errMsg = "";
//END VARIABLE DECLARATIONS



//Has the user supplied their username and password?
if (!empty($_SESSION["username"]))
{
    //print 'You are already logged in, ' . $_SESSION["username"];
    header("Location:http://". $_SERVER['SERVER_NAME'] ."/blackholesun/routes.php");
}
else if ($_SERVER["REQUEST_METHOD"] == "POST")
{
    if (empty($_REQUEST["bhUsername"]))
    {
	$inputErrors["bhUsername"] = "You must provide a username";
	$errFlag = 1;
    }

    if (empty($_REQUEST["bhPassword"]))
    {
        $inputErrors["bhPassword"] = "Please enter your password";
        $errFlag = 1;
    }

    if ($errFlag != 1)
    {
	$inputs["bhUsername"] = scrubInput($_REQUEST["bhUsername"]);
	$inputs["bhPassword"] = scrubInput($_REQUEST["bhPassword"]);

	// The logIn function sets all of the session variables and the redirect to the routes page.
	$pwdMatchFlag = logIn($inputs["bhUsername"],$inputs["bhPassword"]);

        if ($pwdMatchFlag == 1){
            $inputErrors["bhPassword"] = "Authentication failure";
            $errFlag = 1;
        }

    }
    // implode error messages into one string
    $errMsg = implode("<br>", array_filter($inputErrors));
}//END AJAX processing
?>


<html>
    <head>
	<meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <!-- The above 3 meta tags *must* come first in the head; any other head content must come *after* these tags -->
        <meta name="description" content="Blackhole Sun">
        <meta name="author" content="Pittsburgh Supercompuing Center">
        <link rel="icon" href="./icons/favicon.ico">
        <title>Blackhole Sun</title>
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
    </head>
    
    <body>
	<nav class="navbar navbar-inverse navbar-fixed-top">
            <div class="container">
                <div class="navbar-header">
                    <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#navbar" aria-expanded="false" aria-controls="navbar">
                        <span class="sr-only">Toggle navigation</span>
                        <span class="icon-bar"></span>
                        <span class="icon-bar"></span>
                        <span class="icon-bar"></span>
                    </button>
                    <div class="navbar-brand">BlackHole Sun: An ExaBGP Interface</div>
                </div>
                <div id="navbar" class="collapse navbar-collapse">
                    <ul class="nav navbar-nav">
                        <li><a id="menu-home" href="http://<?php echo $_SERVER['SERVER_NAME']?>/blackholesun/about.php">About</a></li>
			<li><a id="menu-faq" href="http://<?php echo $_SERVER['SERVER_NAME']?>/blackholesun/faq.php">FAQ</a></li>
                    </ul>
                </div><!--/.nav-collapse -->
            </div> <!-- END nav container -->
        </nav>
	
	<!-- MAIN Container for Login Form -->
	<?php include ("./modals.php"); ?>
	<div name="container-main" class="container">
	    <div class="row starter-template">
		<h1 class="text-center"> Log In </h1>
	    </div>
	    <div class="row">
		<div name="padding-left" id="padding-left" class="col-8"></div>
		
		<div name="loginSection" id="loginSection" class="col-2 starter-template">
		    
		    <form id="loginForm" name="loginForm" class="form-horizontal col-6" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]);?>" method="post" role="form" class="form-horizontal">
			
			<div class="form-group">
			    <label for="bhUsername">Username</label>
			    <input type="text" id="bhUsername" name="bhUsername" class="form-control">
			</div>
			
			<div class="form-group">
			    <label for="bhPassword">Password</label>
			    <input type="password" id="bhPassword" name="bhPassword" class="form-control">
			</div>
			<div class="form-group">
			    <input type="submit" value="Log In" class="btn btn-primary">
			</div>
		    </form>
		    
		    <p class="text-center"><a href="forgot.php">Forgot username or password?</a></p>
		</div>
		
		<div name="padding-right" id="padding-right" class="col-5"></div>
	    </div>
	</div> <!-- END main container -->
	
    </body>
    
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.1.1/jquery.min.js"></script>
    <script src="bootstrap/dist/js/bootstrap.min.js"></script>
    <script src="trmodals.js"></script>
    <script>
     <?php
     print "loginFormInfo($errFlag, '$errMsg');\n";
     ?>
    </script>
</html>
n
