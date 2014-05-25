// gRollbackurl and gPostRollbackCallback are defined outside of this Javascript snippet
var previousHTML = 'none';

function cancelRollback() {
	$('#rollback-link').html(previousHTML);
}

function rollback() {
	var span = $('#rollback-link');
	if (!span.length) span = $('#rollback-status');
	span.html('<b>' + msg_rollback_inprogress + '</b>');

	$.get(gRollbackurl, function(response) {
		var span = $('#rollback-link');
		if (!span.length) {
			$('#rollback-status').html(response);
			if (typeof gPostRollbackCallback == 'function') gPostRollbackCallback();
			return false; 
		} else {
			if (response.indexOf("<title>Rollback failed") > 0) {
				var msg = '<br/><div style="background: red;"><b>' + msg_rollback_fail + '</b></div>';
			} else {
				var msg = '<br/><div style="background: yellow;"><b>' + msg_rollback_complete + '</b></div>';
			}
			span.html(msg);
			if (typeof gPostRollbackCallback == 'function') gPostRollbackCallback();
		}
	});
	window.oTrackUserAction();

	return false;
}
