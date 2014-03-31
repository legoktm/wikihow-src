<?php
require_once('commandLine.inc');
# Export list of alternative methods for a list of articles to CSV file

#$filename = $argv[0];
#$f = fopen($filename, 'r');
#$contents = fread($f, filesize($filename));
#fclose($f);
#$pages = preg_split('@[\r\n]+@', $contents);
$dbr = wfGetDB(DB_SLAVE);
$sql = "select page_title from page";
$res = $dbr->query($sql, __METHOD__);
$pages=array();
foreach($res as $row) {
	$pages[] = $row->page_title;
}
foreach($pages as $page) {
	$t = Title::newFromText($page);
	$gr = true; GoodRevision::newFromTitle($t);
	if($gr) {
		$r = Revision::newFromTitle($t);
		if($r) {
			$text = Wikitext::getStepsSection($r->getText(), true);
			if(preg_match_all("@===([^=]+)===@", $text[0], $matches)) { 
				print $page;
				foreach($matches[1] as $m) {
					if(!preg_match("@\r\n@",$m)) {
						print ',' . $m;	
					}
				}
				print "\n";
			}
		}
	}
}
