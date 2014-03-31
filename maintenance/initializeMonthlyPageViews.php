<?php 

/******
 * 
 * One-time script used to pre-populate the 30-day pageview field
 * in the page table. After running this script, "updateMonthlyPageViews.php"
 * will grab this field, subtract the value for 30 days ago (gotten out
 * of the titus_historical table) and add on the new value (gotten out
 * of the titus table)
 *
 * The whole script took about 6 minutes on June 13, 2012 -Reuben.
 * 
 ******/

require_once('commandLine.inc');
require_once("$IP/extensions/wikihow/DatabaseHelper.class.php");

$dbr = wfGetDB(DB_SLAVE);
$dbw = wfGetDB(DB_MASTER);

$startTime = microtime(true);

define(BATCHSIZE, 1000);

// first grab all the pages -- this first section takes about 1 minute as
// of June 13 2012 (Reuben).
$articles = DatabaseHelper::batchSelect('page', 
	array('page_id', 'page_title'), 
	array('page_namespace' => NS_MAIN, 'page_is_redirect' => 0),
	__FILE__);

// now we need to recalculate the pv data
$start = time();
$now = wfTimestamp(TS_MW);
$monthAgo = substr(wfTimestamp(TS_MW, $start - 60 * 60 * 24 * 30), 0, 8); // 30 days

print "Starting from " . $monthAgo . " and processing " . count($articles) . " in approx " . ceil(count($articles) / BATCHSIZE) . " batches\n";

$rows = array();
$nulls = 0;
foreach ($articles as $articleData) {
	$total = 0;
	$row = $dbr->selectRow('titus_historical',
		array('ti_datestamp', 'SUM(ti_daily_views) as ti_sum'),
		array('ti_page_id' => $articleData->page_id, "ti_datestamp > {$monthAgo}"),
		__FILE__);
		
	$field = $dbr->selectField('titus', 'ti_daily_views', array('ti_page_id' => $articleData->page_id), __FILE__);

	if ($row) {
		$total += intval($row->ti_sum);
		if($field !== false)
			$total += intval($field);
		$rows[] = array( $articleData->page_id, $total, $now );
	} else {
		$nulls++;
	}
}
print "Found $nulls null rows in titus_historical table\n";

$sqlStart = "INSERT INTO pageview (pv_page, pv_30day, pv_timestamp) VALUES ";
$sqlEnd = " ON DUPLICATE KEY UPDATE pv_30day = VALUES(pv_30day), pv_timestamp = VALUES(pv_timestamp)";
$num_batches = ceil(count($rows) / BATCHSIZE);
for ($page = 0; $page < $num_batches; $page++) {
	$slice = array_slice($rows, BATCHSIZE * $page, BATCHSIZE);
	$middle = array();
	foreach ($slice as $row) {
		$middle[] = '(' . $dbw->makeList($row) . ')';
	}
	$sql = $sqlStart . join(",", $middle) . $sqlEnd;
	$success = $dbw->query($sql, __FILE__);
	
	if ($success) {
		print "Updated batch #$page\n";
	} else {
		print "Unable to update batch #$page\n";
	}
	
	// sleep for 0.5s
	usleep(500000);
}

$endTime = microtime(true);
print "Finished " . __FILE__ . " in " . round($endTime - $startTime) . "s\n";

