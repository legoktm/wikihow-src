<?
	require_once("commandLine.inc");
	$wgUser = User::newFromName("Tderouin"); 

	$dbr = wfGetDB(DB_MASTER); 
	$sql = "select distinct(rc_title) FROM `recentchanges` WHERE rc_namespace=0 and rc_comment LIKE '%Added Image using ImageAdder Tool%' AND rc_timestamp > '20110401144146' AND rc_timestamp < '20110404164139';";
	$res = $dbr->query($sql); 
	while ($row = $dbr->fetchObject($res)) {
		$t = Title::makeTitle(NS_MAIN, $row->rc_title); 
		if (!$t) {
			echo "Can't make title out of {$row->rc_title}\n";
			continue;
		}

		# update imageadder table, reset the stats on this bad boy
		$dbw = wfGetDB(DB_MASTER); 
		$dbw->update("imageadder", array("imageadder_hasimage"=>0, "imageadder_skip"=>0), array("imageadder_page"=>$t->getArticleID()));

		// last revision comment?
		$row = $dbr->selectRow('revision', '*', array('rev_page'=>$t->getArticleID()),
			"fix intro adder", array("ORDER BY"=>"rev_id desc", "LIMIT"=>1));
		if ($row->rev_comment == "Added Image using ImageAdder Tool") {
			$a = new Article($t);
			$a->commitRollback($row->rev_user_text, "Rolling back bug", false, &$results);
			echo "{$t->getFullURL()} rolled back \n";
			continue;
		}

		// how many sections? 
		$r = Revision::newFromTitle($t); 
		if (!$r) {
			echo "Can't make revision out of {$row->rc_title}\n";
			continue;
		}
		$text = $r->getText(); 
		preg_match_all("@^==.*==@m", $text, $matches);
		if (sizeof($matches[0]) == 0) {
			echo "{$t->getFullURL()} has no sections\n";
			// get the last edit
			$lastgood_rev = $dbr->selectRow("revision", "*", array('rev_page'=>$t->getArticleID(), 
					'rev_comment != "Added Image using ImageAdder Tool"', "rev_id < {$row->rev_id}",
					),
					"fix intro adder", array("ORDER BY"=>"rev_id desc", "LIMIT"=>1));
			$rev = Revision::newFromID($lastgood_rev->rev_id); 
			if (!$rev) {
				// ?
				continue;
			}
			$text = $rev->getText();
			$a = new Article($t);
			$a->doEdit($text, "Rolling back bug going back to rev {$lastgood_rev->rev_id} by {$lastgood_rev->rev_user_text}");
		}
	}
