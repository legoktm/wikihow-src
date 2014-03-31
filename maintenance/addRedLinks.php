<?
require_once( "commandLine.inc" );

$debug_on = false;
function debug($str) {
	global $debug_on;
	if ($debug_on) echo "$str\n";
}

$wgMaxReplacementsPerArticle = 10;
$wgMaxPhraseWords = 2;
$wgMaxPhrases = 1000;

function getPhrases ($dbr,$length, $num) {
	$phrases = array();
	debug( "Chcking $length, $num\n");
    $res = $dbr->select('suggested_titles',
            array( 'st_title'),
            array ('st_used' => 0,
                #'page_id in (22911, 5907)',
            ),
            "tmpTool",
            array ("ORDER BY" => "rand()")
            );
    while ( $row = $dbr->fetchObject($res) ) {
        $title = Title::newFromText($row->st_title);
        if (isset($ignore_phrases[strtolower($title->getText())])) continue;
        if ($title && preg_match_all('@\W@i', $title->getText(), $matches) < $length) $phrases[]  = $title;
        if (sizeof($phrases) >= $num) break;
    }       
    $dbr->freeResult($res);
	return $phrases;
}

function replaceText($search, $replace, &$text) {
	global  $wgMaxReplacementsPerArticle;
    $newtext = "";  
 	$i = $j = 0;
	$now = 0; // # the number of replaceuments, limit it to 2 per article
		
	// walk the article ignoring links
  	while ( ($i = strpos($text, "[", $i)) !== false) {
	#debug("\t\ti: {$i}");
   		if (substr($text, $i+1, 1) == "[") 
			$i++;
   		$stext = substr($text, $j, $i - $j);
		$y = $now >  $wgMaxReplacementsPerArticle ? 0 : -1;
		$newtext .= preg_replace($search, $replace, $stext, 1,  &$y);
		$now += $y;
       	$j = $i;
     	$i = strpos($text, "]", $i);
       	if ($i !== false) {
       		$newtext  .= substr($text, $j, $i - $j);
       		$j = $i;
       	} else {
         	$newtext  .= substr($text, $j, strlen($text) - $j);
			$j = strlen($text);
			break;
		}
	}
	if ($now < $wgMaxReplacementsPerArticle) 
		$newtext .= preg_replace($search, $replace, substr($text, $j, strlen($text) - $j), 1,  &$y);
	else
		$newtext .= substr($text, $j, strlen($text) - $j);
	$text = $newtext;
	return $now;
}
	$wgUser = User::newFromName("LinkTool");
	$dbw = wfGetDB(DB_MASTER);

	# get a list of things to ignore
	# ex: How to Bowl is an article, but "Bowl" is ambiguious
	$ignore_phrases = array_flip(split("\n", strtolower(wfMsgForContent('Linktool_ignore_phrases'))));
	$ignore_titles	= array_flip(split("\n", strtolower(wfMsgForContent('Linktool_ignore_articles'))));

	# default: check 1000 articles
	$limit = $argv[0];
	if ($limit == "") $limit = 1000;

	# get a list of articles to check	
	$dbr =& wfGetDB( DB_SLAVE );
	$res = $dbr->select('page', 
			array( 'page_title', 'page_namespace'),
			array ('page_is_redirect' => 0, 'page_namespace' => 0,
				#'page_id in (22911, 5907)',
				//'page_title' => 'Make-a-Bow-and-Arrow',
			),
			"findInlineImages",
			//array ("ORDER BY" => "rand()", "LIMIT" => $limit)
			array ("ORDER BY" => "page_counter desc", "LIMIT" => "$limit")
			);
	$titles = array();
	while ( $row = $dbr->fetchObject($res) ) {
		$title = Title::makeTitle( $row->page_namespace, $row->page_title );
		if (isset($ignore_titles[strtolower($title->getText())])) continue;
		if ($title) $titles[]  = $title;
	}

	$phrases = array();
	for ($i = 0; $i < $wgMaxPhraseWords ; $i++) {
		$phrases = array_merge($phrases,getPhrases($dbr, $i+1, $wgMaxPhrases - sizeof($phrases)));
		if (sizeof($phrases) >= $wgMaxPhrases) break;
	}

	echo "got " . number_format(sizeof($titles), 0, "", ",") . " titles, and " . number_format(sizeof($phrases), 0, "", ",")  ." phrases\n";

	// build the  search and replace arrays
	$fb = "[^a-zA-Z0-9_|\[\]]";
	$search = array();
	$replace = array();
	foreach ($phrases as $x) {
		$s = strtolower($x->getText());
		$s = str_replace("/", '\/', $s);
		$s = str_replace("(", '\(', $s);
		$s = str_replace(")", '\)', $s);
		$search[] = "/($fb)($s)($fb)/im";
		$replace[] = "$1[[{$x->getText()}|$2]]$3";
	}

	$count = 0;
	$updated = 0;
	$totallinks = 0;
	$tcount = 0;
	#print_r($search); exit;
	foreach ($titles as $t) {

		# skip titles that have been recently updated
		#if ($dbw->selectField('recentchanges', array('count(*)'), array('rc_title'=> $t->getDBKey(), 'rc_user_text'=>'LinkTool')) > 0) {
		#	echo "skipping {$t->getText()} because LinkTool recently edited this article\n";
		#	continue;
		#}
		$r = Revision::newFromTitle($t);
		if (!$r) continue;
		
		$text = $r->getText();
		debug("checking {$t->getText()}");

		$now = replaceText($search, $replace, $text);

		echo("{$t->getText()} - $now links added\n");
		if ($now > 0) {
			$wgTitle = $t;
			$updated++;
			//echo "could update {$t->getText()} with $replacements new links\n";
			/*	$a = new Article($t);
			if (!$a->updateArticle($text, "LinkTool is sprinkling some links", true, false)) {
				echo "couldn't update article {$t->getText()}, exiting...\n";
				exit;
			}	
			*/
		}
		$total += $now;
		$wgTitle = null;
		$tcount++;
		#debug("$tcount");
	}	
	echo "checked " . number_format($count, 0, "", ", ") . " articles, " . number_format($total, 0, "", ",") . " new links\n";	
?>
