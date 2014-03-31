<?
/*
* Titus is a meta db of stats pertaining to our articles.  This file includes the classes 
* that store and retreive data from the db
*/

require_once("$IP/extensions/wikihow/DatabaseHelper.class.php");
require_once("$IP/extensions/wikihow/TranslationLink.php");

define('WH_TITUS_TOP10K_GOOGLE_DOC','t-gqKyEfiXJNaqg-wfGGCPw/od6');
define('WH_TITUS_RATINGS_GOOGLE_DOC','0Ag-sQmdx8taXdHc0bzJWRlIzT3dyeEdiTk00VWxmZGc/od8');
define('WH_TITUS_EDITOR_GOOGLE_DOC', 'tEjRzx-ci0KGmvJZ717t9Bg/od6');

if (strpos(@$_SERVER['HOSTNAME'], 'wikidiy.com') !== false) {
        define(TITUS_DB_HOST, WH_DATABASE_MASTER);
} else {
        define(TITUS_DB_HOST, WH_DATABASE_BACKUP);
}

class TitusDB {
	var $titusDB = null;
	var $wikiDB = null;
	var $debugOutput;
	var $dataBatch = array();
	var $statClasses = array();

	const TITUS_TABLE_NAME = 'titus_intl as titus';
	const TITUS_INTL_TABLE_NAME = 'titus_intl';
	const TITUS_HISTORICAL_TABLE_NAME = 'titus_historical_intl';
	const DAILY_EDIT_IDS = "dailyeditids";
	const ALL_IDS = "allids";

	static function getDBName() {
		if(IS_CLOUD_SITE) {
			return('titusdb2');
		}
		else {
			return('titusdb');
		}
	}
	function __construct($debugOutput = false) {
		$this->debugOutput = $debugOutput;
	}
	/** 
	 * Gets a singleton of a particular stat class to avoid re-instantiation
	 */
	function getStatClass($name) {
		if(isset($this->statClasses[$name])) {
			return($this->statClasses[$name]);	
		}
		else {
			$tsName = "TS" . $name;
			$this->statClasses[$name] = new $tsName();
			return($this->statClasses[$name]);
		}
	}
	public function getErrors(&$activeStats) {
		$errors = "";
	  foreach ($activeStats as $stat => $isOn) {
			if ($isOn) {
	      $statCalculator = $this->getStatClass($stat);
				$error=$statCalculator->getErrors();
				if($error) {
					$errors .= $stat . " errors:\n";
					$errors .= $error;
					$errors .= "\n";
				}
			}
		}
		return($errors);
	}
	/** 
	 * Get pages to calculate by statistic 
	 * @return (all_id_stats => (array of stats), daily_edit_stats =>(everything edited today), ids=>(array map of stuff edited today), id_stats=>(list of stats calculated for a limited number of ids))
	 */
	public function getPagesToCalcByStat(&$activeStats, $date) {
		$ret = array("all_id_stats"=>TitusConfig::getBasicStats() , "daily_edit_stats"=>TitusConfig::getBasicStats(), "ids"=>array(), "id_stats"=>TitusConfig::getBasicStats());
		$dbr = $this->getTitusDB();
	  foreach ($activeStats as $stat => $isOn) {
		  if ($isOn) {
				$statCalculator = $this->getStatClass($stat);
				$ids = $statCalculator->getPageIdsToCalc($dbr,$date);
				 
				if(is_array($ids) && !empty($ids)) {
					$ret["id_stats"][$stat] = 1;
					$ret["ids"] = array_merge($ret["ids"], $ids);
				}
				elseif($ids == TitusDB::DAILY_EDIT_IDS) {
					$ret["daily_edit_stats"][$stat] = 1;
				}
				elseif($ids = TitusDB::ALL_IDS) {
					$ret["all_id_stats"][$stat] = 1;	
				}
				else {
					throw new Exception("Return type of getPageIds from " . $stat . " was not found");	
				}
			}
	  }
		
		$ret["ids"] = array_unique($ret["ids"]);
		return($ret);
	}
	/*
	* This function calcs Titus stats for pages that have been most recently edited on wikiHow. 
	* See DailyEdits.class.php for more details
	*/
	public function calcLatestEdits(&$statsToCalc, $lookBack = 1) {
		$dbr = $this->getWikiDB();		
	
		// Offset to convert times to Pacific Time DST
		// Titus runs after midnight PDT, and we want to ensure Titus runs before this is called
		$PDST_OFFSET = 7*60*60;
		$lowDate = wfTimestamp(TS_MW, strtotime("-$lookBack day", strtotime(date('Ymd', time()))) + $PDST_OFFSET);
		$highDate = wfTimestamp(TS_MW, strtotime(date('Ymd', time())) + $PDST_OFFSET);
		$rows = DatabaseHelper::batchSelect('daily_edits', 
			'de_page_id', 
			array("de_timestamp >= '$lowDate'", "de_timestamp < '$highDate'", 
				"(de_edit_type <> " . DailyEdits::DELETE_TYPE . " )"), 
			__METHOD__, 
			array(), 
			1000, 
			$dbr);
		$pageIds = array();
		foreach ($rows as $row) {
			$pageIds[] = $row->de_page_id;
		}
		$pageChunks = array_chunk($pageIds, 1000);
		foreach ($pageChunks as $chunk) {
			$this->calcStatsForPageIds($statsToCalc, $chunk);
		}
	}
		
	/*
	* Get page ids to calculate stats
	*/
	/*
	* Calc Titus stats for an array of $pageIds
	*/
	public function calcStatsForPageIds(&$statsToCalc, &$pageIds) {
		if (sizeof($pageIds) > 1000) {
			throw new Exception("\$pageIds must be an array of 1000 or fewer page ids");
		}

		$dbr = $this->getWikiDB();
		$pageIds = implode(",", $pageIds);


		$rows = DatabaseHelper::batchSelect('page', 
			array('page_id', 'page_title', 'page_counter', 'page_is_featured', 'page_catinfo', 'page_len'), 
			array('page_namespace' => 0, 'page_is_redirect' => 0, 
				"page_id IN ($pageIds)"), 
			__METHOD__, 
			array(),
			DatabaseHelper::DEFAULT_BATCH_SIZE,
			$dbr);
		foreach ($rows as $row) {
			$fields = $this->calcPageStats($statsToCalc, $row);

			if (!empty($fields)) {
				$this->batchStoreRecord($fields);
			}
		}
		// flush out current batch
		$this->flushDataBatch();
	}

	/*
	* Calc Titus stats for all pages in the page table that are NS_MAIN and non-redirect.
	* WARNING:  Use this with caution as calculating all Titus stats takes many hours
	*/
	public function calcStatsForAllPages(&$statsToCalc, $limit = array()) {
		$dbr = $this->getWikiDB();  	

		$rows = DatabaseHelper::batchSelect('page', 
			array('page_id', 'page_title', 'page_counter', 'page_is_featured', 'page_catinfo', 'page_len'), 
			array('page_namespace' => 0, 'page_is_redirect' => 0), 
			__METHOD__, 
			$limit,
			DatabaseHelper::DEFAULT_BATCH_SIZE,
			$dbr);

		foreach ($rows as $row) {
			$fields = $this->calcPageStats($statsToCalc, $row);

			if (!empty($fields)) {
				$this->batchStoreRecord($fields);
			}
		}

		// flush out current batch
		$this->flushDataBatch();
	}

	/*
	* Calc stats for a given article.  An article is represented by a subset of its page data from the page table,
	* but this should probably be abstracted in the future to something like TitusArticle with the appropriate fields
	*/
	public function calcPageStats(&$statsToCalc, &$row) {
		$dbr = $this->getWikiDB();
	
		$t = Title::newFromId($row->page_id); 
		$goodRevision = GoodRevision::newFromTitle($t, $row->page_id);
		$revId = 0;
		if ($goodRevision) {
			$revId = $goodRevision->latestGood();
		}
		$r = $revId > 0 ? Revision::loadFromId($dbr, $revId) : Revision::loadFromPageId($dbr, $row->page_id);

		$fields = array();
		if ($r && $t && $t->exists()) {
			foreach ($statsToCalc as $stat => $isOn) {
				if ($isOn) {
					$statCalculator = $this->getStatClass($stat);
					$fields = array_merge($fields, $statCalculator->calc($dbr, $r, $t, $row));
				}
			}
		}
		return $fields;
	}

