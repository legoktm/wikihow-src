<?php

/*********************
 * 
 * Script that outputs a list of articles that have:
 * 1) HAVE no intro image
 * 2) HAVE an image in the steps section
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
$res = $dbr->select('page', 'page_id', array('page_namespace' => 0, 'page_is_redirect' => 0), __FILE__);
while($row = $dbr->fetchObject($res)) {
	$titles[$row->page_id] = array();
}
$dbr->freeResult($res);

echo "Done grabbing all titles from db at " . microtime(true) . "\n";

$articles = array();
$count = 0;

//first check to see if there are more than 3 steps in the photo
foreach($titles as $id => $info) {
	$title = Title::newFromID($id);
	$revision = Revision::newFromTitle($title);
	
	$intro = Wikitext::getIntro($revision->getText());
	$hasIntroImage = preg_match('@\[\[Image:([^\]|]*)(\|[^\]]*)?\]\]@s', $intro);
	
	if(!$hasIntroImage){
		$section = Wikitext::getStepsSection($revision->getText(), true);
		$num_step_photos = preg_match_all('@\[\[Image:([^\]|]*)(\|[^\]]*)?\]\]@s', $section[0], $matches);

		if($num_step_photos > 0){
			$articles[] = $id;
		}
	}
	$count++;
	if($count % 1000 == 0)
		echo "Done processing " . $count . " artciles\n";
	
}

echo "Done processing all titles. Left with " . count($articles) . " titles. At " . microtime(true) . "\n";

$fo = fopen($argv[0], 'w');

fwrite($fo, "<html><head></head><body>");

//now that we have all the data, spit out the info
foreach($articles as $id) {
	$title = Title::newFromID($id);
	
	fwrite($fo, "<a href='". $title->getFullUrl() ."' target='_blank'>" . $title->getFullUrl() . "</a><br />");
}

fwrite($fo, "</body></html>");

fclose($fo);
	
echo "Finished script at " . microtime(true) . "\n";	
