<?php
// Fix the main namespace count in Hydra cohort users table.

include_once( "commandLine.inc" );

$sql = "select hcu_user from hydra_cohort_user";
$dbr = wfGetDB(DB_SLAVE);
$res = $dbr->query($sql, __METHOD__);

$ids = array();
foreach($res as $row) {
	$ids[] = $row->hcu_user;
}

$sql = "select rev_user, count(*) as ct from revision r join page on rev_page=page_id where page_namespace=0 and rev_user in (" . implode(',', $ids) . ") group by rev_user";
$res = $dbr->query($sql, __METHOD__);
$edits = array();
foreach($res as $row) {
	$edits[$row->rev_user] = $row->ct;
}
foreach($edits as $user => $ct) {
	$dbw = wfGetDB(DB_MASTER);
	$dbw->update("hydra_cohort_user", array("hcu_main_edits" => $ct),array("hcu_user" => $user));
	$dbw->query($sql, __METHOD__);
}
