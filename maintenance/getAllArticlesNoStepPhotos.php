<?php
/**
 * Grab a bunch of random articles from a category and its subcategories, as
 * long as the articles have no images in the Steps section.
 *
 * Usage: php getAllArticlesNoStepPhotos.php Category-Name
 */

require_once('commandLine.inc');

$file = 'all-articles-no-steps-photos.csv';

$dbr = wfGetDB(DB_SLAVE);

// get all pages
$pages = WikiPhoto::getAllPages($dbr);

$lines = array();
foreach ($pages as $page) {
	if (WikiPhoto::articleBodyHasNoImages($dbr, $page['id'])) {
		$lines[] = array(WikiPhoto::BASE_URL . "{$page['key']}", $page['id']);
	}
}

$fp = fopen($file, 'w');
if ($fp) {
	foreach ($lines as $line) {
		fputcsv($fp, $line);
	}
	fclose($fp);
	print "output is in $file\n";
} else {
	print "unable to open file $file\n";
}

