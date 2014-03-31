<?php 

require_once('commandLine.inc');
require_once("$IP/extensions/wikihow/DatabaseHelper.class.php");
require_once("$IP/extensions/wikihow/titus/Titus.class.php");

/******
 * 
 * This script is run nightly (prior to titus), to update the 
 * monthly page view field and the single day page view field 
 * in the pageview table. Grabs the last 30 days from the fastly_pv
 * table and uses those values to determine yesterday and total
 * of the last 30 days. Must sum up both mobile and desktop
 * values.
 *
 * Note: this script took about 9 minutes to run on June 13, 2012
 * -Reuben
 * 
 ******/
if(!defined('IS_PROD_INTL_SITE')) {
	define('IS_PROD_INTL_SITE',false);	
}
$dbr = DatabaseBase::factory('mysql');
$dbr->open(WH_DATABASE_BACKUP, WH_DATABASE_MAINTENANCE_USER, WH_DATABASE_MAINTENANCE_PASSWORD, "wiki_log");
echo "Using spare db to get data\n";

$dbw = wfGetDB(DB_MASTER);

DEFINE(BATCHSIZE, 1000);

//This always defaults to being in debug mode where the pv's don't get reset.
$debug = @$argv[0] != "live";
$tableName = $debug ? "pageview_tmp" : "pageview";

$startTime = microtime(true);

//grab all of the articless
$articles = DatabaseHelper::batchSelect('page', array('page_id', 'page_title'), array('page_namespace' => NS_MAIN, 'page_is_redirect' => 0));

//TESTING CODE
/***
$articles = array();
$obj1->page_id = 2053;
$obj1->page_title = "Kiss";
$articles[] = $obj1;
$obj2->page_id = 400422;
$obj2->page_title = "Have-a-Healthy-Sex-Life-(Teens)";
$articles[] = $obj2;
***/


//now we need to recalculate all the pv data

//want 30 days ago from historical, but it will only return 28. 29 will come from pageview table. 30th day will come from stu
$monthAgo = substr(wfTimestamp(TS_MW, strtotime("-30 day", strtotime(date('Ymd', time())))), 0, 8); 
$yesterday = substr(wfTimestamp(TS_MW, strtotime("-1 day", strtotime(date('Ymd', time())))), 0, 8); 
$yesterdayString = date('Y-m-d', strtotime("-1 day", strtotime(date('Ymd', time()))));
$monthAgoString = date('Y-m-d', strtotime("-30 day", strtotime(date('Ymd', time()))));
$now = wfTimestamp(TS_MW);

echo "Starting from " . $monthAgo . " and processing " . count($articles) . " in " . ceil(count($articles)/BATCHSIZE) . " batches. Using table {$tableName}.\n";

$articleCount = 0;
$sqlStart = "INSERT INTO {$tableName} (pv_page, pv_30day, pv_1day, pv_timestamp) VALUES ";
$sqlEnd = " ON DUPLICATE KEY UPDATE pv_30day = VALUES(pv_30day), pv_1day = VALUES(pv_1day), pv_timestamp = VALUES(pv_timestamp)";
$batches = array();
foreach($articles as $articleData) {
	$title = Title::newFromID($articleData->page_id);
	if(!$title)
		continue;
	
	//get the title encoded
	$titleText = $articleData->page_title;
	
	$monthCount = 0;
	$yesterdayCount = 0;
	$res = $dbr->select('page_views', array('*'), array('page' => '/' . $titleText, "(domain = '$wgLanguageCode.wikihow.com' OR domain = '$wgLanguageCode.m.wikihow.com')", "day >= '{$monthAgoString}'"));
	
	foreach($res as $logItem) {
		$pv = intval($logItem->pv);
		$monthCount += $pv;
		if($logItem->day == $yesterdayString) {
			$yesterdayCount += $pv;
		}
	}
	
	//add this page's data into the batch to be processed later
	$batches[] = "('{$articleData->page_id}', '{$monthCount}', '{$yesterdayCount}', '{$now}')";
	
	$articleCount++;
	if($articleCount % BATCHSIZE  == 0){
		processBatch($dbw, $batches, $sqlStart, $sqlEnd, $articleCount);

		usleep(500000);
	}
}

//see if there are any batches left to process
processBatch($dbw, $batches, $sqlStart, $sqlEnd, $articleCount);

$endTime = microtime(true);

echo "Finished " . __FILE__ . " in " . round($endTime - $startTime) . "s\n";

function processBatch(&$dbw, &$batches, $sqlStart, $sqlEnd, $articleCount) {
	$sql = $sqlStart . join(",", $batches) . $sqlEnd;
	$success = $dbw->query($sql, __METHOD__);

	$batchNum = $articleCount/BATCHSIZE;
	if($success) {
		echo "Updated batch #" . $batchNum . ".\n";
	}
	else {
		echo "Unable to update batch #" . $batchNum . "\n";
	}

	$batches = array();
}

//
// THIS FUNCTION NOT IN USE ANYMORE
//
/*function getDaysPV(&$dbw, &$title, $debug = true) {
	$count = 0;

	$dbKey = $title->getDBkey();
	$articleId = $title->getArticleID();
	
	$query = array('select' => '*', 'from' => 'pv', 'pages' => array($dbKey));
	$ret = AdminBounceTests::doBounceQuery($query);
	if (!$ret['err'] && $ret['results']) {
		AdminBounceTests::cleanBounceData($ret['results']);
		foreach ($ret['results'] as $page => $datum) {
            if (isset($datum['__'])) {
                $count = $datum['__'];
            }
            break; // should only be one record
        }
	}
	
	if(!$debug) {
		$deleteQuery = array('delete' => '*', 'from' => 'pv', 'pages' => array($dbKey));
		AdminBounceTests::doBounceQuery($deleteQuery);
	}
	
	return $count;
	
}*/

/********
 * 
 * CREATE TABLE `wikidb_112`.`pageview` (
 * `pv_page`  INT(8) UNSIGNED NOT NULL,
 * `pv_30day` BIGINT( 20 ) UNSIGNED NOT NULL DEFAULT '0',
 * UNIQUE(
 * `pv_page`
 * )
 * ) ENGINE = InnoDB ;
 * ALTER TABLE `pageview` ADD `pv_1day` INT( 8 ) UNSIGNED NOT NULL DEFAULT '0'
 * ALTER TABLE `pageview` ADD `pv_timestamp` VARCHAR( 14 ) NOT NULL 
 * 
 * CREATE TABLE pageview_tmp (pv_page INT(8) primary key)  SELECT * FROM pageview;
 * 
 ********/
