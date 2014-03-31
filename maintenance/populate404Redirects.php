<?php
/*
 * Initially process all titles to get their folded redirect strings
 */

require_once 'commandLine.inc';
require_once 'EditPageWrapper.php';

function addAll404Redirects() {
	$rows = DatabaseHelper::batchSelect('page',
		array('page_title', 'page_id'),
		array('page_namespace' => NS_MAIN, 'page_is_redirect' => 0),
		__METHOD__);

	foreach ($rows as $row) {
		$title = Title::newFromDBkey($row->page_title);
		if ($title) {
			Misc::modify404Redirect($row->page_id, $title);
		}
	}
}

addAll404Redirects();

