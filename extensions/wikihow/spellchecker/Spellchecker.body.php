<?
/*
* 
*/
global $IP;
require_once("$IP/extensions/wikihow/EditPageWrapper.php");

class Spellchecker extends UnlistedSpecialPage {
	
	var $skipTool;
	
	const SPELLCHECKER_EXPIRED = 3600; //60*60 = 1 hour

	function __construct() {
		global $wgHooks;
		parent::__construct('Spellchecker');
		$wgHooks['getToolStatus'][] = array('Misc::defineAsTool');
	}

	function execute($par) {
		global $wgOut, $wgRequest, $wgUser, $wgHooks;

		$wgHooks['getBreadCrumbs'][] = array('Spellchecker::getBreadCrumbsCallback');
		
		if ($wgUser->isBlocked()) {
			$wgOut->blockedPage();
			return;
		}
		
		if ($wgUser->getID() == 0) {
			$wgOut->setRobotpolicy( 'noindex,nofollow' );
			$wgOut->showErrorPage( 'nosuchspecialpage', 'nospecialpagetext' );
			return;
		}
		
		//start temp code for taking down tool
		/*
		wfLoadExtensionMessages("Spellchecker");
		
		$wgOut->setHTMLTitle(wfMsg('spellchecker'));
		$wgOut->setPageTitle(wfMsg('spellchecker'));
		
		$wgOut->addWikiText("This tool is temporarily down for maintenance. Please check out the [[Special:CommunityDashboard|Community Dashboard]] for other ways to contribute while we iron out a few issues with this tool. Happy editing!");
		return;
		 */
		//end temp code
		
		/*if ( !($wgUser->isSysop() || in_array( 'newarticlepatrol', $wgUser->getRights()) ) ) {
			$wgOut->setRobotpolicy( 'noindex,nofollow' );
			$wgOut->showErrorPage( 'nosuchspecialpage', 'nospecialpagetext' );
			return;
		}*/
		
		wfLoadExtensionMessages("Spellchecker");
		
		$this->skipTool = new ToolSkip("spellchecker", "spellchecker", "sc_checkout", "sc_checkout_user", "sc_page");

		if ( $wgRequest->getVal('getNext') ) {
			$wgOut->disable();
			if( $wgRequest->getVal('articleName') )
				$articleName = $wgRequest->getVal('articleName');
			else
				$articleName = "";
			
			$result = self::getNextArticle($articleName);
			print_r(json_encode($result));
			return;
		}
		else if ($wgRequest->getVal('edit')) {
			$wgOut->disable();
			
			$id = $wgRequest->getVal('id');
			$result = $this->getArticleEdit($id);
			
			print_r(json_encode($result));
			return;
		}
		else if ( $wgRequest->getVal('skip') ) {
			$wgOut->disable();
			$id = $wgRequest->getVal('id');
			$this->skipTool->skipItem($id);
			$this->skipTool->unUseItem($id);
			$result = self::getNextArticle();
			print_r(json_encode($result));
			return;
		}
		else if ( $wgRequest->getVal('cache') ) {
			$this->skipTool->clearSkipCache();
		}
		else if ( $wgRequest->getVal('addWord') ) {
			$wgOut->setArticleBodyOnly(true);
			$result->success = wikiHowDictionary::addWordToDictionary($wgRequest->getVal('word'));
			print_r(json_encode($result));
			return;
		}
		else if ( $wgRequest->getVal('addWords') ) {
			$wgOut->setArticleBodyOnly(true);
			$result->success = wikiHowDictionary::addWordsToDictionary($wgRequest->getArray('words'));
			print_r(json_encode($result));
			return;
		}
		else if ($wgRequest->wasPosted()) {
			$wgOut->setArticleBodyOnly(true);
			if ( $wgRequest->getVal('submitEditForm')) {
				//user has edited the article from within the Spellchecker tool
				$wgOut->disable();
				$this->submitEdit();
				$result = self::getNextArticle();
				print_r(json_encode($result));
				return;
			}
		}

		$wgOut->setHTMLTitle(wfMsg('spellchecker'));
		$wgOut->setPageTitle(wfMsg('spellchecker'));

		$wgOut->addHTML(HtmlSnips::makeUrlTags('css', array('spellchecker.css'), 'extensions/wikihow/spellchecker', false));
		$wgOut->addScript(HtmlSnips::makeUrlTags('js', array('spellchecker.js'), 'extensions/wikihow/spellchecker', false));

		$tmpl = new EasyTemplate( dirname(__FILE__) );
		
		$setVars = $wgUser->isSysop() || in_array( 'newarticlepatrol', $wgUser->getRights() );
		$tmpl->set_vars(array('addWords' => $setVars));
		
		$wgOut->addHTML($tmpl->execute('Spellchecker.tmpl.php'));
		
		// add standings widget
		$group= new SpellcheckerStandingsGroup();
		$indi = new SpellcheckerStandingsIndividual();
		
		$indi->addStatsWidget(); 
		$group->addStandingsWidget();

	}

