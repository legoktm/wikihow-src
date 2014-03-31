<?php
//
// A script to import the titles of a bunch of articles and to which test 
// cohort they belong.
//

require_once('commandLine.inc');

global $IP;
require_once("$IP/extensions/wikihow/TitleTests.class.php");

$filename = '/home/reuben/categories_expt_groups.csv';
$fp = fopen($filename, 'r');
if (!$fp) {
	die("error: cannot open $filename for reading\n");
}

$dbw = wfGetDB(DB_MASTER);

$i = 1;
while (($data = fgetcsv($fp)) !== false) {
	if ($i++ == 1) continue; // skip first line
	$pageid = intval($data[0]);
	$title = Title::newFromID($pageid);
	if (!$title) {
		print "bad title: $pageid\n";
		continue;
	}
	$pageid = $title->getArticleId();
	if (!$pageid) {
		print "not found: $pageid\n";
		continue;
	}
	$experiment = intval($data[2]);
	#$test = intval($data[1]) + 10;
	//print $title->getText()."\n";
	if ($experiment > 0) {
		TitleTests::dbAddRecord($dbw, $title, $experiment);
		//print $title->getText() . "\n";
	}
}

