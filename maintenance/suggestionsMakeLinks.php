<?
/*
	takes a list of suggestions and searches for which articles are most relevant
	and then creates a link from that article to the suggestion
*/
require_once( "commandLine.inc" );

    function scrapeGoogle($q) {
        $q = urlencode($q);
        $url = "http://www.google.com/uds/GwebSearch?callback=google.search.WebSearch.RawCompletion&context=0&lstkp=0&rsz=large&hl=en&source=gsc&gss=.com&sig=c1cdabe026cbcfa0e7dc257594d6a01c&q={$q}%20site%3Awikihow.com&gl=www.google.com&qid=124c49541548cd45a&key=ABQIAAAAYdkMNf23adqRw4vVq1itihTad9mjNgCjlcUxzpdXoz7fpK-S6xTT265HnEaWJA-rzhdFvhMJanUKMA&v=1.0";
        $contents = file_get_contents($url);
        #echo $q . "\n";
        $result = preg_match_all('@unescapedUrl":"([^"]*)"@u',$contents, $matches);
		$ids = array();
		$params = split("{", $contents);
		#print_r($params); exit;
		#echo $contents; echo "$q\n"; print_r($matches); exit;
		#print_r($matches);
		foreach($matches[1] as $m) {
			$m= str_replace('http://www.wikihow.com/', '', $m);
			$r = Title::newFromURL($m);
			if ($r =='') continue;
			if (!$r) {
				echo "Couldn't make title out of $r\n";
				continue;
			} else if ($r->getNamespace() != NS_MAIN) {
				continue;
			} else if ($r->getArticleID() > 0) {
				$ids[] = $r->getArticleID();
				#echo "adding link from {$r->getText()} to " . urldecode($q) . "\n";
			}
		}
		return $ids;
    }

	$wgUser = User::newFromName("LinkTool");
	$dbw = wfGetDB(DB_MASTER);
	
	$sugg = array();
	/*
    $res = $dbw->select(
			array('suggested_titles',
            array( 'st_title', 'st_id'),
            array ('st_used' => 0,
				'st_group' => 2,
			),
            "findInlineImages"
            );    
	*/
	$res = $dbw->query('select st_title, st_id from suggested_titles left join suggested_links on
				st_id=sl_sugg where st_used=0 and st_group=5 and sl_sugg is null order by rand()');
	while ( $row = $dbw->fetchObject($res) ) {
		$s = $row->st_title;
		$sugg[$s] = $row->st_id;
    }
	echo date("r") . " -  got " . sizeof($sugg) . " suggestions\n";
	$count = 0;
	$xx = time();
	foreach ($sugg as $s=>$sid) {
		$t = Title::newFromText($s);
		if (!$t) continue;
		$ids = scrapeGoogle($t->getText());
		if (sizeof($ids) > 0) {
			$sql = "";
			foreach ($ids as $id) {
				$sql .= "($sid,$id,rand()) ";
			}
			$sql = str_replace(" ", ", ", trim($sql));
			$dbw->query("insert into suggested_links values $sql;");
		} else {
			#echo "no results for $s\n";
		}
		$count++;//+=sizeof($ids);;
		if ($count % 500 == 0 && $count > 0) {
			$yy = time() - $xx;
			echo date("r") . " - processed $count suggestions, took $yy seconds, sleeping\n";
			#sleep(15);
			$xx = time();
		}
	}

	echo date("r") . " -  done " . sizeof($sugg) . " suggestions\n";
	