	function getBreadCrumbsCallback(&$breadcrumb) {
		$mainPageObj = Title::newMainPage();
		$spellchecker = Title::newFromText("Spellchecker", NS_SPECIAL);
		$sep = wfMsgHtml( 'catseparator' );
		$breadcrumb = "<li class='home'><a href='{$mainPageObj->getLocalURL()}'>Home</a></li><li>{$sep} <a href='{$spellchecker->getLocalURL()}'>{$spellchecker->getText()}</a></li>";
		return true;
	}
	
	/**
	 *
	 * Gets the html for editing an article
	 * 
	 */
	function getArticleEdit($articleId) {
		$title = Title::newFromID($articleId);

		if ($title) {
			$revision = Revision::newFromTitle($title);
			$article = new Article($title);
			if ($revision) {

				$text = $revision->getRawText();

				$text = self::markBreaks($text);
				$text = self::replaceNewlines($text);
				
				$content['html'] = "<p>{$text}</p>";
				$content['title'] = "<a href='{$title->getFullURL()}' target='new'>" . wfMsg('howto', $title->getText()) . "</a>";
				//$content['title'] = $title->getText();

				$ep = new EditPageWrapper($article);
				$content['summary'] = "<span id='wpSummaryLabel'><label for='wpSummary'>Summary:</label></span><br /><input tabindex='10' type='text' value='" . wfMsg('spch-summary') . "' name='wpSummary' id='wpSummary' maxlength='200' size='60' /><br />";
				$content['buttons'] = $ep->getEditButtons(0);
				$content['buttons']['cancel'] = "<a href='#' id='spch-cancel' class='button secondary'>Done</a>";
				$content['articleId'] = $title->getArticleID();

				return $content;
			}
		}
		
		//return an error message
	}

	
	function getNextArticle($articleName = '') {
		global $wgOut;
		
		$dbr = wfGetDB(DB_SLAVE);
		
		$skippedSql = "";
		$skippedIds = $this->skipTool->getSkipped();
		$expired = wfTimestamp(TS_MW, time() - Spellchecker::SPELLCHECKER_EXPIRED);
		
		$title = Title::newFromText($articleName);
		if($title && $title->getArticleID() > 0) {
			$articleId = $title->getArticleID();
		}
		else if ($skippedIds) {
			$articleId = $dbr->selectField('spellchecker', 'sc_page', array('sc_exempt' => 0, 'sc_errors' => 1, 'sc_dirty' => 0, "sc_checkout < '{$expired}'", "sc_page NOT IN ('" . implode("','", $skippedIds) . "')"), __METHOD__, array("limit" => 1, "ORDER BY" => "RAND()"));
		}
		else
			$articleId = $dbr->selectField('spellchecker', 'sc_page', array('sc_exempt' => 0, 'sc_errors' => 1, 'sc_dirty' => 0, "sc_checkout < '{$expired}'"), __METHOD__, array("limit" => 1, "ORDER BY" => "RAND()"));

		if ($articleId) {
			$sql = "SELECT * from `spellchecker_page` JOIN `spellchecker_word` ON sp_word = sw_id WHERE sp_page = {$articleId}"; 
			$res =  $dbr->query($sql, __METHOD__);

			$words = array();
			$corrections = array();
			while ($row = $dbr->fetchObject($res)) {
				$words[] = $row->sw_word;
				$corrections[] = $row->sw_corrections;
			}

			$caps = wikiHowDictionary::getCaps();
			$exclusions = array();
			foreach($words as $word) {
				if (preg_match('@\s' . $word . '\s@', $caps)) {
					$exclusions[] = strtoupper($word);
				}
			}

			$title = Title::newFromID($articleId);

			if ($title) {
				$revision = Revision::newFromTitle($title);
				$article = new Article($title);
				if ($revision) {

					$text = $revision->getRawText();

					$text = self::markBreaks($text);
					$text = self::replaceNewlines($text);

					$content['html'] = "<p>{$text}</p>";
					$content['title'] = "<a href='{$title->getFullURL()}' target='new'>" . wfMsg('howto', $title->getText()) . "</a>";

					$content['articleId'] = $title->getArticleID();
					$content['words'] = $words;
					$content['exclusions'] = $exclusions;

					$popts = $wgOut->parserOptions();
					$popts->setTidy(true);
					$parserOutput = $wgOut->parse($revision->getText(), $title, $popts);
					$magic = WikihowArticleHTML::grabTheMagic($revision->getText());
					$html = WikihowArticleHTML::processArticleHTML($parserOutput, array('no-ads' => true, 'ns' => NS_MAIN, 'magic-word' => $magic));

					$content['html'] = $html;
					
					$this->skipTool->useItem($articleId);

					return $content;
				}
			}
		}
		
		//return error message
		$content['error'] = true;
		return $content;
	}

