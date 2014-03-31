<?php
//
// We don't really have a place to put random, small pieces of functionality.
// This class addresses that.
//

class Misc {

	private static $displayedLayout = '';

	/*
	 * adminPostTalkMessage
	 * - returns true/false
	 *
	 * $to_user = User object of who is getting this talk message
	 * $from_user = User object of who is sending this talk message
	 * $comment = The text that is displayed in the talk page message
	 */
	public static function adminPostTalkMessage($to_user, $from_user, $comment) {
		global $wgLang;
		$existing_talk = '';

		//make sure we have everything we need...
		if (empty($to_user) || empty($from_user) || empty($comment)) return false;

		$from = $from_user->getName();
		if (!$from) return false; //whoops
		$from_realname = $from_user->getRealName();
		$dateStr = $wgLang->date(wfTimestampNow());
		$formattedComment = wfMessage('postcomment_formatted_comment', $dateStr, $from, $from_realname, $comment)->text();

		$talkPage = $to_user->getUserPage()->getTalkPage();

		if ($talkPage->getArticleId() > 0) {
			$r = Revision::newFromTitle($talkPage);
			$existing_talk = $r->getText() . "\n\n";
		}
		$text = $existing_talk . $formattedComment ."\n\n";

		$flags = EDIT_FORCE_BOT | EDIT_SUPPRESS_RC;

		$article = new Article($talkPage);
		$result = $article->doEdit($text, "", $flags);

		return $result;
	}

	public static function getDTDifferenceString($date, $isUnixTimestamp = false) {
		wfLoadExtensionMessages('Misc');
		if (empty($date)) {
			return "No date provided";
		}

		if ($isUnixTimestamp) {
			$unix_date = $date;
		} else {
			$date = $date . " UTC";
			$unix_date = strtotime($date);
		}

		$now = time();
		$lengths = array("60","60","24","7","4.35","12","10");

		// check validity of date
		if (empty($unix_date)) {
			return "Bad date: $date";
		}

		// is it future date or past date
		if ($now > $unix_date) {
			$difference = $now - $unix_date;
			$tenseMsg = 'rcwidget_time_past_tense';
		} else {
			$difference = $unix_date - $now;
			$tenseMsg = 'rcwidget_time_future_tense';
		}

		for ($j = 0; $difference >= $lengths[$j] && $j < count($lengths) - 1; $j++) {
			$difference /= $lengths[$j];
		}
		$difference = round($difference);

		if ($difference != 1) {
			$periods = array(wfMessage("second-plural")->text(), wfMessage("minute-plural")->text(), wfMessage("hour-plural")->text(), wfMessage("day-plural")->text(),
						wfMessage("week-plural")->text(), wfMessage("month-plural")->text(), wfMessage("year-plural")->text(), wfMessage("decade-plural")->text());
		} else {
			$periods = array(wfMessage("second")->text(), wfMessage("minute")->text(), wfMessage("hour")->text(), wfMessage("day")->text(),
						wfMessage("week")->text(), wfMessage("month-singular")->text(), wfMessage("year-singular")->text(), wfMessage("decade")->text());
		}

		return wfMessage($tenseMsg, $difference, $periods[$j])->text();
	}

	// Format a binary number
	public static function formatBinaryNum($n) {
		return sprintf('%032b', $n);
	}

	// Check if an $ip address (string) is within an IP network
	// and netmask, defined in $range (string).
	//
	// Note: $ip and $range need to be perfectly formatted!
	public static function isIpInRange($ip, $range) {
		list($range, $maskbits) = explode('/', $range);
		list($i1, $i2, $i3, $i4) = explode('.', $ip);
		list($r1, $r2, $r3, $r4) = explode('.', $range);
		$numi = ($i1 << 24) | ($i2 << 16) | ($i3 << 8) | $i4;
		$numr = ($r1 << 24) | ($r2 << 16) | ($r3 << 8) | $r4;
		$mask = 0;
		for ($i = 1; $i <= $maskbits; $i++) {
			$mask |= 1 << (32 - $i);
		}
		$masked = $numi & $mask;
		//print self::formatBinaryNum($masked) . ' ' .
		//	self::formatBinaryNum($numr) . ' ' .
		//	self::formatBinaryNum($numi) . "\n";
		return $masked === $numr;
	}

