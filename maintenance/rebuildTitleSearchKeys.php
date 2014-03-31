<?php
//
// Clear out and rebuild the title_search_key table. The title_search_key 
// table is used for searching wikiHow titles. It is used to check existing 
// titles in the CreatePage and TitleSearch special pages. 
//

require_once('commandLine.inc');

function newSearchKeyRow($dbr, $row) {
	$t = Title::newFromDBKey($row->page_title);
	if (!$t) {
		print "Got null title for {$row->page_title}\n";
		return null;
	}
	$search_key = generateSearchKey($t->getText());
	$featured = intval($row->tl_from != null);
	return array(
		'tsk_title' => $row->page_title,
		'tsk_namespace' => NS_MAIN,
		'tsk_key' => $search_key,
		'tsk_wasfeatured' => $featured,
	);
}

function main() {
	$PAGE_SIZE = 2000;
	$SLEEP_SECS = 2;

	$dbr = wfGetDB(DB_SLAVE);
	$dbw = wfGetDB(DB_MASTER);

	# GET DATA WITH WHICH TO REPOPULATE -- BATCH SELECTS
	print "Pulling list of all articles...\n";
	$rows = array();
	$page = 0;
	while (true) {
		$offset = $page * $PAGE_SIZE;
		$res = $dbr->query("SELECT p1.page_title, tl_from
			FROM page p1 
			LEFT JOIN page p2 ON p1.page_title = p2.page_title
				AND p2.page_namespace = 1
			LEFT JOIN templatelinks ON p2.page_id = tl_from
				AND tl_namespace = 10
				AND tl_title = 'Featured'
			WHERE p1.page_namespace = 0
				AND p1.page_is_redirect = 0
			ORDER BY p1.page_id DESC
			LIMIT $offset,$PAGE_SIZE",
			__METHOD__);

		$added = false;
		foreach ($res as $row) {
			$added = true;
			$rows[] = $row;
		}
		$res->free();

		if (!$added) break;

		$page++;
	}

	$count = count($rows);
	print "Found $count main namespace articles\n";

	# CLEAR OUT TABLE
	$dbw->query("DELETE FROM title_search_key", __METHOD__);

	# REPOPULATE TABLE -- BATCH INSERTS
	print "Re-populating search keys table...\n";
	$newRows = array();
	foreach ($rows as $i => $row) {
		$newRow = newSearchKeyRow($dbr, $row);
		if ($newRow) {
			$newRows[] = $newRow;
		}
		if ($i > 0 && $i % $PAGE_SIZE == 0 && count($newRows)) {
			$dbw->insert('title_search_key', $newRows, __METHOD__, array('IGNORE'));
			$newRows = array();
			sleep($SLEEP_SECS);
		}
	}
	$dbw->insert('title_search_key', $newRows, __METHOD__, array('IGNORE'));

	if ($count) {
		print "Done\n";
	}
}

main();