	/*
	* Stores records in batches sized as specified by the $batchSize parameter
	* NOTE:  This method buffers the data and only stores data once $batchSize threshold has been 
	* met.  To immediately store the bufffered data call flushDataBatch()
	*/
	public function batchStoreRecord($data, $batchSize = 1000) {
		$this->dataBatch[] = $data;
		if (sizeof($this->dataBatch) == $batchSize) {
			$this->flushDataBatch();
		}
	}
	
	/*
	 * Returns all records currently in titus
	 */
	public function getRecords() {
		$dbr = $this->getTitusDB();
		
		$rows = DatabaseHelper::batchSelect(TitusDB::TITUS_INTL_TABLE_NAME, '*', array(), __METHOD__, array(), 2000, $dbr);
		
		return $rows;
	}
	
	public function getOldRecords($datestamp) {
		$dbr = $this->getTitusDB();
		
		$rows = DatabaseHelper::batchSelect(TitusDB::TITUS_HISTORICAL_TABLE_NAME, '*', array('ti_datestamp' => $datestamp), __METHOD__, array(), 2000, $dbr);
		
		return $rows;
	}

	/*
	* Stores multiple records of data.  IMPORTANT:  All data records must constain identical fields of data to  data to insert
	*/
	public function storeRecords(&$dataBatch) {
		if (!sizeof($dataBatch)) {
			return;
		}

		$fields = join(",", array_keys($dataBatch[0]));
		$set = array();
		foreach ($dataBatch[0] as $col => $val) {
			$set[] = "$col = VALUES($col)";
		}
		$set = join(",", $set);

		$values = array();
		foreach ($this->dataBatch as $data) {
			$values[] = "('" . join("','", array_values($data)) . "')";
		}
		$values = implode(",", $values);

		$dbw = $this->getTitusDB();
		$sql = "INSERT INTO " . TitusDB::TITUS_INTL_TABLE_NAME . " ($fields) VALUES $values ON DUPLICATE KEY UPDATE $set";
		if ($this->debugOutput) {
			var_dump($this->dataBatch);
		}
		$res = $dbw->query($sql, __METHOD__);
		if (!$res) {
			die("Error insert into titus: " . mysql_error());
		}
	}

	/*
	* Get the connection for titus db
	*/
	private function getTitusDB() {
		if (is_null($this->titusDB) || !$this->titusDB->ping()) {
			$this->titusDB = DatabaseBase::factory('mysql');
			$this->titusDB->open(TITUS_DB_HOST, WH_DATABASE_MAINTENANCE_USER, WH_DATABASE_MAINTENANCE_PASSWORD, self::getDBName());
		}
		return $this->titusDB;
	}

	/**
	* Get connection to the wiki database 
	*/
	private function getWikiDB() {
		global $wgDBname;
		if (is_null($this->wikiDB) || !$this->wikiDB->ping()) {
			$this->wikiDB = DatabaseBase::factory('mysql');
			$this->wikiDB->open(TITUS_DB_HOST, WH_DATABASE_MAINTENANCE_USER, WH_DATABASE_MAINTENANCE_PASSWORD, $wgDBname);
		}
		return $this->wikiDB;
	}

	public function performTitusQuery($sql) {
		$db = $this->getTitusDB();
		return $db->query($sql);
	}

	/*
	* Store records currently queued in $this->dataBatch
	*/
	private function flushDataBatch() {
		// Flush out remaining records to database
		if (sizeof($this->dataBatch)) {
			$this->storeRecords($this->dataBatch);
			$this->dataBatch = array();
		}
	}
}

/*
* Returns configuration for TitusController represened by an associative array.   of stats available in the TitusDB that can be calculated
* The key of each row represents a TitusStat that can be calculated and the value represents whether to calculate (1 for calc, 0 for don't calc)
*/
class TitusConfig {

	public static function getSocialStats() {
	}

	/*
	*  Get config to calc stu stats
	*/
	public static function getStuStats() {
		global $wgLanguageCode;
		$stats = array(
			"PageId" => 1,
			"LanguageCode" => 1,
			"Timestamp" => 1,
			"Stu" => 1,
			"PageViews" => 1,
		);
		if($wgLanguageCode != "en") {
			$stats["Stu"] = 0;
		}
		return $stats;
	}

	public static function getNightlyStats() {
		return self::getStuStats();
	}

	/*
	* Get config for stats that we want to calculate on a nightly basis
	*/
	public static function getDailyEditStats() {
		$stats = self::getAllStats();
		// Social stats are slow to calc, so remove them from the calcs
		$stats['Social'] = 0;

		// Stu stats don't make sense to calculate on a page edit.  This should be done nightly via
		// across all pages
		$stats['Stu'] = 0;
		$stats['PageViews'] = 0;


		// RobotPolicy is also a bit slow, but we should probably leave it on because it's so important
		// to make sure everything is indexing properly
		//unset($stats['RobotPolicy']);

		return $stats;
	}
	public static function getOtherStats() {
	
	}
	public static function getAllStats() {
		global $wgLanguageCode;
		$stats = array (
			"PageId" => 1,
			"Timestamp" => 1,
			"LanguageCode" => 1,
			"LangLinks" => 0,
			"Title" => 1,
			"Views" => 1,
			"NumEdits" => 1,
			"AltMethods" => 1,
			"ByteSize" => 1,
			"Accuracy" => 1,
			"Stu" => 1,
			"PageViews" => 1,
			"Intl" => 0,	
			"Video" => 1,
			"FirstEdit" => 1,
			"LastEdit" => 1,
			"TopLevelCat" => 1,
			"ParentCat" => 1,
			"NumSteps" => 1,
			"NumTips" => 1,
			"NumWarnings" => 1,
			"TranslatorEdit" => 1, 
			"Photos" => 1,
			"Featured" => 1,
			"RobotPolicy" => 1,
			"RisingStar" => 1,
			"Templates" => 1,
			"RushData" => 1,
			"Social" => 1,
			"Translations" => 1,
			"Sample" => 1,
			"RecentWikiphoto" => 1,
			"Top10k" => 1,
			"Ratings"=> 1,
			"LastFellowEdit" => 1,
			"LastPatrolledEditTimestamp" => 1,
			"BabelfishData" => 0,
			"NAB" => 1,
			"WikiVideo" => 1
			);
		if($wgLanguageCode != "en") {
			$stats["LangLinks"] = 0;
			$stats["Stu"] = 0;
			$stats["RushData"] = 0;
			$stats["RobotPolicy"] = 0;
			$stats["RecentWikiphoto"] = 0;
			$stats["Ratings"] = 0;
			$stats["LastFellowEdit"] = 0;
			$stats["BabelfishData"] = 1;
			// NO NAB on internatioanl
			$stats["NAB"] = 0;
		}
		return $stats;
	}

	public static function getBasicStats() {
		$stats = array (
			"PageId" => 1,
			"LanguageCode" => 1,
			"Timestamp" => 1,
			"Title" => 1,
			);

		return $stats;
	}
}

/*
* Abstract class representing a stat to be calculated by TitusDB
*/
abstract class TitusStat {
	// Abstract function that gets a list of page ids we want to calculate for this stat
	// @return Either an array of page ids, "all" to run through all pages, or "dailyedits"
	abstract function getPageIdsToCalc(&$dbr,$date);