	/**
	 * Add a check to see if the proxy we're going through is CloudFlare. See
	 * ranges:
	 *
	 * https://www.cloudflare.com/wiki/What_are_the_CloudFlare_IP_address_ranges
	 */
	/*public static function checkCloudFlareProxy($ip, &$trusted) {
		$ranges = array(
			'204.93.240.0/24', '204.93.177.0/24', '199.27.128.0/21',
			'173.245.48.0/20', '103.22.200.0/22', '141.101.64.0/18',
			'108.162.192.0/18', '190.93.240.0/20',
		);
		if (!$trusted && preg_match('@^(\d{1,3}\.){3}\d{1,3}$@', $ip)) {
			foreach ($ranges as $range) {
				if (self::isIpInRange($ip, $range)) {
					$trusted = true;
					break;
				}
			}
		}
		return true;
	}*/

	/**
	 * A callback check if the request is behind fastly, and if so, look for
	 * the XFF header.
	 */
	public static function checkFastlyProxy($ip, &$trusted) {
		if (!$trusted) {
			$trusted = checkFastlyProxy();
		}
		return true;
	}

	/**
	 * Send a file to the user that forces them to download it.
	 */
	public static function outputFile($filename, &$output, $mimeType  = 'text/tsv') {
		global $wgOut, $wgRequest;
		$wgOut->setArticleBodyOnly(true);
		$wgRequest->response()->header('Content-type: ' . $mimeType);
		$wgRequest->response()->header('Content-Disposition: attachment; filename="' . addslashes($filename) . '"');
		$wgOut->addHtml($output);
	}

	// Makes a url given a dbkey or page title string
	public static function makeUrl($pageTitle, $domain = 'www.wikihow.com') {
		$pageTitle = str_replace(' ', '-', $pageTitle);
		return "http://$domain/" . urlencode($pageTitle);
	}

	// Url decode string data.  Decode it twice in the case where the user
	// inputted string may include urls already encode
	public static function getUrlDecodedData($data, $decodePlusSign = true) {
		// Keep the plusses around
		$decoded = $data;
		if ($decodePlusSign) {
			$decoded = preg_replace("@\+@", "%2B", $decoded);
		}
		$decoded = urldecode($decoded);
		if ($decodePlusSign) {
			$decoded = preg_replace("@\+@", "%2B", $decoded);
		}
		$decoded = urldecode($decoded);

		return $decoded;
	}

	/* data schema
	 *
	 CREATE TABLE redirect_page (
		rp_page_id int(8) unsigned NOT NULL,
		rp_folded varchar(255) NOT NULL,
		rp_redir varchar(255) NOT NULL,
		PRIMARY KEY(rp_page_id),
		INDEX(rp_folded)
	 );
	 */

	/**
	 * Make a lower case, punctuation-free form of the article title
	 */
	private static function redirectGetFolded($text) {
		$text = strtolower($text);
		$patterns = array('@[[:punct:]]@', '@\s+@');
		$replace  = array('',              ' ');
		$text = preg_replace($patterns, $replace, $text);
		return substr( $text, 0, 255 );
	}

	/**
	 * Callback to check for a case-folded redirect
	 */
	public static function check404Redirect($title) {
		$redir = '';
		if ($title && $title->getNamespace() == NS_MAIN) {
			$dbr = wfGetDB(DB_SLAVE);
			$text = self::redirectGetFolded( $title->getText() );
			$redirPageID = $dbr->selectField('redirect_page', 'rp_page_id', array('rp_folded' => $text), __METHOD__);
			$redirTitle = Title::newFromID($redirPageID);
			if ($redirTitle && $redirTitle->exists()) {
				$partial = $redirTitle->getPartialURL();
				if ($partial != $title->getPartialURL()) {
					$redir = $partial;
				}
			}
		}
		return $redir;
	}

