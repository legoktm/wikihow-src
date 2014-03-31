<?
	require_once("commandLine.inc");
	$dbr = wfGetDB(DB_SLAVE); 

    function getResults($url) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $contents = curl_exec($ch);
        if (curl_errno($ch)) {
            //echo "curl error {$url}: " . curl_error($ch) . "\n";
			return null;
        } else {

        }
        curl_close($ch);
        return $contents;
    }


	$lines = split("\n", file_get_contents($argv[0]));

	$header = array_shift($lines) . ", # inbound local links, featured?, yahoo api links, moz juice passing links, mozRank raw score, age(days)\n";
	echo $header; 

	$wgServer = "http://www.wikihow.com";

	foreach($lines as $line) {
		$line = trim($line);
		if ($line == "") continue;
		$tokens = split(",", $line);
		$title = Title::newFromURL(preg_replace("@^/@", "", $tokens[0]));
		if (!$title) {
			$text .= $line . ",error making title\n";
		}
		if (!$title) {
			echo "could not get title for $line\n";
			exit;
		}
		if (sizeof($tokens) < 7){
			$count = $dbr->selectField('pagelinks', array('count(*)'), array('pl_title'=>$title->getDBKey())); 
			$line .= ",$count";
		}
		if (sizeof($tokens) < 8){
			$fa = $dbr->selectField('templatelinks', array('count(*)'), array('tl_from'=>$title->getArticleID(), 'tl_title'=>'Fa')); 
			$line .= ",$fa";
		}
		if (sizeof($tokens) < 9 && $tokens[8] != "N/A"){
			# yahoo in bound links
			$url = "http://search.yahooapis.com/SiteExplorerService/V1/inlinkData?appid=". WH_YDN_KEY . "&query=" . urlencode($title->getFullURL());
			$results = getResults($url); 
			$count = "N/A";
			if ($results) {
				preg_match_all('@totalResultsAvailable="[0-9]*"@', $results, $matches);
				if (sizeof($matches[0]) >0 ) {
					$count = preg_replace("@[^0-9]@", "", $matches[0][0]);
				}
			}
#echo "\n\n$url\n\n"; echo $results; echo "\n\n$count\n\n"; exit;
			$line .= ",$count";
		}
		if (sizeof($tokens) < 10){
			$nx = urlencode(preg_replace("@http://@", "", $title->getFullURL()));
			$sig = md5($nx . time());
			$expires = time() + 120;
			$url = "http://lsapi.seomoz.com/linkscape/url-metrics/" . $nx . "?AccessID=" . WH_SEOMOZ_ACCESS_ID . "&Expires={$expires}&Signature={$sig}";
			$url = "http://lsapi.seomoz.com/linkscape/url-metrics/{$nx}?AccessID=member-debe281176&Expires=1316715038&Signature=9hDKyAKb2iiXELvbgJvoiuFxORA%3D";
			$results = file_get_contents($url);
			$obj = json_decode($results);
			$line .= ",{$obj->umrp},{$obj->ueid}";
		}
		if (sizeof($tokens) < 12){
			$min = $dbr->selectField('revision', array('min(rev_timestamp)'), array("rev_page"=>$title->getArticleID()));
			$diff = wfTimestamp(TS_UNIX, $min);
			$days = round((time() - $diff) / (60 * 60 * 24));
			$line .= ",$days"; 
		}


		echo preg_replace("@ @", "", $line) . "\n";
	}
	
