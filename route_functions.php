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

/* do different things depending on what the user has decided to do
 * this function, even though I wrote it, strikes me as odd. I believe
 * there is a better way to do this
 *  action: value sent by specific submit button
 *  data: form values
 */
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
	case 'getexaroutes':
	    listExaRoutes($data);
	    break;
	case 'deleteselection':
	case 'confirmbhdata':
	case 'quit':
	    break;
    }
}

/* set the errMsg to give the user feedback on the results */
function confirmBH($data) {
    $data = trim($data);

    if (! is_int($data)) {
        /* we got a strange error back - likely from the database*/
        $_SESSION['errFlag'] = -1;
        $_SESSION['errMsg'] = $data;
    }

    if ($data == 1) {
        $_SESSION['errFlag'] = 1;
        $_SESSION['errMsg'] = "Route successfully added";
    }
    if ($data == -1) {
        $_SESSION['errFlag'] = -1;
        $_SESSION['errMsg'] = "Invalid Route/IP Entered";
    }
    if ($data == -2) {
	$_SESSION['errFlag'] = -1;
        $_SESSION['errMsg'] = "Non Numeric Duration Entered";
    }
    if ($data == -3) {
        $_SESSION['errFlag'] = -1;
        $_SESSION['errMsg'] = "Duration Out Of Range (must be between 0 and 2160)";
    }
}
/*
 * The tables on the routes page require a certain amount of JS in order to function 
 * properly. This function builds 3 different types of tables.
 * 1) Editable list of routes from the DB
 * 2) Non editable list of routes form the DB
 * 3) Non editable list of routes as reported by the ExaBGP instance
 * inputs: type -> noedit produces a non editable table. Any other value does
 *         table -> exatable produces a route list based on ExaBGP values any
 *                  any other values assume that the data source is the database
 * outputs: text string containing the appropriate table headers
 */