	/**
	 *
	 * Marks the BR tags that currently exist in the text so we'll
	 * know to not to remove them later
	 * 
	 */
	function markBreaks($text) {
		$articleText = preg_replace("@<br>@i", "<br class='exists'>", $text);

		return $articleText;
	}

	/**
	 *
	 * Removes the class on the BR tag that marks them as having existed
	 * before the edit
	 *
	 */
	function unmarkBreaks($text) {
		$articleText = preg_replace('@<br class="exists">@i', "<br>", $text);
		$articleText = preg_replace('@<br class=exists>@i', "<br>", $articleText); //IE

		return $articleText;
	}

	/**
	 *
	 * Replaces new lines (\n) with BR tags so the format correctly in
	 * an HTML5 editable field
	 * 
	 */
	function replaceNewlines($text) {
		$articleText = preg_replace("@\\n@", "<br />", $text);

		return $articleText;
	}

	/**
	 *
	 * Replaces BR tags in the text with newline characters (\n)
	 * 
	 */
	function insertNewlines($text) {
		$articleText = preg_replace("@<br>@", "\n", $text);
		$articleText = preg_replace("@<BR>@", "\n", $articleText); //IE

		return $articleText;
	}

	/*
	 *
	 * Removes wrapping spans and paragraph tags which are not included
	 * in the raw wikitext.
	 * 
	 * Also removes font tags
	 *
	 */
	function removeWordWraps($text) {
		$articleText = preg_replace('@<[/]?(span|font|p|[ovwxp]:\w+)[^>]*?>@', '', $text);
		$articleText = preg_replace('@<[/]?(SPAN|FONT|P|[ovwxp]:\w+)[^>]*?>@', '', $articleText); //IE

		return $articleText;
	}
	
	function removeSpaces($text) {
		$articleText = str_replace("&nbsp;", " ", $text);
		
		return $articleText;
	}
	
	function removeHTMLEntities($text) {
		//convert > symbols
		$articleText = str_replace("&gt;", ">", $text);
		
		//convert < symbols
		$articleText = str_replace("&lt;", "<", $articleText);
		
		//convert & symbols
		$articleText = str_replace("&amp;", "&", $articleText);
		
		return $articleText;
	}
	
	/***
	 *  When there's a table in the article some browsers automatically insert
	 *  <tbody> and </tbody> tags which messes things up. This removes
	 *  them.
	 ***/
	function removeTableBodyTags($text) {
		$articleText = str_ireplace("<tbody>", "", $text);
		
		$articleText = str_ireplace("</tbody>", "", $articleText);
		
		return $articleText;
	}
	
	/********
	 *  Before, the spellchecker would turn something like
	 *  <ref name="karl" /> INTO <ref name="karl></ref>
	 *	This undoes this bug by spellchecker
	 *******/
	function fixReferences($text) {
		$articleText = preg_replace('@(<ref[^>]*)(></ref>)@', '$1 />', $text);
		
		return $articleText;
	}

	/*
	 *
	 * Processes an article submit
	 *
	 */
	function submitEdit() {
		global $wgRequest, $wgUser;

		$t = Title::newFromID($wgRequest->getVal('articleId'));
		if ($t) {
			$a = new Article($t);

			$text = $wgRequest->getVal('wpTextbox1');
			$text = self::removeWordWraps($text);
			$text = self::insertNewlines($text);
			$text = self::unmarkBreaks($text);
			$text = self::removeSpaces($text);
			$text = self::removeHTMLentities($text);
			$text = self::fixReferences($text);
			$text = self::removeTableBodyTags($text);
			$summary = $wgRequest->getVal('wpSummary');
			
			if ($a) {
				
				$params = array();
				if($wgRequest->getVal('isIE') == "true")
					$IE = ", IE";
				else
					$IE = "";
				$log = new LogPage( 'spellcheck', false ); // false - dont show in recentchanges, it'll show up for the doEdit call
				$msg = wfMsgHtml('spch-edit-message', "[[{$t->getText()}]]", $IE);
				$log->addEntry('edit', $t, $msg, $params);

				//save the edit
				$a->doEdit($text, $summary, EDIT_UPDATE);
				wfRunHooks("Spellchecked", array($wgUser, $t, '0'));
				
				$this->skipTool->unUseItem($a->getID());
			}

		}
	}
	
	static function markAsDirty($id) {
		$dbw = wfGetDB(DB_MASTER);
		
		$sql = "INSERT INTO spellchecker (sc_page, sc_timestamp, sc_dirty, sc_errors, sc_exempt) VALUES (" . 
					$id . ", " . wfTimestampNow() . ", 1, 0, 0) ON DUPLICATE KEY UPDATE sc_dirty = '1', sc_timestamp = " . wfTimestampNow();
		$dbw->query($sql, __METHOD__);
	}
	
	static function markAsIneligible($id) {
		$dbw = wfGetDB(DB_MASTER);
		
		$dbw->update('spellchecker', array('sc_errors' => 0, 'sc_dirty' => 0), array('sc_page' => $id), __METHOD__);
	}
	
}

