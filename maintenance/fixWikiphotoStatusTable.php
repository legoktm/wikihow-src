#!/usr/local/bin/php
<?php
// Written by Gershon Bialer
// This script load a list of URLs into the wikiphot_article_status table, so they can be loaded into Titus. Conflicting rows in that table are deleted if they are older than 3 months.

require_once 'commandLine.inc';
if(sizeof($argv) != 2) {
	print "Syntax: \n fixWikiphotoStatusTable.php	[input_file]";
	exit;
}
$warning = "Manually added for Titus. No Fred action";
$f=fopen($argv[1],"r");

$urls = array();
while($row=fgets($f)) {
	$urls[] = chop($row);
}
$pages = Misc::getPagesFromURLs($urls, array('page_id','page_title'));
$dbw = wfGetDB(DB_MASTER);
$ts = time();
$lastDate = wfTimeStampNow(TW_MW, $ts - 60*60*24*30*3 );
$ids = array();
foreach($pages as $url=>$page) {
	$ids[] = $page['page_id'];	
}
$query = "DELETE FROM wikiphoto_article_status WHERE article_id in (" . implode(",",$ids) . ") AND processed < " . $dbw->addQuotes($lastDate);
$dbw->query($query);

$query = "insert ignore into wikiphoto_article_status(article_id,creator,processed,reviewed,retry,needs_retry,error,warning,url,images,replaced, steps) values ";
$first = true;
foreach($pages as $url=>$page) {
	if($page != NULL) {
		if(!$first) {
			$query .= ",";	
		}
		else {
			$first = false;	
		}
		$query .= "(" . $dbw->addQuotes($page['page_id']) . "," . $dbw->addQuotes("wikiphoto") . "," . $dbw->addQuotes("20120601") . ",0,0,0," . $dbw->addQuotes("") . "," . $dbw->addQuotes("Manually added for Titus. No Fred action") . "," . $dbw->addQuotes($url) . ",0,0,0)\n";
	}
}
$dbw->query($query);
