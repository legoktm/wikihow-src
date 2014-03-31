<?
require_once( "commandLine.inc" );

$debug = false; 
$reps = isset($argv[0]) ? $argv[0] : 5; 
$newpagecutoff = wfTimestamp (TS_MW, time() - 3600 * 24 * 3);
$me = rand(0, 10000);

	for ($i = 0; $i < $reps; $i++) {
		
		// grab a batch if one is available
		$dbw = wfGetDB( DB_MASTER );
		$batch = $dbw->selectField('urls', 'batch', array()); 
		if ($batch == null) {
			echo $dbw->lastQuery() . "\n";
			echo date("r") . " ($me) No batch to process!\n";
			return;
		}

		echo date("r") . " ($me) Processing batch $batch\n";
		$res = $dbw->query("select url, count as C from urls where batch={$batch} group by url having C > 2 order by C desc;");
		$total =  $actual = 0;

		// use a bucket mapping counter increments to page ids
		// ex:
		// count_array[5] = array(5, 292, 449);		
		// mean the pages with ids 5, 292 and 449 should have their page counter incremented by 5
		$count_array = array();
		while ( $row = $dbw->fetchObject($res) ) {

			$parts = parse_url($row->url); 
			$title = Title::newFromURL(urldecode(preg_replace("@^/@", "", $parts['path'])));

			# check for bad titles
			if (!$title) {
				#echo "Couldn't build proper title for {$row->url}\n";
				continue;
			}
			$id = $title == null ? 0 : $title->getArticleID();
			if ($id == 0) {
				#echo "ID is 0 for  {$row->url}\n";
				continue;
			}

			// update the bucket
			if (!isset($count_array[$row->C]))
				$count_array[$row->C] = array();
			$count_array[$row->C][] = $id;
			$actual += $row->C;
		}	

		# do the updates based on what's in the bucket
		foreach ($count_array as $count => $arg) {
			if ($count > 10) {
				$dbr = wfGetDB(DB_SLAVE);
				foreach ($arg as $a) {
					$time = $dbr->selectField('firstedit', 'fe_timestamp', array('fe_page'=>$a));
					if ($time > $newpagecutoff) {
						$pt = $dbr->selectField('page', 'page_title', array('page_id'=>$a));
						echo date("r") . " ($me) New page $a ($pt) getting big update of $count\n";
					}
					#echo "$a\t$time\t$count\n";
				}
			}
			if (!$debug) {
				$dbw = wfGetDB( DB_MASTER );
				$dbw->query("update page set page_counter=page_counter+{$count} where page_id IN (" . implode(",", $arg) . ");");
			}
		}
		
		# delete this batch from the list of urls to process
		$dbw = wfGetDB( DB_MASTER );
		$total = $dbw->selectField('urls', 'count(*)', array("batch=$batch"));

		# spit out some debugging just for fun
		$inc = $total;
		$total = number_format($total, 0, "", ",");
		$actual = number_format($actual, 0, "", ",");
		echo date("r") . " ($me) Done processing $total rows for $actual page views.\n";

		if (!$debug) {
			$dbw = wfGetDB( DB_MASTER );
			$dbw->query("update site_stats set ss_total_views = ss_total_views + $inc;");
			$dbw->query("delete from urls where batch=$batch;");
		}
	}
	echo date("r") . " ($me) Done Processing batch $batch\n";
?>