class Spellcheckerwhitelist extends UnlistedSpecialPage {

	function __construct() {
		parent::__construct('Spellcheckerwhitelist');
	}

	function execute($par) {
		global $IP, $wgOut, $wgUser, $wgHooks;
		
		if ($wgUser->isBlocked()) {
			$wgOut->blockedPage();
			return;
		}
		
		if ($wgUser->getID() == 0) {
			$wgOut->setRobotpolicy( 'noindex,nofollow' );
			$wgOut->showErrorPage( 'nosuchspecialpage', 'nospecialpagetext' );
			return;
		}
		
		$isStaff = in_array('staff', $wgUser->getGroups());
		
		wfLoadExtensionMessages("Spellchecker");

		
		$dbr = wfGetDB(DB_SLAVE);
		
		$wgOut->addWikiText(wfMsg('spch-whitelist-inst'));
		
		$words = array();
		$res = $dbr->select(wikiHowDictionary::WHITELIST_TABLE, "*", '', __METHOD__);
		while($row = $dbr->fetchObject($res)) {
			$words[] = $row;
		}
		asort($words);
		
		$res = $dbr->select(wikiHowDictionary::CAPS_TABLE, "*", '', __METHOD__);

		$caps = array();
		while($row = $dbr->fetchObject($res)) {
			$caps[] = $row->sc_word;
		}
		asort($caps);
		
		$wgOut->addHTML("<ul>");
		foreach($words as $word) {
			if($word->{wikiHowDictionary::WORD_FIELD} != "")
				$wgOut->addHTML("<li>" . $word->{wikiHowDictionary::WORD_FIELD} );
			if($isStaff && $word->{wikiHowDictionary::USER_FIELD} > 0) {
				$user = User::newFromId($word->{wikiHowDictionary::USER_FIELD});
				$wgOut->addHTML(" (" . $user->getName() . ")");
			}
			$wgOut->addHTML("</li>");
		}
		
		foreach($caps as $word) {
			if($word != "")
				$wgOut->addHTML("<li>" . $word . "</li>");
		}
		
		$wgOut->addHTML("</ul>");
		
		$wgOut->setHTMLTitle(wfMsg('spch-whitelist'));
		$wgOut->setPageTitle(wfMsg('spch-whitelist'));
	}
}

class SpellcheckerArticleWhitelist extends UnlistedSpecialPage {

	function __construct() {
		parent::__construct('SpellcheckerArticleWhitelist');
	}

	function execute($par) {
		global $IP, $wgOut, $wgUser, $wgRequest;
		
		if($wgUser->getID() == 0 || !($wgUser->isSysop() || in_array( 'newarticlepatrol', $wgUser->getRights() ))) {
			$wgOut->setRobotpolicy( 'noindex,nofollow' );
			$wgOut->showErrorPage( 'nosuchspecialpage', 'nospecialpagetext' );
			return;
		}
		
		wfLoadExtensionMessages("Spellchecker");
		
		$this->skipTool = new ToolSkip("spellchecker", "spellchecker", "sc_checkout", "sc_checkout_user", "sc_page");

		$message = "";
		if ( $wgRequest->wasPosted() ) {
			$articleUrl = $wgRequest->getVal('articleName');
			$title = Title::newFromURL($articleUrl);
			
			if($title && $title->getArticleID() > 0) {
				if($this->addArticleToWhitelist($title))
					$message = $title->getText() . " was added to the article whitelist.";
				else
					$message = $articleUrl . " could not be added to the article whitelist.";
			}
			else
				$message = $articleUrl . " could not be added to the article whitelist.";
		}
		
		$tmpl = new EasyTemplate( dirname(__FILE__) );
		
		$tmpl->set_vars(array('message' => $message));

		$wgOut->addHTML($tmpl->execute('ArticleWhitelist.tmpl.php'));
				
		$dbr = wfGetDB(DB_SLAVE);
		$res = $dbr->select("spellchecker", "sc_page", array("sc_exempt" => 1));
		
		$wgOut->addHTML("<ol>");
		while($row = $dbr->fetchObject($res)) {
			$title = Title::newFromID($row->sc_page);
			
			if($title)
				$wgOut->addHTML("<li><a href='" . $title->getFullURL() . "'>" . $title->getText() . "</a></li>");
		}
		$wgOut->addHTML("</ol>");
		
		$wgOut->setHTMLTitle(wfMsg('spch-articlewhitelist'));
		$wgOut->setPageTitle(wfMsg('spch-articlewhitelist'));
	}

	function addArticleToWhitelist($title) {
		$dbw = wfGetDB(DB_MASTER);
		
		$sql = "INSERT INTO spellchecker (sc_page, sc_timestamp, sc_dirty, sc_errors, sc_exempt) VALUES (" . 
					$title->getArticleID() . ", " . wfTimestampNow() . ", 1, 0, 1) ON DUPLICATE KEY UPDATE sc_exempt = '1', sc_timestamp = " . wfTimestampNow();
		return $dbw->query($sql);
	}
}

