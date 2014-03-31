<?
	require_once('commandLine.inc');

	$dbr = wfGetDB(DB_SLAVE);
	//$opts = array("LIMIT"=>1000);
	$opts = array();
	$res = $dbr->select('page', array('page_namespace', 'page_title'), array('page_is_redirect'=>0, 'page_namespace'=>NS_MAIN),
		"update_imageadder",
		$opts);
	$titles = array(); 
	while ($row = $dbr->fetchObject($res)) {
		$t = Title::makeTitle($row->page_namespace, $row->page_title);
		if (!$t) continue;
		$titles[] = $t;
	}

	foreach ($titles as $t) {
		$r = Revision::newFromTitle($t);
		if (!$r) continue;
		$text = $r->getText();
		$intro = Article::getSection($text, 0);
		if (preg_match("@\[\[Image:@", $intro)) {
			$dbw = wfGetDB(DB_MASTER);
			$dbw->update('imageadder', array('imageadder_hasimage'=>1), array('imageadder_page'=>$t->getArticleID()));
		}
	}
