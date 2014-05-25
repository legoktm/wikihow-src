<?php
class Dedup extends UnlistedSpecialPage {
	public function __construct() {
		parent::__construct("Dedup");	
	}
	private $queriesR;
	private $queryMatches;
	private $urlMatches;

	private function printLine() {
		$urlMatches = array();
		$first = true;
		foreach($this->closestUrls as $closestUrl) {
			if($first) {
				$first = false;
				$closeURL = $closestUrl['url'];
				$closeURLScore = $closestUrl['score'];
			}
			else {
				$urlMatches[] = $closestUrl['url'] . " (" . $closestUrl['score'] . ")";
			}
		}

		if(!$closeURLScore || $closeURLScore <= 7) {
			$todo = "write";	
		}
		elseif($closeURLScore >= 35) {
			$todo = "dup URL";	
		}
		else {
			$todo = "not sure";	
		}
		if(sizeof($urlMatches) > 5) { 
			$urlMatches = array_slice($urlMatches, 0,5);	
		}
		print $this->query . "\t" . $todo . "\t" . $closeURL . "\t" . $closeURLScore . "\t" . implode("| ",$this->queryMatches) . "\t" . implode("| ",$urlMatches) . "\n";

	}
	/** 
	 * Match dedup queries against themselves instead of against other things
	 */
	public function getTopMatchBatch() {
		set_time_limit(0);
		require_once('dedupQuery.php');
		print("\"Query\",\"Status\",\"Dup queries >= 35\",\"Dup queries 19-34\"\n");
		$queries = preg_split("@[\r\n]+@",$this->queriesR);
		$dbw = wfGetDB(DB_MASTER);
		$queryE = array();
		foreach($queries as $query) {
			dedupQuery::addQuery($query);
			$queryE[] = $dbw->addQuotes($query);
		}
		dedupQuery::matchQueries($queries);
		$dbr = wfGetDB(DB_SLAVE);
		$sql = "select query1, query2, ct from dedup.query_match where query1 <> query2 and query1 in (" . implode($queryE,",") . ") and query2 in (" . implode($queryE,",") . ") group by query1, query2 order by field(query1," . implode($queryE,",") . ")"; 
		$res = $dbr->query($sql, __METHOD__);
		$last = false;
		
		header("Content-Type: text/csv");
		header('Content-Disposition: attachment; filename="Dedup.csv"');
		$clusters35 = array();
		$clusters19 = array();
		$nondup = array();
		$dup = array();
		$posDup = array();
		foreach($res as $row) {
			if($row->ct >= 35) {
				$clusters35[$row->query1][] = $row->query2;
				if(!in_array($row->query1,$dup) && !in_array($row->query1, $posDup)) {
					$nondup[] = $row->query1;
				}
				if(!in_array($row->query2, $nondup)) {
					$dup[] = $row->query2;	
				}
			}
			elseif($row->ct >= 19) {
				$clusters19[$row->query1][] = $row->query2;
				if(!in_array($row->query1, $dup) && !in_array($row->query1, $posDup)) {
					$nondup[] = $row->query1;
				}
				if(!in_array($row->query2, $nondup)) {
					$posDup[] = $row->query2;
				}
			}
		}
		foreach($queries as $query) {
			print("\"" . addslashes($query) . "\",\"");
			if(in_array($query, $dup)) {
				print("duplicate");	
			}
			elseif(in_array($query, $posDup)) {
				print("possible duplicate");	
			}
			elseif(isset($clusters35[$query])) {
				print("dup check");	
			}
			else {
				print("write");	
			}
			print("\",\"");
			if(isset($clusters35[$query])) {
				print(addslashes(implode("\r",$clusters35[$query])));
			}
			print("\",\"");
			if(isset($clusters19[$query])) {
				print(addslashes(implode("\r",$clusters19[$query])));
			}

			print("\"\n");
		}
		print("\n");
		exit;
	}
	public function getBatch() {
		set_time_limit(0);
		require_once('dedupQuery.php');
		$queries = preg_split("@[\r\n]+@",$this->queriesR);
		$dbw = wfGetDB(DB_MASTER);
		$queryE = array();
		foreach($queries as $query) {
			dedupQuery::addQuery($query);
			$queryE[] = $dbw->addQuotes($query);
		}
		dedupQuery::matchQueries($queries);
		$dbr = wfGetDB(DB_SLAVE);
		$sql = "select query1, query2, ct, tq_title from dedup.query_match left join dedup.title_query on tq_query=query2 where query1 in (" . implode($queryE,",") . ") order by query1, ct desc"; 
		$res = $dbr->query($sql, __METHOD__);
		$query = false;
		header("Content-Type: text/tsv");
		header('Content-Disposition: attachment; filename="Dedup.xls"');

		print("Query\tWhat to do\tclosest URL match\tclosest URL score\tquery matches >= 35\tnext closest URL matches (max 5)\n");
		$this->closestUrls = array();
		$this->query = false;
		$this->queryMatches = array();
		foreach($res as $row) {
			if($this->query != $row->query1) {
				if($this->query) {
					$this->printLine();
				}
				$this->queryMatches = array();
				$this->closestUrls = array();
				$this->query = $row->query1;
			}
			if($row->ct >= 35 && $row->query1 != $row->query2) {
				$this->queryMatches[] = $row->query2;
			}
			if($row->tq_title) {
				$this->closestUrls[] = array('url' => ("http://www.wikihow.com/" . str_replace(" ","-",$row->tq_title)), 'score' => $row->ct);		
			}
		}

		if($this->query) {
			$this->printLine();
		}

		exit;
	}
	public function execute() {
		global $wgOut, $wgRequest, $wgUser;
		$userGroups = $wgUser->getGroups();
		if(!in_array('staff',$userGroups)) {
			$wgOut->setRobotpolicy('noindex,nofollow');
			$wgOut->showErrorPage('nosuchspecialpage', 'nospecialpagetext');
			return;
		}
		$action = $wgRequest->getVal('act');
		$this->queriesR = $wgRequest->getVal('queries');
		if($action == NULL) {
			EasyTemplate::set_path(dirname(__FILE__));
			$wgOut->addHTML(EasyTemplate::html('Dedup.tmpl.php'));
		}
		elseif($action == 'getBatch' && $this->queriesR) {
			$internalDedup = $wgRequest->getVal('internalDedup');
			if($internalDedup) {
				$this->getTopMatchBatch();	
			}
			else {
				$this->getBatch();
			}
		}
	}
}
