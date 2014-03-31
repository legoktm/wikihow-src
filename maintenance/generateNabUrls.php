<?php

global $IP;

require_once ("../extensions/wikihow/NewlyIndexed.class.php");
require_once("../extensions/wikihow/DatabaseHelper.class.php");
require_once('commandLine.inc');

$timeInSiteIndex = "-2 months";

$dateCutoff = wfTimestamp(TS_MW, strtotime($timeInSiteIndex));

$dbr = wfGetDB(DB_SLAVE);

//don't expect this to return a huge number of items
$articles = $dbr->select(array(NewlyIndexed::TABLE_NAME, 'page'), array(NewlyIndexed::PAGE_FIELD, 'page_touched'), array(NewlyIndexed::NAB_FIELD => 1, NewlyIndexed::INDEX_FIELD . " >=" . $dateCutoff, "page_id = " . NewlyIndexed::PAGE_FIELD, "page_is_redirect" => 0), __FILE__);

$lines = array();
foreach($articles as $article) {
	$title = Title::newFromID($article->{NewlyIndexed::PAGE_FIELD});
	
	if(!$title)
		continue;
	
	$lines[] = $title->getFullUrl() . ' lastmod=' .  iso8601_date($article->page_touched);;
}

foreach ($lines as $line) {
	print "$line\n";
}

/***
 * function copied from generateUrls.php
 */
function iso8601_date($time) {
	$date = substr($time, 0, 4)  . "-"
		  . substr($time, 4, 2)  . "-"
		  . substr($time, 6, 2)  . "T"
		  . substr($time, 8, 2)  . ":"
		  . substr($time, 10, 2) . ":"
		  . substr($time, 12, 2) . "Z" ;
	return $date;
}
