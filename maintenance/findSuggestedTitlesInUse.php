<?php

require_once( 'commandLine.inc' );

$dbw = wfGetDB(DB_SLAVE); 

$titles = array();

$res = $dbw->select('suggested_titles',
	array('st_id', 'st_key'),
	'',
	__METHOD__);

while ( $row = $res->fetchObject() ) {
	$titles[$row->st_id] = $row->st_key;
}

$res->free();

echo "checking the title search keys\n";
$check = 0;
foreach ($titles as $id=>$k) {
	$count = $dbw->selectField('title_search_key',
		array('count(*)'),
		array('tsk_key' => $k),
		__METHOD__);
	if ($count > 0) {
		echo "found $k\n";
		$dbw->update('suggested_titles',
			array('st_used'=>1),
			array ('st_id' => $id),
			__METHOD__);
	}
	$check++;
	if ($check % 100 == 0) echo "looking good at $check\n";
}