class wikiHowDictionary{
	const DICTIONARY_LOC	= "/maintenance/spellcheck/custom.pws";
	const WHITELIST_TABLE	= "spellchecker_whitelist";
	const CAPS_TABLE		= "spellchecker_caps";
	const WORD_TABLE		= "spellchecker_word";
	const WORD_FIELD		= "sw_word";
	const USER_FIELD		= "sw_user";
	const ACTIVE_FIELD		= "sw_active";
	
	/***
	 * 
	 * Takes the given word and, if allowed, adds it
	 * to the temp table in the db to be added
	 * to the dictionary at a later time
	 * (added via cron on the hour)
	 * 
	 */
	static function addWordToDictionary($word) {
		global $wgUser, $wgMemc;
		
		$word = trim($word);
		
		//now check to see if the word can be added to the library
		//only allow a-z and apostraphe
		//check for numbers
		if ( preg_match('@[^a-z|\']@i', $word) )
			return false;
		
		$dbw = wfGetDB(DB_MASTER);
		$dbw->insert(self::WHITELIST_TABLE, array(self::WORD_FIELD => $word, self::USER_FIELD => $wgUser->getID(), self::ACTIVE_FIELD => 0), __METHOD__, "IGNORE");
		
		$key = wfMemcKey('spellchecker_whitelist');
		$wgMemc->delete($key);
		
		return true;
	}
	
	static function addWordsToDictionary($words) {
		$success = true;
		
		foreach($words as $word) {
			$success = wikiHowDictionary::addWordToDictionary($word) && $success;
		}
		
		return $success;
	}
	
	static function batchRemoveWordsFromDictionary(&$words) {
		global $IP;
		
		$dbw = wfGetDB(DB_MASTER);
		
		$wordString = file_get_contents($IP . wikiHowDictionary::DICTIONARY_LOC);
		$currentWords = explode("\n", $wordString);
		$currentWords = array_flip($currentWords);
		
		foreach($words as $word) {
			$word = trim($word);
			
			if($word == "")
				continue;
			
			if(!preg_match('@[^A-Z]@', $word) ) {
				//all caps, so remove from caps table
				$dbw->delete(self::CAPS_TABLE, array('sc_word' => $word), __FUNCTION__);
			}
			else {
				$dbw->delete(self::WHITELIST_TABLE, array(self::WORD_FIELD => $word), __FUNCTION__);
			}
			$currentWords[$word] = false;
		}
		
		
		$fileData = "";
		$wordCount = 0;
		
		foreach($currentWords as $key => $value) {
			if($value !== false && $key != "") {
				if($wordCount != 0)
					$fileData .= "\n";
				$fileData .= $key;
				$wordCount++;
			}
		}
		
		//now, rewrite the file
		$handle = fopen($IP . wikiHowDictionary::DICTIONARY_LOC, "w");
		fwrite($handle, $fileData);
		fclose($handle);
	}
	
	/***
	 * 
	 * Called via the cron. Adds all the words in the 
	 * temp table to the dictionary
	 * 
	 * NOT USED ANYMORE
	 * 
	 */
	static function batchAddWordsToDictionary() {
		$dbr = wfGetDB(DB_SLAVE);
		
		$res = $dbr->select(self::WHITELIST_TABLE, '*', array(self::ACTIVE_FIELD => 0), __METHOD__);
		$words = array();
		while($row = $dbr->fetchObject($res)) {
			$words[] = $row;
		}
		
		if (count($words) <= 0)
			return;
		
		$dbw = wfGetDB(DB_MASTER);
		
		$pspell = self::getLibrary();
		
		if(count($words))
			echo "Adding to whitelist: ";
		
		foreach($words as $wordRow) {
			$word = $wordRow->{self::WORD_FIELD};
			//check to see if its an ALL CAPS word
			if ( !preg_match('@[^A-Z]@', $word) ) {
				$dbw->insert(self::CAPS_TABLE, array('sc_word' => $word, 'sc_user' => $wordRow->{self::USER_FIELD}), __METHOD__, "IGNORE");
				
				$dbw->delete(self::WHITELIST_TABLE, array(self::WORD_FIELD => $word), __FUNCTION__);
			}
			else 
				pspell_add_to_personal($pspell, $word);
			
			echo $word . ",";

			//now go through and check articles that contain that word.
			$sql = "SELECT * FROM `" . self::WORD_TABLE . "` JOIN `spellchecker_page` ON `sp_word` = `sw_id` WHERE sw_word = " . $dbr->addQuotes($word);
			$res = $dbr->query($sql, __METHOD__);

			while($row = $dbr->fetchObject($res)) {
				$page_id = $row->sp_page;
				$dbw->update('spellchecker', array('sc_dirty' => "1"), array('sc_page' => $page_id), __METHOD__);
			}
		}
		
		echo "\n";
		
		if(pspell_save_wordlist($pspell)){
			foreach($words as $wordRow) {
				$word = $wordRow->{self::WORD_FIELD};
				$dbw->update(self::WHITELIST_TABLE, array(self::ACTIVE_FIELD => 1), array(self::WORD_FIELD => $word), __METHOD__);
			}
		}
		else{
			mail('bebeth@wikihow.com', 'spellchecker error', 'Unable to save new words to the spellchecker whitelist.');
		}
	}
	
