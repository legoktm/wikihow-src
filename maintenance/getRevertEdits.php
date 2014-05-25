<?php
require_once('commandLine.inc');
global $IP;
require_once("$IP/extensions/wikihow/dedup/SuccessfulEdit.class.php");

if(sizeof($argv) != 1) {
	print("getRevertEdits [articleIdFile]\n");
	exit;
}
$f = fopen($argv[0],"r");
if(!$f) {
	print("Unable to open file\n");
	exit;
}

print "Article Id\tUsername\tBytes Added\tRevision\tGood Revision\n";
while(!feof($f)) {
	$l = fgets($f);
	$l = rtrim($l);
	$articleId = intval($l);
	if($articleId != 0) {
		$se = SuccessfulEdit::getEdits($articleId);

		foreach($se as $e) {
			print $articleId . "\t" . $e['username'] . "\t" . $e['added'] . "\t" . $e['rev'] . "\t" . $e["gr"] . "\n";
		}
	}
}
