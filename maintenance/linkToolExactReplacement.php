<?
require_once( "commandLine.inc" );

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
			),
			"findInlineImages",
			array ("ORDER BY" => "page_counter desc", "LIMIT" => $limit)
			);
	$titles = array();
	while ( $row = $dbr->fetchObject($res) ) {
		$title = Title::makeTitle( $row->page_namespace, $row->page_title );
		if (isset($ignore_titles[strtolower($title->getText())])) continue;
		if ($title) $titles[]  = $title;
	}	
	$dbr->freeResult($res);

	echo "got " . sizeof($titles) . " titles\n";
	$count = 0;
	$updated = 0;
	foreach ($titles as $t) {

		# skip titles that have been recently updated
		if ($dbw->selectField('recentchanges', array('count(*)'), array('rc_title'=> $t->getDBKey(), 'rc_user_text'=>'LinkTool')) > 0) {
			echo "skipping {$t->getText()} because LinkTool recently edited this article\n";
			continue;
		}
		$r = Revision::newFromTitle($t);
		if (!$r) continue;
		
		$replacements = 0;
		$text = $r->getText();
		echo "checking {$t->getText()}\n";
		foreach ($titles as $x) {
		
			# don't link to yourself silly
			if ($x->getText() == $t->getText()) continue;
			if (isset($ignore_phrases[strtolower($x->getText())])) continue;

			$search = strtolower($x->getText());
			$search = str_replace("/", '\/', $search);
			$search = str_replace("(", '\(', $search);
			$search = str_replace(")", '\)', $search);
			#echo "trying $search\n";

			# fake word boundary
			$fb = "[^a-zA-Z0-9_|\[\]]";
		    $newtext = "";  
    		$i = $j = $y = 0;
			$now = 0; // # the number of replaceuments, limit it to 2 per article
			// walk the article ignoring links
    		while ( ($i = strpos($text, "[", $i)) !== false) {
       			if (substr($text, $i+1, 1) == "[") $i++;
        		$stext = substr($text, $j, $i - $j);
				#echo "\n\n--------data - search $search-----\n$stext\n";
				if ($now < 2)
					$newtext .= preg_replace("/($fb)($search)($fb)/im", "$1[[{$x->getText()}|$2]]$3", $stext, 1,  &$y);
				else
					$newtext .= $stext;
				#echo "\n\n--------new text - search $search $y replacements-----\n$newtext\n";
				$now += $y;
				#echo "now $now, y $y\n";
        		$j = $i;
				if ($i > strlen($text)) {
					echo "$i is longer than " . strlen($text) . " exiting\n";
					exit;
				}
        		$i = strpos($text, "]", $i);
        		if ($i !== false) {
            		$newtext  .= substr($text, $j, $i - $j);
            		$j = $i;
        		}
    		}
			if ($now < 2) 
				$newtext .= preg_replace("/($fb)($search)($fb)/im", "$1[[{$x->getText()}|$2]]$3", substr($text, $j, strlen($text) - $j), 1,  &$y);
			else
				$newtext .= substr($text, $j, strlen($text) - $j);

			$text = $newtext; 
			if ($now > 0) {
				echo "Adding link to {$t->getText()} pointing to {$x->getText()}\n";
			}
			$replacements += $now;
			#echo "now $now\n";
			$count++;
			#if ($replacements > 1) 			
			#	break;
			$dbw->query("update recentchanges set rc_patrolled=1 where rc_user_text='LinkTool'");
		}
		if ($replacements > 0) {
			$wgTitle = $t;
			$a = new Article($t);
			if (!$a->updateArticle($text, "LinkTool is sprinkling some links", true, false)) {
				echo "couldn't update article {$t->getText()}, exiting...\n";
				exit;
			}	
			echo "updated {$t->getText()}\n";
			$wgTitle = null;
			$updated++;
		}
		if ($updated == 100) {
			 echo "updated $updated articles, breaking...\n";
			 break;
		}
	}	
	echo "checked " . number_format($count) . " articles\n";	
?>
