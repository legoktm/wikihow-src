<?
	require_once('commandLine.inc');

	$dbr = wfGetDB(DB_SLAVE);

	$lines = split("\n", file_get_contents("/var/www/html/wiki/x/new_keyword_links_tracking_for_chris_good.txt"));

	foreach ($lines as $line) {

		if (trim($line) == "") continue;
		$tokens = split("\t", $line);
		if (sizeof($tokens) < 3) continue;
		$t = Title::newFromText($tokens[2]);
		if (!$t) {
			echo "can't make title for {$tokens[2]}\n";
			continue;
		}
		$count = $dbr->selectField('recentchanges', 
				array('count(*)'),
				array('rc_user_text' => 'LinkTool',
					  'rc_comment' => "Adding keyword links for phrase " . $tokens[0], 			
						'rc_cur_id'	=> $t->getArticleID()
				)
		);
		if ($count == 1) echo $line . "\n";
		#echo "$count \n";
	}
