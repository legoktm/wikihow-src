<?php

/********
 * 
 * This script grabs all articles that meet the following conditions:
 * 1) Has less than 10,000 views
 * 2) Has {{accuracy}} template OR is included in Special:AccuracyPatrol
 * 
 * Then it adds {{Accuracy-bot}} template to the article.
 * 
 ********/

require_once("commandLine.inc");

//first get a list of articles that have
//the {{Accuracy}} template on them.

$dbr = wfGetDB(DB_SLAVE);

$wgUser = User::newFromName("Miscbot");

$accuracyTemplate = "{{Accuracy-bot}}";

if($argv[0] == "update") {
	//this section hasn't been fully tested as we
	//didn't end up using it.
	$articles = array();
	
	$res = $dbr->select('templatelinks', 'tl_from', array('tl_title' => 'Accuracy-bot'), __FUNCTION__, array("LIMIT" => 1));
	echo $dbr->lastQuery();
	while($row = $dbr->fetchObject($res)) {
		$articles[] = $row->tl_from;
	}
	
	foreach($articles as $articleId) {
		//check to see if it has the {{Accuracy}} template on it
		$title = Title::newFromID($articleId);
		if($title) {
			$article = new Article($title);
			$revision = Revision::newFromTitle($title);
			echo "Checking " . $title->getText() . "\n";
			if($revision) {
				$text = $revision->getText();
				$count = 0;
				$newText = preg_replace("@{{accuracy\|[^}]*}}@i", "", $text, -1, $count);
				
				echo "Found " . $count . "\n";
				//The community tempalte should be put in
				if($count > 0) {
					$newText = preg_replace("@{{accuracy-bot}}@i", "{{accuracy-bot|community}}", $newText);
					$article->doEdit($newText, "Fixing Accuracy-bot template");
					echo "Fixed " . $title->getFullURL() . "\n";
					continue;
				}
				
				$res = $dbr->select("rating_low", "*", array("rl_page" => $articleId), __FUNCTION__);
				while($row = $dbr->fetchObject($res)) {
					$percent = $row->rl_avg*100;
					$template = "{{accuracy-bot|patrol|{$percent}|{$row->rl_count}}}";
					$newText = preg_replace("@{{Accuracy-bot}}@i", $template, $newText);
					$articleDo->edit($newText, "Fixing Accuracy-bot template");
					echo "Fixed " . $title->getFullURL() . "\n";
					continue;
				}
				
			}
		}
	}
	
	return;
}
elseif ($argv[0] == "remove") {
	$articles = array();
	
	$res = $dbr->select('templatelinks', 'tl_from', array('tl_title' => 'Accuracy-bot'), __FILE__);
	echo $dbr->lastQuery() . "\n";
	while($row = $dbr->fetchObject($res)) {
		$articles[] = $row->tl_from;
	}
	
	echo "Getting ready to remove template from " . count($articles) . " articles\n";
	foreach($articles as $articleId) {
		//check to see if it has the {{Accuracy-bot}} template on it
		$title = Title::newFromID($articleId);
		if($title) {
			$article = new Article($title);
			$revision = Revision::newFromTitle($title);
			if($revision) {
				$text = $revision->getText();
				$count = 0;
				$newText = str_ireplace("{{Accuracy-bot}}", "", $text);
				
				
				$article->doEdit($newText, "Removing Accuracy-bot template");
				echo "Fixed " . $title->getFullURL() . "\n";
				continue;
			}
		}
	}
	
	return;
}

echo "Starting first query at " . microtime(true) . "\n";

$res = $dbr->select(array('page', 'templatelinks'), array('page_counter', 'page_id'), array('tl_from = page_id', 'tl_title' => "Accuracy", "page_namespace" => "0"), __METHOD__);

echo "Finished last query at " . microtime(true) . "\n";

$articles = array();
while($row = $dbr->fetchObject($res)) {
	if($row->page_counter < 10000)
		$articles[$row->page_id] = $row->page_id;
}

echo "Starting second query at " . microtime(true) . "\n";

$res = $dbr->select(array('page', 'rating_low'), array('page_counter', 'page_id'), array('rl_page = page_id', 'page_namespace' => 0));

echo "Finished second query at " . microtime(true) . "\n";

while($row = $dbr->fetchObject($res)) {
	if($row->page_counter < 10000)
		$articles[$row->page_id] = $row->page_id;
}

echo "Getting ready to add template to " . count($articles) . " articles\n";
echo "\n\n";

foreach($articles as $id) {
	$title = Title::newFromID($id);
	if($title){
		$revision = Revision::newFromTitle($title);
		$article = new Article($title);
		$text = $revision->getText();
		$text = "{{Accuracy-bot}} " . $text;
		$article->doEdit($text, "Marking article with Accuracy-bot template");
		
		echo "Added template to " . $title->getFullURL() . "\n";
	}
}