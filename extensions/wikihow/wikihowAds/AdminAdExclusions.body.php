<?php


class AdminAdExclusions extends UnlistedSpecialPage{

	const EXCLUSION_TABLE = "adexclusions";

	function __construct() {
		parent::__construct( 'AdminAdExclusions' );
	}

	function execute($par) {
		global $wgOut, $wgRequest, $wgUser;

		$userGroups = $wgUser->getGroups();
		if ($wgUser->isBlocked() || !in_array('staff', $userGroups)) {
			$wgOut->setRobotpolicy('noindex,nofollow');
			$wgOut->showErrorPage( 'nosuchspecialpage', 'nospecialpagetext' );
			return;
		}

		$submitted = $wgRequest->getVal("submitted");
		$list = $wgRequest->getVal("list");

		if($submitted == "true") {
			$wgOut->setArticleBodyOnly(true);
			$urlList = $wgRequest->getVal("urls");
			$urlArray = explode("\n", $urlList);
			$errors = $this->addNewTitles($urlArray);

			if(count($errors) > 0) {
				$result['success'] = false;
				$result['errors'] = $errors;

			}
			else {
				$result['success'] = true;
			}

			echo json_encode($result);
		}
		else if($list == "true") {
			$this->getAllExclusions();
		} else {
			$wgOut->setHTMLTitle("Ad Exclusions");
			$wgOut->setPageTitle("Ad Exclusions");
			$wgOut->addJScode('aej');
			$s = Html::openElement( 'form', array( 'action' => '', 'id' => 'adexclusions' ) ) . "\n";
			$s .= Html::element('p', array(''), "Input full URLs (e.g. http://www.wikihow.com/Kiss) for articles that should not have ads on them. Articles on the www.wikihow.com domain will have ads removed from all translations. Articles on other domains will only have ads removed from that article. Please only process 10 urls at a time.");
			$s .= Html::element('br');
			$s .= Html::element( 'textarea', array('id' => 'urls', 'cols' => 55, 'rows' => 5) ) . "\n";
			$s .= Html::element('br');
			$s .= Html::element( 'input',
					array( 'type' => 'submit', 'class' => "button primary", 'value' => 'Add articles' )
				) . "\n";
			$s .= Html::closeElement( 'form' );
			$s .= Html::element('div', array('id' => 'adexclusions_results'));

			$s .= Html::openElement('form', array('action' => "/Special:AdminAdExclusions", "method" => "post")) . "\n";
			$s .= Html::element('input', array('type' => 'hidden', 'name' => 'list', 'value' => 'true'));
			$s .= Html::element('input', array('type' => 'submit', 'class' => 'button secondary', 'id' => 'adexculsion_list', 'value' => 'Get all articles'));
			$s .= Html::closeElement('form');

			$wgOut->addHTML($s);
		}

	}

	/*****
	 * Outputs a csv file that lists out
	 * all urls in all languages that have
	 * ads excluded from them.
	 ***/
	function getAllExclusions() {
		global $wgOut, $wgActiveLanguages;

		$wgOut->setArticleBodyOnly(true);

		$dbr = wfGetDB(DB_SLAVE);

		$ids = array();
		$this->getPageIdsForLanguage($dbr, $ids, "en");

		foreach($wgActiveLanguages as $languageCode) {
			$this->getPageIdsForLanguage($dbr, $ids, $languageCode);
		}

		$pages = Misc::getPagesFromLangIds($ids);

		$date = date('Y-m-d');
		header('Content-type: application/force-download');
		header('Content-disposition: attachment; filename="adexclusions_' . $date . '.xls"');
		foreach($pages as $page) {
			echo Misc::getLangBaseURL($page["lang"]) . "/" . $page["page_title"] . "\n";
		}
	}

	/****
	 *  Ads all page ids for the given language to the '$ids' array that
	 * have ads excluded from them based on the table.
	 ****/
	function getPageIdsForLanguage(&$dbr, &$ids, $languageCode) {
		global $wgDBname;

		if($languageCode == "en")
			$dbr->selectDB($wgDBname);
		else
			$dbr->selectDB('wikidb_'.$languageCode);

		$res = $dbr->select(AdminAdExclusions::EXCLUSION_TABLE, "ae_page", array(), __METHOD__);
		foreach($res as $row) {
			$ids[] = array("lang" => $languageCode, "id" => $row->ae_page);
		}
	}

	/*****
	 * Takes an array of full urls (can be on any wH domain)
	 * and adds them to the database of excluded articles.
	 * For urls on www.wikihow.com, it checks titus for any
	 * translations and adds those to the corresponding intl db.
	 * For urls on intl domains, it only adds that article to
	 * that db.
	 ****/
	function addNewTitles($articles) {
		global $wgDBname;

		$dbw = wfGetDB(DB_MASTER);

		$errors = array();

		$articles = array_map("urldecode", $articles);
		$pages = Misc::getPagesFromURLs($articles);

		foreach($pages as $page) {

			$languageCode = $page['lang'];

			if($languageCode == "en") {
				//first add this article to the english db
				$this->addIntlArticle($dbw, "en", $page['page_id']);

				//now get all the translations
				self::processTranslations($dbw, $page['page_id']);
			}
			else {
				self::addIntlArticle($dbw, $languageCode, $page['page_id']);
			}
		}

		//Find ones that didn't work and tell user about them
		foreach($articles as $article) {
			if(!array_key_exists($article, $pages)){
				$errors[] = $article;
			}
		}
		$dbw->selectDB($wgDBname);

		//reset memcache since we just changed a lot of values
		wikihowAds::resetAllAdExclusionCaches();

		return $errors;
	}

	/****
	 * Given an article id for a title on www.wikihow.com, grabs the titus
	 * data for that article and adds all translations to corresponding
	 * list of excluded articles for those languages
	 ****/
	static function processTranslations(&$dbw, $englishId) {
		global $wgActiveLanguages;

		$titusData = Pagestats::getTitusData($englishId);

		foreach($wgActiveLanguages as $activeLanguageCode) {
			if($titusData->titus->{"ti_tl_".$activeLanguageCode."_id"}) {
				self::addIntlArticle($dbw, $activeLanguageCode, $titusData->titus->{"ti_tl_".$activeLanguageCode."_id"});
			}
		}
	}

	/****
	 * Given an article id and a language code, adds the given
	 * article to the associated excluded article table in the
	 * correct language db.
	 ****/
	static function addIntlArticle(&$dbw, $languageCode, $articleId) {
		global $wgDBname;

		if($languageCode == "en")
			$dbw->selectDB($wgDBname);
		else
			$dbw->selectDB('wikidb_'.$languageCode);

		$sql = "INSERT IGNORE into " . AdminAdExclusions::EXCLUSION_TABLE . " VALUES ({$articleId})";
		$dbw->query($sql, __METHOD__);
	}

	/****
	 * Updates all ad exclusion translations based on the article ids
	 * that are in the English database
	 ****/
	public function updateEnglishArticles() {
		$dbr = wfGetDB(DB_SLAVE);
		$res = $dbr->select(AdminAdExclusions::EXCLUSION_TABLE, array('ae_page'));

		$dbw = wfGetDB(DB_MASTER);
		foreach($res as $row) {
			self::processTranslations($dbw, $row->ae_page);
		}
	}

} 