<?
	require_once('commandLine.inc');
	$dbr = wfGetDB(DB_SLAVE); 
	$old = wfTimestamp(TS_MW, time() - 7200); // last 2 hours
	$new = wfTimestampNow();
	$res = $dbr->select('ipblocks', array('ipb_user'), 
		array('ipb_user != 0', "ipb_expiry >= '{$old}'", "ipb_expiry <= '{$new}'"));
	while ($row = $dbr->fetchObject($res)) {
		Vanilla::setUserRole($row->ipb_user, 8);
	}
