<?
class CatSearchUI extends UnlistedSpecialPage {

	function __construct() { 
		parent::__construct( 'CatSearchUI' );
	}
	
	function execute($par) {
		global $wgOut, $wgRequest, $wgUser;

		$fname = 'CatSearchUI::execute';
		wfProfileIn( $fname );

		$wgOut->setRobotpolicy( 'noindex,nofollow' );
		if ($wgUser->getId() == 0) {
			$wgOut->showErrorPage( 'nosuchspecialpage', 'nospecialpagetext' );
		}

		$js = HtmlSnips::makeUrlTags('js', array('catsearchui.js'), '/extensions/wikihow/catsearch?rev='.WH_SITEREV, CATSEARCH_DEBUG);
		$css = HtmlSnips::makeUrlTags('css', array('catsearchui.css'), '/extensions/wikihow/catsearch?rev='.WH_SITEREV, CATSEARCH_DEBUG);
		$vars = array('js' => $js, 'css' => $css, 'csui_search_label' => wfMsg('csui_search_label'), 
			'csui_interests_label' => wfMsg('csui_interests_label'), 'csui_suggested_label' => wfMsg('csui_suggested_label'),
			'csui_no_interests' => wfMsg('csui_no_interests'));
		$this->getUserCategoriesHtml($vars);
		EasyTemplate::set_path( dirname(__FILE__).'/' );
		$html = EasyTemplate::html('CatSearchUI', $vars);

		$embedded = intval($wgRequest->getVal('embed'));
		$wgOut->setArticleBodyOnly($embedded);
		$wgOut->addHtml($html);

		wfProfileOut( $fname );
	}

	function getUserCategoriesHtml(&$vars) {
		$cats = CategoryInterests::getCategoryInterests();
		$html = "";
		if(sizeof($cats)) {
			$vars['cats'] = self::getCategoryDivs($cats);
			$vars['nocats_hidden'] = 'csui_hidden';
		}

		$suggested = self::getSuggestedCategoryDivs(CategoryInterests::suggestCategoryInterests());
		$vars['suggested_cats'] = $suggested;
	}


	function getSuggestedCategoryDivs(&$cats) {
		$html = "";
		foreach ($cats as $key => $cat) {
			$catName = trim(str_replace("-", " ", $cat));
			$cats[$key] = "<div class='csui_suggestion csui_font_small'><div class='csui_hidden'>$cat</div>$catName</div>";
		}
		$html = implode(", ", $cats);

		return $html;
	}

	function getCategoryDivs(&$cats) {
		$html = "";
		foreach ($cats as $cat) {
			$catName = str_replace("-", " ", $cat);
			$html .= "<div class='csui_category ui-corner-all'><span class='csui_close'><img class='csui_minus_icon' src='/skins/WikiHow/images/csui_minus.png'/></span>$catName<div class='csui_hidden'>$cat</div></div>\n";
		}
		return $html;
	}
}
