<?php

global $IP;

require_once("../extensions/wikihow/DatabaseHelper.class.php");
require_once('commandLine.inc');

$dbr = wfGetDB(DB_SLAVE);

$res = $dbr->select('dv_sampledocs','dvs_doc', '', __FILE__, array('GROUP BY' => 'dvs_doc'));

$lines = array();
foreach($res as $row) {
	$url = 'http://www.wikihow.com/Sample/'.$row->dvs_doc;
	$lines[] = $url;
}

foreach ($lines as $line) {
	print "$line\n";
}