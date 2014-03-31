<?
require_once('commandLine.inc');
echo "Running with $wgLanguageCode\n";
require_once("$IP/extensions/wikihow/titus/Titus.class.php");

$maintenance = new TitusMaintenance();
$maintenance->nightly();
//$maintenance->sendErrorEmail();

// To repair - run this call instead of nightly. This will recalc daily edits for the number of days lookback
// that you specify.  NOTE: historical data for the lookback period will not be repaired, just the current day
//$lookBack = 1;
//$maintenance->repairTitus($lookBack);

class TitusMaintenance {
	var $titus = null;

	private function getWikiDBName() {
		global $wgLanguageCode;
		if($wgLanguageCode == "en") {
			return("wikidb_112");
		}
		else {
			return("wikidb_" . $wgLanguageCode);
		}
	}
	/*
	* Run the nightly maintenance for the titus and titus_historical tables
	*/
	public function nightly() {
		$this->titus = new TitusDB(true);

		$this->updateHistorical();
		$this->trimHistorical();
		$this->incrementTitusDatestamp();
		$this->removeDeletedPages();
		$this->removeRedirects();
		$this->updateTitus();
		$this->fixBlankRecords();
		$this->reportErrors();	
	}
	/*
	* Function for sending error email with doing a full re-run on Titus
	*/
	public function sendErrorEmail() {
		$this->titus = new TitusDB(true);
    $titus = $this->titus;

    $activeStats = TitusConfig::getAllStats();
    $statGroups = $this->titus->getPagesToCalcByStat($activeStats, wfTimestampNow());
		$this->reportErrors();	

	}

	/**
	 * Calculate miscellaneous stats that aren't edit
	 */
	private function calcMiscStats(&$ids, $stats) {
		if(!empty($ids)) {
			$n=0;
			$chunk = array();
			foreach($ids as $id) {
				$chunk[] = $id;
				if(sizeof($chunk) >= 1000) {
					print "calculating chunk #: " . $n . " :" . wfTimestampNow() . "\n";
					$n++;
					$this->titus->calcStatsForPageIds($stats, $chunk);
					$chunk = array();
				}
			}
			if(sizeof($chunk) > 0) {
				print "calculating chunk #: " . $n . " :" . wfTimestampNow() . "\n";
				$this->titus->calcStatsForPageIds($stats, $chunk);
			}
		}
		else {
			echo "Misc stats do not need to be calculated for anything\n";	
		}
	}
	private function updateTitus() {
			
		$titus = $this->titus;

		$activeStats = TitusConfig::getAllStats();
		$statGroups = $this->titus->getPagesToCalcByStat($activeStats, wfTimestampNow());
	
		echo "calcLatestEdits start: " . wfTimestampNow() . "\n";
		$titus->calcLatestEdits($statGroups['daily_edit_stats']);
		echo "calcLatestEdits finish: " . wfTimestampNow() . "\n";
	
		echo "calculating misc stats start: " . wfTimestampNow() . "\n";
		$this->calcMiscStats($statGroups['ids'], $statGroups['id_stats']);	
		echo "calculated misc stats finished: " . wfTimestampNow() . "\n";
		
		// Run nightly stats
		echo "calcStatsForAllPages start: " . wfTimestampNow() . "\n";
		$titus->calcStatsForAllPages($statGroups['all_id_stats']);
		echo "calcStatsForAllPages finish: " . wfTimestampNow() . "\n";

		// Calc accuracy stats for newly rated pages
		$this->calcAccuracy();
	}

	private function calcAccuracy() {
		echo "calcAccuracy start: " . wfTimestampNow() . "\n";
		$accuracyStats = TitusConfig::getBasicStats();
		$accuracyStats['Title'] = 0;
		$accuracyStats['Accuracy'] = 1;

		$lowDate = wfTimestamp(TS_MW, strtotime("-1 day", strtotime(date('Ymd', time()))));
		$dbr = wfGetDB(DB_SLAVE);
		$res = $dbr->select('rating', 'distinct rat_page', 
			array("rat_timestamp >= '$lowDate'"), __METHOD__);
		$ids = array();
		foreach ($res as $row) {
			$ids[] = $row->rat_page;
		}

		if (!empty($ids)) {
			$pageChunks = array_chunk($ids, 1000);
			foreach ($pageChunks as $chunk) {
				$this->titus->calcStatsForPageIds($accuracyStats, $chunk);
			}
		}
		echo "calcAccuracy finish: " . wfTimestampNow() . "\n";
	}

	public function repairTitus($lookBack = 1) {
		$titus = new TitusDB(true);
		$dailyEditStats = TitusConfig::getDailyEditStats();
		$titus->calcLatestEdits($dailyEditStats, $lookBack);

		$nightlyStats = TitusConfig::getNightlyStats();
		$titus->calcStatsForAllPages($nightlyStats);
	}

