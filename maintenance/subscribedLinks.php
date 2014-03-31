<?
require_once( "commandLine.inc" );

	$maxTextLength = 80;
	$dbr =& wfGetDB( DB_SLAVE );
	$res = $dbr->select('page', 
			array( 'page_title', 'page_namespace'),
			array ('page_is_redirect' => 0, 'page_namespace=0'),
			"findInlineImages",
			array("ORDER BY" => "page_counter desc", "LIMIT" => "1000")
			);
	echo "<Results>\n";
	echo "<AuthorInfo description='How-to subscribed links' author='wikiHow'/>\n";
	while ( $row = $dbr->fetchObject($res) ) {
		$t = Title::makeTitle( $row->page_namespace, $row->page_title );
		if (!$t) continue;
		if ($t->getText() == "Main Page" || $t->getText() == "Spam Blacklist" || $t->getText() == "Categories") continue;
		$wgTitle = $t; // just cuz
		// get the summary
		$r = Revision::newFromTitle($t);
		$summary = Article::getSection($r->getText(), 0); 
		$summary = strip_tags($wgOut->parse($summary));
                // trip out all MW and HTML tags
                $summary = ereg_replace("<.*>", "", $summary);
                $summary = ereg_replace("\[\[.*\]\]", "", $summary);
                $summary = ereg_replace("\{\{.*\}\}", "", $summary);

		// split up the first setence of the summary into 3 chunks of less than $maxTextLength chars
		$t_array = array('', '', '');
		$s_index = 0;
		//if (strpos($summary, ".") !== false) 
		// $summary = substr($summary, 0, strpos($summary, "."));
		$s_array = split(" ", $summary);
		for ($i = 0; $i < sizeof($t_array) && $s_index < sizeof($s_array); $i++) {
			while (strlen($t_array[$i] . " " . FeedItem::xmlEncode($s_array[$s_index])) < $maxTextLength 
				&& $s_index < sizeof($s_array)) {
				$t_array[$i] .= " " . FeedItem::xmlEncode($s_array[$s_index]);
				$s_index++;
			}
			$t_array[$i] = trim($t_array[$i]);
			/*
			if ($i == sizeof($t_array) - 1 && $s_index < sizeof($s_array) - 1 ) {
				if (strlen($t_array[$i]) < $maxTextLength - 3)
					$t_array[$i] .= "...";
				else
					$t_array[$i] = substr($t_array[$i], 0, strlen($t_array[$i]) - 3) . "...";
			} else {
			}
			*/
		}
		$key 	= FeedItem::xmlEncode($t->getPrefixedURL());
		$query 	= FeedItem::xmlEncode(strtolower($t->getText()) );
		$title 	= FeedItem::xmlEncode(wfMsg('howto', $t->getText()) );
		
		echo "
	<ResultSpec id='{$key}'>
	<Query>{$query}</Query>
  	<Response>
    		<Output name='title'>{$title}</Output>
    		<Output name='more_url'>{$t->getFullURL()}</Output>
    		<Output name='text1'>{$t_array[0]}</Output>
    		<Output name='text2'>{$t_array[1]}</Output>
    		<Output name='text3'>{$t_array[2]}</Output>
  	</Response>
	</ResultSpec>		
";
	}	
	$dbr->freeResult($res);

	echo "
</Results>";
?>
