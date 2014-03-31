<? 
class BabelfishMaintenance extends WAPMaintenance {
	/*
	* Babelfish reliest on the translation links tool to determin which articles are completed. 
	* This looks at the translation_link table to see which articles were tranlated from the $startDate
	* and then marks them complete in babelfish.
	*/
	protected function completedReport($startDate, $endDate) {
		$wapDB = WAPDB::getInstance($this->dbType);
		$langs = $this->wapConfig->getSupportedLanguages();
		$linker = new WAPLinker($this->dbType);
		$defaultUserText = $this->wapConfig->getDefaultUserName();
		foreach ($langs as $lang) {
			$urls = array();
			$articles = $wapDB->getTranslatedArticlesFromDate($lang, $startDate, $endDate);
			foreach ($articles as $a) {
				$userText = $a->getUserText();
				$userText = empty($userText) ? $defaultUserText : $userText; 
				$urls[] = $linker->makeWikiHowUrl($a->getPageTitle()) . "\t{$userText}";
			}
			$wapDB->completeTranslatedArticles($articles, $lang);

			if (!empty($urls)) {
				$subject = $this->getSubject("Completed Articles", $lang);
				$body = "The following articles were completed via the translation links tool yesterday.\n\n" . 
					implode("\n", $urls) . "\n\n";
				$emails = $this->wapConfig->getMaintenanceCompletedEmailList();
				mail($emails, $subject, $body);
			}
		}
	}

	protected function checkupCompletedArticles() {
		$dbr = wfGetDB(DB_SLAVE);
		$articleTable = $this->wapConfig->getArticleTableName();
		$sql = "SELECT ct_page_id, ct_lang_code, ct_page_title, ct_user_id, ct_user_text, tl_timestamp as extra FROM  $articleTable b, translation_link t 
			WHERE ct_page_id = tl_from_aid AND tl_from_lang = 'en' AND ct_lang_code = tl_to_lang and ct_completed = 0 and ct_user_text != 'BugBabelfish' AND  tl_timestamp < '{$this->startDate}' 
			ORDER BY tl_timestamp, ct_lang_code";
		$this->genericCheckup($sql, self::COMPLETED_TYPE);
	}

	protected function handleUnassignedIdRemoval(&$idsToRemove, $lang, $subject) {
		echo "$subject - $lang: NO AUTOMATIC REMOVAL\n";
	}
}
