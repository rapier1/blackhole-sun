$(document).ready(function () {
    //use setInterval to call these.
    // if you want to get fancy you can load the names in to an array
    // and cycle through it but that seems like a waste of time
    // interval should be 15 seconds for the client
    // maybe 30 seconds for the interface and exabgp server
    getHeartbeat('clibeat');
    getHeartbeat('exabeat');
    getHeartbeat('bgpbeat');
    //setInterval("getHeartbeat('clibeat')", 30000);
    //setInterval("getHeartbeat('exabeat')", 30000);
    //setInterval("getHeartbeat('bgpbeat')", 30000);
    //changed my mind as it's a lot of calls just get it on page load
});

function getHeartbeat (heartbeat) {
    $.post('./heartbeat.php', {heartbeat_type: heartbeat}, function (data) {handleResult(data, status, heartbeat);});
}

function handleResult (data, status, heartbeat) {
    if (heartbeat == 'clibeat') {
	if (data == 1) {
	    $('#clibeatdot').attr('src', './icons/greendot.png');
	} else {
	    $('#clibeatdot').attr('src', './icons/reddot.png');
	}
    }
    if (heartbeat == 'exabeat') {
	if (data == 1) {
	    $('#exabeatdot').attr('src', './icons/greendot.png');
	} else {
	    $('#exabeatdot').attr('src', './icons/reddot.png');
	}
    }
    if (heartbeat == 'bgpbeat') {
	if (data == 1) {
	    $('#bgpbeatdot').attr('src', './icons/greendot.png');
	} else {
	    $('#bgpbeatdot').attr('src', './icons/reddot.png');
	}
    }
}
    
