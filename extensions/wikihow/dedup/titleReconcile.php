<?php

class TitleReconcile
{
	public static function reconcile() {
		global $wgLanguageCode;

		// Add titles missing from our system with associated keywords
		$dbr = wfGetDB(DB_SLAVE);
		$sql = "select page.* from page left join dedup.title_query on tq_page_id=page_id AND tq_lang=" . $dbr->addQuotes($wgLanguageCode) . " where page_namespace=0 and page_is_redirect=0 and tq_title is NULL group by page.page_id";
		$res = $dbr->query($sql, __METHOD__);
		$missingTitles = array();
		foreach($res as $row) {
			$missingTitles[] = $row;
		}
		foreach($missingTitles as $title) {
			print("Adding title to system " . $title->page_title . "\n");
			$t = Title::newFromRow($title);
			DedupQuery::addTitle($t, $wgLanguageCode);	
		}

		//Deal with titles turned into deletes or redirects
		$dbr = wfGetDB(DB_SLAVE);
		$sql = "select tq_title from dedup.title_query left join page on tq_page_id=page_id where page_namespace=0 and page_is_redirect=0 and page_title is NULL";
		$res = $dbr->query($sql, __METHOD__);
		$deletedTitles = array();
		foreach($res as $row) {
			$deletedTitles[] = $row->page_title;	
		}
		foreach($deletedTitles as $title) {
			print("Removing title from system " . $row->page_title . "\n");
			DedupQuery::removeTitle($row->page_title,$wgLanguageCode);	
		}
	}
}
