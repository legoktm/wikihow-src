<?

	require_once('commandLine.inc');

	$dbr = wfGetDB(DB_SLAVE);
	$dbw = wfGetDB(DB_MASTER);

	$batch = isset($argv[0]) ? $argv[0] : 0;
	$opts = array("ORDER BY" => "page_id", "LIMIT" => 10000, "OFFSET" => ($batch * 10 *1000));
	$res = $dbr->select('page', array('page_namespace', 'page_title'), 
		array('page_namespace'=>NS_MAIN, 'page_is_redirect'=>0),
		"init_toplevelcategories.php",
		$opts
		//'page_title'=>'Have-an-Awesome-Overseas-Class-Trip',
		);


	function flatten($arg, &$results = array()) {
		if (is_array($arg)) {
			foreach ($arg as $a=>$p) {
				if (is_array($p)) 
					flatten($p, $results);
				else
					$results[] = $a;
			}
		}
		return $results;
	}

	//initialize the top level array of categories;
	$x = Categoryhelper::getTopLevelCategoriesForDropDown();
	$top = array();
	foreach ($x as $cat) {
		$cat = trim($cat); 
		if ($cat == "" || $cat == "Other" || $cat == "WikiHow") 
			continue;
		$top[] = $cat;
	}

	#print_r($top);

	if ($batch == 0) 
		$dbw->query("delete from categorylinkstop;"); 

	$count = 0;
	$updates = array();
	$titles = array();
	while ($row = $dbr->fetchObject($res)) {
		$t = Title::makeTitle($row->page_namespace, $row->page_title);
		if (!$t) {
			continue;
		}
		$titles[] = $t;
	}

	echo "Got titles\n";
	foreach ($titles as $t) {
		#echo "\tgetting parentcategory tree for {$t->getText()}\n";
		$tree = $t->getParentCategoryTree();
		if ($count == 0) 
			echo "Starting with page id {$t->getArticleID()}\n";
			#echo"\t\got tree, getting mine\n";
		$mine = array_unique(flatten($t->getParentCategoryTree()));
			#echo"\tflattened\n";
		foreach ($mine as $m) {
			#echo"\tchecking $m\n";
			$y = Title::makeTitle(NS_CATEGORY, str_replace("Category:", "", $m));
			if (in_array($y->getText(), $top)) {
				#echo "{$t->getText()} - {$y->getText()}\n";
				$updates[] = array('cl_from'=>$t->getArticleID(), 'cl_to'=>$y->getDBKey());
			}
		}
		$count++;
		if ($count % 1000 == 0)  {
			echo "Done $count\n";
		}
	}
	echo "doing " . sizeof($updates) . "\n";
	$count = 0;
	foreach ($updates as $u ) {
		$dbw = wfGetDB(DB_MASTER);
		$dbw->insert("categorylinkstop", $u);
        $count++;
        if ($count % 1000 == 0)  {
            echo "Done $count\n";
        }
	}

//d$dbw->insert('categorylinkstop', array('cl_from'=>$t->getArticleID(), 'cl_to'=>$y->getDBKey()));
