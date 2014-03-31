<?php

/*********************
 * 
 * Script that outputs a list of articles that have:
 * 1) HAVE 3 or more images in the steps section
 * 2) NOT been edited by exception list (wikiphoto, wrm, etc)
 * 3) Images in the steps section are 50% or more from a user (rather than flickr or wikimedia)
 * 
 * Resulting script is outputted into a file in the format:
 * article url, user page url, date of last user edit (on any page), user page url, date of last user edit
 * 
 */

require_once("commandLine.inc");

if($argv[0] == null){
	echo "Must pass in the name of the file to save to\n";
	return;
}

$dbr = wfGetDB(DB_SLAVE);
$titles = array();

echo "Starting script at " . microtime(true) . "\n";

//first grab a list of all articles
$res = $dbr->select('page', 'page_id', array('page_namespace' => 0, 'page_is_redirect' => 0));
while($row = $dbr->fetchObject($res)) {
	$titles[$row->page_id] = array();
}
$dbr->freeResult($res);

echo "Done grabbing all titles from db at " . microtime(true) . "\n";

$userIds = array();
$userNames = array("Wikiphoto", "ElizabethD", "WRM", "Thomscher", "GoldenZebra", "Emazing", "ChloeChen");
foreach($userNames as $name) {
	$user = User::newFromName($name);
	$userIds[] = $user->getID();
}

//first check to see if there are more than 3 steps in the photo
foreach($titles as $id => $info) {
	$title = Title::newFromID($id);
	$revision = Revision::newFromTitle($title);
			
	$section = Wikitext::getStepsSection($revision->getText(), true);
	$num_step_photos = preg_match_all('@\[\[Image:([^\]|]*)(\|[^\]]*)?\]\]@s', $section[0], $matches);
	
	if($num_step_photos < 3){
		unset($titles[$id]);
		continue;
	}
	
	//now check to see if wikiPhoto has ever touched the article
	$numUserImages = 0;
	$numOutsideImages = 0;
	$foundWikiPhoto = false;
	for($i = 0; $i < $num_step_photos; $i++) {
		$imageFile = wfFindFile($matches[1][$i]);
		if(!$imageFile)
			continue;
			
		$imageUserId = $imageFile->getUser("id");
		if(in_array($imageUserId, $userIds)) {
			unset($titles[$id]);
			$foundWikiPhoto = true;
			break;
		}
		
		$imageTitle = Title::newFromText($matches[1][$i], NS_IMAGE);
		if(!$imageTitle)
			continue;
		$imageRevision = Revision::newFromTitle($imageTitle);
		
		if(!$imageRevision) {
			continue;
		}
		
		$revisionText = $imageRevision->getText();
		if(stripos($revisionText, "{{flickr") === false && stripos($revisionText, "{{commons") === false) {
			$titles[$id][$imageUserId] = $imageUserId;
			$numUserImages++;
		}
		else{
			$numOutsideImages++;
		}
	}
	
	if(count($titles[$id]) == 0)
		unset($titles[$id]);
	
	if(!$foundWikiPhoto) {
		if($numOutsideImages > $numUserImages) {
			unset($titles[$id]);
		}
	}
}

echo "Done processing all titles. Left with " . count($titles) . " titles. At " . microtime(true) . "\n";

$fo = fopen($argv[0], 'w');

//now that we have all the data, spit out the info
foreach($titles as $id => $info) {
	$title = Title::newFromID($id);
	
	fwrite($fo, $title->getFullUrl() . ", ");
	foreach($info as $userId) {
		$res = $dbr->select("revision", array("rev_user_text", "rev_timestamp"), array("rev_user" => $userId), __METHOD__, array("ORDER BY" => "rev_timestamp DESC", "LIMIT" => 1));
		while($row = $dbr->fetchObject($res)) {
			fwrite($fo, "http://www.wikihow.com/User:" . $row->rev_user_text . ", ");
			fwrite($fo, date("n/j/Y", wfTimestamp(TS_UNIX, $row->rev_timestamp)) . ", ");
		}
	}
	fwrite($fo, "\n");
}

fclose($fo);
	
echo "Finished script at " . microtime(true) . "\n";	