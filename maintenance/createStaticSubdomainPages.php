<?
/*
* Maintenance script to scrape html and reformat hrefs to www.wikihow.com for subdomain test
*/
require_once('commandLine.inc');
require_once("$IP/extensions/wikihow/DomainsTest.class.php");
$rootDir = "$IP/x/subdomain";

@mkdir($root);
foreach (DomainTest::$domainMap as $title => $domain) {
	$domainDir = "$rootDir/$domain";
	@mkdir($domainDir);
	@chdir($domainDir);
	$title = escapeshellcmd($title);
	$cmd = "curl -L http://www.wikihow.com/$title 2> /dev/null | sed \"s@href=\(\\\"\|'\)/@href=\\1http://www.wikihow.com/@gm\" > $title.html";
	var_dump($cmd);
	shell_exec($cmd);
}
