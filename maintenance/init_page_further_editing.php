<?
	require_once('commandLine.inc');

	$dbr = wfGetDB(DB_SLAVE);
	//$opts = array("LIMIT"=>1000);
	$templates = split("\n", wfMsgForContent('templates_further_editing'));
	$regexps = array(); 
	foreach ($templates as $t) {
		$t = trim($t); 
		if ($t == "") continue;
		$regexps[] ='\{\{' . $t; 
	}
	$re = "@" . implode("|", $regexps) . "@i"; 

#print_r($regexps); echo $re; exit;
	
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
		$updates = array();
		if (preg_match_all($re, $text, $matches)) {
			$updates['page_further_editing'] = 1;
		}
		if (preg_match("@\{\{fa\}\}@i", $text)) {
			$updates['page_is_featured'] = 1;
		}
		if (sizeof($updates) > 0) {		
			$dbw = wfGetDB(DB_MASTER);
			$dbw->update('page', $updates, array('page_id'=>$t->getArticleID()));
		}
	}
