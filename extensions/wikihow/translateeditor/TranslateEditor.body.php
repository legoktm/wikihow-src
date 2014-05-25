<?
require_once('TranslationLink.php');
/** 
 * Modifies the edit page to ask for a URL to translate, fetches content to translate and tracks
 * translations between languages in teh database.
 */
class TranslateEditor extends UnlistedSpecialPage {
	const TOOL_NAME="TRANSLATE_EDITOR";
	const API_URL="http://www.wikihow.com/api.php";

	// These templates will be removed when we translate
	function __construct() {
		parent::__construct('TranslateEditor');
	}
	/**
	 * Check if the user is a translater, and return true if a translator and false otherwise
	 */
	static function isTranslatorUser() {
		global $wgUser, $wgLanguageCode, $wgServer;

		$userGroups = $wgUser->getGroups();

		//if ($wgUser->getID() == 0 || (!(in_array('translator', $userGroups) ) )) {
		if ($wgUser->getID() == 0 || (!(in_array('translator', $userGroups) ) && strcasecmp($wgUser->getName() , wfMsg('translator_account'))!=0 ) || $wgLanguageCode == "en") {
			return false;
		}
		return true;
	}
	/**
	 * Regex for matching section names to replace
	 */
	static function getSectionRegex($sectionName) {
		return("== *" . $sectionName . " *==");	
	}
	/**
	 * Name of section name to change them to
	 */
	static function getSectionWikitext($sectionName) {
		return("== " . $sectionName . " ==");	
	}
	/**
	 * Called when the user goes to an edit page
	 * Override the functionality of the edit to require a URL to translate
	 */
	static function onCustomEdit() {
		global $wgRequest, $wgOut;
		
		$draft = $wgRequest->getVal('draft', null);
		$target = $wgRequest->getVal('title', null);
		$action = $wgRequest->getVal('action', null);
		$section = $wgRequest->getVal('section',$wgRequest->getVal('wpSection',null));
		$save = $wgRequest->getVal('wpSave',null);
		$title = Title::newFromURL($target);
		// We have the dialog to enter the URL when we are adding a new article, and have no existing draft.
		if(self::isTranslatorUser()) {

			if($draft == null 
			&& !$title->exists() 
			&& $action=='edit') {
		
				EasyTemplate::set_path(dirname(__FILE__).'/');
				// Templates to remove from tranlsation
				$remove_templates = array("{{FA}}", "\\[\\[Category:[^\\]]+\\]\\]");
				// Words or things to automatically translate 
				$translations = array(array('from'=>self::getSectionRegex('Steps'), 'to' =>self::getSectionWikitext(wfMsg('Steps'))),
															array('from'=>self::getSectionRegex('Tips'),'to'=>self::getSectionWikitext(wfMsg('Tips'))),
															array('from'=>self::getSectionRegex('Warnings'),'to'=>self::getSectionWikitext(wfMsg('Warnings'))),
															array('from'=>self::getSectionRegex('Ingredients'),'to'=>self::getSectionWikitext(wfMsg('Ingredients'))),
															array('from'=>self::getSectionRegex("Things You'll need"),'to'=>self::getSectionWikitext(wfMsg('Thingsyoullneed'))),
															array('from'=>self::getSectionRegex("Related wikiHows"),'to'=>self::getSectionWikitext(wfMsg('Related'))),
															array('from'=>self::getSectionRegex("Sources and Citations"),'to'=>self::getSectionWikitext(wfMsg('Sources'))),
															);
				$vars = array('title' => $target, 'checkForLL' => true, 'translateURL'=>true, 'translations' => json_encode($translations), 'remove_templates'=> array_map(preg_quote,$remove_templates));
				$html = EasyTemplate::html('TranslateEditor.tmpl.php', $vars);
				$wgOut->addHTML($html);
				QuickEdit::showEditForm($title);
				return false;
			}
			elseif($section == null && $save == null) {
				EasyTemplate::set_path(dirname(__FILE__).'/');
				$vars = array('title' => $target, 'checkForLL' => true, 'translateURL'=>false);
				$html = EasyTemplate::html('TranslateEditor.tmpl.php', $vars);
				$wgOut->addHTML($html);
				QuickEdit::showEditForm($title);
				return(false);
			}
		}
		return true;
	}
	/**
	 * EXECUTE
	 **/
	function execute ($par) {
		global $wgRequest, $wgOut, $wgUser, $wgLang;
		if ($wgUser->isBlocked()) {
			$wgOut->blockedPage();
			return;
		}
		$userGroups = $wgUser->getGroups();

		if (!self::isTranslatorUser()) {
			$wgOut->setRobotpolicy( 'noindex,nofollow' );
			$wgOut->showErrorPage( 'nosuchspecialpage', 'nospecialpagetext' );
			return;
		}
		$action = $wgRequest->getVal('action', null);
		$target = $wgRequest->getVal('target', null);
		$toTarget = $wgRequest->getVal('toTarget', null);
			
		if($action == "getarticle") {
			$this->startTranslation($target, $toTarget);		
		}
	}
		/**
	 * Use API.php to get information about the article on English 
	 * This is done so the code can run properly on international wikis
	 */
	static function getArticleRevisionInfo($target) {
		$url = TranslateEditor::API_URL . "?action=query&prop=revisions&titles=" . urlencode($target) . "&rvprop=content|ids&format=json";
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$text = curl_exec($ch);
		curl_close($ch);

		return($text);
	}
	/** 
	 * Start a translation by fetching the article to be translated,
	 * and logging it.
	 */
	function startTranslation($fromTarget, $toTarget) {
		global $wgOut, $wgRequest, $wgLanguageCode;
		$target = urldecode($fromTarget);
		$text = self::getArticleRevisionInfo($target);

		$output = array();
		$wgOut->setArticleBodyOnly(true);
		$json = json_decode($text, true);
		$ak = array_keys($json['query']['pages']);
		$fromAID = intVal($ak[0]);
		//The article we are translating exists
		if($fromAID > 0 ) { 
			$exists = false;
			$links = TranslationLink::getLinksTo("en", $fromAID, $wgLanguageCode);
			foreach($links as $link) {
				if($link->toLang == $wgLanguageCode) {
					$exists = true;	
				}
			}
			if(!$exists) {
				$fromRevisionId = $json['query']['pages'][$fromAID]['revisions'][0]['revid'];
				$txt = $json['query']['pages'][$fromAID]['revisions'][0]['*'];
				if(preg_match("/#REDIRECT/",$txt)) {
					$output['error'] = "It seems the article you are attempting to translate is a redirect. Please contact your project manager.";
					$output['success'] = false;
				}
				else {
					$output['success'] = true;
					$output['aid'] = $fromAID;
					$output['text'] = $txt;
					TranslationLink::writeLog(TranslationLink::ACTION_NAME, 'en', $fromRevisionId, $fromAID,$target,$wgLanguageCode,$toTarget);
				}
			}
			else {
				$output['success'] = false;
				$output['error'] = "It seems the article was already translated. Please contact your project manager."; 
			}
		}
		else {
			$output['success'] = false;
			$output['error'] = "No article at given URL. Please contact your project manager.";
		}
		$wgOut->addHTML(json_encode($output));
	}
	/** 
	 * Check if URL exists and is not a redirect
	 */
	static function checkUrl($url) {
		$pages = Misc::getPagesFromURLs(array($url));
		foreach($pages as $u => $p) {
			if($p['page_is_redirect'] == 0 && $p['page_namespace'] == NS_MAIN) {
				return true;	
			}
		}
		return false;	
	}
	/**
	 * Check if there is a link between two article ids
	 * If so, return true otherwise return false
	 */
	static function isLink($langA, $aidA, $langB, $aidB) {
		$dbh = wfGetDB(DB_SLAVE);
		$sql = "select count(*) as ct from language_links where (ll_from_lang=" . $dbh->addQuotes($langA) . " AND ll_from_aid=" . $dbh->addQuotes($aidA) . " AND " . "ll_to_lang=" . $dbh->addQuotes($langB) . " AND ll_to_aid=" . $dbh->addQuotes($aidB) . ") OR "
		. "(ll_from_lang=" . $dbh->addQuotes($langB) . " AND ll_from_aid=" . $dbh->addQuotes($aidB) . " AND " . "ll_to_lang=" . $dbh->addQuotes($langA) . " AND ll_to_aid=" . $dbh->addQuotes($aidA) . ")";

		$res = $dbh->query($sql);
		$row = $dbh->fetchObject($res);
		if($row->ct == 1) {
			return true;	
		}
		else {
			return false;	
		}
	}
		/**
	 * Article ready to be saved. If a translation, we will use the appropiate translation user.
	 */
	static function onSave(&$article, &$user, $text, $summary, $min,$a,$b,&$flags) {
		if(($flags & EDIT_NEW) && self::isTranslatorUser()) {
			// Save our user, and do edit as translator user
			global $whgOurUser;
			$whgOurUser = $user;
			$user = User::newFromName(wfMsg("translator_account"));
		}
		return true;
	}
	/**
	 * Article save complete is called when the article is saved. We use this
	 * hook to track when translated articles are saved, and switch back to current user after saving article.
	 */
	static function onSaveComplete(&$article, &$user, $text, $summary, $minor,$a,$b,&$flags,$revision) {
		global $wgLanguageCode, $wgUser;

		if(($flags & EDIT_NEW) && self::isTranslatorUser()) {
			//Switch back to our user
			global $whgOurUser;
			$user = $whgOurUser;
			$wgUser = $whgOurUser;
			//Add translation link information to database
			$toTitle = $article->getTitle();

			if(preg_match("/\[\[en:([^\]]+)\]\]/", $text, $matches)) {
				$fromTitle = urldecode($matches[1]);
				$fromTitle = str_replace(" ","-",$fromTitle);
				$json = json_decode(self::getArticleRevisionInfo($fromTitle),true);
				$ak = array_keys($json['query']['pages']);
				$fromAID = $ak[0];
				$fromRevisionId = $json['query']['pages'][$fromAID]['revisions'][0]['revid'];

				$tl = new TranslationLink();
				$tl->fromAID = $fromAID;
				$tl->fromLang = "en";
				$tl->toLang = $wgLanguageCode;
				$tl->toAID = $toTitle->getArticleId();
				$tl->insert();
				TranslationLink::writeLog(TranslationLink::ACTION_SAVE, "en", $fromRevisionId, $fromAID, $fromTitle, $wgLanguageCode, $toTitle->getText(), $toTitle->getArticleId()  );
			}
			else {
				// Error, we should have an interwiki link on the page. We will still log it.
				TranslationLink::writeLog(TranslationLink::ACTION_SAVE, "en", NULL, NULL, NULL, $wgLanguageCode, $toTitle->getText(), $toTitle->getArticleId());
			}
		}
		return true;
	}
	static function shouldUseDrafts($par = NULL) {
		if(self::isTranslatorUser()) {
			return(false);	
		}
		return(true);
	}
}
