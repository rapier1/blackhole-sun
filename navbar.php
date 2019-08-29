<?php 
/* we are defining all of the nav bar buttons we will be using here
 * an identifier on each page will indicate which set of buttons
 * we should display
 */
/* load the javascript to handle the server status fun */
print "<script src='./heartbeat.js'></script>";

$logout_btn = "<p class = 'navbar-right navbar-btn'> <button id='logout'
		  onClick=\"window.location='http://" . $_SERVER['SERVER_NAME'] .
	          "/blackholesun/login.php'\" type='button' 
                  class='btn btn-sm btn-primary'>Log Out</button></p>";
$login_btn = "<p class = 'navbar-right navbar-btn'> <button id='login'
		 onClick=\"window.location='http://" . $_SERVER['SERVER_NAME'] .
	         "/blackholesun/login.php'\" type='button' 
                 class='btn btn-sm btn-primary'>Log In</button></p>";
$user_management_btn =  "<p class='navbar-right navbar-btn'><button id='userManagement'
                            onClick=\"window.location='http://" . $_SERVER ['SERVER_NAME'] .
			    "/blackholesun/usermanagement.php'\"  type='button'
                            class='btn btn-sm btn-primary'>Users</button></p>";
$customers_btn =  "<p class='navbar-right navbar-btn'><button id='customers'
                      onClick=\"window.location='http://" . $_SERVER ['SERVER_NAME'] .
		      "/blackholesun/customers.php'\"  type='button'
                      class='btn btn-sm btn-primary'>Customers</button></p>";
$account_btn = "<p class='navbar-right navbar-btn'> <button id='account' 
                   onClick=\"window.location='http://" . $_SERVER['SERVER_NAME'] .
	           "/blackholesun/accountmgmt.php'\" type='button' 
                   class='btn btn-sm btn-primary'>Account</button></p>";
$routes_btn = "<p class='navbar-right navbar-btn'><button id='routeList' 
                  onClick=\"window.location='http://" . $_SERVER['SERVER_NAME'] .
	          "/blackholesun/routes.php'\" type='button' 
                  class='btn btn-sm btn-primary'>Route List</button></p>";
$new_user_btn = "<p class='navbar-right navbar-btn'><button id='newUser'
                    onClick=\"window.location='http://" . $_SERVER['SERVER_NAME'] . 
	            "/blackholesun/newuser.php'\" type='button'
                    class='btn btn-sm btn-primary'>New User</button></p>";
$new_customer_btn = "<p class='navbar-right navbar-btn'> <button id='newCustomer'
			onClick=\"window.location='http://" . $_SERVER['SERVER_NAME'] .
		        "/blackholesun/newcustomer.php'\" type='button' 
                        class='btn btn-sm btn-primary'>New Customer</button></p>";

if (! empty($_SESSION['username'])) {
    $user_name = $_SESSION['username'] . " @ " . $_SESSION['bh_customer_name'];
} else {
    $user_name = "";
}

/* start the nav bar */
print "
<nav class='navbar navbar-inverse navbar-fixed-top'>
<div class='container'>
<div class='navbar-header'>
<div class='navbar-brand'>BlackHole Sun</div>
</div>
<div id='navbar' class='collapse navbar-collapse'>
<ul class='nav navbar-nav'>
<li><a id='menu-home'
href=\"http://" . $_SERVER['SERVER_NAME'] . "/blackholesun/about.php\">About</a></li>
		<li><a id='menu-faq'
		       href=\"http://" . $_SERVER['SERVER_NAME'] . "/blackholesun/faq.php\">FAQ</a></li>
		<li><a>Status: </a></li>
		<li><a>
		    <img id='clibeatdot' src='./greendot.png' height='10' width='10' 
			 title='BHS Web Interface Status'>
		</a></li>
		<li><a>
		    <img id='exabeatdot' src='./greendot.png' height='10' width='10' 
			 title='BHS ExaBGP Interface Status'>
		</a></li>
		<li><a>
		    <img id='bgpbeatdot' src='./greendot.png' height='10' width='10' 
			 title='ExaBGP Server Status'>
		</a></li>
		<li><a>$user_name</a></li>
	    </ul>";
/* every page has a logout and account button if they are logged in */
if (! empty($_SESSION['username'])) {
    print $logout_btn;
    print $account_btn;
    if ($_SESSION['bh_user_role'] != 4) {
	if ($page_id == "account" or $page_id == "faq" or $page_id == "about") {
	    print $routes_btn;
	}
    }
} else {
    /* else print out a login button */
    print $login_btn;
}
/* we aren't explictly checking for a log in here
 * because they cant have a session without logging in
 */
if ($_SESSION ['bh_user_role'] == 4) {
    if ($page_id == "routes") {
	print $user_management_btn;
	print $customers_btn;
    }
    if ($page_id == "user_mgmt") {
	print $routes_btn;
	print $customers_btn;
	print $new_user_btn;
    }
    if ($page_id == "new_user") {
	print $customers_btn;
	print $routes_btn;
	print $user_management_btn;
    }
    if ($page_id == "customers") {
	print $user_management_btn;
	print $routes_btn;
	print $new_customer_btn;
    }
    if ($page_id == "new_customer") {
	print $user_management_btn;
	print $routes_btn;
	print $customers_btn;
    }
    if ($page_id == "account") {
	print $user_management_btn;
	print $routes_btn;
	print $customers_btn;
    }
    if ($page_id == "faq"){
	print $customers_btn;
	print $routes_btn;
	print $user_management_btn;
    }
    if ($page_id == "about"){
	print $customers_btn;
	print $routes_btn;
	print $user_management_btn;
    }

}
print "	    </div>
	    <!--/.nav-collapse -->
	</div>
	<!-- END nav container -->
    </nav>";
?>
