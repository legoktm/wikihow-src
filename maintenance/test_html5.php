<?

require_once('commandLine.inc');

function process($title) {
	global $wgOut, $wgTitle;
	$rev = Revision::newFromTitle($title);
	if (!$rev) continue;
	#echo "Checkng {$title->getText()}\n";
	$text = $rev->getText();
	$oldtext = $text;
	wfRunHooks('ArticleBeforeOutputWikiText', array(&$article, &$text));
	$html = $wgOut->parse($text);
	$wgTitle = $title;
	$html = WikihowArticleHTML::postProcess($html, array('no-ads'=>0));
	$editor = new Html5editor();
	$newtext = $editor->convertHTML2Wikitext($html, $oldtext);
	echo "{$title->getFullURL()} - {$title->getArticleID()}\n";
}

// good for debuggin individual articles
if (isset($argv[0])) {
	$title = Title::newFromID($argv[0]);
	process($title);
	exit;
}

// debug a random one
$dbr = wfGetDB(DB_SLAVE);
$res = $dbr->select('page', 
		array('page_title', 'page_namespace'), 
		array('page_namespace' => 0, 'page_is_redirect' => 0),
		"test_html5_images",
		#array ("ORDER BY" => "page_counter desc", "LIMIT"=>500)
		array ("ORDER BY" => "rand()", "LIMIT"=>1)
	);

while ($row = $dbr->fetchObject($res)) {
	$title = Title::makeTitle($row->page_namespace, $row->page_title);
	process($title);
	#echo "{$title->getFullURL()}\n\nold:$text\n\nnew:$newtext\n";
}

