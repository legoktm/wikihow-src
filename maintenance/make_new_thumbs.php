<?
require_once( "commandLine.inc" );

global $wgUser;		
$sk = $wgUser->getSkin();

if (count($argv)) {
	// you can pass in a parameter like A-L, or M-Z etc
	$parts = preg_split('@-@', $argv[0]);
	$where_sql = " AND page_title >= '{$parts[0]}' AND page_title <= '{$parts[1]}'";
}
$sql = 'SELECT page_title FROM page WHERE page_namespace = 0 AND page_is_redirect = 0' . $where_sql;

$dbr = wfGetDB(DB_SLAVE);
$res = $dbr->query($sql);

$titles = array();
foreach ($res as $row) {
	//next title...
	$title = Title::newFromDBkey($row->page_title);
	if (!$title || $title->getArticleID() == 0) continue;
	$titles[] = $title;
}

foreach ($titles as $title) {
	//get the title image
	$file = Wikitext::getTitleImage($title);
	if ($file && isset($file)) {
		//render it, crop it
		$thumb = $file->getThumbnail(222, 222, true, true);
		if ($thumb->url) {
			print $title->getText().": ".$thumb->url."\n";
		} else {
			print $title->getText().": (none)\n";
		}
	}
}

