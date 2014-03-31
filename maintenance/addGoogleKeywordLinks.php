<?
require_once( "commandLine.inc" );

$debug_on = false;
function debug($str) {
	global $debug_on;
	if ($debug_on) echo "<!---$str--->\n";
}


$wgMaxReplacementsPerArticle = 10;
$wgMaxPhraseWords = 2;
$wgMaxPhrases = 1000;
$wgMaxNewInboundLinks = 20; 

function getPhrases () {
	$phrases = array();
	$f = file_get_contents("/home/tderouin/KeywordsForTravisEdited2.txt");
	$lines = split("\n", $f);
	foreach ($lines as $line) {
		if (trim($line)== "") continue;
		$tokens = split("\t", $line);
		$phrases[$tokens[0]] = $tokens[1];
		debug("{$tokens[0]}\t\t{$tokens[1]}");
	}
	return $phrases;
}

function doReplace($search, $replace, $baretext, $text, &$y) {
	global $titles, $desttitles, $wgMaxNewInboundLinks;
	if ($y == 0) return;
	for ($i = 0; $i < sizeof($search); $i++) {
		if ($titles[$desttitles[$i]] >= $wgMaxNewInboundLinks) {
			debug("Got {$destitles[$titles[$i]]} links already for {$desttitles[$i]}, skipping");
			continue;
		}
		$text =preg_replace($search[$i], $replace[$i], $text, -1, $count);
		if ($count > 0) {
			echo "<li>{$baretext[$i]}</li>\n";
			$y += $count;
		}
	}
	return $text;

}
function replaceText($search, $replace, $baretext, &$text) {
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
		#$haha = preg_replace($search, $replace, $stext, 1,  &$y);
		$haha = doReplace($search, $replace, $baretext, $stext, &$y);
		#echo "$now\n";
		if ($haha != $newtext) {
			#echo "<tr><td>{$stext}</td><td>{$haha}</tr>\n";
		}
		$newtext .= $haha;
		if ($y > 0) 
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
		if ($now > $wgMaxReplacementsPerArticle) 
			break;
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
		if ($title) $titles[$title->getText()]  = 0;
	}

	$phrases = getPhrases();

	echo "got " . number_format(sizeof($titles), 0, "", ",") . " titles, and " . number_format(sizeof($phrases), 0, "", ",")  ." phrases\n";

	// build the  search and replace arrays
	$fb = "[^a-zA-Z0-9_|\[\]]";
	
	$replace = array();
	$destitles = array();
	$baretext = array();
	foreach ($phrases as $key=>$link) {
		$s = strtolower($key);
		$s = str_replace("/", '\/', $s);
		$s = str_replace("(", '\(', $s);
		$s = str_replace(")", '\)', $s);
		$search[] = "/($fb)($s)($fb)/im";
		$replace[] = "$1[[{$link}|$2]]$3";
		$baretext[] = "$s - $link";
		$desttitles[] = $links;
	}

	#print_r($search); print_r($replace); exit;
	$count = 0;
	$updated = 0;
	$totallinks = 0;
	$tcount = 0;
	#print_r($search); exit;
	#echo "<table>";
	foreach ($titles as $t=>$c) {

		# skip titles that have been recently updated
		#if ($dbw->selectField('recentchanges', array('count(*)'), array('rc_title'=> $t->getDBKey(), 'rc_user_text'=>'LinkTool')) > 0) {
		#	echo "skipping {$t->getText()} because LinkTool recently edited this article\n";
		#	continue;
		#}
		$titleObj = Title::newFromText($t);
		if (!$titleObj) continue;
		$r = Revision::newFromTitle($titleObj);
		if (!$r) continue;
		
		$text = $r->getText();
		debug("checking {$titleObj->getText()}");

		#echo "<tr><td colspan='2'><b>{$t->getText()}</td></tr>\n";
		echo "<b>{$titleObj->getText()}</b><ul>\n";
		$now = replaceText($search, $replace, $baretext, $text);
		echo "</ul>\n";
		#echo("{$t->getText()} - $now links added\n");
		if ($now > 0) {
			$wgTitle = $titleObj;
			$updated++;
			//echo "could update {$t->getText()} with $replacements new links\n";
			$a = new Article($titleObj);
			#if (!$a->updateArticle($text, "LinkTool is sprinkling some RED links", true, false)) {
			#	echo "couldn't update article {$t->getText()}, exiting...\n";
			#	exit;
			#}	
		}
		$total += $now;
		$wgTitle = null;
		$tcount++;
		#debug("$tcount");
	}	
	echo "</table>";
	echo "checked " . number_format($count, 0, "", ", ") . " articles, " . number_format($total, 0, "", ",") . " new links\n";	
?>
