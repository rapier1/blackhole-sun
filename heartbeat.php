<?php
/* when a page is loaded send a quick query to the client
 * which then queries the exabgp server to see if it's
 * still alive. Report the status on the navbar line
 */
function getServerStatus ($action) {
    /* actions are
     * clibeat verifies that the client interface is up
     * exabeat ensures that the exabgp interface is up
     * bgpbeat ensures that the exabgp server is up
     */ 
    $request['action'] = $action;
    $json = json_encode($request);
    /* the response should be an int (1) indicating that the 
     * system in question is responding. Any other response
     * or lack there of is cause for a warning
     */ 
    $result = sendToProcessingEngine($json);
    if ($result != 1) {
        return -1;
    }
    return $result;
}

include("./functions.php");
header('Content-Type: application/json');

$request = $_POST['heartbeat_type']; 
$response = getServerStatus($request);
print $response;
?>