	private $_error=false;
	function reportError($msg) {
		global $wgLanguageCode;
		print("Reporting error on " . $wgLanguageCode . "  : $msg\n");
		if(!$this->_error) {
			$this->_error = "";	
		}
		$this->_error .=  " " . $msg . "\n";	
	}
	function getErrors() {
		return($this->_error);	
	}
	function checkForRedirects(&$dbr, &$ids) {
		global $wgLanguageCode;

		$query = "select page_id,page_title  from " . Misc::getLangDB($wgLanguageCode) . ".page where page_is_redirect=1 AND page_id in (" . implode($ids,",") . ")";
		$res = $dbr->query($query,__METHOD__);
		$redirects = "";
		foreach($res as $row) {
			if($redirects == "") {
				$redirects = "The following pages are redirects: ";	
			}
			else {
				$redirects .= ",";	
			}
			$redirects .= $this->getBaseUrl() . '/' . $row->page_title . " (" . $row->page_id . ")";

		}
		if($redirects != "") {
			$this->reportError($redirects);
		}
	}
	function checkForMissing(&$dbr, &$ids) {
		global $wgLanguageCode;
		$query = "select page_id  from " . Misc::getLangDB($wgLanguageCode) . ".page where page_id in (" . implode($ids,",") . ")";
		$res = $dbr->query($query,__METHOD__);
		$foundIds = array();
		foreach($res as $row) {
			$foundIds[] = $row->page_id;
		}
		$missing = array_diff($ids, $foundIds);
		if(sizeof($missing) > 0) {
			$error = "The following article were not found and may have been deleted (" . implode($missing,',') . ")";
			$this->reportError($error);
		}
	}

	// Abstract function that returns calculated stats.  IMPORTANT: All status must be returned with a 
	// default value or batch insertion of records will break
	abstract function calc(&$dbr, &$r, &$t, &$pageRow);
	function getBaseUrl() {
		global $wgLanguageCode;
		if($wgLanguageCode == "en") {
			return("http://www.wikihow.com");       
		}
	  else {
			return("http://" . $wgLanguageCode . ".wikihow.com"); 
	  }
	}

}

/*
* Provides stats on whether es, pt or de articles have been created for this article
*/
class TSIntl extends TitusStat {
	public function getPageIdsToCalc(&$dbr,$date) {
		return(TitusDB::DAILY_EDIT_IDS);		
	}

	public function calc(&$dbr, &$r, &$t, &$pageRow) {
		$txt = $r->getText();
		$stats = array("ti_langs" => "");
		$langs = implode("|", explode("\n", trim(wfMsg('titus_langs'))));
		if (preg_match_all("@\[\[($langs):@i", $txt, $matches)) {
			$matches = $matches[1];
			$stats["ti_langs"] = strtolower(implode(",", $matches));
		}

		return $stats;
	}
}

/*
* Provides top level category for Article
*/
class TSTopLevelCat extends TitusStat {
	public function getPageIdsToCalc(&$dbr,$date) {
		return(TitusDB::DAILY_EDIT_IDS);		
	}

	public function calc(&$dbr, &$r, &$t, &$pageRow) {
		global $wgCategoryNames;
		$topCat = "";
		$catMask = $pageRow->page_catinfo; 
		if ($catMask) {
			foreach ($wgCategoryNames as $bit => $cat) {
				if ($bit & $catMask) {
					$topCat = $dbr->strencode($cat);
					break;
				}
			}
		}
		return array('ti_top_cat' => $topCat);
	}
}

/*
* Provides parent category for article
*/
class TSParentCat extends TitusStat {
	public function getPageIdsToCalc(&$dbr,$date) {
		return(TitusDB::DAILY_EDIT_IDS);		
	}

	public function calc(&$dbr, &$r, &$t, &$pageRow) {
		global $wgContLang; 
		$text = $r->getText();
		$parentCat = "";
		if(preg_match("/\[\[(?:" . $wgContLang->getNSText(NS_CATEGORY) . "|Category):([^\]]*)\]\]/im", $text, $matches)) {
			$parentCat = $dbr->strencode(trim($matches[1]));
		}
		return array('ti_cat' => $parentCat);
	}
}
/*
* Language of this wiki
*/
class TSLanguageCode extends TitusStat {
	public function getPageIdsToCalc(&$dbr,$date) {
 		return(TitusDB::ALL_IDS);		
	}

  public function calc(&$dbr, &$r, &$t, &$pageRow) {
	 global $wgLanguageCode;
   return array("ti_language_code" => $wgLanguageCode);
  }
}

/*
* Links from English to given language pages
*/
class TSLangLinks extends TitusStat {
  public function getPageIdsToCalc(&$dbr,$date) {
	    return(TitusDB::DAILY_EDIT_IDS);       
	} 

  public function calc(&$dbr, &$r, &$t, &$pageRow) {
		global $wgLanguageCode;
    $sql = "select p.page_title FROM " . WH_DATABASE_NAME_EN . ".langlinks JOIN " . WH_DATABASE_NAME_EN . ".page p on p.page_id=ll_from WHERE ll_lang='" . $wgLanguageCode . "' AND ll_title='" . $dbr->strencode($t->getText()) . "'";
    $res = $dbr->query($sql);
    $urls = array();
    while($row = $dbr->fetchObject($res)) {
		 $urls[]=$dbr->strencode("http://www.wikihow.com/" . $row->page_title);  
    }
    return array("ti_lang_links" => implode($urls,","));    
  }
}


/*
* Number of views for an article
*/
class TSViews extends TitusStat {
	public function getPageIdsToCalc(&$dbr,$date) {
	    return(TitusDB::ALL_IDS); 
	} 

	public function calc(&$dbr, &$r, &$t, &$pageRow) {
		return array("ti_views" => $pageRow->page_counter);
	}
}

/*
* Title of an article
*/
class TSTitle extends TitusStat {
	public function getPageIdsToCalc(&$dbr,$date) {
	    return(TitusDB::ALL_IDS); 
	} 

	public function calc(&$dbr, &$r, &$t, &$pageRow) {
		return array("ti_page_title" => $dbr->strencode($pageRow->page_title));
	}
}

/*
* Page id of an article
*/
class TSPageId extends TitusStat {
	public function getPageIdsToCalc(&$dbr,$date) {
	    return(TitusDB::ALL_IDS); 
	} 

	public function calc(&$dbr, &$r, &$t, &$pageRow) {
		return array("ti_page_id" => $dbr->strencode($pageRow->page_id));
	}
}


/*
* Number of bytes in in an article
*/
class TSByteSize extends TitusStat {
	public function getPageIdsToCalc(&$dbr,$date) {
		return(TitusDB::DAILY_EDIT_IDS); 
	} 

	public function calc(&$dbr, &$r, &$t, &$pageRow) {
		$byteSize = $r->getSize();
		if(is_null($byteSize)) {
			$byteSize = strlen($r->getText());
		}
		return array("ti_bytes" => $byteSize);
	}
}

/*
* Date of first edit
*/
class TSFirstEdit extends TitusStat {
	public function getPageIdsToCalc(&$dbr,$date) {
		return(TitusDB::DAILY_EDIT_IDS); 
	} 

	public function calc(&$dbr, &$r, &$t, &$pageRow) {
		$stats = array("ti_first_edit_timestamp" => "", "ti_first_edit_author" => "");
		$res = $dbr->select('firstedit', array('fe_timestamp', 'fe_user_text'), array('fe_page' => $pageRow->page_id), __METHOD__);
		if ($row = $dbr->fetchObject($res)) {
			$stats['ti_first_edit_timestamp'] = $row->fe_timestamp;
			$stats['ti_first_edit_author'] = $dbr->strencode($row->fe_user_text);
		}
		return $stats;
	}
}

/*
* Total number of edits to an article
*/
class TSNumEdits extends TitusStat {
	public function getPageIdsToCalc(&$dbr,$date) {
		return(TitusDB::DAILY_EDIT_IDS); 
	} 
	public function calc(&$dbr, &$r, &$t, &$pageRow) {
		return array("ti_num_edits" => 
			$dbr->selectField('revision', array('count(*)'), array('rev_page' => $pageRow->page_id)));
	}
}

/*
* Determines whether a 'wikifellow' (as defined by mw message 'wikifellows') user account 
* has edited this article
*/
class TSTranslatorEdit extends TitusStat {
	public function getPageIdsToCalc(&$dbr,$date) {
		return(TitusDB::DAILY_EDIT_IDS); 
	} 

	public function calc(&$dbr, &$r, &$t, &$pageRow) {
		$fellows = explode("\n", trim(wfMsg('wikifellows')));
		$fellows = "'" . implode("','", $fellows) . "'";

		$lastEdit = $dbr->selectField(
			'revision', 
			array('rev_timestamp'), 
			array('rev_page' => $pageRow->page_id, "rev_user_text IN ($fellows)"),
			__METHOD__,
			array('ORDER BY' => 'rev_id DESC', "LIMIT" => 1));
		if ($lastEdit === false) {
			$lastEdit = 0;
		}
		return array("ti_last_retranslation" => $lastEdit);
	}
}