	/**
	 * Callback to create, modify or delete a case-folded redirect
	 */
	public static function modify404Redirect($pageid, $newTitle) {
		static $dbw = null;
		if (!$dbw) $dbw = wfGetDB(DB_MASTER);
		$pageid = intval($pageid);

		if ($pageid <= 0) {
			return;
		} elseif (!$newTitle
				|| !$newTitle->exists()
				|| $newTitle->getNamespace() != NS_MAIN)
		{
			$dbw->delete('redirect_page', array('rp_page_id' => $pageid), __METHOD__);
		} else {
			// debug:
			//$field = $dbw->selectField('redirect_page', 'count(*)', array('rp_page_id'=>$pageid));
			//if ($field > 0) { print "$pageid $newTitle\n"; }
			$newrow = array(
				'rp_page_id' => intval($pageid),
				'rp_folded' => self::redirectGetFolded( $newTitle->getText() ),
				'rp_redir' => substr( $newTitle->getText(), 0, 255 ),
			);
			$dbw->replace('redirect_page', 'rp_page_id', $newrow, __METHOD__);
		}
	}

	/********
	 *
	 * Function returns true if the user is required to
	 * be logged in to view the given title. False, if
	 * no login is required.
	 *
	 *******/
	public static function requiresLogin($title) {
		if(!$title)
			return false;

		if($title->getNamespace() != NS_SPECIAL)
			return false;

		switch($title->getText()) {
			case "Spellchecker":
			case "Videoadder":
			case "RCPatrol":
			case "IntroImageAdder":
			case "EditFinder/Topic":
				return true;
			default:
				return false;
		}
	}

	/*******
	 *
	 ******/
	public static function allowRedirectFromLogin($title) {
		if(!$title)
			return false;

		if($title->getNamespace() != NS_SPECIAL)
			return false;

		switch($title->getText()) {
			case "Spellchecker":
			case "Videoadder":
			case "RCPatrol":
			case "IntroImageAdder":
			case "EditFinder/Topic":
			case "ListRequestedTopics":
			case "Categorizer":
			case "EditFinder/Format":
			case "EditFinder/Stub":
			case "EditFinder/Copyedit":
			case "EditFinder/Cleanup":
			case "QG":
			case "Newarticleboost":
			case "NFDGuardian":
			case "TweetItForward":
			case "CreatePage":
			case "Random":
				return true;
			default:
				return false;
		}
	}

	public static function addVarnishHeaders() {
		global $wgTitle, $wgRequest;

		if ($wgRequest && $wgTitle) {
			$layoutStr = self::$displayedLayout ? self::$displayedLayout : 'dt';
			$wgRequest->response()->header("X-Layout: $layoutStr");

			$id = $wgTitle->getArticleID();
			$cachePercent = ($id >= 0 ? $id % 10 : 0);
			$rollingResetStr = 'roll' . $cachePercent;
			$wgRequest->response()->header("Surrogate-Key: $layoutStr $rollingResetStr");
		}

		return true;
	}

	public static function setMobileLayoutHeader($caller) {
		if ($caller && get_class($caller) == 'MobileWikihow') {
			self::$displayedLayout = 'mb';
		}
		return true;
	}

	/**
	  * Get database for a given language code
	  */
	public static function getLangDB($lang) {
		global $wgWikiHowLanguages;
		if ($lang == "en") {
			return WH_DATABASE_NAME_EN;
		} elseif (in_array($lang, $wgWikiHowLanguages)) {
			if ($lang == "es" && IS_TEST_INTL_SITE) {
				return 'wikidb_es2';
			} else {
				return 'wikidb_' . $lang;
			}
		} else {
			throw new Exception("$lang is not a WikiHow language in getLangDB");
			return '';
		}
	}

	/**
	 * Get a base URL for a given language code
	 */
	public static function getLangBaseURL($lang) {
		global $wgActiveLanguages;
		if ($lang == "en") {
			return "http://www.wikihow.com";
		} elseif (in_array($lang, $wgActiveLanguages)) {
			return "http://" . $lang . ".wikihow.com";
		} else {
			return "";
		}
	}

	/**
	 * Get a language code and partial URL from a full URL
	 */
	public static function getLangFromFullURL($url) {
		global $wgActiveLanguages;
		if (preg_match('@^http://([a-zA-Z]+)\.wikihow\.com/([^?]+)@', $url, $matches)) {
			if ($matches[1] == 'www') {
				return array('en', $matches[2]);
			} elseif (in_array($matches[1], $wgActiveLanguages)) {
				return array($matches[1], $matches[2]);
			}
		}
		return array('', '');
	}

