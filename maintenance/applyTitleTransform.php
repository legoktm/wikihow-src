<?php
//
// Run the new title case fixing algorithm on a file
//

require_once "/home/reuben/prod/maintenance/commandLine.inc";
require_once "$IP/extensions/wikihow/EditPageWrapper.php";

$lines = file("top10k_clean");
$outfp = fopen('top10k_cased.csv', 'w');
foreach ($lines as $line) {
	$title = trim($line);
	if ($title) {
		$newTitle = EditPageWrapper::formatTitle($title);
		$fields = array('How to ' . $newTitle, 'how to ' . $title);
		fputcsv($outfp, $fields);
	}
}
fclose($outfp);