/*
* Date of last edit to this article
*/
class TSLastEdit extends TitusStat {
	public function getPageIdsToCalc(&$dbr,$date) {
		return(TitusDB::DAILY_EDIT_IDS); 
	} 

	public function calc(&$dbr, &$r, &$t, &$pageRow) {
		return array("ti_last_edit_timestamp" => 
			$dbr->selectField('revision', 
				array('rev_timestamp'), 
				array('rev_page' => $pageRow->page_id), 
				__METHOD__, 
				array('ORDER BY' => 'rev_id DESC', 'LIMIT' => '1'))
		);
	}
}

/*
* Number of alternate methods in the article
*/
class TSAltMethods extends TitusStat {
	public function getPageIdsToCalc(&$dbr,$date) {
		return(TitusDB::DAILY_EDIT_IDS); 
	} 

	public function calc(&$dbr, &$r, &$t, &$pageRow) {
		$altMethods = intVal(preg_match_all("@^===@m", $r->getText(), $matches));
		return array("ti_alt_methods" => $altMethods);
	}
}

/*
* Whether the article has a video 
*/
class TSVideo extends TitusStat {
	public function getPageIdsToCalc(&$dbr,$date) {
		return(TitusDB::DAILY_EDIT_IDS); 
	} 

	public function calc(&$dbr, &$r, &$t, &$pageRow) {
		$video = strpos($r->getText(), "{{Video") ? 1 : 0;
		return array("ti_video" => $video);
	}
}

/*
* Whether the article has been featured
*/
class TSFeatured extends TitusStat {
	public function getPageIdsToCalc(&$dbr,$date) {
		return(TitusDB::DAILY_EDIT_IDS); 
	} 

	public function calc(&$dbr, &$r, &$t, &$pageRow) {
		return array("ti_featured" => $pageRow->page_is_featured);
	}
}

/*
*  Whether the article has a bad template
*/
class TSTemplates extends TitusStat {
	public function getPageIdsToCalc(&$dbr,$date) {
		return(TitusDB::DAILY_EDIT_IDS); 
	} 

	public function calc(&$dbr, &$r, &$t, &$pageRow) {
		$txt = $r->getText();

		$badTemplates = implode("|", explode("\n", trim(wfMsg('titus_bad_templates'))));
		$hasBadTemp = preg_match("@{{($badTemplates)[}|]@mi", $txt) == 1 ? 1 : 0;
		
		$badTransTemplates = implode("|", explode("\n", trim(wfMsg('titus_bad_translate_templates'))));
		$hasBadTransTemp = preg_match("@{{($badTransTemplates)[}|]@mi", $txt) == 1 ? 1 : 0;

		$templates = array();
		$articleTemplates = implode("|", explode("\n", trim(wfMsg('titus_templates'))));
		if (preg_match_all("@{{($articleTemplates)[}|]@mi", $txt, $matches)) {
			$templates = $matches[1];
		}
		
		$templates = sizeof($templates) ? $dbr->strencode(implode(",", $templates)) : '';

		return array("ti_bad_template" => intVal($hasBadTemp), 'ti_templates' => $templates, "ti_bad_template_translation" => intVal($hasBadTransTemp) );
	}
}

/*
* Number of steps (including alt methods) in the article
*/
class TSNumSteps extends TitusStat {
	public function getPageIdsToCalc(&$dbr,$date) {
		return(TitusDB::DAILY_EDIT_IDS); 
	} 

	public function calc(&$dbr, &$r, &$t, &$pageRow) {
		$text = Wikitext::getStepsSection($r->getText(), true);
		$text = $text[0];
		$num_steps = 0;
		if ($text) {
			$num_steps = preg_match_all('/^#[^*]/im', $text, $matches);
		}
		return array("ti_num_steps" => intVal($num_steps));
	}
}

/*
*  Number of tips in the article
*/
class TSNumTips extends TitusStat {
	public function getPageIdsToCalc(&$dbr,$date) {
		return(TitusDB::DAILY_EDIT_IDS); 
	} 

	public function calc(&$dbr, &$r, &$t, &$pageRow) {
		$text = Wikitext::getSection($r->getText(), wfMsg('tips'), true);
		$text = $text[0];
		if ($text) {
			$num_tips = preg_match_all('/^\*[^\*]/im', $text, $matches);
		}
		return array("ti_num_tips" => intVal($num_tips));
	}
}

/*
* Number of warnings in the article
*/
class TSNumWarnings extends TitusStat {
	public function getPageIdsToCalc(&$dbr,$date) {
		return(TitusDB::DAILY_EDIT_IDS); 
	} 

	public function calc(&$dbr, &$r, &$t, &$pageRow) {
		$text = Wikitext::getSection($r->getText(), wfMsg('warnings'), true);
		$text = $text[0];
		$num_warnings = 0;
		if ($text) {
			$num_warnings = preg_match_all('/^\*[^\*]/im', $text, $matches);
		}
		return array("ti_num_warnings" => intVal($num_warnings));
	}
}

/*
*  Accuracy percentage, number of votes, and last reset date to accuracy
*/
class TSAccuracy extends TitusStat {
	public function getPageIdsToCalc(&$dbr,$date) {
		return(TitusDB::ALL_IDS); 
	} 

	public function calc(&$dbr, &$r, &$t, &$pageRow) {
		$stats = array();
		$pageId = $pageRow->page_id; 
		$sql = "
			select count(*) as C from rating where rat_page = $pageId and rat_rating = 1 and rat_isdeleted = 0 
			UNION ALL
			select count(*) as C from rating  where rat_page  = $pageId and (rat_rating = 0 OR rat_rating=1) and rat_isdeleted = 0
			UNION ALL
			select max(rat_deleted_when) as C from rating where rat_page = $pageId";

		$res = $dbr->query($sql);
		$row = $dbr->fetchObject($res);

		$accurate = intVal($row->C);
		$row = $dbr->fetchObject($res);
		$total = intVal($row->C);
		$stats['ti_accuracy_percentage'] = $this->percent($accurate, $total); 
		$stats['ti_accuracy_total'] = $total; 

		$row = $dbr->fetchObject($res);
		$lastReset = $row->C;
		$stats['ti_accuracy_last_reset_timestamp'] = ""; 
		if(!is_null($lastReset) && '0000-00-00 00:00:00' != $lastReset) { 
			$stats['ti_accuracy_last_reset_timestamp'] = wfTimestamp(TS_MW, strtotime($row->C));
		}

		return $stats;
	}

	function percent($numerator, $denominator) {
		$percent = $denominator != 0 ? ($numerator / $denominator) * 100 : 0;
		return number_format($percent, 0);
	}
}

/*
*  Date of last update to Titus record
*/
class TSTimestamp extends TitusStat {
	public function getPageIdsToCalc(&$dbr,$date) {
		return(TitusDB::ALL_IDS); 
	} 

	public function calc(&$dbr, &$r, &$t, &$pageRow) {
		return array("ti_timestamp" => wfTimestamp(TS_MW));
	}
}

/*
* Whether the article is a rising star
*/
class TSRisingStar extends TitusStat {
	public function getPageIdsToCalc(&$dbr,$date) {
		return(TitusDB::DAILY_EDIT_IDS); 
	} 

	public function calc(&$dbr, &$r, &$t, &$pageRow) {
		return array("ti_risingstar" => 
			$dbr->selectField('pagelist', array('count(*)'), array('pl_page' => $pageRow->page_id, 'pl_list' => 'risingstar')));
	}
}

/*
* Number of wikiphotos, community photos and if the article has enlarged (> 499 px photos)
*/
class TSPhotos extends TitusStat {
	public function getPageIdsToCalc(&$dbr,$date) {
		return(TitusDB::DAILY_EDIT_IDS); 
	} 

