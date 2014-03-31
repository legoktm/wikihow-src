<?php

if (!defined('MEDIAWIKI')) die();

global $IP;
require_once($IP.'/extensions/wikihow/sphinx/sphinxapi.php');

class SphinxSearch {

	public function __construct() { }

	private function getInfo($index, &$matches) {
		foreach ($matches as $docinfo) {
			$ids[] = $docinfo['id'];
		}
		$id_str = join(',', $ids);

		if ($index == 'suggested_titles') {
			$titleCol = 'st_title';
			$idCol = 'st_id';
		} else {
			$titleCol = 'skey_title';
			$idCol = 'skey_id';
		}
		$dbr =& wfGetDB(DB_SLAVE);
		$sql = "
			SELECT $titleCol as title, $idCol as id FROM $index
			WHERE $idCol IN ($id_str);";
		$res = $dbr->query($sql);

		$docinfo = array();
		while ($row = $dbr->fetchRow($res)) {
			$docinfo[ $row['id'] ] = $row;
		}

		foreach ($matches as &$docinfo_match) {
			$id = $docinfo_match['id'];
			$attrs =& $docinfo_match['attrs'];
			if (isset($docinfo[$id])) {
				$attrs = array_merge( $attrs, $docinfo[ $id ] );
			}
		}

		$titles = array();
		foreach ($docinfo as $di) {
			$titles[ $di['id'] ] = $di['title'];
		}

		return $titles;
	}

	public function searchSuggestedTitles($q, $limit = 10) {
		$index = 'suggested_titles';
		$weights = array('st_title' => 5, 'st_key' => 2);
		return $this->doSearch($q, $index, $weights, $limit);
	}

	public function searchTitles($q, $limit = 10) {
		$index = 'titles';
		$weights = array('skey_title' => 5, 'skey_key' => 2);
		return $this->doSearch($q, $index, $weights, $limit);
	}

	private function doSearch($q, $index, $weights, $limit) {

		$mode = SPH_MATCH_ALL;
		$page = 1;
		$page_size = $limit;
		// very small limit because we can never go to next
		// page with autocomplete
		$limit = $limit;
		$ranker = SPH_RANK_PROXIMITY_BM25;
		$host = 'localhost';
		$port = 9312;

		$cl = new SphinxClient();
		$cl->SetServer($host, $port);
		//$cl->SetConnectTimeout(1);
		$cl->SetSortMode(SPH_SORT_RELEVANCE);
		$cl->SetArrayResult(true);
		$cl->SetWeights($weights);
		$cl->SetMatchMode($mode);
		$cl->SetRankingMode($ranker);
		$cl->SetLimits(($page - 1) * $page_size, $page_size, $limit);

		// don't search w/ leading "how to" if user added it
		$q_prime = preg_replace('@^\s*' . wfMsg('howto', '') . '\s+@i', '', $q);
		$res = $cl->Query($q_prime, $index);

		$error = ($res === false ? $cl->GetLastError() : '');
		$warning = $cl->GetLastWarning();

		/*$spelling = $this->getSpellingInfo($q);
		if ($spelling) {
			$res['spelling'] = $spelling;
		} else {
			$res['spelling'] = '';
		}*/

		if (count($res['matches']) > 0) {
			$titles = $this->getInfo($index, $res['matches']);
			$keys = array_keys($titles);
			$excerpts = $cl->BuildExcerpts($titles, $index, $q);
			foreach ($excerpts as $i => $excerpt) {
				$excerpts[ $keys[$i] ] = $excerpt;
				unset($excerpts[$i]);
			}
			foreach ($res['matches'] as $i => &$docinfo) {
				$id = $docinfo['id'];
				$docinfo['attrs']['excerpt'] = $excerpts[$id];
			}
		} else {
			$error = wfMsg('search-keywords-not-found', $q);
		}

		// construct paging bar
		/*
		$total = (int)ceil(1.0 * $res['total_found'] / $page_size);
		$paging = array();
		if ($page > 1) $paging[] = 'prev';
		if ($page > 1) $paging[] = 1;
		if ($page >= 5) $paging[] = '...';
		if ($page >= 4) $paging[] = $page - 2;
		if ($page >= 3) $paging[] = $page - 1;
		$paging[] = $page;
		if ($page < $total) $paging[] = $page + 1;
		if ($page+1 < $total) $paging[] = $page + 2;
		if ($page+2 < $total) $paging[] = '...';
		if ($page < $total) $paging[] = 'next';
		*/

		$vars = array(
			'results' => $res,
			'q' => $q,
			'error' => $error,
			'warning' => $warning,
			'page' => $page,
			'page_size' => $page_size,
			'paging' => $paging,
		);
		return $vars;
	}

	/**
	 * Set html template path for Easyimageupload actions
	 */
	private static function setTemplatePath() {
		EasyTemplate::set_path( dirname(__FILE__).'/sphinx/' );
	}

}