function buildTableJS ($type, $table) {
    /* need to define this to supress warnings */
    $owner_list = ""; 

    /* exabgp data source */
    if ($table == "exatable") {
	$container = "pagerNoEdit";
	$tableid = "bhTableNoEdit";
    } else {
	/* DB data source */
	if ($type == "noedit") {
	    /* do not allow edits */
    	    $container = "pagerNoEdit";
    	    $tableid = "bhTableNoEdit";
    	    $owner_list = "";
	} else {
	    /* allow edits */
    	    $container = "pager";
    	    $tableid = "bhTable";
	    /* getOwnerData builds a json like string that is used to build a select widget */
    	    $owner_list = getOwnerData();
	}
    }

    /* this instantiates the pager and sorter */
    $table_sorter_header = "
    <script type='text/javascript'>
    $(document).ready(function(){
         pagerOptions = {
              container: $(\"." . $container . "\"),
              output: '{startRow} - {endRow} / {filteredRows} ({totalRows})',
              fixedHeight: false,
              removeRows: false,
              cssGoto: '.gotoPage'
         };

	$('#" . $tableid . "').tablesorter({
	    widgets        : ['zebra', 'columns', 'pager', 'filter'],
            widgetOptions: {
                filter_hideFilters: true,
                filter_placeholder: { search: 'Search...' },
            },
	    usNumberFormat : false,
	    sortReset      : true,
	    sortRestart    : true
	}).tablesorterPager(pagerOptions);";

    /* this allows for editing of the table */
    $table_edit = "
    $('#" . $tableid . "').Tabledit({
	url: './editdb.php',
	editButton: true,
	deleteButton: false,
	onSuccess: function (data, textStatus, jqXHR) {
	    obj = JSON.parse(data);
	    if (obj.results == 'Success') {
		    alert('Route updated');
	    } else {
		    if (obj.results == '-1') {
		        error = 'Invalid route';
		    } else if (obj.results == '-2') {
		        error = 'Non numeric duration';
		    } else if (obj.results == '-3') {
		        error = 'Duration out of bounds';
		    } else {
		       error = 'Unknown error';
		    }
		    // on a failure we should find some way to revert to the prior values
		    alert('Update failure: ' + error);
	    }
	    return;
	},
	// on a failure we should find some way to revert to the prior values
	onFail(jqXHR, textStatus, errorThrown) {
	    alert('fail! ' + errorThrown  + ' : ' + textStatus);
	    return;
	},
	columns: {
	    identifier: [0, 'id'],
	    editable: [[1, 'bh_active', '{\"0\": \"no\", \"1\": \"yes\"}'],
    		[2, 'bh_route'],
    		[3, 'bh_lifespan'],
    		[8, 'bh_owner_id','" . $owner_list . "'],
    		[9, 'bh_comment', 'textarea'],
    		[10, 'bh_index', 'hidden'],
            [11, 'bh_customer_id', 'hidden'],
            [12, 'bh_requestor', 'hidden']]
        }
    });\n";

    /* this builds the controls for the pager/sorter */
    $table_sorter_footer = "});
</script>
<div class='" . $container . "'>
Page: <select class='gotoPage'></select>
<img src='jquery/tablesorter-master/addons/pager/icons/first.png' class='first' alt='First' title='First page' />
<img src='jquery/tablesorter-master/addons/pager/icons/prev.png' class='prev' alt='Prev' title='Previous page' />
<span class='pagedisplay'></span> <!-- this can be any element, including an input -->
<img src='jquery/tablesorter-master/addons/pager/icons/next.png' class='next' alt='Next' title='Next page' />
<img src='jquery/tablesorter-master/addons/pager/icons/last.png' class='last' alt='Last' title= 'Last page' />
<select class='pagesize'>
<option value='10'>10</option>
<option value='20'>20</option>
<option value='30'>30</option>
<option value='40'>40</option>
</select>
</div>";

    /* this builds the table header row for the two types of supported tables */
    if ($table == "exatable") {
	$table_header = "
<table id='" . $tableid . "' class='display' width='100%' align='center' cellspacing='0'>
<thead>
<tr><th>Index</th><th>Route in ExaBGP</th><tr>
</thead>
<tbody>";
    } else {
	$table_header ="
<table id='" . $tableid . "' class='display' width='100%' align='center' cellspacing='0'>
<thead>
<tr>
<th>Index</th>
<th>Active</th>
<th style='width:100px'>Route</th>
<th>Duration</th>
<th>Start Time</th>	
<th>Remaining</th>
<th>Requestor</th>
<th>Customer</th>
<th>Owner</th>
<th style='width:100px'>Comments</th>
</tr>
</thead>
<tbody>";
    }
    
    /* concatonate everything into a coherent js/header for the table */
    if ($type == "noedit") {
    	return $table_sorter_header . $table_sorter_footer . $table_header;
    }
    return $table_sorter_header . $table_edit . $table_sorter_footer . $table_header;
}

/* get a list of the owner data so we can build an json string suitable for use
 *  in tabledit format is {"ownerid1": "ownername1", "ownerid2": "ownername2,...}
 */
function getOwnerData() {
    $dbh = getDatabaseHandle();
    $query = "SELECT bh_customer_id, bh_customer_name
              FROM bh_customers";
    $sth = $dbh->prepare($query);
    $sth->execute();
    $i=0;
    while ($row = $sth->fetch()) {
	/* this is the required format for tabledit select boxes */
	$name_array[$i] = "\"$row[0]\": \"$row[1]\"";
	$i++;
    }
    //    $sth->close();
    $list = "{" . implode(",", $name_array) . "}";
    return $list;
}

/* formats the list of blackhole routes
 * request: display all routes or just active routes
 */
function formatList($request) {
    $table_data = json_decode($request, true);
    $noeditflag = 0;
    $editable_rows = 0;
    /* build the header for the table */
    print buildTableJS("edit", "dbtable");
    foreach ($table_data as $row) {
	/* rows are marked as editable or not. If the are a user then this row is skipped */
	if ($row['editable'] != 1 && $_SESSION['bh_user_role'] == 1) {
	    $noeditflag = 1;
	    continue;
	}
	$editable_rows++;
	/* default values over written if the row is active */
        $active = "no";
	$remaining = "00:00";
        if ($row['bh_active'] == 1) {
	    /* determine the amount of time left for this route to be active */
	    $remaining = findRemainingTime($row['bh_starttime'], $row['bh_lifespan']);
	    $active = "yes";
        }
        print "\n<tr>\n";
        print "\t<td id='identifier' name='identifier'>" . $row['bh_index'] . "</td>\n";
        print "\t<td id='bh_active' name='bh_active'>" . $active . "</td>\n";
        print "\t<td id='bh_route' name='bh_route'>" . $row['bh_route'] . "</td>\n";
        print "\t<td id='bh_lifespan' name='bh_lifespan'>" . $row['bh_lifespan'] . "</td>\n";
        print "\t<td>" . $row['bh_starttime'] . "</td>\n";
        print "\t<td>" . $remaining . "</td>\n";
        print "\t<td>" . $row['bh_requestor'] . "</td>\n";
        print "\t<td id='bh_customer_name' name='bh_customer_name'>" . $row['bh_customer_name'] . "</td>\n";
        print "\t<td id='bh_owner_id' name ='bh_owner_id'>" . $row['bh_owner_name'] . "</td>\n";
        print "\t<td id='bh_comment' name ='bh_comment'>" . $row['bh_comment'] . "</td>\n";
        /* we hide the folllwing cells - we need to be able to pass along these values but
         * not allow them to edit them. This required a change to the jquery.tabledit code. 
         * NB: Someone can still change this value by modifying the value in the console
         */
        print "\t<td id='bh_index' name='bh_index'>" . $row['bh_index'] . "</td>\n";
        print "\t<td id='bh_customer_id' name='bh_customer_id'>". $row['bh_customer_id']. "</td>\n";
        print "\t<td id='bh_requestor' name='bh_requestor'>". $row['bh_requestor']. "</td>\n";
        print "</tr>\n";
    }
    /* if this value is zero they they have nothing they can edit so make sure they know it's not a mistake*/
    if ($editable_rows == 0) {
	print "<tr><td align=center colspan=9>No Editable Routes Available</td></tr>";
    }
    print "</tbody>\n</table>\n";

    /* we now load any noneditable routes into another table */
    if ($_SESSION['bh_user_role'] == 1 && $noeditflag == 1) {
	/* print a table of non-editable rows  */
	print "<hr>";
	print buildTableJS("noedit", "dbtable");
	foreach ($table_data as $row) {
	    /*obviously we want to skip rows they can edit */
	    if ($row['editable'] == 1) {
		continue;
	    }
	    $remaining = "00:00";
	    $active = "no";
	    if ($row['bh_active'] == 1) {
		$remaining = findRemainingTime($row['bh_starttime'], $row['bh_lifespan']);
		$active = "yes";
	    }
	    print "\n<tr>\n";
	    print "\t<td id='identifier' name='identifier'>" . $row['bh_index'] . "</td>\n";
	    print "\t<td id='bh_active' name='bh_active'>" . $active . "</td>\n";
	    print "\t<td id='bh_route' name='bh_route'>" . $row['bh_route'] . "</td>\n";
	    print "\t<td id='bh_lifespan' name='bh_lifespan'>" . $row['bh_lifespan'] . "</td>\n";
	    print "\t<td>" . $row['bh_starttime'] . "</td>\n";
	    print "\t<td>" . $remaining . "</td>\n";
	    print "\t<td>" . $row['bh_requestor'] . "</td>\n";
	    print "\t<td>" . $row['bh_customer_name'] . "</td>\n";
	    print "\t<td>" . $row['bh_owner_name'] . "</td>\n";
	    print "\t<td>" . $row['bh_comment'] . "</td>\n";
	    print "</tr>\n";
	}
	print "</tbody>\n</table>\n";
    }
}

/* get a list of the routes as reported by exabgp */
function listExaRoutes () {
    /* send the request to the processing engine
     * TODO: define what the specific request looks like here
     */
    $routes_json = sendToProcessingEngine(json_encode($_POST) . "\n");
    $routes_obj = json_decode($routes_json);
    $i = 0;
    /* build the table header */
    print buildTableJS("noedit", "exatable");
    /* populate the table */
    foreach ($routes_obj as $key=>$value){
	$i++;
	print "\t<tr><td>$i</td><td>$key</td></tr>\n";
    }
    /* warn if there is nothing there */
    if ($i == 0) {
	print "<tr><td align=center colspan=2>No Available Routes on Server</td></tr>";
    }	
    /* close the table */
    print "</tbody></table>";
}

/* calculate how much time is remaining before this route expires
 * start = time date value of when the flow was instantiated
 * life = initial lifespan of the route
 * return : string in hours:min format
 */
function findRemainingTime ($start, $life) {
    /* magic number for an immortal route */
    if ($life == 9999) {
	return "Immortal";
    }
    $currentdate = new DateTime(date('m/d/Y H:i:s'));
    $startdate = new DateTime($start);
    $diff = $currentdate->diff($startdate);
    $hours = $diff->days*24 + $diff->h + ($diff->i/60);
    $remaining = $life - $hours;
    $fraction = $remaining - intval($remaining);
    $timeleft = intval($remaining). ":" . $fraction*60;
    if ($timeleft < 0) {
	return "00:00";
    }
    return $timeleft;
}

/* we need to build an option list of customers using the bh_customers table in the
 * bhs database
 * inputs: optional user affiliation - customer id number
 * return: html option list or error
 */
function buildCustomerList ($affiliation) {
    $dbh = getDatabaseHandle();
    $query = "SELECT bh_customer_id,
                     bh_customer_name
              FROM bh_customers";
    try{
        $sth = $dbh->prepare($query);
        $sth->execute();
        $result = $sth->fetchall(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        $error = "Something went wrong while interacting with the database: "
	       . $e->getMessage();
        return array(-1, $error);
    }
    if (! isset($result)) {
        // The DB didn't return a result or there was an error
        $error = "No results were returned. This shouldn't happen unless you haven't created any customers after install.";
        return array(-1, $error);
    }
    /* we are building a select list widget here */
    $list = "<select name='bh_owner_id' class='form-control' required>\n";
    $list .= "<option value=''>---</option>\n";
    foreach ($result as $line) {
        $selected = "";
        if ($affiliation == $line['bh_customer_id']) {
	    $selected = "SELECTED";
        }
        $list .= "<option $selected value='" . $line['bh_customer_id'] . "'>" . $line['bh_customer_name'] . "</option>\n";
    }
    $list .= "</select>";
    return array(0, $list);
}

/* using the customer id deteremine what the human readable name is */
/* input customerid = int */
function getCustomerNameFromID($customerid) {
    $dbh = getDatabaseHandle();
    $query = "SELECT bh_customer_name
              FROM bh_customers
              WHERE bh_customer_id = :customerid";
    try{
        $sth = $dbh->prepare($query);
        $sth->bindParam(':customerid', $customerid, PDO::PARAM_STR);
        $sth->execute();
        $result = $sth->fetchColumn();
    } catch(PDOException $e) {
        $error = "Something went wrong while interacting with the database: "
	       . $e->getMessage();
        return array(-1, $error);
    }
    if (! isset($result)) {
        // The DB didn't return a result or there was an error
        $error = "No results were returned. This shouldn't happen unless you haven't created any customers after install.";
        return array(-1, $error);
    }
    return array(0, $result);
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

/* not much happening here yet. */
function printFooter () {
    exit;
}


/* we want to ensure that all submissions have a mask on them
 * in case the user doesn't add one then we assume it's a single
 * ip and add either a /32 for v4 or /128 for v6
 */
function normalizeRoute ($route) {
    if (strpos($route, "/") !== false) {
        list ($address, $mask) = explode ("/", $route);
    } else {
        $address = $route;
    }
    
    # make sure we have *something* here
    if (!isset($address)) {
        return -1;
    }
    /* they may not supply a mask so we need to assume that if they
     * don't then it's a single address 32 for v4 & 128 for v6*/
    if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
        if (!isset($mask)) {
            $mask = 32;
        }
        return ($address . "/" . $mask);
    }
    if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
        if (!isset($mask)) {
            $mask = 128;
        }
        return ($address . "/" . $mask);
    }
    return -1;
}

/* we need to ensure that the route requested lives inside
 * of one of the address blocks owned by the customer
 * so load the approved route blocks from the database
 * if the incoming route is a /32 we just see if it's
 * in one of those blocks
 * if it is not a /32 we need to find the
 * upper and lower bounds of the network and ensure that
 * each of them exists within a block
 * $route is the addess supplied by the user
 * $customer is the customer/customer id as retreived from the user profile
 */

function validateRoute ($route, $customerid) {
    /* we are importing a class to handle this but only
     * include it if we are goign to be using it. Ya know?
     */
    include_once("./CIDR.php");

    /* ensure that the address is in properl IP/mask format */
    $route = normalizeRoute($route);
    if ($route == -1) {
        return array(-1, "This address is not valid IPv4 or IPv6", null);
    }

    /* first we are going to ensure that the supplied route is actually
     * a valid ip address
     */

    if (validateCIDR($route) == -1) {
        return array (-1, "This address is not valid IPv4 or IPv6", null);
    }

    /* next by get the route blocks from the bh_customers table */
    $dbh = getDatabaseHandle();
    $query = "SELECT bh_customer_blocks
              FROM bh_customers
              WHERE bh_customer_id = :customerid";
    try{
        $sth = $dbh->prepare($query);
        $sth->bindParam(':customerid', $customerid, PDO::PARAM_STR);
        $sth->execute();
        $result = $sth->fetch(PDO::FETCH_ASSOC);
    }
    catch(PDOException $e) {
        $error =  "Something went wrong while interacting with the database:"
               . $e->getMessage();
        return array(-1, $error, null);
    }
    if (! isset($result)) {
        // The DB didn't return a result or there was an error
        $error = "No results were returned. There may be a problem with the database.";
        return array (-1, $error, null);
    }
    $blocks = json_decode ($result['bh_customer_blocks'], true);
    
    /* now that we have the blocks we need to get the upper and lower
     * ranges of the user submitted route if they have a cidr mask */
    list ($upper, $lower) = explodeAddress($route);

    /* we now have the upper and lower bounds
     * right now i'm just going to brute force this and
     * not figure out if they are the same address
     * I'll just run both under the assumption that
     * it won't bog things down too much as opposed to
     * spending the time to write an elegant but of logic to
     * handle it properly. I need more coffee. This is obvious
     */

    $cidrtest = new CIDR();
    
    foreach ($blocks['blocks'] as $block) {
        $uppertest = $cidrtest->match($upper, $block);
        $lowertest = $cidrtest->match($lower, $block);
        if ($uppertest === true and $lowertest === true) {
            return array (1, null, $route);
        }
    }
    /* no matches*/
    return array (-1, "The IP address is not in range of any address blocks you control", null);
}


/* this find the high and low range of a CIDR route
 * in other words it returns the network and broadcast address of
 * any given IP with CIDR mask. Handles IPv4 and IPv6
 * eg 10.0.0.1/24 would return 10.0.0.1 and 10.0.0.255
 */
function explodeAddress ($route) {
    
    list($address, $mask) = explode ("/", $route);
    
    /* if the mask is not set then it's a single address and not a range */
    if (!isset($mask)) {
        return array ($address, $address);
    }
    
    /* we need to determine if its an ipv4 or ipv6 */
    if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
        if ($mask < 0 or $mask > 32) {
            return array(-1, "Invalid CIDR mask for IPv4");
        }
        /* get and return the network and broadcast */
        $network = long2ip((ip2long($address)) & ((-1 << (32 - (int)$mask))));
        $broadcast = long2ip((ip2long($network)) + pow(2, (32 - (int)$mask)) - 1);
        return array($network, $broadcast);
    }
    
    if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
        if ($mask < 0 or $mask > 128) {
            return array(-1, "Invalid CIDR mask for IPv6");
        }
        /* the following is take from Sander Steffan at
         * https://stackoverflow.com/questions/10085266/php5-calculate-ipv6-range-from-cidr-prefix
         * because copypasta from stackoverflow is how we get things done
         */
        
        // Parse the address into a binary string
        $firstaddrbin = inet_pton($address);
        
        // Convert the binary string to a string with hexadecimal characters
        # unpack() can be replaced with bin2hex()
        # unpack() is used for symmetry with pack() below
        $firstaddrhex = reset(unpack('H*', $firstaddrbin));
        
        // Overwriting first address string to make sure notation is optimal
        $firstaddrstr = inet_ntop($firstaddrbin);
        
        // Calculate the number of 'flexible' bits
        $flexbits = 128 - $mask;
        
        // Build the hexadecimal string of the last address
        $lastaddrhex = $firstaddrhex;
        
        // We start at the end of the string (which is always 32 characters long)
        $pos = 31;
        while ($flexbits > 0) {
            // Get the character at this position
            $orig = substr($lastaddrhex, $pos, 1);
            
            // Convert it to an integer
            $origval = hexdec($orig);
            
            // OR it with (2^flexbits)-1, with flexbits limited to 4 at a time
            $newval = $origval | (pow(2, min(4, $flexbits)) - 1);
            
            // Convert it back to a hexadecimal character
            $new = dechex($newval);
            
            // And put that character back in the string
            $lastaddrhex = substr_replace($lastaddrhex, $new, $pos, 1);
            
            // We processed one nibble, move to previous position
            $flexbits -= 4;
            $pos -= 1;
        }
        
        // Convert the hexadecimal string to a binary string
        # Using pack() here
        # Newer PHP version can use hex2bin()
        $lastaddrbin = pack('H*', $lastaddrhex);
        
        // And create an IPv6 address from the binary string
        $lastaddrstr = inet_ntop($lastaddrbin);
        return array($firstaddrstr, $lastaddrstr);
    }
}

?>
