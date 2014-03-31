<?php
/**
 * List all articles within a top level category (which includes its sub-
 * categories).
 *
 * Usage: php listAllCategoryArticles.php Category-Name
 */

require_once('commandLine.inc');

if (count($argv) < 1) {
	print "usage: php listAllCategoryArticles.php <category-name-encoded>\n";
	print "  example of category name: Hobbies-and-Crafts\n";
	exit;
}

$dbr = wfGetDB(DB_SLAVE);

$topLevel = $argv[0];
$file = $topLevel . '.csv';

// get the category and all sub-categories
$cats = WikiPhoto::getAllSubcats($dbr, $topLevel);
$cats[] = $topLevel;
sort($cats);
$cats = array_unique($cats);

// get all pages
$pages = array();
foreach ($cats as $cat) {
	$results = WikiPhoto::getPages($dbr, $cat);
	// make results unique based on page_id
	foreach ($results as $result) {
		print WikiPhoto::BASE_URL . $result['key'] . "\n";
	}
}

