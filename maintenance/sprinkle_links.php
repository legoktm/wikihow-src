<?

require_once("commandLine.inc");
require_once("Newarticleboost.body.php");


function shuffle_assoc($input_array){
   foreach($input_array as $key => $value){
      $temp_array[$value][key] = $key;
      }
   shuffle($input_array);
   foreach($input_array as $key => $value){
      $return_array[$temp_array[$value][key]] = $value;
      }
   return $return_array;
}

$username = "LinkSprinkler";
$summary = "updating with sprinkled links";

$dbr = wfGetDB(DB_SLAVE);
$wgUser = User::newFromName($username); 
$wgUser->load();
$wgUser->mGroups[] = "sysop"; 

// build REs
$kw = array(); 
$count = array();
$linked = array();
$lines = split("\n", file_get_contents($argv[0]));
foreach ($lines as $l) {
	$l = trim($l);
	if ($l  == "") continue;
	$tokens = split("\t", $l);
	$url = preg_replace("@^/@", "", $tokens[0]);
	$url = preg_replace("@^http://www.wikihow.com/@", "", $url);
	$dest = Title::newFromURL($url);
	if (!$dest) {
		echo "cant get it from $line\n"; exit;
	}
	$words = split(",", $tokens[1]);
	foreach($words as $w) {
		$w = trim(str_replace('"', '', $w));
		$kw[$w] = $dest->getFullText();
		$count[$w] = 0;
		$linked[$dest->getFullText()] = 0;
	}
}

$titles = array();
$used = array(); 
foreach ($kw as $k=>$text) {
	$l = new LSearch();
   	$hits  = $l->googleSearchResultTitles('"' . $k . '"',0, 30);
	foreach ($hits as $h) {
		if ($h->getNamespace() == NS_MAIN && !isset($used[$h->getText()])) {
			$used[$h->getText()] = 1;
			$titles[] = $h;
		}
	}
}



/*
$res = $dbr->select('page', array("page_namespace", "page_title"), 
	array("page_namespace"=>NS_MAIN, "page_is_redirect=0", "page_id not in (5)" ), "sprinkle_links", 
	array("ORDER BY"=>"page_counter desc", "LIMIT"=>20000));

$titles = array();
while ($row = $dbr->fetchObject($res)) {
	$t = Title::makeTitle($row->page_namespace, $row->page_title);
	if (!$t) continue;
	$titles[] = $t;
}
*/

#echo sizeof($titles); exit;
$index = 0;
$old = wfTimestamp(TS_MW, time() - 60 * 60);
foreach ($titles as $t) {
	$recentedits = $dbr->selectField('recentchanges', array('count(*)'), array('rc_cur_id'=>$t->getArticleID(), 'rc_user_text'=>$username, "rc_timestamp > '{$old}'")); 
	if ($recentedits > 0) {
		echo "{$t->getText()} was recently edited, skipping.\n";
		continue;
	}	
	$r = Revision::newFromTitle($t);
	$kw = shuffle_assoc($kw);
	if (!$r) continue;
	$text = $r->getText();
	$parts = preg_split("@(\[\[[^\]]*\]\]|\{\{[^\}]*\}\}|\[[^\]]*])@", $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY); 
	$changed = false;
	$onthispage = 0; 
	$newtext = $text;
	$thisround = array();
	foreach ($parts as $p) {
		$newp = $p;
		foreach ($kw as $w=>$dest) {
			if (isset($thisround[$w])) break;
			if ($dest == $t->getText()) continue; // don't link to yourself
			if ($count[$w] >= 15) continue; // dont put more than 15 links to a page at a time
			if (preg_match("@^\[|\{@", $p)) continue; // don't link inside of an internal, external link or a template
			$newp  = preg_replace("@\b({$w})\b@i", "[[{$dest}|$1]]", $newp, 1, &$rep);
			if ($rep > 0) {
				$thisround[$w] = 1;
				$count[$w]++; 
				$changed = true;
				$onthispage++;
				$linked[$dest] += $rep;
				#print_r($count);  exit;
			}
		}
		$newtext = str_replace($p, $newp, $newtext);
		if ($onthispage >= 5) break; 
	}
	if ($changed) {
		$a = new Article($t);
		if(!$a->doEdit($newtext, $summary)) {
			echo "can't update {$t->getFullText()}\n";
		};
		echo "updating {$t->getFullText()}\n";
	}
	$index++;
	if ($index % 100 == 0) {
		echo date(DATE_RFC822) . ": $index\n";
	}
}

foreach ($kw as $w=>$dest) {
	if ($linked[$dest] == 0) {
		$t = Title::newFromText($dest);
		AddRelatedLinks::addLinkToRandomArticleInSameCategory($t, $summary); 
	}	
}