	/**
	 * Get pages from language ids
	 * @param langIds List of language ids as an array-hash array('lang'=> ,'id'=>)
	 *
	 */
	public static function getPagesFromLangIds($langIds, $cols=array()) {
		$ll = array();
		foreach($langIds as $li) {
			$ll[$li['lang']][] = $li['id'];
		}
		$dbh = wfGetDB(DB_SLAVE);

		$startSQL = "";
		if(empty($cols)) {
			$startSQL = "select * from ";
		}
		else {
			$startSQL = "select " . implode(',', $cols) . " from ";
		}
		$pages = array();
		foreach($ll as $l=>$ids) {

			$sql = $startSQL . Misc::getLangDB($l) . ".page where page_id in (" . implode(',',$ids) . ")";
			$res = $dbh->query($sql, __METHOD__);
			while($row = $dbh->fetchObject($res)) {
				$row = get_object_vars($row);
				$pages[$l][$row['page_id'] ] = array_merge($row, array('lang'=>$l));
			}
		}
		$rows = array();
		foreach($langIds as $li) {
			if(isset($pages[$li['lang']][$li['id']])) {
				$rows[] = $pages[$li['lang']][$li['id']];
			}
			else {
				$rows[] = array('page_id'=>$li['id'],'lang'=>$li['lang']);
			}
		}

		return($rows);
	}

	/**
	 * Fetch pages for desktop urls in multiple languages
	 * @param $urls array of urls. These URLs should be decoded before being passed to function
	 * @return Hash-map of URL to pages
	 */
	public static function getPagesFromURLs($urls, $cols=array(), $includeRedirects=false) {
		global $wgActiveLanguages;
		$urlsByLang = array();
		$dbh = wfGetDB(DB_SLAVE);

		foreach($urls as $url) {
			list($lang, $partial) = self::getLangFromFullURL($url);
			if ($lang && $partial) {
				$urlsByLang[$lang][] = $dbh->addQuotes($partial);
			}
		}
		$startSQL = "";
		if(empty($cols)) {
			$startSQL = "select * from ";
		}
		else {
			// page_title is required
			if (!in_array('page_title', $cols)) $cols[] = 'page_title';
			$startSQL = "select " . implode(',', $cols) . " from ";
		}
		$results = array();

		foreach($urlsByLang as $lang => $titles) {
			$db = self::getLangDB($lang);
			$baseURL = self::getLangBaseURL($lang);
			$redirectsSQL = !$includeRedirects ? ' AND page_is_redirect=0' : '';
			$sql = $startSQL . $db . ".page WHERE page_title in (" . implode(',',$titles) . ") AND page_namespace=" . NS_MAIN . $redirectsSQL;
			$res = $dbh->query($sql, __METHOD__);
			while($row = $dbh->fetchObject($res)) {
				$row = get_object_vars($row);
				$row['lang'] = $lang;
				$results[$baseURL . '/' . $row['page_title']] = $row;
			}
		}
		return($results);
	}

	/**
	 * Get just the page-name part of the url for any page on wikiHow.
	 */
	public function fullUrlToPartial($url) {
		if (preg_match("/http:\/\/([a-zA-Z]+)\.wikihow\.com\/(.+)/", $url, $matches)) {
			return($matches[2]);
		}
		return("");
	}

	/**
	 * Rollout of a feature based on $wgTitle.
	 * @param int $startTime time in seconds since Jan 1, 1970 (unix time)
	 * @param int $duration time in seconds that the rollout period should last
	 * @return int true if and only if article should be rolled out
	 *
	 * Example:
	 *  $startTime = strtotime('March 20, 2013');
	 *  $twoWeeks = 2 * 7 * 24 * 60 * 60;
	 *  $rolloutArticle = Misc::percentileRollout($startTime, $twoWeeks);
	 */
	function percentileRollout($startTime, $duration, $titleObj = null) {
		if (!$titleObj) {
			global $wgTitle;
			$title = $wgTitle ? $wgTitle->getText() : '';
		} else {
			$title = $titleObj->getText();
		}
		$crc = crc32($title);
		$time = time();
		if ($time < $startTime) return false;
		if ($time > $startTime + $duration) return true;
		$percentTime = round( 100 * (($time - $startTime) / $duration) );
		$percentArticle = $crc % 100;
		return $percentTime > $percentArticle;
	}

