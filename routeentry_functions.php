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

/* formats the list of blackhole routes 
 * request: display all routes or just active routes
 */
function formatList($request) {
    $table_data = json_decode($request, true);
    
    // the ajax can't use the client process
    // so we need to update the database in a different function
    // in this case editdb.php. I dislike breaking up the
    // database access functions like this but if I want to
    // use the table edit function I need to go this route. 
    print "<!-- Pick a theme, load the plugin & initialize plugin -->
    <script type='text/javascript'>
    $(document).ready(function(){
  pagerOptions = {
    // target the pager markup - see the HTML block below
    container: $(\".pager\"),
    // output string - default is '{page}/{totalPages}';
    // possible variables: {size}, {page}, {totalPages}, {filteredPages}, {startRow}, {endRow}, {filteredRows} and {totalRows}
    // also {page:input} & {startRow:input} will add a modifiable input in place of the value
    output: '{startRow} - {endRow} / {filteredRows} ({totalRows})',
    // if true, the table will remain the same height no matter how many records are displayed. The space is made up by an empty
    // table row set to a height to compensate; default is false
    fixedHeight: true,
    // remove rows from the table to speed up the sort of large tables.
    // setting this to false, only hides the non-visible rows; needed if you plan to add/remove rows with the pager enabled.
    removeRows: false,
    // go to page selector - select dropdown that sets the current page
    cssGoto: '.gotoPage'
  };

	$('#bhTable').tablesorter({
	    widgets        : ['zebra', 'columns', 'pager'],
	    usNumberFormat : false,
	    sortReset      : true,
	    sortRestart    : true
	}).tablesorterPager(pagerOptions);

	$('#bhTable').Tabledit({
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
			   [8, 'bh_index']]
	    }
	});
    });
    </script>
    <hr>
	<div class='pager'>
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
	</div>
    <table id='bhTable' class='display' width='100%' align='center' cellspacing='0'>
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
    <th>Customer</th>
    </tr>
    </thead>
    <tbody>";

    foreach ($table_data as $row) {
	$remaining = findRemainingTime($row[bh_starttime], $row[bh_lifespan]);
	$active = "no";
	if ($row[bh_active] == 1) {
	    $active = "yes";
	}
	print "\n<tr>\n";
	print "\t<td id='identifier' name='identifier'>" . $row[bh_index] . "</td>\n";
	print "\t<td id='bh_active' name='bh_active'>" . $active . "</td>\n";
	print "\t<td id='bh_route' name='bh_route'>" . $row[bh_route] . "</td>\n";
	print "\t<td id='bh_lifespan' name='bh_lifespan'>" . $row[bh_lifespan] . "</td>\n";
	print "\t<td>" . $row[bh_starttime] . "</td>\n";
	print "\t<td>" . $remaining . "</td>\n";
	print "\t<td>" . $row[bh_requestor] . "</td>\n";
	print "\t<td>" . $row[bh_client_name] . "</td>\n";
	/* we hide this cell - we need to be able to pass along the index value but we
	 * don't want them to edit it. This works without modifying the tabledit js code.
         * NB: Someone can still change this value by modifying the value in the console
	 */
	print "<td style='display:none' id='bh_index' name='bh_index'>" . $row[bh_index] . "</td>\n"; 
	print "</tr>\n";
    }
    print "</tbody>\n</table>\n";
}

function findRemainingTime ($start, $life) {
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
