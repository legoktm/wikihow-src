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
	public function getBatch() {
		set_time_limit(0);
		require_once('dedupQuery.php');
		$queries = preg_split("@[\r\n]+@",$this->queriesR);
		$queryE = array();
		$dbw = wfGetDB(DB_MASTER);
		foreach($queries as $query) {
			dedupQuery::addQuery($query);
			$queryE[] = $dbw->addQuotes($query);
		}
		$sql = "insert ignore into dedup.query_match select ql.ql_query as query1, ql2.ql_query as query2, count(*) as ct from dedup.query_lookup ql join dedup.query_lookup ql2 on ql2.ql_url=ql.ql_url where ql.ql_query in (" . implode(',',$queryE) . ") group by ql.ql_query, ql2.ql_query";
		$dbw->query($sql, __METHOD__);
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
		global $wgOut, $wgRequest;
		$action = $wgRequest->getVal('act');
		$this->queriesR = $wgRequest->getVal('queries');
		if($action == NULL) {
			EasyTemplate::set_path(dirname(__FILE__));
			$wgOut->addHTML(EasyTemplate::html('Dedup.tmpl.php'));
		}
		elseif($action == 'getBatch' && $this->queriesR) {
			$this->getBatch();
		}
	}
}