	/**
	 * Generate a string of random characters
	 */
	static function genRandomString($chars = 20) {
		$str = '';
		$set = array(
			'0','1','2','3','4','5','6','7','8','9',
			'a','b','c','d','e','f','g','h','i','j','k','l','m',
			'n','o','p','q','r','s','t','u','v','w','x','y','z',
			'A','B','C','D','E','F','G','H','I','J','K','L','M',
			'N','O','P','Q','R','S','T','U','V','W','X','Y','Z',
		);
		for ($i = 0; $i < $chars; $i++) {
			$r = mt_rand(0, count($set) - 1);
			$str .= $set[$r];
		}
		return $str;
	}

	/**
		* Get list of active languages with their names
		*/
	static function getActiveLanguageNames() {
		global $wgActiveLanguages, $wgLanguageNames;

		$languageInfo[] = array('languageCode' => 'en', 'languageName' => 'English');
		foreach($wgActiveLanguages as $lang) {
			$languageInfo[] = array('languageCode' => $lang, 'languageName' => Language::fetchLanguageName($lang));
		}

		return($languageInfo);
	}

	static function defineAsTool(&$isTool) {
		global $wgTitle;
		$isTool = true;
		return true;
	}

	static function removeGrayContainerCallback(&$showGrayContainer) {
		$showGrayContainer = false;
		return true;
	}

	static function removePostProcessing($title, &$processHTML) {
		$processHTML = false;
		return true;
	}

	static function getDeleteReasonFromCode($article, $outputPage, &$defaultReason) {

		$whArticle = WikihowArticleEditor::newFromTitle($article->getTitle());
		$intro = $whArticle->getSummary();
		$matches = array();
		preg_match('/{{nfd.*}}/i', $intro, $matches);

		if ($matches[0] != null) {
			$loc = stripos($matches[0], "|", 4);
			if ($loc) { //there is a reason
				$loc2 = stripos($matches[0], "|", $loc + 1);
				if (!$loc2) {
					$loc2 = stripos($matches[0], "}", $loc + 1);
				}

				//ok now grab the reason
				$nfdreason = substr($matches[0], $loc + 1, $loc2 - $loc - 1);
				switch ($nfdreason) {
					case 'acc':
						$defaultReason = "Accuracy";
						break;
					case 'adv':
						$defaultReason = "Advertising";
						break;
					case 'cha':
						$defaultReason = "Character";
						break;
					case 'dan':
						$defaultReason = "Extremely Dangerous";
						break;
					case 'dru':
						$defaultReason = "Drug focused";
						break;
					case 'hat':
						$defaultReason = "Hate/racist";
						break;
					case 'imp':
						$defaultReason = "Impossible";
						break;
					case 'inc':
						$defaultReason = "Incomplete";
						break;
					case 'jok':
						$defaultReason = "Joke";
						break;
					case 'mea':
						$defaultReason = "Mean-spirited";
						break;
					case 'not':
						$defaultReason = "Not a how-to";
						break;
					case 'pol':
						$defaultReason = "Political opinion";
						break;
					case 'pot':
						$defaultReason = "Potty humor";
						break;
					case 'sar':
						$defaultReason = "Sarcastic";
						break;
					case 'sex':
						$defaultReason = "Sexually explicit";
						break;
					case 'soc':
						$defaultReason = "Societal Instructions";
						break;
					case 'ill':
						$defaultReason = "Universally illegal";
						break;
					case 'van':
						$defaultReason = "Vanity pages";
						break;
					case 'jok':
						$defaultReason = "Joke";
						break;
					case 'dup':
						$defaultReason = "Duplicate";
						break;
				}
			}
		}

		return true;
	}