	/*
	* Dumps the current state of the titus table into titus_historical.  At the time of the dump, this should be a full days
	* worth of titus page rows. The titus_historical table should maintain 30-60 days worth of titus table dumps
	*/
	private function updateHistorical() {
		global $wgLanguageCode;
		$sql = "INSERT IGNORE INTO titus_historical_intl SELECT * FROM titus_intl WHERE ti_language_code='" . $wgLanguageCode . "'";
		$this->performMaintenanceQuery($sql);
	}

	private function trimHistorical($lookBack = 30) {
		global $wgLanguageCode;
		$lowDate = substr(wfTimestamp(TS_MW, strtotime("-$lookBack day", strtotime(date('Ymd', time())))), 0, 8);
		$sql = "DELETE FROM titus_historical_intl WHERE ti_datestamp < '$lowDate' AND ti_language_code='" . $wgLanguageCode . "'";
		$this->performMaintenanceQuery($sql);
	}

	private function performMaintenanceQuery($sql) {
		$titus = $this->titus;
		return $titus->performTitusQuery($sql);
	}


	private function incrementTitusDatestamp() {
		global $wgLanguageCode;
		$today = wfTimestamp(TS_MW, strtotime(date('Ymd', time())));
		$sql = "UPDATE titus_intl set ti_datestamp = '$today' WHERE ti_language_code='". $wgLanguageCode . "'";
		$this->performMaintenanceQuery($sql);
	}

	private function removeDeletedPages() {
		global $wgLanguageCode;
		$dbr = wfGetDB(DB_SLAVE);
		$lowDate = wfTimestamp(TS_MW, strtotime("-1 day", strtotime(date('Ymd', time()))));

		$sql = "SELECT de_page_id FROM daily_edits WHERE de_timestamp >= '$lowDate' AND de_edit_type = " . DailyEdits::DELETE_TYPE;
		$res = $dbr->query($sql);
		$ids = array();
		foreach ($res as $row) {
			$ids[] = $row->de_page_id;
		}
		if (!empty($ids)) {
			$ids = "(" . implode(", ", $ids) . ")";
			$sql = "DELETE FROM titus_intl where ti_page_id IN $ids AND ti_language_code='" . $wgLanguageCode . "'";	
			$this->performMaintenanceQuery($sql);
		}
	}

	private function removeRedirects() {
		global $wgLanguageCode;
		$dbr = wfGetDB(DB_SLAVE);
		$lowDate = wfTimestamp(TS_MW, strtotime("-1 day", strtotime(date('Ymd', time()))));

		$sql = "SELECT de_page_id FROM daily_edits WHERE de_timestamp >= '$lowDate' AND de_edit_type = " . DailyEdits::EDIT_TYPE;
		$res = $dbr->query($sql);
		$ids = array();
		foreach ($res as $row) {
			$ids[] = $row->de_page_id;
		}
		if (!empty($ids)) {
			$ids = "(" . implode(", ", $ids) . ")";
			
			$sql = "DELETE  t.* from titus_intl t LEFT JOIN " . $this->getWikiDBName() . ".page p ON t.ti_page_id = p.page_id 
				WHERE p.page_is_redirect = 1 AND ti_page_id IN $ids AND ti_language_code='" . $wgLanguageCode . "'";
			$this->performMaintenanceQuery($sql);
		}
		
	}

	private function fixBlankRecords() {
		global $wgLanguageCode;
		$sql = "SELECT ti_page_id FROM titus_intl where ti_page_title = '' AND ti_language_code='" . $wgLanguageCode . "'";
		$ids = array();
		$res = $this->performMaintenanceQuery($sql);
		foreach ($res as $row) {
			$ids[] = $row->ti_page_id;
		}
		if (!empty($ids)) {
			$pageChunks = array_chunk($ids, 1000);
			foreach ($pageChunks as $chunk) {
				$titus = $this->titus;
				$dailyEditStats = TitusConfig::getDailyEditStats();
				$titus->calcStatsForPageIds($dailyEditStats, $chunk);
			}

		}
	}
	private function reportErrors() {
    $activeStats = TitusConfig::getAllStats();
		$errors = $this->titus->getErrors($activeStats);
		if($errors != "") {
				print("Errors are " . $errors);
				$to = new MailAddress("gershon@wikihow.com,elizabeth@wikihow.com,reuben@wikihow.com,chris@wikihow.com");
				$from = new MailAddress("alerts@wikihow.com");
				$subject = "Titus Errors";
				UserMailer::send($to,$from, $subject, $errors);
		}
	}
}
