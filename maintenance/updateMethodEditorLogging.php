<?php

/****
 * Script that handles the special logging table used for method editor logging.
 * We only log "keeps" and only keep 35 days. This script can be used to populate
 * the table initially and the other will be run from the cron to get rid of unused
 * rows in the table.
 */

require_once('commandLine.inc');

$action = $argv[0];

if($action == "") {
	echo "You need to indicate what you want to do (init/cull)\n";
	exit(0);
}

$dbw = wfGetDB(DB_MASTER);

switch($action) {
	case "cull":
		$expired = wfTimestamp(TS_MW, strtotime("-35 day", strtotime(date('Ymd', time()))));
		$res = $dbw->delete(MethodEditor::LOGGING_TABLE_NAME, array("mel_timestamp <= {$expired}"), __FILE__);
		break;
	case "init":
		$dbw->delete(MethodEditor::LOGGING_TABLE_NAME, '*', __FILE__);
		$edits = DatabaseHelper::batchSelect('logging', array('log_timestamp', 'log_user'), array('log_type' => 'methedit'), __FILE__);
		foreach($edits as $edit) {
			$dbw->insert(MethodEditor::LOGGING_TABLE_NAME, array('mel_timestamp' => $edit->log_timestamp, 'mel_user' => $edit->log_user));
		}
		break;
}