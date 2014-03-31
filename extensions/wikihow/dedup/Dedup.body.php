<?php
class Dedup extends UnlistedSpecialPage {
	public function __construct() {
		parent::__construct("Dedup");	
	}
	public function execute() {
		global $wgOut, $wgRequest;
		$action = $wgRequest->getVal('act');
		$queriesR = $wgRequest->getVal('queries');
		if($action == NULL) {
			EasyTemplate::set_path(dirname(__FILE__));
			$wgOut->addHTML(EasyTemplate::html('Dedup.tmpl.php'));
		}
		elseif($action == 'getBatch' && $queriesR) {
			set_time_limit(0);
			require_once('dedupQuery.php');
			$queries = preg_split("@[\r\n]+@",$queriesR);
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

			print("Query1\tQuery2\tTitle\tScore\t...");
                        foreach($res as $row) {
                                if($query != $row->query1) {
                                        print("\n" . $row->query1);
                                        $query = $row->query1;
                                }
				print ("\t" . $row->query2 . "\t" .($row->tq_title ? ("http://www.wikihow.com/" . str_replace(" ","-",$row->tq_title)) : "") . "\t" . $row->ct);
                        }
			exit;
		}
	}
}
