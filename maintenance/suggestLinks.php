<?
require_once( "commandLine.inc" );


	$wgUser = User::newFromName("LinkTool");

	$ignore = array_flip(split("\n", wfMsgForContent('Link_suggestions_phrases_to_ignore')));

	$limit = $argv[0];
	if ($limit == "") $limit = 1000;
	
	$dbr =& wfGetDB( DB_SLAVE );
	$res = $dbr->select('page', 
			array( 'page_title', 'page_namespace'),
			array ('page_is_redirect' => 0, 'page_namespace' => 0),
			"findInlineImages",
			array ("ORDER BY" => "page_counter desc", "LIMIT" => $limit)
			);
	$titles = array();
	while ( $row = $dbr->fetchObject($res) ) {
		$title = Title::makeTitle( $row->page_namespace, $row->page_title );
		if ($title) $titles[]  = $title;
	}	
	$dbr->freeResult($res);

	echo "got " . sizeof($titles) . " titles\n";
	
	// check for phrases
    $start = "\[\[";
    $end = "\]\]";
	$count = 0;
	$captions = array();
	foreach ($titles as $t) {
		$r = Revision::newFromTitle($t);
		$text = $r->getText();
		#if (preg_match_all("/$start[^:\|]*|[^\]]*$end/im", $text, $matches) > 0) {
		if (preg_match_all("/{$start}[^\]]*{$end}/", $text, $matches) > 0) {
			foreach ($matches[0] as $m) {
				if (strpos($m, "|") === false) continue;
				if (strpos($m, "[[:") === 0) continue;
				if (strpos($m, "Special:") !== false) continue;
				$i = strpos($m, "|");
				$caption = substr($m, $i + 1, strlen($m) - $i - 3);
				$link = substr($m, 2, strpos($m, "|") - 2);
				if (strpos($link, ":") !== false) continue;
				if ($caption == "How to $link") continue;
				if (strtolower(trim($caption)) == strtolower(trim($link))) continue;
				if (!isset($captions[$caption]))
					$captions[$caption] = array();

        		while (!ereg("[a-zA-Z0-9)\"]$", $caption)) {
            		$caption = substr($caption, 0, strlen($caption) - 1);
        		}
		 		if (isset($captions[$caption][$link])) {
					$captions[$caption][$link]++;
				} else {
					$captions[$caption][$link] = 1;
				}	
			}
		}		
	}
	foreach ($captions as $key=>$c) {
		foreach ($c as $link=>$counter) {
			if ($counter == 1) continue;
			echo "$key\t$link\t$counter\n";
		}
	}
	return;

	$count = 0;
	$updated = 0;
	foreach ($titles as $t) {
		$r = Revision::newFromTitle($t);
		if (!$r) continue;
		$text = preg_replace("/\[\[[^\]]*\]\]/", "", $r->getText());
		$text = strip_tags($text);
		$replacements = 0
		foreach ($titles as $x) {
			if ($x->getText() == $t->getText()) continue;
			if (isset($ignore[strtolower($x->getText())])) continue;
			$search = strtolower($x->getText());
			$search = str_replace("/", '\/', $search);
			$search = str_replace("(", '\(', $search);
			$search = str_replace(")", '\)', $search);
			#echo "trying $search\n";
			$now = 0;
			$text = preg_replace("/\b($search)\b/im", "[[{$x->getText()}|\0]]", $text, 1,  &$now);
			if ($now > 0) {
				echo "Adding link to {$t->getText()} pointing to {$x->getText()}\n";
			}
			$replacements += $now;
			$count++;
			if ($replacements > 10) 			
				break;
		}
		echo "replacements $replacements\n";
		if ($replacements > 0) {
			$a = new Article($t);
			if (!$a->updateArticle($text, "LinkTool is adding links", true, false)) {
				echo "couldn't update article {$t->getText()}, exiting...\n";
				exit;
			}
			$updated++;
		}
		if ($updated == 5) break;
	}	
	echo "checked " . number_format($count) . " articles\n";	
?>
