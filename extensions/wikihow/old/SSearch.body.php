<?php

if (!defined('MEDIAWIKI')) die();

global $IP;
require_once($IP.'/extensions/wikihow/sphinx/sphinxapi.php');

class SSearch extends UnlistedSpecialPage {

	public function __construct() {
		parent::__construct('SSearch');
	}

	private function getInfo(&$matches) {
		foreach ($matches as $docinfo) {
			$ids[] = $docinfo['id'];
		}
		$id_str = join(',', $ids);

		$dbr =& wfGetDB(DB_SLAVE);
		$sql = "
			SELECT wst_id, wst_title, wst_url_title, wst_popularity,
			  wst_is_featured, wst_timestamp, wst_img_thumb_100
			FROM wh_sphinx_text
			WHERE wst_id IN ($id_str);";
		$res = $dbr->query($sql);

		$docinfo = array();
		while ($row = $dbr->fetchRow($res)) {
			$docinfo[ $row['wst_id'] ] = $row;
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
			$titles[ $di['wst_id'] ] = $di['wst_title'];
		}

		return $titles;
	}

	private function doSearch($q, $page) {
		global $wgOut;

		$mode = SPH_MATCH_ALL;
		$index = 'suggested_titles';
		$page_size = 20;
		$limit = 1000;
		$ranker = SPH_RANK_PROXIMITY_BM25;
		$host = 'localhost';
		$port = 9312;

		$cl = new SphinxClient();
		$cl->SetServer($host, $port);
		//$cl->SetConnectTimeout(1);
		$cl->SetSortMode(SPH_SORT_RELEVANCE);
		$cl->SetArrayResult(true);
		$cl->SetWeights( array('wst_title' => 5, 'wst_text' => 2) );
		$cl->SetMatchMode($mode);
		$cl->SetRankingMode($ranker);
		$cl->SetLimits(($page - 1) * $page_size, $page_size, $limit);

		// don't search w/ leading "how to" if user added it
		$q_prime = preg_replace('@^\s*how\s+to\s+@i', '', $q);
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
			$titles = $this->getInfo($res['matches']);
			$keys = array_keys($titles);
			$excerpts = $cl->BuildExcerpts($titles, 'suggested_titles', $q);
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

	public static function searchBox($q) {
		$vars = array(
			'q' => $q,
		);
		return EasyTemplate::html('search-box', $vars);
	}

	public static function searchResultsJS() {
		return EasyTemplate::html('search-results-js');
	}

	/**
	 * Executes the SSearch special page and all its sub-calls
	 */
	public function execute($par) {
		global $wgRequest, $wgUser, $wgOut, $wgLang, $wgServer;

		wfLoadExtensionMessages('SSearch');

		self::setTemplatePath();

		if ($wgUser->isBlocked()) {
			$wgOut->blockedPage();
			return;
		}

		$q = $wgRequest->getVal('q', '');
		$q = strip_tags($q);
		$q = trim($q);
		$page = $wgRequest->getInt('p', 1);
		if (empty($q)) {
			$wgOut->setPageTitle(wfMsg('search-wikihow'));
		} else {
			$wgOut->setHTMLTitle(wfMsg('search-for-title', $q));
		}

		if (!empty($q)) {
			$vars = $this->doSearch($q, $page);
			$html = EasyTemplate::html('search-results', $vars);
			$wgOut->addHTML($html);
		} else {
			$html = EasyTemplate::html('landing');
			$wgOut->addHTML($html);
		}
	}
}

