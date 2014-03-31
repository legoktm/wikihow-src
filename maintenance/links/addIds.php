<?php
/* 
 *
 * Script that aggregates all external links from a text file created by
 * echo 'select el_from, el_to from externallinks' |mysql wikidb_112 > externallinks.txt
 *
 */

global $IP, $wgTitle;
require_once('../commandLine.inc');

$datasource = $argv[0];
$fi = fopen($datasource, 'r');

$domainsString = "\n";

$lines = array();
while ( !feof( $fi ) ) { 
	$fcontent = fgets($fi);
	$tcontent = trim($fcontent);
	$data = split("\t", $tcontent);
	$t = Title::newFromId($data[0]);
	if ($t && $t->exists()) {
		$data[] = $t->getFullUrl();
	}
	$lines[] = $data;
}

fclose($fi);

$fo = fopen($datasource , 'w');

foreach ($lines as $line) {
	fwrite($fo, implode("\t", $line) . "\n");
}