	static function invalidateArticlesWithWord(&$dbr, &$dbw, $word) {
		//now go through and check articles that contain that word.
		$sql = "SELECT * FROM `" . self::WORD_TABLE . "` JOIN `spellchecker_page` ON `sp_word` = `sw_id` WHERE sw_word = " . $dbr->addQuotes($word);
		$res = $dbr->query($sql, __METHOD__);

		while($row = $dbr->fetchObject($res)) {
			$page_id = $row->sp_page;
			$dbw->update('spellchecker', array('sc_dirty' => "1"), array('sc_page' => $page_id), __METHOD__);
		}
	}
	
	/***
	 * 
	 * Gets a link to the pspell library
	 * 
	 */
	static function getLibrary() {
		global $IP;
		
		$pspell_config = pspell_config_create("en", 'american');
		pspell_config_mode($pspell_config, PSPELL_FAST);
		//no longer using the custom dictionary
		//pspell_config_personal($pspell_config, $IP . wikiHowDictionary::DICTIONARY_LOC);
		$pspell_link = pspell_new_config($pspell_config);

		return $pspell_link;
	}
	
	/***
	 * 
	 * Checks the given word using the pspell library
	 * and our separate caps whitelist
	 * 
	 * Returns: -1 if the word is ok
	 *			id of the word in the spellchecker_word table
	 * 
	 */
	function spellCheckWord(&$dbw, $word, &$pspell, &$caps, &$wordArray) {
		
		// Ignore numbers
		//if (preg_match('/^[A-Z]*$/',$word)) return;
		if (preg_match('/[0-9]/',$word)) return;
		
		// Return dictionary words
		if (pspell_check($pspell,$word)) {
			// this word is OK
			return -1;
		}
		
		//check against our internal whitelist
		//exactly as is
		if($wordArray[$word] === true)
			return -1;
		
		//if only the first letter is capitalized, then
		//uncapitalize it and see if its in our list
		$regWord = lcfirst($word);
		if($wordArray[$regWord] === true)
			return -1;
		
		$suggestions = pspell_suggest($pspell,$word);
		$corrections = "";
		if (sizeof($suggestions) > 0) {
			if (sizeof($suggestions) > 5) {
				$corrections = implode(",", array_splice($suggestions, 0, 5));
			} else {
				$corrections = implode(",", $suggestions);
			}
		} 
		
		//first check to see if it already exists
		$id = $dbw->selectField(self::WORD_TABLE, 'sw_id', array('sw_word' => $word), __METHOD__);
		if ($id === false) {
			$dbw->insert(self::WORD_TABLE, array('sw_word' => $word, 'sw_corrections' => $corrections), __METHOD__);
			$id = $dbw->insertId();
		}
		
		return $id;

	}
	
	/******
	 * 
	 * Returns an array of words that make up our internal whitelist.
	 * 
	 ******/
	static function getWhitelistArray() {
		global $wgMemc;
		
		$key = wfMemcKey('spellchecker_whitelist');
		$wordArray = $wgMemc->get($key);
		
		if(!is_array($wordArray)) {
			$dbr = wfGetDB(DB_SLAVE);
			$res = $dbr->select(wikiHowDictionary::WHITELIST_TABLE, '*', array('sw_active' => 1), __METHOD__);
			
			$wordArray = array();
			foreach($res as $word) {
				$wordArray[$word->sw_word] = true;
			}
			
			$wgMemc->set($key, $wordArray);
		}
		
		return $wordArray;
		
		
	}
	
	/***
	 * 
	 * Returns a string with all the CAPS words in them
	 * to compare against words that are in articles
	 * 
	 */
	static function getCaps() {
		$dbr = wfGetDB(DB_SLAVE);
		
		$res = $dbr->select(self::CAPS_TABLE, "*", '', __METHOD__);
		
		$capsString = "";
		while($row = $dbr->fetchObject($res)) {
			$capsString .= " " . $row->sc_word . " ";
		}
		
		return $capsString;
	}
}

class ProposedWhitelist extends UnlistedSpecialPage {
	
	function __construct() {
		parent::__construct('ProposedWhitelist');
	}
	
