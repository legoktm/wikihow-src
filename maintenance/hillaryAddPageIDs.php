<?php
//
// Populate the hillary_pages table for the Hillary / Stubs feature
//

require_once('commandLine.inc');

$fp = fopen('/home/reuben/hill_1-2.txt', 'r');
if (!$fp) die("File reading problem!\n");

$ids = array();
while (($data = fgetcsv($fp)) !== false) {
	$id = intval($data[0]);
	if ($id) $ids[] = $id;
}

#$db = wfGetDB(DB_MASTER);
#$db->query('DELETE FROM hillary_pages', __FILE__);
#$db->query('DELETE FROM hillary_votes', __FILE__);
#$db->query('DELETE FROM hillary_votes_archive', __FILE__);

$added = Hillary::populatePages($ids);
print "Added $added articles to Hillary\n";

