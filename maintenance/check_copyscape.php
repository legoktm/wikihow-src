<?
	require_once("commandLine.inc");
	require_once("copyscape_functions.php");

	// ignore articles created in last hour
	$cutoff = wfTimestamp(TS_MW, time() - 3600); 

	// the percentage of words match threshold
	$threshold = 0.25;

	$wgUser = User::newFromName("Copyviocheckbot");

	$dbr = wfGetDB(DB_MASTER);

	$tags = array("Category", "Image");
	/*
	$res = $dbr->select('recentchanges', 
		array('rc_namespace', 'rc_title'), 
		array('rc_namespace'=>NS_MAIN, 'rc_new' => 1, "rc_timestamp <'{$cutoff}'"), 
		"check_copyscape",
		array('ORDER BY'=>"rc_id DESC"
		//	, "LIMIT"=>100
		)
	);
	*/

	$checkstoday = $dbr->selectField('copyviocheck', array('count(*)'), array("cv_timestamp > '" . wfTimestamp(TS_MW, time() - 24 * 3600) . "'"));
	echo "Have done $checkstoday API checks in last 24 hours\n";

	$limit = max(1000 - $checkstoday, 0);
	if ($limit == 0) {
		echo "reached our limit, exiting\n";
	}

	// get all of the new pages, newest first that aren't redirects and haven't been already checked
	$res = $dbr->query("SELECT * FROM recentchanges 
		LEFT JOIN copyviocheck ON cv_page=rc_cur_id 
		LEFT JOIN page ON rc_cur_id = page_id 
		WHERE rc_namespace = 0 AND rc_new = 1 AND page_is_redirect=0  " 
		. (isset($argv[0]) ? " and page_id =  " . $argv[0] : " AND cv_page is null ")
		. " ORDER BY page_id desc LIMIT $limit");

	$index = 0;
	$found = 0;
	while ($row = $dbr->fetchObject($res)) { 

		if ($checkstoday > 1000) {
			echo "We have done $checkstoday in the last 24 hours, so we are going to call it a day so we don't kill copyscape.\n";
		}

		// build the title and check to see we are sane
		// rc_title stays the same when the page moves, so that's why we used page_title
		$t = Title::makeTitle($row->page_namespace, $row->page_title);
		if (!$t) {
			echo "Can't make title out of {$row->rc_title}\n";
		}
		$r = Revision::newFromTitle($t);
		if (!$r) {
			echo "Can't make title out of {$row->rc_title}\n";
		}
	
		// build the text to send to copyscape
		$text = $r->getText();
		
		// skip redirects, we'll get them anyway
		if (preg_match("@^#REDIRECT@m", $text)) {
			echo "{$t->getFullURL()} is a redirect\n";
			continue;
		}
		// does it already have a copyvio?
		if (preg_match("@\{\{copyvio@i", $text)) {
			echo "{$t->getFullURL()} has a copyvio tag already\n";
			continue;
		}

		// only focus on the steps and intro
		$sections = preg_split("@(^==[^=]*==)@m", $text, 0, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
		$text = "";
		if (!preg_match("@^==@", $sections[0])) {
			// do the intro
			$text = trim(array_shift($sections)) . "\n";
		}
		while (sizeof($sections) > 0) {
			if (preg_match("@^==[ ]*" . wfMsg('steps') . "@", $sections[0])) {
				$text .= $sections[1];
				break;
			}
			array_shift($sections);
		}

		// take out category and image links
		$text = preg_replace("@^#[ ]*@m", "", $text);
		foreach ($tags as $tag) {
			$text = preg_replace("@\[\[{$tag}:[^\]]*\]\]@", "", $text);
		}

		// take out internal links
		preg_match_all("@\[\[[^\]]*\|[^\]]*\]\]@", $text, $matches); 
		foreach ($matches[0] as $m) {
			$n = preg_replace("@.*\|@", "", $m);
			$n = preg_replace("@\]\]@", "", $n);
			$text = str_replace($m, $n, $text);
		}

		/* debugging 
		$text = preg_replace("@\{\{[^\}]*\}\}@", "", $text);
		if (strpos($text, "{{") !== false) {
			echo $text;
			exit;
		}
		if (preg_match("@\[\[@", $text)) {
			echo $text;
			exit;
		}
		*/

		// do the search
		$copyviourl = null;
		$match = null;
		$results = copyscape_api_text_search_internet($text, 'ISO-8859-1', 2);
		$checkstoday++;
		if ($results['count']) {
			$words = $results['querywords'];
			$index = 0;
			foreach($results['result'] as $r) {
				if (!preg_match("@^http://[a-z0-9]*.(wikihow|whstatic|youtube).com@i", $r['url'])) {
					if ($r['minwordsmatched'] / $words > $threshold) {
						// can we find a reference to us?
						$f = file_get_contents($r['url']);
						if (strpos($f, $t->getFullURL()) !== false) {
							echo "Got a reference to {$t->getFullURL()} on {$r['url']}\n";
							continue;
						}
						$match = number_format($r['minwordsmatched'] / $words, 2);
						echo "{$t->getFullURL()}\t{$r['url']}: $words,{$r['minwordsmatched']}, $match\n";
						$copyviourl = $r['url'];
						break;
					}
				}
			}
		}

		// apply the template if we found a violation
		if ($copyviourl) {
			// grab a fresh one from the fridge in case that the api is slow
			$r = Revision::newFromTitle($t);	
			$text = "{{copyviobot|" . preg_replace("@=@", "%3F", $copyviourl) . "|date=" . date("Y-m-d") . "|match={$match}}}\n" . $r->getText();
			$a = new Article($t); 
			$a->doEdit($text, "The Copyviocheckbot has found a potential copyright violation");
			$found++;
		}

		// log it so we don't check it again
		$dbw = wfGetDB(DB_MASTER);
		$dbw->query("INSERT INTO copyviocheck VALUES ({$t->getArticleID()}, '" . wfTimestampNow() . "', 1, " . ($copyviourl == null? 0 : 1) . ")
			on DUPLICATE KEY update cv_timestamp='" . wfTimestampNow() . "', cv_checks = cv_checks + 1");

		if ($found == 10) {
	//		break;
		}
		$index++;
	}