	public function calc(&$dbr, &$r, &$t, &$pageRow) {
		global $wgLanguageCode, $wgContLang;

		$text = Wikitext::getSection($r->getText(), wfMsg('steps'), true);
		$text = $text[0];
    $numPhotos = preg_match_all('/(?:\[\[Image|\{\{largeimage|\[\[' . $wgContLang->getNSText(NS_IMAGE) . ')/im', $text, $matches);

		$stats=array();
		$stats['ti_num_photos'] = $numPhotos;
		if($wgLanguageCode == "en") {
			$numWikiPhotos = intVal($dbr->selectField(array('imagelinks','image'),'count(*)', array('il_from' => $pageRow->page_id, 'img_name = il_to', 'img_user_text' => 'Wikiphoto')));
			$stats = array_merge($stats, $this->getIntroPhotoStats($r));
			$stats['ti_num_wikiphotos'] = $numWikiPhotos;
			$stats['ti_enlarged_wikiphoto'] = intVal($this->hasEnlargedWikiPhotos($r));
			$stats['ti_num_community_photos'] = $numPhotos - $numWikiPhotos;
		}
		else {
			$stats['ti_enlarged_intro_photo'] = 0;
			$stats['ti_intro_photo'] = 0;
			$stats['ti_num_wikiphotos'] = 0;
			$stats['ti_enlarged_wikiphoto'] = 0;
			$stats['ti_num_community_photos'] = 0;
		}
		return $stats;
	}

	private function hasEnlargedWikiPhotos(&$r) {
		$enlargedWikiPhoto = 0;
		$text = Wikitext::getStepsSection($r->getText(), true);
		$text = $text[0];
		if ($text) {
			// Photo is enlarged if it is great than 500px (and less than 9999px)
			$enlargedWikiPhoto = preg_match('/\|[5-9][\d]{2,3}px\]\]/im', $text);
		}
		return $enlargedWikiPhoto;
	}

	private function getIntroPhotoStats(&$r) {
		$text = Wikitext::getIntro($r->getText());
		$stats['ti_intro_photo'] = intVal(preg_match('/\[\[Image:/im', $text));
		// Photo is enlarged if it is great than 500px (and less than 9999px)
		$stats['ti_enlarged_intro_photo'] = intVal(preg_match('/\|[5-9][\d]{2,3}px\]\]/im', $text));
		return $stats;
	}

}

/**
 * Count number of wikivideos on the site
 */
class TSWikiVideo extends TitusStat {
	public function getPageIdsToCalc(&$dbr,$date) {
		return(TitusDB::DAILY_EDIT_IDS); 
	} 

	public function calc(&$dbr, &$r, &$t, &$pageRow) {
	   	$stats = array(); 
		$text = Wikitext::getSection($r->getText(), wfMsg('steps'), true);
		$text = $text[0];
		$num = preg_match_all("@\{\{ *whvid\|[^\}]+ *\}\}@",$text, $matches);
		$stats['ti_num_wikivideos'] = $num;
		return($stats);
	}
	
}
/*
* Stu data (www and mobile) for article
*/
class TSStu extends TitusStat {
   public function getPageIdsToCalc(&$dbr,$date) {
	return(TitusDB::ALL_IDS); 
   } 

   public function calc(&$dbr, &$r, &$t, &$pageRow) {
     	$stats = array('ti_stu_10s_percentage_mobile' => 0, 'ti_stu_views_mobile' => 0, 
		'ti_stu_10s_percentage_www' => 0, 'ti_stu_3min_percentage_www' => 0, 'ti_stu_views_www' => 0);
	$domains = array('bt' => 'www', 'mb' => 'mobile');
	foreach ($domains as $domain => $label) {
		if(IS_CLOUD_SITE) {
			$query = "select * from stu.stu_dump where domain=" . $dbr->addQuotes($domain) . " AND page=" . $dbr->addQuotes($t->getDBKey());
			$res = $dbr->query($query);
			$rets = array();
			foreach($res as $row) {
				$rets[$row->page][$row->k] = $row->v;	
			}
			AdminBounceTests::cleanBounceData($rets);
			$stats = array_merge($stats, $this->extractStats($rets, $label));
		}
		else {
			$query = $this->makeQuery(&$t, $domain);
			$ret = AdminBounceTests::doBounceQuery($query);		
		
			if (!$ret['err'] && $ret['results']) {
				AdminBounceTests::cleanBounceData($ret['results']);
				$stats = array_merge($stats, $this->extractStats($ret['results'], $label));
			}		
		}
	}
        return $stats;
    }

    protected function makeQuery(&$t, $domain = 'bt') {
        return array(
            'select' => '*',
            'from' => $domain,
            'pages' => array($t->getDBkey()),
        );
    }

    private function extractStats(&$data, $label) {
        $headers = array('0-10s', '3+m');
        $stats = array();
        foreach ($data as $page => $datum) {
            AdminBounceTests::computePercentagesForCSV($datum, '');
            if (isset($datum['0-10s'])) {
                $stats['ti_stu_10s_percentage_' . $label] = $datum['0-10s'];
            }

            if ($label != 'mobile' && isset($datum['3+m'])) {
                $stats['ti_stu_3min_percentage_' . $label] = $datum['3+m'];
            }

            if (isset($datum['__'])) {
                $stats['ti_stu_views_' . $label] = $datum['__'];
            }
            break; // should only be one record
        }
        return $stats;
    }
}

/*
* Stu data (pv) for article
*/
class TSPageViews extends TitusStat {
	public function getPageIdsToCalc(&$dbr,$date) {
		return(TitusDB::ALL_IDS); 
	} 

    public function calc(&$dbr, &$r, &$t, &$pageRow) {
        $stats = array('ti_daily_views' => 0, 'ti_30day_views' => 0);
		
		$res = $dbr->select('pageview', array('pv_30day', 'pv_1day'), array('pv_page' => $pageRow->page_id), __METHOD__);
		while($row = $dbr->fetchObject($res)) {
			//only 1 row
			break;
		}
		
		if($row !== false) {
			$stats['ti_daily_views'] = intval($row->pv_1day);
			$stats['ti_30day_views'] = intval($row->pv_30day);
		}
		
        return $stats;
    }
}

/*
* Meta robot policy for article
*/
class TSRobotPolicy extends TitusStat {
	public function getPageIdsToCalc(&$dbr,$date) {
		return(TitusDB::DAILY_EDIT_IDS); 
	} 

	public function calc(&$dbr, &$r, &$t, &$pageRow) {
		$stats = array('ti_robot_policy' => '');
		// Request the html  a few times just in case the request is bad
		$i = 0;
		do {
			$html = $this->curlUrl($this->getBaseUrl() . $t->getLocalUrl());
			if(preg_match('@<meta name="robots" content="([^"]+)"@', $html, $matches)) {
				$stats['ti_robot_policy'] = $matches[1];
			}
		} while ($html == '' && ++$i < 5);
		
		/****
		 * NOTE: If we change how this is coded to return the integer value
		 * of the robot policy, we need to make sure hooks that use this
		 * are changed accordingly
		 */
		wfRunHooks('TitusRobotPolicy', array($t, $stats['ti_robot_policy']));
		
		return $stats;
	}
	
	function curlUrl($url) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 2);
        $contents = curl_exec($ch);
        if (curl_errno($ch)) {
            echo "curl error {$url}: " . curl_error($ch) . "\n";
        }

        curl_close($ch);
		return $contents;
    }
}

/*
* SEM Rush data for article
*/
class TSRushData extends TitusStat {
	public function getPageIdsToCalc(&$dbr,$date) {
		return(TitusDB::DAILY_EDIT_IDS); 
	} 

	public function calc(&$dbr, &$r, &$t, &$pageRow) {
		$aid = $pageRow->page_id;
		$stats = array('ti_rush_topic_rank' => 0, 'ti_rush_cpc' => 0, 'ti_rush_query' => '');

		$sql = "select * from rush_data ra inner join 
			(select rush_page_id, max(rush_volume) as max_vol from rush_data where rush_page_id = $aid group by rush_page_id) rb on  
			ra.rush_page_id = rb.rush_page_id and ra.rush_volume = rb.max_vol and ra.rush_page_id = $aid LIMIT 1";
		$res = $dbr->query($sql);
		if ($row = $dbr->fetchObject($res)) {
			$stats['ti_rush_topic_rank'] = $row->rush_position;
			$stats['ti_rush_cpc'] = $row->rush_cpc;
			$stats['ti_rush_query'] = $row->rush_query;
		}
		return $stats;
	}
}

