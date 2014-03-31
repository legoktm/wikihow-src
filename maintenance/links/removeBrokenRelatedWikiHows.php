<?php

/**
 * 
 * This tool scrolls through all articles and checks their
 * related wikiHows for broken internal links, and removes
 * them. If all related wikiHows are removed, the entire
 * section is removed from the article.
 * 
 */

global $IP, $wgTitle, $wgUser;
require_once('../commandLine.inc');

$dbr = wfGetDB(DB_SLAVE);

$res = $dbr->select('page', '*', array('page_namespace' => 0, 'page_is_redirect' => 0), __FUNCTION__, array("LIMIT" => 20));

$ids = array();

while($row = $dbr->fetchObject($res)) {
	$ids[] = $row->page_id;
}

$wgUser = User::newFromName("Broken-Internal-Link-Removal");
foreach($ids as $id) {
	$title = Title::newFromID($id);
	if($title) {
		$article = new Article($title);
		
		$wikiHow = new WikihowArticleEditor();
		$wikiHow->loadFromArticle($article);
		$relatedArticles = $wikiHow->getSection(wfMsg('Relatedwikihows'));
		$relatedId = $wikiHow->getSectionNumber(wfMsg('Relatedwikihows'));
		if($relatedArticles != "") {
			$changed = false;
			
			/**
			 * The Article::getSection returns more than the 
			 * WikihowArticleEditor::getSection. So need to grab the difference
			 * before processing so we can add it back later.
			 */
			$revision = Revision::newFromTitle($title);
			$fullRelated = $article->getSection($revision->getText(), $relatedId);
			
			$loc = stripos($fullRelated, $relatedArticles);
			$remainderRelated = substr($fullRelated, $loc + strlen($relatedArticles));
			if($remainderRelated === false)
				$remainderRelated = "";
			
			
			$links = explode("*", $relatedArticles);
			
			$matchesarray = array();
			foreach($links as $link) {
				if($link == "")
					continue;
				
				$linkTitle = preg_match('@\[\[(.*[^\]])\]\]@', $link, $matchesarray);
				$linkParts = explode("|", $matchesarray[1]);
				
				$relatedTitle = Title::newFromText($linkParts[0]);
				if(!$relatedTitle || !$relatedTitle->exists() ) {
					echo "Removing " . $linkParts[0] . " from " . $title->getText() . "\n";
					$changed = true;
					$relatedArticles = str_replace("*".$link, "", $relatedArticles);
				}
				
			}
			
			if($changed) {
				$relatedArticles = trim($relatedArticles);
				if($relatedArticles == ""){
					$newText = $article->replaceSection($relatedId, $relatedArticles . $remainderRelated, "Removing broken links");
				}
				else {
					$relatedArticles = "\n== " . wfMsg('relatedwikihows') . " ==\n" . $relatedArticles;
					$newText = $article->replaceSection($relatedId, $relatedArticles . $remainderRelated, "Removing broken links");
				}
				
				$article->doEdit($newText, "Removing broken links");
			}
		}
	}
}


