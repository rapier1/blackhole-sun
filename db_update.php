<?php
/* this script handles the incoming request to edit the database
 * entries. There are two things that need to happen. First, 
 * the database needs to be updated. Second, any changes have to be
 * pushed out to the exaBGP server. The first part is easy. The second...
 * we will see, won't we?
 */

/* incoming data is a POST request */
/* it will have the following elements
 * id: correspond to bh_index
 * bh_active: is the rout active
 * bh_route: the route in question
 * bh_lifespan: time the route is alive in hours
 * action: expect to see edit here
 */

header('Content-Type: application/json');

if (! isset($_POST)) {
    gotoerr("We didn't receive a request!");
}

/* this should be impossible */
if (! isset($_POST['id'])) {
    gotoerr("Missing id data in request");
}

/* this should also be impossible */
if (! isset($_POST['bh_active'])) {
    gotoerr("Active flag data missing in request");
}

/* we don't want a blank value */
if ($_POST['bh_route'] == "") {
    gotoerr("Black hole route missing in request");
}

/* the duration must exist and be positive */
if ($_POST['bh_lifespan'] == "") {
    gotoerr("Duration data missing in request");
}
if ($_POST['bh_lifespan'] < 0) {
    gotoerr("Duration must be a positive number");
}
if (! is_numeric($_POST['bh_lifespan'])) {
    gotoerr("Invalid duration. This value must be numeric.");
}

/* everything needs to be checked for consistency and injection attacks
 * bh_route and community are the only ones that are of concern
 * as we already have a check for bh_lifespan. 
 */

/*TODO: check route format here once we know what it is */

/* route is good. We'll use real_escape_string to sanitize the input
 * so we need to open a connection to the database
 */

$host='localhost';
$port='3306';
$user='bhson';
$password='washawaytherain';
$database='blackholesun';
$mysqli = new mysqli($host, $user, $password, $database, $port);
if (mysqli_connect_errno()) {
    $myerr = mysqli_connect_error();
    gotoerr("Failed to connect to database: $myerr");
}

$route = $mysqli->real_escape_string($_POST['bh_route']);

$query = "UPDATE bh_routes
          SET bh_active = ?,
              bh_lifespan = ?,
              bh_route = ?
          WHERE bh_index = ?";

if ($stmt = $mysqli->prepare($query)){
    $stmt->bind_param("dissi", $_POST['bh_active'], $_POST['bh_lifespan'],
                      $route, $_POST['id']);
$stmt->execute();
if ($stmt->errno) {
    gotoerr("Update failed:" . $stmt->error);
}
} else {
gotoerr("Failed to prepare query:" . $mysqli->error);
}


$foo = json_encode($_POST);
print $foo;

function gotoerr ($message) {
    $error = array (
        'header' => 'error',
        'message' => $message);
    print json_encode($error);
    exit;
}
                  
?>