/*
* Number of likes, plus ones and tweets
*/
class TSSocial extends TitusStat {
	public function getPageIdsToCalc(&$dbr,$date) {
		return(TitusDB::DAILY_EDIT_IDS); 
	} 

	public function calc(&$dbr, &$r, &$t, &$pageRow) {
		$url = $this->getBaseUrl() . urldecode($t->getLocalUrl());
		$stats = array();
		$stats['ti_tweets'] = $this->getTweets($url);
		// Turn off Facebook because it is getting rate limited, and we don't really use this stat
		//$stats['ti_facebook'] = $this->getLikes($url);
		$stats['ti_plusones'] = $this->getPlusOnes($url);
		return $stats;
	}
	
	function getTweets($url) {
		$json_string = file_get_contents('http://urls.api.twitter.com/1/urls/count.json?url=' . $url);
		$json = json_decode($json_string, true);
	 
		return intval($json['count']);
	}
	 
	function getLikes($url) {
		// is there no comma in WH url?
	 	if (strpos($url, ',') === false) {
			$fburl = 'http://graph.facebook.com/?ids=' . $url;
		} else {
			// per: http://stackoverflow.com/questions/12163978/facebook-graph-api-returns-error-2500-when-there-are-commas-in-the-id-url
			$fburl = 'http://graph.facebook.com/' . $url;
		}
		$json_string = file_get_contents($fburl);
		$json = json_decode($json_string, true);
	 
		return intval($json[$url]['shares']);
	}
	 
	function getPlusOnes($url) {
	 
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL, "https://clients6.google.com/rpc");
		curl_setopt($curl, CURLOPT_POST, 1);
		curl_setopt($curl, CURLOPT_POSTFIELDS, 
			'[{"method":"pos.plusones.get","id":"p","params":{"nolog":true,"id":"' . $url . 
			'","source":"widget","userId":"@viewer","groupId":"@self"},"jsonrpc":"2.0","key":"p","apiVersion":"v1"}]');
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-type: application/json'));
		$curl_results = curl_exec ($curl);
		curl_close ($curl);
	 
		$json = json_decode($curl_results, true);
	 
		return intval($json[0]['result']['metadata']['globalCounts']['count']);
	}
}
class TSTranslations extends TitusStat {

	public function getPageIdsToCalc(&$dbr, $date) {
		global $wgLanguageCode;

		$ts = wfTimestamp(TS_UNIX, $date);
		$start = wfTimestamp(TS_MW, strtotime("-2 day", strtotime(date('Ymd',$ts))));
	
		$sql = "select distinct tl_from_aid from " . WH_DATABASE_NAME_EN . ".translation_link where tl_from_lang=" . $dbr->addQuotes($wgLanguageCode) . " AND tl_timestamp > " . $dbr->addQuotes($start);
		$res = $dbr->query($sql, __METHOD__);
		$ids = array();
		foreach($res as $row) {
			$ids[] = $row->tl_from_aid;	
		}
		$sql = "select distinct tl_to_aid from " . WH_DATABASE_NAME_EN . ".translation_link where tl_to_lang=" . $dbr->addQuotes($wgLanguageCode) . " AND tl_timestamp > " . $dbr->addQuotes($start);
		$res = $dbr->query($sql, __METHOD__);
		foreach($res as $row) {
			$ids[] = $row->tl_to_aid;	
		}
		$sql = "select distinct tll_from_aid from " . WH_DATABASE_NAME_EN . ".translation_link_log where tll_from_lang=" . $dbr->addQuotes($wgLanguageCode) . " AND tll_timestamp>" . $dbr->addQuotes($start) . " AND NOT (tll_from_aid is NULL)";
		$res = $dbr->query($sql, __METHOD__);
		foreach($res as $row) {
			$ids[] = $row->tll_from_aid;	
		}

		$sql = "select distinct tll_to_aid from " . WH_DATABASE_NAME_EN . ".translation_link_log where tll_to_lang=" . $dbr->addQuotes($wgLanguageCode) . " AND tll_timestamp>" . $dbr->addQuotes($start) . " AND NOT (tll_to_aid is NULL)";
		$res = $dbr->query($sql, __METHOD__);
		foreach($res as $row) {
			$ids[] = $row->tll_to_aid;	
		}
		
		$ids = array_unique($ids);
		
		return($ids);
	}
	private function fixURL($url) {
		if(preg_match("@(http://[a-z]+\.wikihow\.com/)(.+)@",$url,$matches)) {
			return($matches[1] . urlencode($matches[2]));
		}
		else {
			return($url);	
		}
	}
	public function calc(&$dbr, &$r, &$t, &$pageRow) {
		global $wgLanguageCode;
		global $wgActiveLanguages;

		// Languages supported by Titus language_links
		$langs = $wgActiveLanguages;
		$langs[] = "en";

		// Added template fields to each language
		$ret = array();
		$links = array();
		foreach($langs as $l) {
			
			$ret["ti_tl_" . $l] =  "";
			$ret["ti_tl_" . $l . "_id"] = "";
		}
		$links = array_merge($links,TranslationLink::getLinksTo($wgLanguageCode,$pageRow->page_id));

		foreach($links as $l) {
			if($l->fromAID == $pageRow->page_id && $wgLanguageCode == $l->fromLang && in_array($l->toLang,$langs)) {
				if(isset($l->toURL)) {
					$ret["ti_tl_" . $l->toLang ] = $dbr->strencode($this->fixURL($l->toURL));
				}
				$ret["ti_tl_" . $l->toLang . "_id"] = intVal($l->toAID);
			}
			elseif($l->toAID == $pageRow->page_id && $wgLanguageCode == $l->toLang && in_array($l->fromLang, $langs)) {
				if(isset($l->fromURL)) {
					$ret["ti_tl_" . $l->fromLang] = $dbr->strencode($this->fixURL($l->fromURL));
				}
				$ret["ti_tl_" . $l->fromLang . "_id"] = intVal($l->fromAID);
			}
		}
		return $ret;
	}

}
/**
 * Does the page have a sample in Titus 
 */
class TSSample extends TitusStat {
  public function getPageIdsToCalc(&$dbr, $date) {
		return(TitusDB::DAILY_EDIT_IDS);
	}
	public function calc(&$dbr, &$r, &$t, &$pageRow) {
		$txt = $r->getText();
		$samples = 0;
		preg_match_all("/\[\[Doc:[^\]]*\]\]/", $txt, $matches);
		foreach($matches[0] as $match) {
			$samples++;
			$samples += preg_match_all('/,/', $match, $dummyMatches); 
					
		}
		$ret["ti_sample"] = $samples;
		return $ret;
	}
}
/**
 * Get info about most recent wikiphotos added to article 
 */
class TSRecentWikiphoto extends TitusStat {
	public function getPageIdsToCalc(&$dbr, $date) {
		return(TitusDB::DAILY_EDIT_IDS);	
	}
	public function calc(&$dbr, &$r, &$t, &$pageRow) {
		$res = $dbr->select("wikiphoto_article_status", array("creator","error","processed"),array("article_id" => $pageRow->page_id));
		$row = $dbr->fetchObject($res);
	
		$ret = array();
		if($row->creator != NULL && $row->error == NULL) {
			$ret["ti_wikiphoto_creator"] = $row->creator;
			$ret["ti_wikiphoto_timestamp"] = $row->processed;
		}
		else {
			$ret["ti_wikiphoto_creator"] = "";
			$ret["ti_wikiphoto_timestamp"] = "";
		}
		return($ret);
	}
}
/**
 * Update list of top 10k
 */
