<?php
/* 
 *
 * Script that aggregates all external links from a text file created by
 * echo 'select el_from, el_to from externallinks' |mysql wikidb_112 > externallinks.txt
 *
 */

global $IP, $wgTitle;
require_once('../commandLine.inc');
require_once("$IP/extensions/wikihow/DatabaseHelper.class.php");

echo "Starting batchSelect " . date('H:i') . "\n";

$dbr = wfGetDB(DB_SLAVE);

$linksArray = DatabaseHelper::batchSelect('externallinks', array('el_to', 'el_from'), array(), __FILE__);

/********
//TESTING CODE
$dbr = wfGetDB(DB_SLAVE);
$linksArray = $dbr->select('externallinks', array('*'), array(), __FILE__, array("LIMIT" => 10));
$obj->el_to = "http://emiliastone.blog.com/2009/11/03/wholesale-dvds-a-treasure-trove-of-deals/";
$obj->el_from = 636659;
$obj2->el_to = "http://hubpages.com/hub/Authority_Summit ";
$obj2->el_from = 6666;
$linksArray[] = $obj;
$linksArray[] = $obj2;
/********/

echo "Done batchSelect " . date('H:i') . "\n";

//putting this up here so the database doesn't crap out
$whitelistString = wfMsg('Spam-whitelist');

$domainsString = "\n";

foreach($linksArray as $link) {
//while ( !feof( $fi ) ) { 
	//parses to only get the domain (not including the subdomain)
	$urlInfo = parse_url(trim($link->el_to));
	$parts = preg_split('&\.&', $urlInfo['host']);
	$total = count($parts);
	$domain = $parts[$total - 2] . '.' . $parts[$total - 1];
	
	$fullUrl = $urlInfo['host'];
	
	if($domains[$domain] == null){
		$domains[$domain] = array();
		$domains[$domain]['found'] = false;
	}
	if($domains[$domain][$fullUrl] == null) {
		$domains[$domain][$fullUrl] = array();
		$domainsString .= $fullUrl . "\n";
	}
	
	$domains[$domain][$fullUrl][$link->el_from] = $link->el_from;
	
}

$dbr->ping();

echo "Done parsing input domains " . date('H:i') . "\n";

//$fi1 = fopen('wikihow_blacklist.txt', 'r');
$fi2 = fopen('dom-bl-base.txt', 'r');
$fi3 = fopen('mozilla-blacklist.txt', 'r');
$fo = fopen('external_domains_known_spam-' . date("Ymd") . '.txt', 'w');
$fo2 = fopen('external_domains_unknown-' . date("Ymd") . '.txt', 'w');

//$contents = fread($fi, filesize('external_domains.txt'));

//fclose($fi);

echo "Checking wikihow-blacklist " . date('H:i') . "\n";
$blacklistTitle = Title::newFromText("Spam-Blacklist");
if($blacklistTitle) {
	$revision = Revision::newFromTitle($blacklistTitle);
	$domainString = $revision->getText();
	$blackDomains = split("\n", $domainString);
	foreach($blackDomains as $domain) {
		$domain = trim( preg_replace( '/#.*$/', '', $domain ) );
		if($domain != ""){
			checkDomain($domain, $domainsString, $domains);
		}
	}
}

$dbr->ping();
echo "Done checking Spam-Blacklist " . date('H:i') . "\n";

while( !feof( $fi2) ) {
	$domain = fgets($fi2);
	if($domain != ""){
		checkDomain($domain, $domainsString, $domains);
	}
}

fclose($fi2);

echo "Done checking dom-bl-base.txt " . date('H:i') . "\n";

while( !feof( $fi3) ) {
	$domain = fgets($fi3);
	$domain = trim( preg_replace( '/#.*$/', '', $domain ) );
	if($domain != ""){
		checkDomain($domain, $domainsString, $domains);
	}
}

$dbr->ping();
fclose($fi3);

echo "Done checking mozilla-blacklist.txt " . date('H:i') . "\n";

echo "Checking wikihow-whitelist " . date('H:i') . "\n";

$whiteDomains = split("\n", $whitelistString);
foreach($whiteDomains as $domain) {
	$domain = trim( preg_replace( '/#.*$/', '', $domain ) );
	if($domain == "")
		continue;
	
	$urlInfo = parse_url("http://" . $domain);
	$parts = preg_split('&\.&', $urlInfo['host']);
	$total = count($parts);
	$domain = $parts[$total - 2] . '.' . $parts[$total - 1];
	if(substr($domain, -1) == "\\"){
		$domain = substr($domain, 0, -1);
	}
	
	//Is that domain in our list anywhere?
	//just mark that domain as one we don't want
	checkDomain($domain, $domainsString, $domains, false);
	/*if($domain != ""){
		checkSubDomain($urlInfo['host'], $domainsString, $domains);
	}*/
}

function checkDomain($domain, &$domainsString, &$domains, $markValue = true){
	$count = preg_match('&'. trim($domain) .'&', $domainsString, $matches);
	if($count > 0){
		if (!empty($domains[$matches[0]])) {
			//mark it found
			$domains[$matches[0]]['found'] = $markValue;
		}
		
	}
	else{

		//echo 'no match ' . $domain . "\n";
	}
}

function checkSubDomain($subdomain, &$domainsString, &$domains){
	$count = preg_match('&'. trim($subdomain) .'&', $domainsString, $matches);
	if($count > 0){
		$urlInfo = parse_url("http://" . $matches[0]);
		$parts = preg_split('&\.&', $urlInfo['host']);
		$total = count($parts);
		$domain = $parts[$total - 2] . '.' . $parts[$total - 1];
		if (!empty($domains[$domain]) && !empty($domains[$domain][$matches[0]])) {
			//mark it found
			$domains[$domain][$matches[0]]['found'] = false;
		}
		
	}
	else{

		//echo 'no match ' . $domain . "\n";
	}
}

foreach($domains as $domain => $array) {
	if($array['found']) {
		$lines = "";
		foreach($array as $subdomain => $subArray) {
			$ids = "";
			if($subdomain == "found")
				continue;
			$found = true;
			foreach($subArray as $key => $id) {
				if($key != "found") {
					$ids .= $id . "\t";
				}
				else{
					$found = $id;
				}
			}
			$lines .= $subdomain . "\t" . $ids . "\n";
		}
		if($found)
			fwrite($fo, $lines);
		else
			fwrite($fo2, $lines);
	}
	else {
		//ok sites
		foreach($array as $subdomain => $subArray) {
			$ids = "";
			if($subdomain == "found")
				continue;
			foreach($subArray as $key => $id) {
				if($key != "found") {
					$ids .= $id . "\t";
				}
			}
			
			fwrite($fo2, $subdomain . "\t" . $ids . "\n");
		}
	}
}

fclose($fo);
fclose($fo2);

echo "Done file " . date('H:i') . "\n";

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