	/**
	 * Add global variables
	 */
	public static function addGlobalVariables(&$vars, $outputPage) {
		global $wgFBAppId;
		$vars['wgWikihowSiteRev'] = WH_SITEREV;
		$vars['wgFBAppId'] = $wgFBAppId;

		return true;
	}

	/*
	* Styling for the default EditForm
	*/
	public static function onShowEditFormFields(&$editform, &$wgOut) {
		$editform->editFormTextBeforeContent = Html::openElement( 'div', array( 'class' => 'minor_section') );
		$editform->editFormTextAfterContent = Html::closeElement( 'div' );
		$editform->editFormTextAfterContent .= Html::openElement( 'div', array( 'class' => 'minor_section') );
		$editform->editFormTextAfterTools = Html::closeElement( 'div' );
		$editform->editFormTextAfterTools .= Html::closeElement( 'div' ); //Bebeth adding, not sure exactly why it's needed (seems like an extra </div> but it fixes it.
		$editform->editFormTextAfterTools .= Html::openElement( 'div', array( 'class' => 'minor_section') );
		$editform->editFormTextBottom = Html::closeElement( 'div' );

		return true;
	}

	/*
	* Plug in our logged in search Special Page in certain cases
	*/
	public static function onLanguageGetSpecialPageAliases(&$specialPageAliases, $langCode) {
		global $wgUseGoogleMini, $wgRequest;

		if ($wgRequest->getVal('advanced', null) == null && $wgUseGoogleMini) {
			$specialPageAliases['Search'] = array('LSearch');
		}
		return true;
	}

	public static function onBeforeWelcomeCreation( &$welcome_creation_msg, &$injected_html ) {
		global $wgRedirectOnLogin, $wgLanguageCode;

		if($wgLanguageCode != "en") {
			$dashboardPage = Title::makeTitle(NS_PROJECT, wfMessage("community")->text());
			$wgRedirectOnLogin = $dashboardPage->getFullText();
		}
		else {
			$wgRedirectOnLogin = 'Special:CommunityDashboard';
		}

		return true;
	}

	/**
	 * Login to MediaWiki as a specific user while running script
	 * @param $user The username of the user to login as
	 * @param $forceBot if true, we will ensure we are in the bot group
	 */
	public static function loginAsUser($user, $forceBot=true) {
		global $wgUser;
		// next 2 lines taken from maintenance/deleteDefaultMessages.php
		$wgUser = User::newFromName($user);
		if ($forceBot && !in_array('bot',$wgUser->getGroups())) {
			$wgUser->addGroup('bot');
		}
	}

	/**
	 * Mediawiki 1.21 seems to redirect pages differently from 1.12, so we recreate
	 * the 1.12 functionality from "redirect" articles that are present in the DB.
	 */
	public static function onInitializeArticleMaybeRedirect($title, $request, $ignoreRedirect, $target, $article) {
		if ( !$ignoreRedirect && !$target && $article->isRedirect() ) {
			$target = $article->followRedirect();
			if($target instanceof Title)
				$target = $target->getFullURL();
		}
		return true;
	}

	/**
	 * Mediawiki 1.21 doesn't natively redirect immediately if your http Host header
	 * isn't the same as $wgServer. We rely on this functionality so that domain names
	 * like wiki.ehow.com redirect to www.wikihow.com.
	 */
	public static function onBeforeInitialize($title, $unused, $output, $user, $request, $page) {
		global $wgServer, $wgURLprefix;
		$hostname = (string)@$_SERVER['HOSTNAME'];
		$httpHostHeader = (string)$request->getHeader('HOST');
		if ((strpos($hostname, 'app') === 0 || IS_DEV_SITE)
			&& $wgServer != $wgURLprefix . $httpHostHeader
			&& !preg_match("@[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+@", $httpHostHeader) // check not an IP
			&& !IS_SPARE_HOST
			&& !IS_CLOUD_SITE)
		{
			header('HTTP/1.1 301 Moved Permanently');
			header('Location: ' . $wgServer . $_SERVER['REQUEST_URI']);
			exit;
		}
		return true;
	}