require_once('GoogleSpreadsheet.class.php');
class TSTop10k extends TitusStat {
	private $_kwl = array();
	private $_ln = array();
	private $_ids = array();
	private $_gotSpreadsheet = false;
	private $_badSpreadsheet = false;
	/**
	 * Get the spreadsheet to feed the calculations
	 */
	private function getSpreadsheet(&$dbr) {
		global $wgLanguageCode;
		print "Getting spreadsheet\n";
		try {
			$gs = new GoogleSpreadsheet();
			$gs->login(WH_TITUS_GOOGLE_LOGIN, WH_TITUS_GOOGLE_PW);
			$cols = $gs->getCols(WH_TITUS_TOP10K_GOOGLE_DOC,1,4,2);
			$urlList = array();	
			$pageIds = array();
			$dups = "";
			foreach($cols as $col) {
				if(is_numeric($col[1]) && $wgLanguageCode == $col[2]) {
					if(isset($this->_kwl[$col[1]])) {
						if($dups == "") {
							$dups = "Duplicate article ids: ";	
						}
						else {
							$dups .= ",";	
						}
						$dups .= $col[1];
					}
					$this->_kwl[$col[1]] = $col[0];	
					$this->_ln[$col[1]] = $col[3];
					$ids[] = $col[1];	
				}
			}
			if($dups != "") {
				$this->reportError($dups);	
			}
			if(sizeof($this->_kwl) < 1000 && $wgLangaugeCode == "en") {
				$this->_gotSpreadsheet = true;
				$this->_badSpreadsheet = true;
				$this->reportError("Top10k problem fetching spreadsheet. Fewer than 1000 ids found ");
				return;
			}
			$query = "select page_id,page_title  from " . Misc::getLangDB($wgLanguageCode) . ".page where page_is_redirect=1 AND page_id in (" . implode($ids,",") . ")";
			$res = $dbr->query($query,__METHOD__);
			$this->checkForRedirects($dbr, $ids);
			$this->checkForMissing($dbr, $ids);

			if($redirects != "") {
				$this->reportError($redirects);	
			}

			$query = "select ti_page_id, ti_top10k FROM " . TitusDB::getDBName() . "." . TitusDB::TITUS_INTL_TABLE_NAME  . " WHERE ti_language_code=" . $dbr->addQuotes($wgLanguageCode) ;
			$res = $dbr->query($query, __METHOD__);
			$pageIds = array();
			foreach($res as $row) {
				if(isset($this->_kwl[$row->ti_page_id])) {
					if($this->_kwl[$row->ti_page_id] != $row->ti_top10k) {
						$pageIds[] = $row->ti_page_id;	
					}
				}
				else {
					if($row->ti_top10k != NULL && $row->ti_top10k != "") {
						$pageIds[] = $row->ti_page_id;	
					}
				}
			}
			$this->_ids = $pageIds;
			$this->_gotSpreadsheet = true;
			$this->_badSpreadsheet = false;
		}
		catch(Exception $e) {
			$this->_gotSpreadsheet = true;
			$this->_badSpreadsheet = true;
			$this->reportError("Top10k problem fetching spreadsheet :" . $e->getMessage());
		}
	}
	/**
	 * Get the page ids to calculate
	 */
	public function getPageIdsToCalc(&$dbr, $date) {
		if(! $this->_gotSpreadsheet) {
			$this->getSpreadsheet($dbr);	
		}
		if($this->_badSpreadsheet) {
			return(array());	
		}
		return($this->_ids);
	}
	public function calc(&$dbr, &$r, &$t, &$pageRow) {
		global $wgLanguageCode;
		if(!$this->_gotSpreadsheet) {
			$this->getSpreadsheet($dbr);	
		}
		if($this->_badSpreadsheet) {
			return(array());	
		}
		$ret =array('ti_top10k'=>'', 'ti_is_top10k'=>0, 'ti_top_list' => '');
		if(isset($this->_kwl[$pageRow->page_id])) {
			//1000 is the database limit for the size of the keywords
			if(sizeof($this->_kwl[$pageRow->page_id]) > 1000) {
				$this->reportError("Keyword for " . $pageRow->page_id . " over 1000 characters(truncating) :" . $this->_kwl[$pageRow->page_id]); 
			}
			else {
				$ret['ti_top10k'] = $dbr->strencode($this->_kwl[$pageRow->page_id]);
				$ret['ti_is_top10k'] = 1;
				$ret['ti_top_list'] = $this->_ln[$pageRow->page_id];
			}
		}
		return($ret);
	}
}
class TSRatings extends TitusStat {
	private $_kwl = array();
	private $_ids = array();
	private $_gotSpreadsheet = false;
	private $_badSpreadsheet = false;

	private function getSpreadsheet(&$dbr) {
		global $wgLanguageCode;
		print "Getting ratings spreadsheet\n";
		try {
			$gs = new GoogleSpreadsheet();
			$gs->login(WH_TITUS_GOOGLE_LOGIN, WH_TITUS_GOOGLE_PW);
			$cols = $gs->getCols(WH_TITUS_RATINGS_GOOGLE_DOC,1,3,2);
			$ids = array();
			$badDates = 0;
			foreach($cols as $col) {
				if(is_numeric($col[0])) {
					$output = array($col[1],$this->fixDate($col[2]));
					if($output[1] == NULL) {
						$badDates++;	
					}
					if(isset($this->_kwl[$col[0]])) {
						$this->reportError("Duplicate ratings for article " . $col[0]);
					}
					$this->_kwl[$col[0]] = $output;	
					$ids[] = $col[0];
				}
			}
			if($badDates > 100) {
				$this->reportError("Unable to parse over 100 dates in spreadsheet");

				$this->_gotSpreadsheet=true;
				$this->_badSpreadsheet=true;
				return;

			}
			if(sizeof($ids) < 1000) {
				$this->reportError("Less than 1000 ratings in ratings spreadsheet found");
				$this->_gotSpreadsheet=true;
				$this->_badSpreadsheet=true;
				return;
			}
			$this->checkForRedirects($dbr, $ids);
			$this->checkForMissing($dbr, $ids);

			$query = "select ti_page_id, ti_rating, ti_rating_date FROM " . TitusDB::getDBName() . "." . TitusDB::TITUS_INTL_TABLE_NAME . " WHERE ti_language_code=" . $dbr->addquotes($wgLanguageCode) ;
			$res = $dbr->query($query, __METHOD__);
			$pageIds = array();
			foreach($res as $row) {
				if(isset($this->_kwl[$row->ti_page_id])) {
					if($this->_kwl[$row->ti_page_id][0] != $row->ti_rating || $this->_kwl[$row->ti_page_id][1]!=$row->ti_rating_date) {
						$pageIds[] = $row->ti_page_id;	
					}
				}
				else {
					if(($row->ti_rating != NULL && $row->ti_rating != "") || ($row->ti_rating_date != NULL && $row->ti_rating_date != "") ) {
						$pageIds[] = $row->ti_page_id;
					}
				}
			}
			$this->_ids = $pageIds;	

			$this->_gotSpreadsheet = true;
			$this->_badSpreadsheet = false;

		}
		catch(Exception $e) {
			$this->_gotSpreadsheet = true;
			$this->_badSpreadsheet = true;
			$this->reportError("Problem fetching spreadsheet :" . $e->getMessage());
		}
	}
	private function fixDatePart($part) {
		if($part < 10) {
			return('0' . $part);	
		}
		else {
			return($part);	
		}
	}
	private function fixDate($date) {
		$d=date_parse($date);
		if($d) {
			return($d['year'] . $this->fixDatePart($d['month']) . $this->fixDatePart($d['day']) );
		}
		else {
			return(NULL);				
		}
	}
	public function calc(&$dbr, &$r, &$t, &$pageRow) {
		global $wgLanguageCode;

		if(!$this->_gotSpreadsheet) {
			$this->getSpreadsheet($dbr);
		}
		if(isset($this->_kwl[$pageRow->page_id]) && $wgLanguageCode == "en") {
			$a = $this->_kwl[$pageRow->page_id];
			return(array("ti_rating"=> $a[0],"ti_rating_date"=> $a[1]));		
		}
		else {
			return(array("ti_rating"=>"","ti_rating_date"=>""));	
		}
	}
	public function getPageIdsToCalc(&$dbr, $date) {
		global $wgLanguageCode;
		if($wgLanguageCode != "en") { 
			return(array());	
		}
		if(!$this->_gotSpreadsheet) {
			$this->getSpreadsheet($dbr);
		}
		
		return($this->_ids);	
	}
}
class TSLastFellowEdit extends TitusStat {
	private $_kwl = array();
	private $_ids = array();
	private $_gotSpreadsheet = false;
	private $_badSpreadsheet = false;
  private function fixDatePart($part) {
	   if($part < 10) {
			 return('0' . $part);
		 }
		 else {
			return($part);
	   }
	}

