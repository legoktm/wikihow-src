<?
	require_once('commandLine.inc');

	$f = file_get_contents($argv[0]);
	
	$parts =  preg_split("@(^==.*)@im", $f,
                   0, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

	$dbw = wfGetDB(DB_MASTER);
	$dbw->query('delete from rssfeed');
	while ($p = array_shift($parts)) {
		if (preg_match("@^==[ ]*\d*-\d*@", $p)) {
			$date = trim(preg_replace("@[=]@", "", $p));
			while (sizeof($parts) > 0 && !preg_match("@==@", $parts[0])) {
				$url = trim(array_shift($parts));
				#echo "$date - $url\n";
				$ts = preg_replace("@-@", "", $date) . "000000";
				$alt = null;
				if (strpos($url, " ") !== false) {
					$alt = preg_replace("@^.* @U", "", $url);
				}
				$url = preg_replace("@ .*@", "", $url);
				$t = Title::newFromURL(urldecode(preg_replace("@http://www.wikihow.com/@", "", $url)));
				if (!$t) {
					echo "can't build title from $url\n";
					continue;
				}
				if (!$t->getArticleID()) {
					echo "no article for $url\n";
					continue;
				}
				$dbw->insert('rssfeed', array('rss_page'=>$t->getArticleID(), 'rss_timestamp'=>$ts, 'rss_approved'=>1, 'rss_alt_title'=>$alt));
			}	
		}
	}
