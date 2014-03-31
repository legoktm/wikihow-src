<?
	require_once('commandLine.inc');

	$dbw = wfGetDB(DB_MASTER);

	$sql = "SELECT page_title, page_namespace FROM templatelinks left join page on tl_from=page_id WHERE page_namespace=0 and tl_title in ('Copyedit', 'Stub', 'Cleanup') ";
	//$sql .= " ORDER BY rand() LIMIT 100";

	$res = $dbw->query($sql);

	while ($row= $dbw->fetchObject($res)) {
		$t = Title::makeTitle($row->page_namespace, $row->page_title);
		$l = new LSearch();
		$results = $l->googleSearchResultTitles($t->getFullText(), 0, 30, 5);
		//echo "{$t->getFullText()} size of results ". sizeof($results) . "\n";
		$x = strtolower(str_replace(" ", "", $t->getText()));
		foreach($results as $r) {
			$y = strtolower(str_replace(" ", "", $r->getText()));
			if ($x == $y) continue;
			$dbw->insert('improve_links',
				array('il_from' => $r->getArticleID(), 
					'il_namespace' => $t->getNamespace(), 
					'il_title'	=> $t->getDBKey()
				)
			);
			echo "{$t->getFullText()}\t{$r->getFullText()}\n";
		}

	}
