<?php

class WikihowHomepage extends Article {
	var $faStream;
	var $rsStream;
	const FA_STARTING_CHUNKS = 6;
	const FA_ENDING_CHUNKS = 2;
	
	// Used only for intl
	const FA_MIDDLE_CHUNKS = 2;
	// Used only for English 
	const RS_CHUNKS = 2;


	const SINGLE_WIDTH = 163; // (article_shell width - 2*article_inner padding - 3*SINGLE_SPACING)/4
	const SINGLE_HEIGHT = 119; //should be .73*SINGLE_WIDTH
	const SINGLE_SPACING = 16;

	function __construct( Title $title, $oldId = null ) {
		global $wgHooks;

		$wgHooks['ShowBreadCrumbs'][] = array('WikihowHomepage::removeBreadcrumb');
		$wgHooks['AfterHeader'][] = array('WikihowHomepage::showTopImage');
		parent::__construct($title, $oldId);
	}

	public function execute() {

	}

	function view() {
		global $wgOut, $wgUser, $wgCategoryNames, $wgLanguageCode, $wgCategoryNamesEn, $wgContLang;

		$wgHooks['ShowGrayContainer'][] = array('Misc::removeGrayContainerCallback');

		$faViewer = new FaViewer($this->getContext());
		$this->faStream = new WikihowArticleStream($faViewer, $this->getContext(), 0);
		$html = $this->faStream->getChunks(WikihowHomepage::FA_STARTING_CHUNKS, WikihowHomepage::SINGLE_WIDTH, WikihowHomepage::SINGLE_SPACING, WikihowHomepage::SINGLE_HEIGHT);

		// We add more from the FA stream on international, because we don't have rising stars on international
		if($wgLanguageCode != "en") {
			$this->faStream = new WikihowArticleStream($faViewer, $this->getContext(), $this->faStream->getStreamPosition() + 1);
			$html2 = $this->faStream->getChunks(WikihowHomepage::FA_MIDDLE_CHUNKS, WikihowHomepage::SINGLE_WIDTH, WikihowHomepage::SINGLE_SPACING, WikihowHomepage::SINGLE_HEIGHT);

		}
		else {
			$rsViewer = new RsViewer($this->getContext());
			$this->rsStream = new WikihowArticleStream($rsViewer, $this->getContext());
			$html2 = $this->rsStream->getChunks(WikihowHomepage::RS_CHUNKS, WikihowHomepage::SINGLE_WIDTH, WikihowHomepage::SINGLE_SPACING, WikihowHomepage::SINGLE_HEIGHT);
		}
		$this->faStream = new WikihowArticleStream($faViewer, $this->getContext(), $this->faStream->getStreamPosition() + 1);
		$html3 = $this->faStream->getChunks(WikihowHomepage::FA_ENDING_CHUNKS, WikihowHomepage::SINGLE_WIDTH, WikihowHomepage::SINGLE_SPACING, WikihowHomepage::SINGLE_HEIGHT);

		$wgOut->addHTML("<div id='fa_container'>{$html}{$html2}{$html3}</div>");

		// $catmap = Categoryhelper::getIconMap();
		// ksort($catmap);

		$categories = array();
		foreach($wgCategoryNames as $ck => $cat) {
			$category = urldecode(str_replace("-", " ", $cat));
			if($wgLanguageCode == "zh") {
				$category = $wgContLang->convert($category);	
			}
			// For Non-English we shall try to get the category name from message for the link. We fallback to the category name, because 
			// abbreviated category names are used for easier display. For the icon, we convert to English category names of the corresponding category.
			if($wgLanguageCode != "en") {
				$enCat = $wgCategoryNamesEn[$ck];
				$msgKey = strtolower(str_replace(' ','-',$enCat));
				$foreignCat = str_replace('-',' ',urldecode(wfMessage($msgKey)->text()));
				$catTitle = Title::newFromText("Category:" . $foreignCat);
				if(!$catTitle) {
					$catTitle = Title::newFromText("Category:" . $cat);
				}
				$cat = $enCat;
			}
			else {
				$catTitle = Title::newFromText("Category:" . $category);
			}

			$categories[$category]->url = $catTitle->getLocalURL();
			//$categories[$category]->icon = ListRequestedTopics::getCategoryImage($category);

			//icon
			if($wgLanguageCode != "en") {
				$cat = $wgCategoryNamesEn[$ck];	
			}
			$cat_class = 'cat_'.strtolower(str_replace(' ','',$cat));
			$cat_class = preg_replace('/&/','and',$cat_class);
			$categories[$category]->icon = $cat_class;
		}

		$tmpl = new EasyTemplate( dirname(__FILE__) );
		$tmpl->set_vars(array(
			'categories' => $categories
		));
		$html = $tmpl->execute('categoryWidget.tmpl.php');

		$sk = $this->getContext()->getSkin();


		$sk->addWidget(wfMessage('main_page_worldwide_new', wfGetPad())->text());

		$sk->addWidget( $html );

		$wgOut->setRobotPolicy('index,follow', 'Main Page');
	}

	public static function removeBreadcrumb(&$showBreadcrumb) {
		$showBreadcrumb = false;
		return true;
	}

	public static function showTopImage() {
		global $wgUser, $wgLanguageCode;

		$items = array();

		$dbr = wfGetDB(DB_SLAVE);
		$res = $dbr->select(WikihowHomepageAdmin::HP_TABLE, array('*'), array('hp_active' => 1), __METHOD__, array('ORDER BY' => 'hp_order'));

		$i = 0;
		foreach($res as $result) {
			$item = new stdClass();
			$title = Title::newFromID($result->hp_page);
			$item->url = $title->getLocalURL();
			$item->text = $title->getText();
			$imageTitle = Title::newFromID($result->hp_image);
			if($imageTitle) {
				$file = wfFindFile($imageTitle->getText());
				if($file) {
					$item->imagePath = wfGetPad($file->getUrl());
					$item->itemNum = ++$i;
					$items[] = $item;
				}
			}
		}

		if ($wgLanguageCode == 'en') {
			//using BOSS here only
			$searchTitle = Title::makeTitle(NS_SPECIAL, "LSearch");
			$search  = '
			<form id="cse-search-hp" name="search_site" action="/wikiHowTo" method="get">
			<input type="text" class="search_box" name="search" />
			</form>';

		} else {
			//PUNTING FOR NOW
			//INTL: International search just uses Google custom search
			$search = GoogSearch::getSearchBox("cse-search-hp");
		}

		$tmpl = new EasyTemplate( dirname(__FILE__) );
		$tmpl->set_vars(array(
			'items' => $items,
			'imagePath' => wfGetPad('/skins/owl/images/home1.jpg'),
			'login' => ($wgUser->getID() == 0 ? UserLoginBox::getLogin() : ""),
			'search' => $search
		));
		$html = $tmpl->execute('top.tmpl.php');

		echo $html;

		return true;
	}

	public static function onArticleFromTitle(&$title, &$article) {
		if($title->getText() == wfMessage('mainpage')->text()) {
			$article = new WikihowHomepage($title);
			return true;
		}

		return true;
	}

}
