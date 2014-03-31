<?php
/*
 * This takes an inputted list of article ids and domains and turns them
 * into one consolidated list of domain -> array of article Ids that contain
 * that domain in the article. Each line is in the form:
 * DOMAIN \t ID \t ID \t ID \n
 *
 */

require_once('../commandLine.inc');

if($argv[0] == null){
	echo 'Must pass in the name of the file to be parsed';
	return;
}

$fi = fopen($argv[0], 'r');
echo "processing " . $argv[0] . "\n";

while ( !feof( $fi ) ) {
	$fcontent = fgets($fi);
	$tcontent = trim($fcontent);
	$data = split("\t", $tcontent);

	//grab domain without subdomain
	$urlInfo = parse_url(trim($data[1]));
	$parts = preg_split('&\.&', $urlInfo['host']);
	$total = count($parts);
	$domain = $parts[$total - 2] . '.' . $parts[$total - 1];

	$pageId = trim($data[0]);

	if($domains[$domain] == null){
		$domains[$domain] = array();
	}
	$domains[$domain][$pageId] = $pageId;
}

fclose($fi);

echo "Done parsing input file\n";

$fo = fopen($argv[1], 'w');
echo "Putting data into " . $argv[1] . "\n";

foreach ($domains as $domain => $idArray) {
	if (sizeof($idArray) > 0 ) {
		$line = $domain;
		foreach ($idArray as $id) {
			$line .= "\t" . $id;
		}
		fwrite($fo, $line . "\n");
	}
}

fclose($fo);