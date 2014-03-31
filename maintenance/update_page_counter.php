<?php

/*************
 * 
 * With the move to Fastly we can no longer rely on the old varnish method of counting
 * visits. So we're usin the data that we're already getting from stu to increment the 
 * page_counter field. This will happen once a day and this script must be called AFTER
 * updateMontlyPageView.php script in the nightly cron.
 * 
 */

require_once('commandLine.inc');
require_once("$IP/extensions/wikihow/DatabaseHelper.class.php");

$start = microtime(true);
$articles = DatabaseHelper::batchSelect('pageview', array('pv_page', 'pv_1day'));

echo "done getting all the articles\n";

$dbw = wfGetDB(DB_MASTER);

$articleCount = 0;
foreach($articles as $article) {
	$dbw->update('page', array('page_counter = page_counter + ' . $article->pv_1day), array('page_id' => $article->pv_page), __FILE__);
	$articleCount++;
	if($articleCount % 1000 == 0){
		sleep(5);
	}
}
$end = microtime(true);

echo "Finished in " . ($end - $start) . " seconds.\n";