	private function fixDate($d) {
		if(is_numeric($d) && sizeof($d) == 14) {
			return(substr($d,0,8));	
		}
		else {
			$p = date_parse($d);
			if(isset($p['year']) && isset($p['month']) && isset($p['day'])) {
				return($p['year'] . $this->fixDatePart($p['month']) . $this->fixDatePart($p['day']));
			}
			else {
				return(NULL);	
			}
		}
	}
	private function getSpreadsheet(&$dbr) {
		global $wgLanguageCode;
		print "Getting ratings spreadsheet\n";
		try {
			$gs = new GoogleSpreadsheet();
			$gs->login(WH_TITUS_GOOGLE_LOGIN, WH_TITUS_GOOGLE_PW);
			$cols = $gs->getCols(WH_TITUS_EDITOR_GOOGLE_DOC,1,3,2);
			$ids = array();
			$badDates = 0;
			foreach($cols as $col) {
				if(is_numeric($col[0])) {
					$output = array($this->fixDate($col[1]),$col[2]);
					if($output[1] == NULL) {
						$badDates++;
					}
					if(isset($this->_kwl[$col[0]])) {
						$this->reportError("Duplicate entry for article " . $col[0]);
					}
					$this->_kwl[$col[0]] = $output;	
					$ids[] = $col[0];
				}
			}
			if($badDates > 100) {
				$this->reportError("Unable to parse over 100 dates in spreadsheet");

				$this->_gotSpreadsheet=true;
				$this->_badSpreadsheet=true;
				return;

			}
			if(sizeof($ids) < 1000) {
				$this->reportError("Less than 1000 ratings in ratings spreadsheet found");
				$this->_gotSpreadsheet=true;
				$this->_badSpreadsheet=true;
				return;
			}
			$this->checkForRedirects($dbr, $ids);
			$this->checkForMissing($dbr, $ids);

			$query = "select ti_page_id, ti_last_fellow_edit, ti_last_fellow_edit_timestamp FROM " . TitusDB::getDBName() . "." . TitusDB::TITUS_INTL_TABLE_NAME . " WHERE ti_language_code=" . $dbr->addquotes($wgLanguageCode) ;
			$res = $dbr->query($query, __METHOD__);
			$pageIds = array();
			foreach($res as $row) {
				if(isset($this->_kwl[$row->ti_page_id])) {
					if($this->_kwl[$row->ti_page_id][0] != $row->ti_last_fellow_edit_timestamp || $this->_kwl[$row->ti_page_id][1]!=$row->ti_last_fellow_edit) {
						$pageIds[] = $row->ti_page_id;	
					}
				}
				else {
					if(($row->ti_last_fellow_edit_timestamp != NULL && $row->ti_last_fellow_edit_timestamp != "") || ($row->ti_last_fellow_edit != NULL && $row->ti_last_fellow_edit != "") ) {
						$pageIds[] = $row->ti_page_id;
					}
				}
			}
			$this->_ids = $pageIds;	

			$this->_gotSpreadsheet = true;
			$this->_badSpreadsheet = false;

		}
		catch(Exception $e) {
			$this->_gotSpreadsheet = true;
			$this->_badSpreadsheet = true;
			$this->reportError("Problem fetching spreadsheet :" . $e->getMessage());
		}
	}
	public function calc(&$dbr, &$r, &$t, &$pageRow) {
		global $wgLanguageCode;

		if(!$this->_gotSpreadsheet) {
			$this->getSpreadsheet($dbr);
		}
		if(isset($this->_kwl[$pageRow->page_id]) && $wgLanguageCode == "en") {
			$a = $this->_kwl[$pageRow->page_id];
			return(array("ti_last_fellow_edit_timestamp"=> $a[0],"ti_last_fellow_edit"=> $a[1]));		
		}
		else {
			return(array("ti_last_fellow_edit_timestamp"=>"","ti_last_fellow_edit"=>""));	
		}
	}
	public function getPageIdsToCalc(&$dbr, $date) {
		global $wgLanguageCode;
		if($wgLanguageCode != "en") { 
			return(array());	
		}
		if(!$this->_gotSpreadsheet) {
			$this->getSpreadsheet($dbr);
		}
		
		return($this->_ids);	
	}

}
class TSLastPatrolledEditTimestamp extends TitusStat {
	public function getPageIdsToCalc(&$dbr,$date) {
		return(TitusDB::DAILY_EDIT_IDS); 
	}

	public function calc(&$dbr, &$r, &$t, &$pageRow) {
		return(array('ti_last_patrolled_edit_timestamp' => $r->getTimestamp() ));
	}
}
/**
 * Load Babelfish score and rank into Titus
 */
class TSBabelfishData extends TitusStat {
	public function getPageIdsToCalc(&$dbr,$date) {
		return(TitusDB::DAILY_EDIT_IDS);	
	}

	public function calc(&$dbr, &$r, &$t, &$pageRow) {
		global $wgLanguageCode;

		$ret = array('ti_babelfish_rank' => NULL, 'ti_babelfish_score' => NULL);
		if($wgLanguageCode != 'en') {
			$sql = "select ct_rank, ct_score FROM " . WH_DATABASE_NAME_EN . ".babelfish_articles " 
		. " JOIN " . WH_DATABASE_NAME_EN . ".translation_link on tl_from_lang='en' AND tl_from_aid = ct_page_id AND tl_to_lang=ct_lang_code"
		. " WHERE tl_to_aid=" . $dbr->addQuotes($pageRow->page_id) . " AND ct_lang_code=" . $dbr->addQuotes($wgLanguageCode);
			$res = $dbr->query($sql, __METHOD__);
			if($row = $dbr->fetchObject($res)) {
				$ret['ti_babelfish_rank'] = $row->ct_rank;
				$ret['ti_babelfish_score'] = $row->ct_score;
			}
		}
		else {
			// Grabbing Spanish rank for article from English because all articles have the same rank and score for all languages
			$sql = "select ct_rank, ct_score FROM " . WH_DATABASE_NAME_EN . ".babelfish_articles WHERE ct_page_id=" . $dbr->addQuotes($pageRow->page_id) . " AND ct_lang_code='es'";	
			$res = $dbr->query($sql, __METHOD__);
			if($row = $dbr->fetchObject($res)) {
				$ret['ti_babelfish_rank'] = $row->ct_rank;
				$ret['ti_babelfish_score'] = $row->ct_score;
			}
		}

		return($ret);
	}
}
/**
 * When Titus article was new article boosted
 */
class TSNAB extends TitusStat {
	public function getPageIdsToCalc(&$dbr,$date) {
		global $wgLanguageCode;

		$ts = wfTimestamp(TS_UNIX, $date);
		$d = wfTimestamp(TS_MW, strtotime("-2 day", strtotime(date('Ymd',$ts))));
		$langDB = Misc::getLangDB($wgLanguageCode);
		$sql = "select nap_page FROM " . $langDB .  ".newarticlepatrol JOIN " . $langDB . ".page on page_id = nap_page WHERE page_is_redirect = 0 AND nap_patrolled = 1 AND nap_timestamp_ci > " . $dbr->addQuotes($d);
		$res = $dbr->query($sql, __METHOD__);

		$pr = array();
		foreach($res as $row) {
			$pr[$row->nap_page] = 1;
		}
		$sql = "select de_page_id FROM " . $langDB . ".daily_edits left join " . $langDB . ".newarticlepatrol on de_page_id = nap_page where nap_patrolled is NULL AND de_edit_type <> " . DailyEdits::DELETE_TYPE . " AND de_timestamp > " . $dbr->addQuotes($d) ;
		$res = $dbr->query($sql, __METHOD__);
		foreach($res as $row) {
			$pr[$row->de_page_id] = 1;	
		}
		$ids = array_keys($pr);

		return($ids);
	}

	public function calc(&$dbr, &$r, &$t, &$pageRow) {
		$nab = Newarticleboost::isNABbed($dbr, $pageRow->page_id) ? '1' : '0';
		$ret = array('ti_nab' => $nab);
		return($ret);
	}
}
