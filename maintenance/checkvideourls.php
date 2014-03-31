<?

require_once("commandLine.inc");

	$urls = split("\n", file_get_contents($argv[0]));

$found = array();
$notfound = array(); 
    
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

function search($title, $site = "") {
	global $notfound, $found;
	#$urls = split("\n", $q);
	$query  = $title->getText() . " $site";
	$url = "http://www.google.com/search?q=" . urlencode($query) . "&num=100";
	$results = getResults($url); 
	if ($results == null) return -2;
    $doc = new DOMDocument('1.0', 'utf-8');
	$doc->formatOutput = true;
    $doc->strictErrorChecking = false;
    $doc->recover = true;
    @$doc->loadHTML($results);
    $xpath = new DOMXPath($doc);
	$nodes = $xpath->query("//div[@class='vresult']");
	$index = 1;
	$turl = "http://www.wikihow.com/" . urldecode($title->getPrefixedURL());
	foreach ($nodes as $node) {
		$links = $xpath->query(".//a[@class='l']", $node);
		foreach ($links as $link) {
			$href = $link->getAttribute("href");
			#echo "$href\n";
			#echo "+++{$doc->saveXML($link)}\n";
			if ($href == $turl) {
				$found[] = $title;
				return $index;
			}
			$index++;
		}
	}		

	#echo str_replace("<", "\n<", $doc->saveXML()); exit;
	$notfound[] = $title; 
	return -1;
}

	$check = wfTimestampNow();
	foreach ($urls as $u) {
		$u = trim(urldecode($u));
		if ($u == "") {
			continue;
		}
		$t = Title::newFromUrl($u);
		if (!$t) {
			echo "can't make t out of $u\n";
			continue;
		}
		$result = search($t); 
		echo "{$check},{$t->getText()},$result\n";
	}

