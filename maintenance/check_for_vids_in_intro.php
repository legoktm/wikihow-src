<?
	require_once("commandLine.inc");
	$wgUser = User::newFromName("Tderouin"); 

	$dbr = wfGetDB(DB_MASTER); 
	$sql = "select distinct(rc_title) FROM `recentchanges` WHERE rc_namespace=0 and rc_comment LIKE '%Added Image using ImageAdder Tool%' ";
	$res = $dbr->query($sql); 
	while ($row = $dbr->fetchObject($res)) {
		$t = Title::makeTitle(NS_MAIN, $row->rc_title); 
		if (!$t) {
			echo "Can't make title out of {$row->rc_title}\n";
			continue;
		}

		$r = Revision::newFromTitle($t);
		$text = $r->getText();
		$intro = Article::getSection($text, 0); 
		if (preg_match("@\{\{Video:@", $intro)) {
			#echo "{$t->getFullURL()} has a video in the intro,oops\n";
			// last revision comment?
			$row = $dbr->selectRow('revision', '*', array('rev_page'=>$t->getArticleID()),
				"fix intro adder", array("ORDER BY"=>"rev_id desc", "LIMIT"=>1));
			if ($row->rev_comment == "Added Image using ImageAdder Tool") {
				$a = new Article($t);
				$a->commitRollback($row->rev_user_text, "Rolling back bug", false, &$results);
				echo "{$t->getFullURL()} rolled back \n";
				continue;
			} else {
				echo "{$t->getFullURL()} needs TLC\n";
			}
		}

	}
