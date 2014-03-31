<?
	require_once('commandLine.inc');


	function checkGoogle($query) {
        $url = "http://www.google.com/search?q=" . urlencode($query . " site:wikihow.com") . "&num=100";
        $contents = file_get_contents($url);
        $matches = array();
        $preg = "/href=\"http:\/\/[^\"]*\"*/";
        $preg = "/href=\"http:\/\/[^\"]*\" class=l */ ";
        preg_match_all($preg, $contents, $matches);

#echo "checking $query\n";
		$results = array();
        foreach ($matches[0] as $url) {
            $url = substr($url, 6, strlen($url) - 7);
            $url = str_replace('" class=', '', $url);

                // check for cache article
            if (strpos($url, "/search?q=cache") !== false || strpos($url, "google.com/") !== false)
                continue;
			#echo $url . "\n";
			$url = str_replace('http://www.wikihow.com/', '', $url);
			$t = Title::newFromURL($url);
			if ($t)
				$results[] = $t;
		}
		return $results;
	}

	function checkTitles($titles) {
		$mini = $ajax = $google = 0;
		$ret = array('google' => 0, 'mini' => 0, 'ajax' => 0);
		foreach ($titles as $t) {

			#echo "checking {$t->getText()}\n";	
			$results = checkGoogle($t->getText());
            if (!$results) $results = array();  
            foreach ($results as $r) {
                #echo "{$r->getDBKey()}\t{$t->getDBKey()}\n";
                if ($r->getDBKey() == $t->getDBKey()) {
                    #echo "check!"; exit;
					$ret['google']++;
                    break;
                }
            }
/*	
			// get the ajax results	
			$results = GoogleAjaxSearch::scrapeGoogle($t->getText());
			if (!$results) $results = array();	
			foreach ($results as $r) {
				#echo "{$r->getDBKey()}\t{$t->getDBKey()}\n";
				if ($r->getDBKey() == $t->getDBKey()) {
					#echo "check!"; exit;
					$ret['ajax']++;
					break;
				}
			}
	
			$l = new LSearch();
			$results = $l->googleSearchResultTitles($t->getText());
			if (!$results) $results = array();	
	        foreach ($results as $r) {
	            if ($r->getDBKEy() == $t->getDBKey()) {
					$ret['mini']++;
	                break;
	            }
	        }
*/
		
		}
		return $ret;
	}

	$limit = 1000;

	$dbr = wfGetDB(DB_MASTER); 
	$res = $dbr->query("select page_namespace, page_title from page where page_namespace=0 and page_is_redirect = 0 
					order by rand() limit $limit");
	/*
	$res = $dbr->query("select page_namespace, page_title from page where page_namespace=0 and page_is_redirect = 0 
					and page_title='Convince-Your-Parents-to-Get-a-Guinea-Pig'
					order by rand() limit $limit");
	*/
	$titles = array();
	while ($row = $dbr->fetchObject($res)) {
		$t = Title::makeTitle($row->page_namespace, $row->page_title);
		if (!$t) 			
			continue;
		$titles[] = $t;
	}

	#$results = checkTitles($titles);
	
	#$total = sizeof($titles);
	#$ajax = $results['ajax']; $mini = $results['mini'];
	
	#echo sizeof($titles) . "\t" . number_format($ajax/$total * 100, 2) . "%\t"
			#. number_format($mini/$total * 100, 2) . "%\n";

	$now = time();
	//for ($i = 0; $i < 168; $i +=2 ) {
	for ($i = 0; $i < 7; $i++ ) {
		#$ts1 = wfTimestamp(TS_MW, $now - 60 * 60 * ($i + 2));	
		#$ts2 = wfTimestamp(TS_MW, $now - 60 * 60 * $i);	
		$ts1 = wfTimestamp(TS_MW, $now - 60 * 60 * ($i + 1) * 24);	
		$ts2 = wfTimestamp(TS_MW, $now - 60 * 60 * $i * 24);	
		$res = $dbr->query("select page_namespace, page_title from newarticlepatrol left join page on
				nap_page = page_id where page_namespace = 0 
				and nap_timestamp >= '{$ts1}' and nap_timestamp < '{$ts2}' limit $limit");
		$titles = array();
		while ($row= $dbr->fetchObject($res)) {
        	$t = Title::makeTitle($row->page_namespace, $row->page_title);
        	if (!$t) continue;
        	$titles[] = $t;
		}
	    $results = checkTitles($titles);

    	$total = sizeof($titles);
    	$ajax = $results['ajax']; $mini = $results['mini']; $google = $results['google'];

    	echo "{$i}\t" . sizeof($titles) 
			#. "\t" . number_format($ajax/$total * 100, 2) . "%" 
			#. "\t" . number_format($mini/$total * 100, 2) . "%"
			. "\t" . number_format($google/$total * 100, 2) . "%"
			. "\n";
	}
