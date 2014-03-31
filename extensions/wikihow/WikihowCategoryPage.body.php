<?

if (!defined('MEDIAWIKI')) die();

class WikihowCategoryPage extends CategoryPage {
	
	const STARTING_CHUNKS = 20;
	const PULL_CHUNKS = 5;
	const SINGLE_WIDTH = 163; // (article_shell width - 2*article_inner padding - 3*SINGLE_SPACING)/4
	const SINGLE_HEIGHT = 119; //should be .73*SINGLE_WIDTH
	const SINGLE_SPACING = 16;

	var $catStream;

	function view() {
		global $wgOut, $wgRequest, $wgUser, $wgTitle, $wgHooks;
		 
		if (!$wgTitle->exists()) {
			parent::view();
			return;
		}
		
		if (count($wgRequest->getVal('diff')) > 0) {
			return Article::view();
		}
		
		$restAction = $wgRequest->getVal('restaction');
		if ($restAction == 'pull-chunk') {
			$wgOut->setArticleBodyOnly(true);
			$start = $wgRequest->getInt('start');
			if (!$start) return;
			$categoryViewer = new WikihowCategoryViewer($wgTitle, $this->getContext());
			$this->catStream = new WikihowArticleStream($categoryViewer, $this->getContext(), $start);
			$html = $this->catStream->getChunks(4, WikihowCategoryPage::SINGLE_WIDTH, WikihowCategoryPage::SINGLE_SPACING, WikihowCategoryPage::SINGLE_HEIGHT);
			$ret = json_encode( array('html' => $html) );
			$wgOut->addHTML($ret);
		} else {
			$wgOut->setRobotPolicy('index,follow', 'Category Page');
			$wgOut->setPageTitle($wgTitle->getText());
			if ($wgRequest->getVal('viewMode',0)) {
				$from = $wgRequest->getVal( 'from' );
				$until = $wgRequest->getVal( 'until' );
				$viewer = new WikihowCategoryViewer( $this->mTitle, $this->getContext(), $from, $until );
				$viewer->clearState();
				$viewer->doQuery();
				$viewer->finaliseCategoryState();
				$wgOut->addHtml('<div class="section minor_section">');
				$wgOut->addHtml('<ul><li>');
				if (is_array($viewer->articles_fa)) {
					$articles = array_merge($viewer->articles_fa, $viewer->articles);
				} else {
					$articles = $viewer->articles;
				}
				$wgOut->addHtml( implode("</li>\n<li>", $articles) );
				$wgOut->addHtml('</li></ul>');
				$wgOut->addHtml('</div>');
			}
			else {
				//$wgHooks['BeforePageDisplay'][] = array('WikihowCategoryPage::addCSSAndJs');
				$this->addCSSAndJs();
				$viewer = new WikihowCategoryViewer($wgTitle, $this->getContext());
				$this->catStream = new WikihowArticleStream($viewer, $this->getContext(), 0);
				$html = $this->catStream->getChunks(self::STARTING_CHUNKS, WikihowCategoryPage::SINGLE_WIDTH, WikihowCategoryPage::SINGLE_SPACING, WikihowCategoryPage::SINGLE_HEIGHT);
				$wgOut->addHTML($html);
			}


			$sk = $this->getContext()->getSkin();
			$subCats = $viewer->shortListRD( $viewer->children, $viewer->children_start_char );
			if ($subCats != "") {
				$subCats  = "<h3>{$this->mTitle->getText()}</h3>{$subCats}";
				$sk->addWidget($subCats);
			}

			$furtherEditing = $viewer->getArticlesFurtherEditing($viewer->articles, $viewer->article_info);
			if ($furtherEditing != "") {
				$sk->addWidget($furtherEditing);
			}
		}
	}

	function addCSSAndJs() {
		global $wgOut;
		
		$wgOut->addCSScode('catc');
		$wgOut->addJScode('catj');

		return true;
	}

	function isFileCacheable() {
		return true;
	}

	public static function newFromTitle(&$title, &$page) {
		switch ($title->getNamespace()) {
			case NS_CATEGORY:
				$page = new WikihowCategoryPage($title);
		}
		return true;
	}

}

