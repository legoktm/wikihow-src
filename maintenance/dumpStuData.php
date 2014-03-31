<?php
# Dump all the STU data for all main namespace articels
require_once('commandLine.inc');

/**
 * Allow dumping all the stu data to the stu_dump table.
 */
class DumpStuData {
	const STU_DATABASE='stu';
	const STU_TABLE='stu_dump';
	const STU_BATCH_SIZE=500;
	const STU_DELAY=500000;
	const STU_RETRY_DELAY=500000;
	const STU_MAX_RETRIES=20;

	private $dbr;
	private $dbw;
	private $pagesProcessed;
	private $errors;
	public $dryRun;

	function __construct() {
		$this->dbr = wfGetDB(DB_SLAVE);
		$this->dbw = DatabaseBase::factory('mysql');
		$this->dbw->open(WH_DATABASE_BACKUP, WH_DATABASE_MAINTENANCE_USER, WH_DATABASE_MAINTENANCE_PASSWORD, self::STU_DATABASE);
		$this->pagesProcessed = 0;
		$this->errors = false;
		$this->warnings = false;
		$this->dryRun = false;
	}
	function deleteStuData() {
		$sql = "delete from " . self::STU_TABLE;
		if($this->dryRun) {
			print $sql;	
		}
		else {
			$this->dbw->query($sql, __METHOD__);	
		}
	}

	/**
 	 * Get STU data for the all main namespace pages to put into the database
	 */
	function getAllPages() {
		$res = $this->dbr->select('page',array('page_title'),array('page_namespace' => 0, 'page_is_redirect'=>0));
		$pageTitles = array();
		foreach($res as $row) {
			$pageTitles[] = $row->page_title;
		}
		$domains = array("bt","mb","pv");
		foreach($domains as $domain) {
			$pages = array();
			foreach($pageTitles as $pageTitle) {
				$pages[] = $pageTitle;
				if(sizeof($pages) >= self::STU_BATCH_SIZE) {
					$this->getPagesWithRetry($pages, $domain, self::STU_MAX_RETRIES);
					$pages = array();
				}
			}
			$this->getPagesWithRetry($pages, $domain, self::STU_MAX_RETRIES);
		}

	}

	/**
	 * Get pages from STU doing a retry when it fails
	 * @param pages The pages to get
	 * @param domain STU domain to get
	 * @param maxRetries The maximum number of times to retry before giving up
	 */
	function getPagesWithRetry($pages, $domain, $maxRetries=10) {
		$retries = 0;
		// Delay before trying
		usleep(self::STU_DELAY);

		while($retries < $maxRetries && !$this->getPages($pages, $domain)) {
			// Delay between retries when there is a fail
			usleep(self::STU_RETRY_DELAY);
			$retries++;	
		}
		if($retries > 0) {
			if($retries == $maxRetries) {
				print "ERROR: Failed for domain " . $domain . " on articles:\n";
				foreach($pages as $page) {
					print($page . "\n");	
					if(!$this->errors) 
						$this->errors=0;
					$this->errors++;
				}
			}
			print "WARNING: Succeeded after " . $retries . " retries for pages:\n";
			foreach($pages as $page) {
				print($page . "\n");	
				if(!$this->warnings)
					$this->warnings=0;
				$this->warnings++;
			}
		}
	}

	/** 
 	 * Get a bunch of pages from STU, and put them into the database
	 */	
	function getPages($pages, $domain) {
		$ret = AdminBounceTests::doBounceQuery(array('select' => '*',
					      	     'from'   => $domain,
      					             'pages'  => $pages));
		$data = $ret['results'];
		
		$didInsert = false;
		if(is_array($data)) {
			$sql = 'insert ignore into ' . self::STU_TABLE . '(page, domain, k, v) values';
			$first = true;
			foreach($data as $page => $datum) {
				if(is_array($datum)) {
					foreach($datum as $k => $v) {	
						if($first) {
							$first=false;
							
						}
						else {
							$sql .= ",";
						}
						$sql .= "(" . $this->dbw->addQuotes($page) . "," . $this->dbw->addQuotes($domain) . "," . $this->dbw->addQuotes($k) . "," . $this->dbw->addQuotes($v) . ")\n";
						$this->pagesProcessed++;
						print $page . "\t" . $domain . "\t" . $k . "\t" . $v . "\n";	
					}
					
				}
			}
			// Only do insert if we have the data
			if(!$first) {
				if($this->dryRun) {
					print($sql);	
				} else {
					$this->dbw->query($sql, __METHOD__);
				}
				return(true);
			}
			else {
				return(false);	
			}
		}
		else {
			return(false);	
		}
	}

	/** 
	 * Log the number of pages processed into a log. This db log, will be
	 * used to activate Titus, when it is available
	 */
	function logDump() {
		global $hostname;
		if($this->dryRun) {
			$arr = array('sdl_finished'=> wfTimestampNow(),
																						 'sdl_hostname'=> $hostname,
																						 'sdl_pages_processed' => $this->pagesProcessed);
			print_r($arr);
		}
		else {
			$this->dbw->insert("stu_dump_log", array('sdl_finished'=> wfTimestampNow(),
																						 'sdl_hostname'=> $hostname,
																						 'sdl_pages_processed' => $this->pagesProcessed));
		}
	}
	
	function sendErrorEmail() {
		if($this->errors || $this->warnings || $this->pagesProcessed == 2000) {
			$to = new MailAddress("gershon@wikihow.com, reuben@wikihow.com, john@wikihow.com");
			$from = new MailAddress("alerts@wikihow.com");
			$subject = "Errors or warnings doing STU dump";
			$msg = "";
			if($this->warnings) {
				$msg .= "Retries were required to get STU data on " . $this->warnings . " articles.";
			}	
			if($this->errors) {
				$msg .= " Failed to get STU data on " . $this->errors . " articles."; 
			}	
			if($this->pagesProcessed < 2000) { 
				$msg .= " Less than 2000 pages processed";
			}
			UserMailer::send($to, $from, $subject, $msg); 
		}
	}
}

$dsd = new DumpStuData();
if($argv[0] == "dry") {
	$dsd->dryRun = true;
	print "Dry run";
}
$dsd->deleteStuData();
$dsd->getAllPages();
$dsd->logDump();
$dsd->sendErrorEmail();
