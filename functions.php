<?php
/* need to open a socket to the server
 * send the request
 * and get anything back for display
 */

function sendToProcessingEngine ($request) {
    include ("./bhconfig.php");
    /* open the socket */
    if (!($sock = socket_create(AF_INET, SOCK_STREAM, 0))) {
        $errorcode = socket_last_error();
        $errormsg = socket_strerror($errorcode);
        print "I cowardly refused to create a socket: [$errorcode], $errormsg\n";
        exit;
    }
    /* connect to the processing engine */
    if (! socket_connect($sock, $server, 20202)) {
        $errorcode = socket_last_error();
        $errormsg = socket_strerror($errorcode);
        print "Could not connect to processing engine: [$errorcode], $errormsg\n";
        exit;
    }    
    /* send the data */
    if (! socket_send($sock, $request, strlen($request), 0)) {
        $errorcode = socket_last_error();
        $errormsg = socket_strerror($errorcode);
        print "Could not send data: [$errorcode] $errormsg \n";
        exit;
    }
    /* read the response */
    /* TEMPNOTE i Need to have the processing 
     * engine spit some back to test that this works
     * Might want to just take the inbound json (from here)
     * reencode it, and spit it back 10/12/2018*/
    if (!($buf = socket_read($sock, 64000, PHP_NORMAL_READ))) {
        $errorcode = socket_last_error();
        $errormsg = socket_strerror($errorcode);
        die("Could not receive data: [$errorcode] $errormsg \n");
    }
    return $buf;
}
?>