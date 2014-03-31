<?

	require_once("commandLine.inc");

	$dbr = wfGetDB(DB_SLAVE);

	$ts = substr(wfTimestamp(TS_MW), 0, 6) . "01000000";

	for ($i = 0; $i < 12; $i++) {	
		$uts = wfTimestamp(TS_UNIX, $ts);
		$ts2= substr(wfTimestamp(TS_MW, $ts - 10*24*3600), 0, 6) . "01000000";
		#echo "would calculate between $ts2 and $ts\n";	

		$sql = "SELECT rev_user, count(*) as C from revision left join page on rev_page=page_id where page_namespace=0 and rev_user != 0 and rev_timestamp <= '$ts' and rev_timestamp >= '$ts2' group by rev_user having C >= 5;";
		#echo $sql; exit;
		$res = $dbr->query($sql);
		echo "For the month of " . substr($ts2, 0, 6) . " - " . $dbr->numRows($res) . "\n";
		$ts = $ts2;
	}
