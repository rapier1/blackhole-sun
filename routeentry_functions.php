<?php

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

function buildTableJS ($type) {
    if ($type == "noedit") {
	$container = "pagerNoEdit";
	$tableid = "bhTableNoEdit";
	$owner_list = "";
    } else {
	$container = "pager";
	$tableid = "bhTable";
	$owner_list = getOwnerData();
    }

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
		       [10, 'bh_index'],
		       [8, 'bh_owner_id','" . $owner_list . "'],
                       [9, 'bh_comment', 'textarea']]
        }
    });\n";

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
    $query = "SELECT bh_client_id, bh_client_name
              FROM bh_clients";
    $sth = $dbh->prepare($query);
    $sth->execute();
    $i=0;
    while ($row = $sth->fetch()) {
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
    print buildTableJS("edit");
    foreach ($table_data as $row) {
	if ($row['editable'] != 1 && $_SESSION['bh_user_role'] == 1) {
	    $noeditflag = 1;
	    continue;
	}
	$editable_rows++;
	$remaining = findRemainingTime($row['bh_starttime'], $row['bh_lifespan']);
        $active = "no";
        if ($row['bh_active'] == 1) {
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
        print "\t<td id='bh_client' name='bh_client'>" . $row['bh_client_name'] . "</td>\n";
	print "\t<td id='bh_owner_id' name ='bh_owner_id'>" . $row['bh_owner_name'] . "</td>\n";
	print "\t<td id='bh_comment' name ='bh_comment'>" . $row['bh_comment'] . "</td>\n";
        /* we hide this cell - we need to be able to pass along the index value but we
         * don't want them to edit it. This works without modifying the tabledit js code.
         * NB: Someone can still change this value by modifying the value in the console
         */
        print "\t<td style='display:none' id='bh_index' name='bh_index'>" . $row['bh_index'] . "</td>\n"; 
        print "</tr>\n";
    }
    if ($editable_rows == 0) {
	print "<tr><td align=center colspan=9>No Editable Routes Available</td></tr>";
    }
    print "</tbody>\n</table>\n";
    
    if ($_SESSION['bh_user_role'] == 1 && $noeditflag == 1) {
	/* print a table of non-editable rows  */
	print "<hr>";
	print buildTableJS("noedit");
	foreach ($table_data as $row) {
	    if ($row['editable'] == 1) {
		continue;
	    }
	    $remaining = findRemainingTime($row['bh_starttime'], $row['bh_lifespan']);
            $active = "no";
            if ($row['bh_active'] == 1) {
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
            print "\t<td>" . $row['bh_client_name'] . "</td>\n";
	    print "\t<td>" . $row['bh_owner_name'] . "</td>\n";
	    print "\t<td>" . $row['bh_comment'] . "</td>\n";
            print "</tr>\n";
	}
	print "</tbody>\n</table>\n";
    }
}

function findRemainingTime ($start, $life) {
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

/* we need to build an option list of customers using the bh_clients table in the 
 * bhs database
 * inputs: optional user affiliation - customer id number
 * return: html option list or error
 */

function buildCustomerList ($affiliation) {
    $dbh = getDatabaseHandle();
    $query = "SELECT bh_client_id,
                     bh_client_name
              FROM bh_clients";
    try{
        $sth = $dbh->prepare($query);
        $sth->execute();
        $result = $sth->fetchall(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        // TODO need beter exception message passing here
        $error = "Something went wrong while interacting with the database: "
	       . $e->getMessage();
        return array(-1, $error);
    }
    if (! isset($result)) {
        // The DB didn't return a result or there was an error
        $error = "No results were returned. This shouldn't happen unless you haven't created any customers after install.";
        return array(-1, $error);    
    }
    $list = "<select name='bh_owner_id' class='form-control' required>\n";
    $list .= "<option value=''>---</option>\n";
    foreach ($result as $line) {
        $selected = "";
        if ($affiliation == $line['bh_client_id']) {
	    $selected = "SELECTED";
        }
        $list .= "<option $selected value='" . $line['bh_client_id'] . "'>" . $line['bh_client_name'] . "</option>\n";
    }
    $list .= "</select>";
    return array(0, $list);
}

function getClientNameFromID($clientid) {
    $dbh = getDatabaseHandle();
    $query = "SELECT bh_client_name
              FROM bh_clients
              WHERE bh_client_id = :clientid";
    try{
        $sth = $dbh->prepare($query);
        $sth->bindParam(':clientid', $clientid, PDO::PARAM_STR);
        $sth->execute();
        $result = $sth->fetchColumn();
    } catch(PDOException $e) {
        // TODO need beter exception message passing here
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

function printFooter () {
    exit;
}
?>
