<?

require_once( "commandLine.inc" );
$wgUser = User::newFromName("LinkTool");

$ignore_pages = array(
	"main page",
	"about wikihow",
	);

$ignore_words = explode(",", "get,create,choose,stop,clean,avoid,keep,start,tell,remove,take,draw,survive,install,change,put,know,prepare,act,buy,cope,not,convince,set,win,fix,add,care,learn,convert,eat,throw,perform,give,improve,understand,apply,how,cook,teach,treat,save,turn,grow,organize,beat,catch,talk,run,plan,read,rid,of,your,own,be");

function getPhrases ($filename) {
    $phrases = array();
    $f = file_get_contents($filename);
    $lines = split("\n", $f);
	shuffle($lines); // why not?
    foreach ($lines as $line) {
        if (trim($line)== "") continue;
        $tokens = split("\t", $line);
        $phrases[$tokens[0]] = $tokens[1];	
	}
    return $phrases;
}


function recentlyProcessedPhrase($phrase) {
    $dbr = wfGetDB(DB_SLAVE);
    $count = $dbr->selectField('recentchanges',
                    array('count(*)'),
                    array(
						//'rc_cur_id' => $result->getArticleID(), 
                        'rc_comment' => "Adding keyword links for phrase " . $phrase
                        )   
        );
    return $count > 0;
}   
function recentlyEdited($result, $phrase) {
	$dbr = wfGetDB(DB_SLAVE);
	$count = $dbr->selectField('recentchanges',
					array('count(*)'),
					array('rc_cur_id' => $result->getArticleID(), 
						'rc_comment' => "Adding keyword links for phrase " . $phrase
						)
		);
	return $count > 0;
}

function markPatrolled() {
    $dbw = wfGetDB(DB_MASTER);
    $dbw->update('recentchanges',
            array('rc_patrolled=1'),
			array('rc_user_text' => "LinkTool")
        );
    return $count > 0;
}
# takes an article ($result), and links occurences 
# of $phrase with a link to $title
function smartReplace($phrase, $title, $result, &$numreplace) {

	#fake word boundary
	$fb = "[^a-zA-Z0-9_|\[\]]";
	#echo "Smart replace called on p: {$phrase}, t:{$title}, r:{$result->getText()}\n";
	$tObj = Title::newFromText($title);
	if (!$tObj  || $tObj->getArticleID() == 0) {
		#echo "No article for $title\n";
		return;
	}
	$title = $tObj->getText();
	$rev = Revision::newFromTitle($result);
	if (!$rev) return 0;
	$text = $rev->getText();
	if (stripos($text, $phrase) === false) return 0;

	# @(\[{1,2}[^\]]*\]{1,2})@m
	$re = '\[{1,2}[^\]]*\]{1,2}|'
		. '\<[^>]*>|'
		.'\{{1,2}[^\}]*\}{1,2}';

	$parts  = preg_split('@(' . $re . ')@m', $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

	$ret = "";
	$replaced = 0;
	$inlink = false; // for hardcoded <a href=> links.
	while ($p = array_shift($parts)) {
		if (strpos($p, "[") === 0)  {
		} else if (strpos($p, "{") === 0) {
		} else if (stripos($p, "<a") === 0) {
			$inlink = true;
		} else if (stripos($p, "</a>") === 0) {
			$inlink = false;
		} else if (!$inklink) {
			$p = preg_replace("@($fb)({$phrase}[s]?)($fb)@im", "$1[[{$title}|$2]]$3", $p, 1, $replaced);
		}
		$ret .= $p;
		if ($replaced > 0) 
			break;
	}

	// anything at the end? 
	$ret .= implode("", $parts);
	if ($replaced > 0) {
		#debug this a bit
		$a = new Article($result);
		if (!recentlyEdited($result, $phrase)) {
			if ($ret == $text) {
				echo "No changes detected for {$result->getText()}, WTF?\n";
			} else if ($a->updateArticle($ret, "Adding keyword links for phrase " . $phrase, true, false)) {
				$numreplace = $replaced;
				echo "{$phrase}\t{$title}\t{$result->getText()}\n";
			} else {
				echo "Couldn't update article {$result->getText()}, WTF?\n";
			}
		} else {
			#echo "{$result->getText()} was recently linked... skipping.\n";
		}
	} else {
		#echo "Couldn't find {$phrase} in this text\n\n";
		#echo "$text ------\n\n------\n\n";
	}
	return $ret;
}

function getFirstWord($t) {
	global $ignore_words;
	$words = preg_split("/\s/", strtolower($t));
	foreach ($words as $w) {
		$ret .= $w . " ";
		if (!in_array($w, $ignore_words)) {
			break;
		}	
	}
	return trim($ret);
}
$phrases = getPhrases(isset($argv[0]) ? $argv[0] : "/var/www/html/wiki/maintenance/keywords_to_link.txt");

# iterate over the list of phrases
$totallinks = 0;
foreach ($phrases as $phrase=>$title) {

	// skip ones we've already done recently
	if (recentlyProcessedPhrase($phrase))
		continue;
	$titleObj = Title::newFromText($title);
	if (!$titleObj) {
		$errs .= "<li>Could not generate at title for '{$title}'</li>";
		continue;
	}
	if ($titleObj->getArticleID() == 0) 
		$titleObj = Title::newFromURL($title);
	
	if (!$titleObj || $titleObj->getArticleID() == 0) {
		$errs .= "<li>The article for '{$title}' does not exist</li>";
		continue;
	}

	# get results from the Google Mini
	$l = new LSearch();
	$results = $l->googleSearchResultTitles('"' . $phrase. '"');
	$newresults = array();

	# filter out some of the results (links to their own pages, videos, etc)
	foreach ($results as $r) {
		if (strtolower($r->getText()) == strtolower($title)) continue;
		if ($r->getNamespace() != NS_MAIN || strpos($r->getText(), "Video/") !== false) continue;
		$a = new Article($r);
		if ($a->isRedirect()) continue;
		if (in_array(strtolower($r->getText()), $ignore_pages)) continue;
		$first_w_r = getFirstWord($r->getText());
		$first_w_p = getFirstWord($phrase);
		if ($first_w_r == $first_w_p) {
			#echo "The first words ({$first_w_p}, {$first_w_r}) match for phrase '{$phrase}' and result '{$r->getText()}', skipping...\n";
			continue;
		} else {
			#echo "({$first_w_p}, {$first_w_r}) DO NOT match for phrase {$phrase} and result {$r->getText()}\n";
		}
		$newresults[] = $r;
	}

	# any results?
	$newlinks = 0; // # of new links linking phrase to title
	foreach ($newresults as $r) {
		$numreplace= 0;
		smartReplace($phrase, $title, $r, &$numreplace);
		$newlinks += $numreplace;
		$totallinks += $numreplace;
		markPatrolled();
	}
	if ($totallinks >= 500) {
		echo "Created $totallinks ... exiting..\n";
		break;
	}
}
