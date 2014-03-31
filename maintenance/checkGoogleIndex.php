<?

require_once('commandLine.inc');

global $wgLastCurlError;

function getResults($url) {
	$ch = curl_init();
	$useragent = "Mozilla/5.0 (Macintosh; U; Intel Mac OS X 10.6; en-US; rv:1.9.2.13) Gecko/20101203 Firefox/3.6.13";
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_TIMEOUT, 5);
	curl_setopt($ch, CURLOPT_USERAGENT, $useragent);
	$contents = curl_exec($ch);
	if (curl_errno($ch)) {
		$wgLastCurlError = curl_error($ch);
		return null;
	} else {

	}
	curl_close($ch);
	return $contents;
}


function isIndexed($t) {
	$query = $t->getText() . " site:wikihow.com";
	$url = "http://www.google.com/search?q=" . urlencode($query) . "&num=100";
#echo "using {$t->getText()} - $url\n";
	$results = getResults($url); 
	if ($results == null) {
		return null;
	}
	$doc = new DOMDocument('1.0', 'utf-8');
	$doc->formatOutput = true;
	$doc->strictErrorChecking = false;
	$doc->recover = true;
	@$doc->loadHTML($results);
	$xpath = new DOMXPath($doc);
	$nodes = $xpath->query('//a[contains(concat(" ", normalize-space(@class), " "), " l")]');
	$index = 1;
	$turl = urldecode($t->getFullURL());
	foreach ($nodes as $node) {
		$href = $node->getAttribute("href");
		#echo "{$title->getFullURL()}, {$href}\n";
		#if (preg_match("@/url?q=" . $title->getFullURL() . "@", $href))
		if ($href == $turl) {
			$found[] = $title;
			return $index;
		}
		$index++;
	}
	return 0;
}
	
$dbw = wfGetDB(DB_MASTER); 

$dbw->query("insert IGNORE into google_indexed (gi_page, gi_page_created) select fe_page, fe_timestamp from firstedit where fe_user_text='WRM'"); 

$res = $dbw->select(array('google_indexed', 'page'), 
			array("page_title", "page_id"), 
			array("page_id = gi_page"), 
			"checkGoogleIndex",
			array("ORDER BY" => "gi_times_checked, rand()", "LIMIT" => 500)
		);

$titles = array(); 
while ($row = $dbw->fetchObject($res)) {
	$titles[] = Title::newFromDBKey($row->page_title);
}


foreach ($titles as $t) {
	if (!$t) continue; 
	$ts = wfTimestampNow();
	$ret = isIndexed($t); 

#echo "got return of $ret\n"; exit;
	$dbw = wfGetDB(DB_MASTER); 
	$opts = array('gl_page'=>$t->getArticleID(), 'gl_pos'=>($ret == null? 0 : 1), 'gl_checked'=>$ts);
	if ($ret == null) {
		$opts['gl_err_str'] = $wgLastCurlError;
		$opts['gl_err'] = 1;
	}

	$dbw->insert('google_indexed_log', $opts);
	if ($ret) {
		$indexed = $ret > 0 ? 1 : 0;
		$dbw->update('google_indexed', 
				array('gi_lastcheck' => $ts, 'gi_indexed' => $indexed, 'gi_times_checked = gi_times_checked + 1'), 
				array('gi_page'=>$t->getArticleID() )
			);
	}
	
	// throttle
	$x = rand(1,3);
	sleep($x); 

}
