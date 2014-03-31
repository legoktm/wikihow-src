<?php
//
// Delete all pages in the Kaltura and Kaltura_talk namespaces

require_once('commandLine.inc');

global $IP, $wgUser;
require_once("$IP/extensions/wikihow/DatabaseHelper.class.php");

$wgUser = User::newFromName("MiscBot");

$maxAge = 60*60*24*31*6; //6 months measured in seconds

$dbr = wfGetDB(DB_SLAVE);

echo "Pulling Kaltura pages on " . date("F j, Y") . "\n";

$res = $dbr->select('page', array('page_id', 'page_title'), array('page_namespace' => array(KALTURA_NAMESPACE_ID, KALTURA_DISCUSSION_NAMESPACE_ID)), __FILE__);

$articles = array();
foreach ($res as $row) {
	$articles[] = $row;
}

echo "About to check " . count($articles) . " pages\n";

foreach($articles as $article) {
	deletePage($article, "Delete old unused Kaltura ad page");
}

echo "Finished. Deleted pages.\n";

/**
 *
 * @param $article - object with the following fields (page_id and page_title)
 * @param $reason - reason for the deletion 
 */
function deletePage($article, $reason) {
	$title = Title::newFromID($article->page_id);
	if($title) {
		$article = new Article($title);
		if($article) {
			echo $title->getFullURL() . "\n";
			$article->doDelete($reason);
		}
	}
}



