<?php

class GoogSearch extends SpecialPage {

	function __construct() {
		parent::__construct( 'GoogSearch' );
		$this->setListed(false);
	}
	
	public static function removeSideBarCallback(&$showSideBar) {
		$showSideBar = false;
		return true;
	}
	
	public static function removeBreadCrumbsCallback(&$showBreadCrum) {
		$showBreadCrum = false;
		return true;
	}
	
	public static function removeGrayContainerCallback(&$showGrayContainer) {
		$showGrayContainer = false;
		return true;
	}

	function getSearchBox($formid, $q = '', $size = 30) {
		global $wgServer, $wgLanguageCode;
		$search_box = wfMessage('cse_search_box_new', "", $formid, $size, htmlspecialchars($q), $wgLanguageCode)->text();
		$search_box = preg_replace('/\<[\/]?pre\>/', '', $search_box);
		return $search_box;
	}

	function getSearchBoxJS() {
		global $wgLanguageCode;
		$html = <<<EOHTML
<script type="text/javascript">
	$(document).ready(function () {
		loadGoogleCSESearchBox('$wgLanguageCode');
	});
</script>
EOHTML;
		return $html;
	}

	function execute($par = '') {
		global $wgUser, $wgOut, $wgScriptPath, $wgRequest, $wgServer;
		global $wgLanguageCode, $wgUseLucene, $wgHooks;
		global $gCurrent, $gResults, $gEn, $IP;

		if (! $wgUseLucene) {
			require_once("$IP/includes/Search.php");
			GoogSearch::execute();
			return;
		}
		$me = Title::makeTitle(NS_SPECIAL, "GoogSearch");

		$wgHooks['ShowBreadCrumbs'][] = array($this, 'removeBreadCrumbsCallback');
		$wgHooks['ShowSideBar'][] = array($this, 'removeSideBarCallback');
		$wgHooks['ShowGrayContainer'][] = array($this, 'removeGrayContainerCallback');
		

		$q = $wgRequest->getVal('q');
		$q = strip_tags($q); // clean of html to avoid XSS attacks
		$wgRequest->setVal('q', $q);

		$start = $wgRequest->getInt('start', 0);

		$wgOut->setHTMLTitle(wfMsg('lsearch_title_q', $q));
		$wgOut->addMeta('robots', 'noindex,nofollow');

		$fname = "GoogSearch::execute";
		$search_page_results = wfMsg('cse_search_page_results');
		$search_page_results = preg_replace('/\<[\/]?pre\>/', '', $search_page_results);
		
		$wgOut->addHTML('<div class="wh_block cse_search_page_block">'.$search_page_results.'</div>');
		return;
	}

}


