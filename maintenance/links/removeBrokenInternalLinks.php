<?php

/*
 * This script removes broken internal links from the
 * intro and steps sections. It ignores the following 
 * types of internal links
 * 
 * 1. [[Image:...]] -> Image links
 * 2. [[Wikipedia:...]] -> Links to Wikipedia
 * 3. [[Wiktionary:...] -> Links to Wiktionary
 * 4. [[#...]] -> anchor link to within the page
 * 5. [[http...]] -> ill formed external links
 * 
 * The script replaces the bad link with the text
 * for the link if it exists, otherwise the link title.
 * 
 */


global $IP, $wgTitle, $wgUser;
require_once('../commandLine.inc');

$dbr = wfGetDB(DB_SLAVE);

$replacements = array();

$res = $dbr->select('page', '*', array('page_namespace' => 0, 'page_is_redirect' => 0), __FUNCTION__, array("LIMIT" => 20));

$ids = array();

while($row = $dbr->fetchObject($res)) {
	$ids[] = $row->page_id;
}

$wgUser = User::newFromName("Broken-Internal-Link-Removal");
foreach($ids as $id) {
	$title = Title::newFromID($id);
	if($title) {
		$stepsChanged = false;
		$introChanged = false;
		
		$article = new Article($title);
		
		$revision = Revision::newFromTitle($title);
		$wikiText = $revision->getText();
		
		$intro = Wikitext::getIntro($wikiText, true);
		if($intro != "") {
				
			$intro = replaceBrokenLinksInSection($intro, $introChanged, $title);

			if($introChanged) {
				$wikiText = Wikitext::replaceIntro($wikiText, $intro, true);
			}
			
		}
		
		list($steps, $sectionID) = Wikitext::getStepsSection($wikiText, true);
		if($steps != "") {
			$steps = replaceBrokenLinksInSection($steps, $stepsChanged, $title);

			if($stepsChanged) {
				$wikiText = Wikitext::replaceStepsSection($wikiText, $sectionID, $steps, true);
			}
			
		}
		
		if($stepsChanged || $introChanged) {
			$article->doEdit($wikiText, "Removing broken links");
		}
				
	}
}

function replaceBrokenLinksInSection($sectionText, &$changed, &$title) {
	$matchesarray = array();
	
	$sectionText = preg_replace_callback("@<nowiki>[^<]*</nowiki>@i", 'handleNoWikiTags', $sectionText);
	
	preg_match_all('@\[\[([^\]]*)\]\]@', $sectionText, $matchesarray);
	
	foreach($matchesarray[1] as $match) {
		$linkParts = explode("|", $match);
		
		$zero = (int)0;
		if(stripos($linkParts[0], "Wikipedia:") === $zero || stripos($linkParts[0], "wiktionary:") === $zero || stripos($linkParts[0], "http") === $zero || stripos($linkParts[0], "Image:") === $zero || stripos($linkParts[0], "#") === $zero)
			continue;

		$linkTitle = Title::newFromText(rawurldecode($linkParts[0]));
		if(!$linkTitle || ($linkTitle->getNamespace() != NS_SPECIAL && !$linkTitle->exists() && !$linkTitle->isExternal()) ) {
			echo "Removing " . $linkParts[0] . " from " . $title->getText() . "\n";
			$changed = true;
			
			$replacement = "";
			if($linkTitle && stripos($linkParts[0], "Category:") === $zero)
				$replacement = ""; //If its an actual category link, don't replace with anything
			else if(count($linkParts) > 1)
				$replacement = $linkParts[1]; //there's link text so use that
			else
				$replacement = $linkParts[0]; //there isn't link text, so just use article title 
			
			if(count($linkParts) > 1)
				$sectionText = str_replace("[[".$match."]]", $replacement, $sectionText);
			else
				$sectionText = str_replace("[[".$match."]]", $replacement, $sectionText);
		}
	}
	
	$sectionText = preg_replace_callback("@<REPLACE([0-9])+>@", "replaceNoWikiTags", $sectionText);
	
	return $sectionText;
}

function handleNoWikiTags($tag){
	global $replacements;
	
	$replacements[] = $tag[0];
	return "<REPLACE" . (count($replacements) - 1) . ">";
}

function replaceNoWikiTags($tag){
	global $replacements;
	
	return $replacements[intval($tag[1])];
}