	/**
	 * Provide the traditional Special:RecentChanges parameters that should
	 * follow the user around as they are patrolling.
	 */
	public static function getRecentChangesBrowseParams($request, $rc=null) {
		$query = array();
		if ($rc) $query['rcid'] = $rc->getAttribute('rc_id');
		$query += array(
			'namespace' => $request->getVal('namespace', ''),
			'invert' => $request->getInt('invert'),
			'associated' => $request->getInt('associated'),
			'reverse' => $request->getInt('reverse'),
			'redirect' => 'no',
			'fromrc' => 1,
		);
		return $query;
	}

	/**
	 * Add 'reverse' option to traditional Special:RecentChanges page.
	 */
	public static function onSpecialRecentChangesPanel($extraOpts, $opts) {
		global $wgRequest;
		$reverse = $wgRequest->getInt('reverse');
		$labelText = wfMessage('reverseorder')->text();
		$description = 'Check this box to show recent changes in reverse order';
		$extraOpts['reverse'] = array(
			'<input name="reverse" type="checkbox" value="1" id="nsreverse" title="' . $description . '"' . ($reverse ? ' checked=""' : '') . '>', 
			'<label for="nsreverse" title="' . $description . '">' . $labelText . '</label>');
		return true;
	}

	/**
	 * Use 'reverse' option in RecentChanges queries
	 */
	public static function onSpecialRecentChangesQuery($conds, $tables, $join_conds, $opts, $query_options, $fields, $reverse)
	{
		global $wgRequest;
		$reverseOpt = $wgRequest->getInt('reverse');
		if ($reverseOpt == 1) $reverse = 1;
		return true;
	}

	public static function capitalize($str) {
		if(mb_strlen($str) == 0) {
			return($str);
		}

		$fc = mb_substr($str,0,1);
		$fc = mb_strtoupper($fc);
		if(mb_strlen($str) > 1) {
			$ros = mb_substr($str,1);
			return($fc . $ros);
		} else {
			return($fc);
		}
	}

	/**
	 * Decide whether on not to autopatrol an edit
	 */
	public static function onMaybeAutoPatrol($page, $user, &$patrolled) {
		// If this edit was already flagged autopatrol, only
		// keep this flag if the user has the autopatrol preference on
		if ( $patrolled && !$user->getOption('autopatrol') ) {
			$patrolled = false;
		}

		// All edits from users in the bot group are autopatrolled
		if ( in_array('bot', $user->getGroups()) ) {
			$patrolled = true;
		}

		// All edits to User_kudos and User_kudos_talk namespace are autopatrolled
		$pageNamespace = $page->mTitle->getNamespace();
		if ( in_array( $pageNamespace, array(NS_USER_KUDOS, NS_USER_KUDOS_TALK) ) ) {
			$patrolled = true;
		}

		// All edits to User namespace (if editing their own page page) are autopatrolled
		if ($pageNamespace == NS_USER) {
			$userName = $user->getName();
			$pageUser = $page->mTitle->getBaseText();
			if ($userName == $pageUser) {
				$patrolled = true;
			}
		}

		return true;
	}

	public static function onTitleSquidURLs($title, &$urls) {
		global $wgContLang;

		// Do we really need to purge the history of a video page? probably not
		// anons don't care about video histories
		if ($title->getNamespace() != NS_VIDEO) {
			$historyUrl = $title->getInternalURL( 'action=history' );
			if(IS_PROD_EN_SITE) {
				$partialUrl = preg_replace("@^https?://[^/]+/@", "/", $historyUrl);
				$historyUrl = 'http://www.wikihow.com' . $partialUrl;
			}
			$urls[] = $historyUrl;

			// purge variant urls as well
			if ($wgContLang->hasVariants()) {
				$variants = $wgContLang->getVariants();
				foreach ($variants as $vCode) {
					if ($vCode == $wgContLang->getCode()) continue; // we don't want default variant
					$urls[] = $title->getInternalURL('', $vCode);
				}
			}
		}
		return true;
	}

	// Reuben, upgrade 1.21: Special:Mostlinked is expensive, so we make the
	// page contain at most 1000 cached results
	public static function onPopulateWgQueryPages(&$wgQueryPages) {
		foreach ($wgQueryPages as &$page) {
			if ($page[0] == 'MostlinkedPage') {
				$page[2] = 1000;
				break;
			}
		}
		return true;
	}

}
