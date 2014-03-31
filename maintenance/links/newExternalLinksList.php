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

while ( !feof( $fi ) ) { 
	$fcontent = fgets($fi);
	$tcontent = trim($fcontent);
	$data = split("\t", $tcontent);

	//parses to only get the domain (not including the subdomain)
	$urlInfo = parse_url(trim($data[1]));
	$parts = preg_split('&\.&', $urlInfo['host']);
	$total = count($parts);
	$domain = $parts[$total - 2] . '.' . $parts[$total - 1];

	$pageId = trim($data[0]);
	
	if($domains[$domain] == null){
		$domains[$domain] = array();
		$domains[$domain]['found'] = false;
		$domainsString .= $domain . "\n";
	}
	$domains[$domain][$pageId] = $pageId;
	
}

fclose($fi);

echo "Done parsing input domains\n";

$fi1 = fopen('wikihow_blacklist.txt', 'r');
$fi2 = fopen('dom-bl-base.txt', 'r');
$fi3 = fopen('mozilla-blacklist.txt', 'r');
$fo = fopen($datasource . '_external_domains_known_spam.txt', 'w');
$fo2 = fopen($datasource . '_external_domains_unknown.txt', 'w');

//$contents = fread($fi, filesize('external_domains.txt'));

//fclose($fi);

while( !feof( $fi1 ) ) {
	$domain = trim( fgets($fi1) );
	if($domain != ""){
		checkDomain($domain, $domainsString, $domains);
	}
}

fclose($fi1);

echo "Done checking external_domains.txt\n";

while( !feof( $fi2) ) {
	$domain = fgets($fi2);
	if($domain != ""){
		checkDomain($domain, $domainsString, $domains);
	}
}

fclose($fi2);

echo "Done checking dom-bl-base.txt\n";

while( !feof( $fi3) ) {
	$domain = fgets($fi3);
	$domain = trim( preg_replace( '/#.*$/', '', $domain ) );
	if($domain != ""){
		checkDomain($domain, $domainsString, $domains);
	}
}

fclose($fi3);

echo "Done checking mozilla-blacklist.txt\n";

function checkDomain($domain, &$domainsString, &$domains){
	$re = "@" . trim($domain) . "@";
	$count = preg_match($re, $domainsString, $matches);
	if($count > 0){
		//mark it found
		$domains[$matches[0]]['found'] = true;

	}
	else{

		//echo 'no match ' . $domain . "\n";
	}
}

foreach ($domains as $domain => $array) {
	if($array['found']){
		fwrite($fo, $domain . "\n");
	}
	else{
		fwrite($fo2, $domain . "\n");
	}
}

fclose($fo);
fclose($fo2);



//arsort($domains);
//arsort($links);

/*$fh = fopen('/home/bebeth/prod/maintenance/external_domains.txt', 'w');

foreach( $domains as $domain => $count ) {
	if($domain != "")
		fwrite( $fh, $domain . " \t" . $count . "\n" );
}

fclose($fh);

$fh = fopen('/home/bebeth/prod/maintenance/external_urls.txt', 'w');

foreach( $links as $link => $count ) {
	if($link != "")
		fwrite( $fh, $link . " \t" . $count . "\n" );
}

fclose($fh);*/

/*while( $obj = $dbr->fetchObject($res) ){
	$results[] = $obj;
}

foreach ( $results as $result ) {
	if($sites[$result->el_to] == null)
		$sites[$result->el_to] = 1;
	else
		$sites[$result->el_to]++;
}

arsort($sites);

$fh = fopen('/usr/local/wikihow/log/external_links.txt', 'w');

foreach ( $sites as $site => $count ) {
	fwrite( $fh, $site . " " . $count . "\n" );
}

fclose($fh);*/
