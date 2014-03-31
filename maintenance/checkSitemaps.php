<?
//
// Check the wikihow sitemap at en and intl URLs to make sure it can be 
// retrieved. Displays a message if anything goes wrong and nothing otherwise.
//

$sitemaps = array(
	array('url' => 'http://www.wikihow.com/sitemap_index.xml', 'min' => '120000'),
	array('url' => 'http://pt.wikihow.com/sitemap.xml', 'min' => '1000'),
	array('url' => 'http://de.wikihow.com/sitemap.xml', 'min' => '1000'),
	array('url' => 'http://es.wikihow.com/sitemap.xml', 'min' => '1000'),
);

function pullXML($url) {
	$ch = curl_init($url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_ENCODING, 'gzip');
	$out = curl_exec($ch);
	// remove this line: <?xml version="1.0" encoding="UTF-8"? >
	//if (preg_match('@^\<\?xml@', $out)) {
	//	$pos = strpos($out, "\n");
	//	if ($pos) {
	//		$out = substr($contents, $pos+1, strlen($contents));
	//	}
	//}
	return $out;
}

function checkSitemap($url) {
	$count = 0;
	try {
		$xml = pullXML($url);
		$doc = new DOMDocument('1.0', 'UTF-8');
		$doc->loadXML($xml);

		// Check if we have a sitemap index
		$sitemaps = $doc->getElementsByTagName('sitemap');
		foreach ($sitemaps as $elem) {
			$newurl = $elem
				->getElementsByTagName('loc')
				->item(0)
				->nodeValue;
			$count += checkSitemap($newurl);
		}

		// If not, treat it like a real sitemap
		if (!$count) {
			$locs = $doc->getElementsByTagName('loc');
			foreach ($locs as $node) {
				$count++;
			}
		}
	} catch (Exception $e) {
		$count = 0;
	}

	return $count;
}

// Display a message if anything goes wrong.
function checkSitemaps($sitemaps) {
	$msg = "";
	foreach ($sitemaps as $sitemap) {
		$count = checkSitemap($sitemap['url']);
		if ($count < $sitemap['min']) {
			$msg .= "Could not retrieve, parse or find {$sitemap['min']} URLS in sitemap: {$sitemap['url']}\n";
		}
	}
	print $msg;
}

checkSitemaps($sitemaps);