	function execute($par) {
		global $wgOut, $wgUser, $wgRequest;
		
		if($wgUser->getID() == 0 || !($wgUser->isSysop() || in_array( 'newarticlepatrol', $wgUser->getRights() ) || $wgUser->getName() == "Gloster flyer" || $wgUser->getName() == "Byankno1" )) {
			$wgOut->setRobotpolicy( 'noindex,nofollow' );
			$wgOut->showErrorPage( 'nosuchspecialpage', 'nospecialpagetext' );
			return;
		}
		
		wfLoadExtensionMessages('Spellchecker');
		
		$wgOut->setHTMLTitle('Spellchecker Proposed Whitelist');
		$wgOut->setPageTitle('Spellchecker Proposed Whitelist');
		
		$wgOut->addHTML("<style type='text/css' media='all'>/*<![CDATA[*/ @import '" . wfGetPad('/extensions/min/f/extensions/wikihow/spellchecker/spellchecker.css?') . WH_SITEREV . "'; /*]]>*/</style> ");
		
		$dbr = wfGetDB(DB_SLAVE);
		
		$wgOut->addHTML("<p>" . wfMsgWikiHtml('spch-proposedwhitelist') . "</p>");
		
		if ($wgRequest->wasPosted()) {
			$wordsAdded = array();
			$wordsRemoved = array();
			$dbw = wfGetDB(DB_MASTER);
			foreach ($wgRequest->getValues() as $key=>$value) {
				$wordId = intval(substr($key, 5)); // 5 = "word-"
				$word = $dbr->selectField(wikiHowDictionary::WHITELIST_TABLE, 'sw_word', array('sw_id' => $wordId), __METHOD__);
				$msg = "";
				switch($value) {
					case "lower":
						$lWord = lcfirst($word);
						$lWordId = $dbr->selectField(wikiHowDictionary::WHITELIST_TABLE, 'sw_id', array('sw_word' => $lWord), __METHOD__);
						
						if($lWordId == $wordId) {
							//submitting the same word as was entered
							$dbw->update(wikiHowDictionary::WHITELIST_TABLE, array('sw_active' => 1), array('sw_id' => $wordId) );
							$msg = "Accepted {$word} into the whitelist";
						} else {
							//they've chosen to make it lowercase, when it wasn't to start
							if($lWordId === false) {
								//doesn't exist yet
								$dbw->insert(wikiHowDictionary::WHITELIST_TABLE, array(wikiHowDictionary::WORD_FIELD => $lWord, wikiHowDictionary::USER_FIELD => $wgUser->getID(), wikiHowDictionary::ACTIVE_FIELD => 1), __METHOD__, "IGNORE");
							}
							else {
								//already exists, so update it
								$dbw->update(wikiHowDictionary::WHITELIST_TABLE, array('sw_active' => 1), array('sw_id' => $lWordId) );
							}
							
							$dbw->delete(wikiHowDictionary::WHITELIST_TABLE, array('sw_id' => $wordId));
							$msg = "Put {$lWord} into whitelist as lowercase. Removed uppercase version.";
						}
						
						wikiHowDictionary::invalidateArticlesWithWord($dbr, $dbw, $lWord);
						$wordsAdded[] = $lWord;
						break;
					case "reject":
						$dbw->delete(wikiHowDictionary::WHITELIST_TABLE, array('sw_id' => $wordId));
						$msg = "Removed {$word} from the whitelist";
						$wordsRemoved[] = $word;
						break;
					case "caps":
						$uWord = ucfirst($word);
						$uWordId = $dbr->selectField(wikiHowDictionary::WHITELIST_TABLE, 'sw_id', array('sw_word' => $uWord), __METHOD__);
						if($uWordId == $wordId) {
							//submitting the same word as was entered
							$dbw->update(wikiHowDictionary::WHITELIST_TABLE, array('sw_active' => 1), array('sw_id' => $wordId) );
							$msg = "Accepted {$word} into the whitelist";
						} else {
							//they've chosen to make it lowercase, when it wasn't to start
							if($uWordId === false) {
								//doesn't exist yet
								$dbw->insert(wikiHowDictionary::WHITELIST_TABLE, array(wikiHowDictionary::WORD_FIELD => $uWord, wikiHowDictionary::USER_FIELD => $wgUser->getID(), wikiHowDictionary::ACTIVE_FIELD => 1), __METHOD__, "IGNORE");
							}
							else {
								//already exists, so update it
								$dbw->update(wikiHowDictionary::WHITELIST_TABLE, array('sw_active' => 1), array('sw_id' => $uWordId) );
							}
							
							$dbw->delete(wikiHowDictionary::WHITELIST_TABLE, array('sw_id' => $wordId));
							$msg = "Put {$uWord} into whitelist as uppercase. Removed lowercase version.";
						}
						wikiHowDictionary::invalidateArticlesWithWord($dbr, $dbw, $uWord);
						$wordsAdded[] = $uWord;
						break;
					case "ignore":
					default:
						break;
				}
				
				if($msg != "") {
					$log = new LogPage( 'whitelist', false ); // false - dont show in recentchanges
					$t = Title::newFromText("Special:ProposedWhitelist");
					$log->addEntry($value, $t, $msg);
				}
			}
			
			if(count($wordsAdded) > 0) 
				$wgOut->addHTML("<p><b>" . implode(", ", $wordsAdded) . "</b> " . ((count($wordsAdded)>1)?"were":"was") . " added to the whitelist.</p>");
			if(count($wordsRemoved) > 0)
				$wgOut->addHTML("<p><b>" . implode(", ", $wordsRemoved) . "</b> " . ((count($wordsRemoved)>1)?"were":"was") . " were removed from the whitelist.</p>");
						
		}	
		
		//show table
		list( $limit, $offset ) = wfCheckLimits(50, '');
		
		$res = $dbr->select(wikiHowDictionary::WHITELIST_TABLE, '*', array(wikiHowDictionary::ACTIVE_FIELD => 0), __METHOD__, array("LIMIT" => $limit, "OFFSET" => $offset));
		$num = $dbr->numRows($res);

		$words = array();
		foreach($res as $item) {
			$words[] = $item;
		}
		
		$paging = wfViewPrevNext( $offset, $limit, "Special:ProposedWhitelist", "", ( $num < $limit ) );

		$wgOut->addHTML("<p>{$paging}</p>");
		
		//ok, lets create the table
		$wgOut->addHTML("<form name='whitelistform' action='/Special:ProposedWhitelist' method='POST'>");

		$wgOut->addHTML("<table id='whitelistTable' cellspacing='0' cellpadding='0'><thead><tr>");
		$wgOut->addHTML("<td>Word</td><td class='wide'>Correctly spelled,<br /> always require that the first letter be capitalized</td><td class='wide'>Correctly spelled,<br /> do not require first letter to be capitalized</td><td>Reject - not a word</td><td>I'm not sure</td></tr></thead>");

		/////SAMPLES
		$wgOut->addHTML("<tr class='sample'><td class='word'>kiittens</td>");
		$wgOut->addHTML("<td><input type='radio' disabled='disabled' value='caps' name='sample-1'></td>");
		$wgOut->addHTML("<td><input type='radio' disabled='disabled' value='lower' name='sample-1'></td>");
		$wgOut->addHTML("<td><input type='radio' disabled='disabled' checked='checked' value='reject' name='sample-1'></td>");
		$wgOut->addHTML("<td><input type='radio' disabled='disabled' value='ignore' name='sample-1'></td>");
		$wgOut->addHTML("</tr>");
		$wgOut->addHTML("<tr class='sample'><td class='word'>hawaii</td>");
		$wgOut->addHTML("<td><input type='radio' disabled='disabled' checked='checked' value='caps' name='sample-2'></td>");
		$wgOut->addHTML("<td><input type='radio' disabled='disabled' value='lower' name='sample-2'></td>");
		$wgOut->addHTML("<td><input type='radio' disabled='disabled' value='reject' name='sample-2'></td>");
		$wgOut->addHTML("<td><input type='radio' disabled='disabled' value='ignore' name='sample-2'></td>");
		$wgOut->addHTML("</tr>");
		$wgOut->addHTML("<tr class='sample'><td class='word'>Dude</td>");
		$wgOut->addHTML("<td><input type='radio' disabled='disabled' value='caps' name='sample-3' /></td>");
		$wgOut->addHTML("<td><input type='radio' disabled='disabled' checked='checked' value='lower' name='sample-3' /></td>");
		$wgOut->addHTML("<td><input type='radio' disabled='disabled' value='reject' name='sample-3' /></td>");
		$wgOut->addHTML("<td><input type='radio' disabled='disabled' value='ignore' name='sample-3' /></td>");
		$wgOut->addHTML("</tr>");
		
		if(count($words) == 0) {
			//no words waiting to be approved
			$wgOut->addHTML("<tr><td colspan='5' class='word'>No words to approve right now. Please check back again later</td></tr>");
		} 
		else {
			
			
			foreach($words as $word) {
				$firstLetter = substr($word->{wikiHowDictionary::WORD_FIELD}, 0, 1);
				$wgOut->addHTML("<tr><td class='word'>" . $word->{wikiHowDictionary::WORD_FIELD} . " [<a target='_blank' href='http://www.google.com/search?q=" . $word->{wikiHowDictionary::WORD_FIELD} . "'>?</a>]</td>");
				$wgOut->addHTML("<td><input type='radio' value='caps' name='word-" . $word->sw_id . "'></td>");
				$wgOut->addHTML("<td><input type='radio' value='lower' name='word-" . $word->sw_id . "'></td>");
				$wgOut->addHTML("<td><input type='radio' value='reject' name='word-" . $word->sw_id . "'></td>");
				$wgOut->addHTML("<td><input type='radio' value='ignore' name='word-" . $word->sw_id . "' checked='checked'></td>");
				$wgOut->addHTML("</tr>");
			}
			
			$wgOut->addHTML("<tr><td colspan='5'><input type='button' onclick='document.whitelistform.submit();' value='Submit' class='guided-button' /></td></tr>");
			
			
		}
		
		$wgOut->addHTML("</table></form>");
	}
}
