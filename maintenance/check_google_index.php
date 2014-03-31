<?  
# September, 2010
# Check to see what % of articles are indexed in Google

require_once('commandLine.inc');

function printStats($titles) {
	$columns = array('counter', 'revisions', 'links', 'age'); 
	$stats = array();
	foreach ($titles as $t) {
		if (!$t) continue;
		$stats[] = getStats($t);		
	}
	foreach ($columns as $c) {
		echo "$c:\t";
		if (strlen($c) < 6) echo "\t";
		$sum = 0;
		foreach ($stats as $x) {
			$sum += $x[$c];
		}
		if (sizeof($stats) == 0) {
			echo "-\n";
		} else {
			echo number_format($sum / sizeof($stats), 2) . "\n";
		}
	}
	echo "\n\n";
}

function getStats($t) {
	$dbr = wfGetDB(DB_SLAVE);
	$x = array();
	$x['counter'] 	= $dbr->selectField('page', array('page_counter'), array('page_id' => $t->getArticleID()));
	$x['revisions'] = $dbr->selectField('revision', array('count(*)'), array('rev_page' => $t->getArticleID()));
	$x['links'] 	= $dbr->selectField('pagelinks', array('count(*)'), array('pl_title' => $t->getDBKey(), 'pl_namespace'=>NS_MAIN));
	$ts 			= $dbr->selectField('revision', array('min(rev_timestamp)'), array('rev_page' => $t->getArticleID()));
	$x['age']		= round((time() - wfTimestamp(TS_UNIX, $ts)) / 3600 / 24);
	$x['title']		= $t;
	$x['found']		= 0;
	return $x;
}

    function getResults($url) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $contents = curl_exec($ch);
        if (curl_errno($ch)) {
            echo "curl error {$url}: " . curl_error($ch) . "\n";
        }
        curl_close($ch);
        return $contents;
    }

$notfound = array(); 
$found = array(); 

function search($title, $site = "") {
	global $notfound, $found;
	#$urls = split("\n", $q);
	$query  = $title->getText() . " $site";
	$url = "http://www.google.com/search?q=" . urlencode($query) . "&num=100";
	$results = getResults($url); 
#echo $url . "\n\n"; echo str_replace("<a href", "\n<a href", $results) . "\n";
#exit;
	if ($results == null) return -2;
    $doc = new DOMDocument('1.0', 'utf-8');
	$doc->formatOutput = true;
    $doc->strictErrorChecking = false;
    $doc->recover = true;
    @$doc->loadHTML($results);
    $xpath = new DOMXPath($doc);
	$nodes = $xpath->query("//a");
	$index = 1;
	$turl = urldecode($title->getFullURL());
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

	#echo str_replace("<", "\n<", $doc->saveXML()); exit;
	$notfound[] = $title; 
	return -1;
}

$dbr = wfGetDB(DB_MASTER); 
$limit = isset($argv[0]) ? $argv[0] : 1000;
$res = $dbr->query("SELECT page_title from page where page_is_redirect=0 and page_namespace=0 order by rand() limit $limit;");

$total = $indexed = 0;
while ($row = $dbr->fetchObject($res)) {
	$t = Title::makeTitle(NS_MAIN, $row->page_title);
	if (!$t) continue;
	$ret = search($t, "site:wikihow.com");
	if ($ret > 0) $indexed++;
	if ($ret > -2 ) $total++;
	
	echo "$ret\t" . number_format($total / $limit * 100, 2) . "%\t{$t->getFullURL()}\n";
}

echo "NOT FOUND:\n";
printStats($notfound); 
echo "FOUND:\n";
printStats($found); 


echo "$total\t$indexed\t" . number_format($indexed / $total * 100, 2) . "%\n";
	

