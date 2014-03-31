<?
//
// Generate a list of (With Video, With Pictures) type extra info that
// you find for titles.  This is for Chris.
//
// Copied and changed from GenTitleExtraInfo.body.php by Reuben.
//

require_once("commandLine.inc");

global $IP;
require_once("$IP/skins/WikiHowSkin.php");

print "querying database...\n";
$dbr = wfGetDB(DB_SLAVE);
$titles = array();
$sql = 'SELECT page_title FROM page WHERE page_namespace=' . NS_MAIN . ' AND page_is_redirect=0';
$res = $dbr->query($sql, __FILE__);
while ($obj = $res->fetchObject()) {
	$titles[] = Title::newFromDBkey($obj->page_title);
}
print "found " . count($titles) . " articles.\n";

$file = isset($argv[0]) ? $argv[0] : 'out.csv';
print "writing output to $file...\n";
$fp = fopen($file, 'w');
fputs($fp, "url,full-title,length\n");

foreach ($titles as $title) {
	$tt = TitleTests::newFromTitle($title);
	if (!$tt) continue;
	$new = $tt->getTitle();
	$url = 'http://www.wikihow.com/' . $title->getPartialURL();
	$out = array($url, $new, strlen($new));
	fputcsv($fp, $out);
}

fclose($fp);

print "done.\n";

