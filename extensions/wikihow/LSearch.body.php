<?php

/**
 * Our Google Search Appliance special page.  Used to use Lucene search.
 */
class LSearch extends SpecialPage {

	const RESULTS_PER_PAGE = 30;

	const SEARCH_OTHER = 0;
	const SEARCH_LOGGED_IN = 1;
	const SEARCH_MOBILE = 2;
	const SEARCH_APP = 3;
	const SEARCH_RSS = 4;
	const SEARCH_RAW = 5;
	const SEARCH_404 = 6;
	const SEARCH_CATSEARCH = 7;
	const SEARCH_LOGGED_OUT = 8;

	const MAXAGE_SECS = 21600; // 6 hours

	var $mResults = array();
	var $mSpelling = array();
	var $mLast = 0;
	var $mQ = '';
	var $mStart = 0;
	var $logSearch = true;

	public function __construct() {
		parent::__construct('LSearch');
		$this->setListed(false);
	}

	/**
	 * Used to log the search in the site_search_log table, to store this data for 
	 * later analysis.
	 */
	private function logSearch($q, $host_id, $cache, $error, $curl_err, $gm_tm_count, $gm_ts_count, $username, $userid, $rank, $num_results, $gm_type) {
		if ($this->logSearch) {
			$dbw = wfGetDB(DB_MASTER);
			$q = $dbw->strencode($q);
			$username = $dbw->strencode($username);
			$vals = array(
					'ssl_query' 		=> strtolower($q),
					'ssl_host_id' 		=> $host_id,
					'ssl_cache' 		=> $cache,
					'ssl_error' 		=> $error,
					'ssl_curl_error'	=> $curl_err,
					'ssl_ts_count' 		=> $gm_ts_count,
					'ssl_tm_count' 		=> $gm_tm_count,
					'ssl_user'			=> $userid,
					'ssl_user_text' 	=> $username,
					'ssl_num_results'	=> $num_results,
					'ssl_rank'			=> $rank,
					'ssl_type'			=> $gm_type
				);
			$res = $dbw->insert('site_search_log', $vals, __METHOD__);
		}
	}

	/**
	 * A callback used to parse the output of the google search appliance.
	 */
	/**
	 * The public interface into this class used to list a bunch of
	 * titles from the GSA index.
	 */
	public function googleSearchResultTitles($q, $first = 0, $limit = 30, $minrank = 0, $searchType = self::SEARCH_OTHER) {
		$this->googleSearchResults($q, $first, $limit, $searchType);
		$results = array();
		$searchResults = $this->mResults['results'];
		if (!is_array($searchResults)) return $results;
		foreach ($searchResults as $r) {
			$url = str_replace("http://www.wikihow.com/", "", $r['url']);
			$t = Title::newFromURL(urldecode($url));
			if ($t && $t->exists()) $results[] = $t;
		}
		return $results;
	}
	
	private function loggedOutSearch() {
		global $wgRequest, $wgHooks;

		$this->logSearch = false;
		$q = $wgRequest->getVal('search','');
		$this->googleSearchResults($q, $this->mStart, 10, self::SEARCH_LOGGED_OUT);

		$results = array();
		$searchResults = $this->mResults['results'];
		if (!is_array($searchResults)) {
			return $results;
		}
	}

	/**
	 * Query the GSA, return the results in XML.
	 */
	private function googleSearchResults($q, $start, $limit = 30, $gm_type = self::SEARCH_OTHER) {
		global $wgOut, $wgRequest, $wgUser,  $wgMemc, $wgBogusQueries, $IP;

		$key = wfMemcKey("YahooBoss1", str_replace(" ", "-", $q), $start, $limit);
		$set_cache = true;

		$q = trim($q);
		if (in_array(strtolower($q), $wgBogusQueries) ) {
			return null;
		}

		// All yahoo boss searches have host_id of 100
		$host_id = 100;
		$cache = 0;
		$gm_tm_count = 0;
		$res = $wgMemc->get($key);
		if ($res) {
			$contents = $res;
			$set_cache = false;
			$cache = 1;
		} else {
			$cc_key = WH_YAHOO_BOSS_API_KEY;
			$cc_secret = WH_YAHOO_BOSS_API_SECRET; 
			$url = "http://yboss.yahooapis.com/ysearch/web";
			// Request spelling results for logged in search
			if ($gm_type == self::SEARCH_LOGGED_IN || $gm_type == self::SEARCH_LOGGED_OUT) {
				$url .= ",spelling";
			}
			$args = array('q' => $q, 'format' => 'json', 'sites' => 'www.wikihow.com', 'start' => $start, 'count' => $limit);

			// Yahoo boss required OAuth 1.0 authentication
			require_once("$IP/extensions/wikihow/common/oauth/OAuth.php");

			$consumer = new OAuthConsumer($cc_key, $cc_secret);
			$request = OAuthRequest::from_consumer_and_token($consumer, NULL,"GET", $url, $args);
			$request->sign_request(new OAuthSignatureMethod_HMAC_SHA1(), $consumer, NULL);
			$url = sprintf("%s?%s", $url, OAuthUtil::build_http_query($args));
			$ch = curl_init();
			$headers = array($request->to_header());
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
			curl_setopt($ch, CURLOPT_TIMEOUT, 5);
			$rsp = curl_exec($ch);
			$contents = null;

			$gm_tm_count = curl_getinfo($ch, CURLINFO_TOTAL_TIME);
			$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			if ($http_code != 200 || curl_errno($ch)) {
				$wgOut->addHTML(
					"Sorry! An error was experienced while processing your search. Try refreshing the page or click <a href=\"{$wgRequest->getRequestURL()}\">here</a> to try again. " );

				//self::logSearch($q, $host_id, 0, 1, curl_errno($ch), $gm_tm_count, 0, $wgUser->getName(), $wgUser->getID(), 0, 0, $gm_type);
				curl_close($ch);
				return null;
			} else {
				$contents = json_decode($rsp, true);
				curl_close($ch);
			}
		}

		$num_results = $contents['totalresults'] ? $contents['totalresults'] : 0;
		//self::logSearch($q, $host_id, $cache, 0, 0, $gm_tm_count, 0, $wgUser->getName(), $wgUser->getId(), 0, $num_results, $gm_type);

		if ($set_cache) {
			$wgMemc->set($key, $contents, 3600); // 1 hour
		}

		if ($gm_type == self::SEARCH_LOGGED_IN || $gm_type == self::SEARCH_LOGGED_OUT) {
			$this->mSpelling = $contents['bossresponse']['spelling'];
		}
		$this->mResults = $contents['bossresponse']['web'];
		$this->mLast = $this->mStart + $this->mResults['count'];

		return $contents;
	}

	private function cleanTitle(&$t) {
		// remove detailed title from search results
		$t = str_replace(" - wikiHow", "", $t);
		$t = str_replace(" - wiki How", "", $t);
		$t = str_replace(" - wikihow", "", $t);
		$t = preg_replace("@ \(with[^\.]+[\.]*@", "", $t);
		$t = preg_replace("/\:(.*?)steps/", "", $t);
		$t = str_replace(' - how to articles from wikiHow', '', $t);
	}

	private function localizeUrl(&$url) {
		return preg_replace('@^http://([^/]+\.)?wikihow\.com/@', '', $url);
		
	}
	
	/**
	 * Trim all the "- wikiHow" etc off the back of the titles from GSA.
	 * Make sure the titles can be turned into a MediaWiki Title object.
	 */
	public function makeTitlesUniform($gsaResults) {
		$results = array();
		foreach($gsaResults as $r) {
			$t = htmlspecialchars_decode($r['title']);
			$this->cleanTitle($t);

			$url = $this->localizeUrl($r['url']);
			$tobj = Title::newFromURL(urldecode($url));
			if (!$tobj || !$tobj->exists()) continue;
			$key = $tobj->getDBkey();

			$results[] = array(
				'title_match' => $t,
				'url' => $url,
				'key' => $key,
				'id' => $tobj->getArticleId()
			);
		}
		return $results;
	}

	public function cleanLoggedOutResults(&$rawResults) {
		$results = array();
		foreach ($rawResults as $r) {
			$t = htmlspecialchars_decode($r['title']);
			$this->cleanTitle($t);
			if (stripos($r['url'], 'Category:')) {
				$t = "Category: " . $t;
			}

			$url = $this->localizeUrl($r['url']);
			$abstract = preg_replace("@ ([\.]+)$@", "$1", $r['abstract']);
			$results[] = array('title_match' => $t, 'url' => $url, 'abstract' => $abstract, 'dispurl' => $r['dispurl']);
		}
		return $results;
	}

	/**
	 * Add our own meta data to the search results to make them more
	 * interesting and informative to look at.
	 */
	public function supplementResults($titles) {
		global $wgMemc;
		$enc_q = urlencode($this->mQ);
		$cachekey = wfMemcKey('supp', $this->mStart, $enc_q);
		$results = $wgMemc->get($cachekey);
		if (!$results) {
			$results = array();

			$ids = array();
			foreach ($titles as $title) {
				$ids[] = $title['id'];
			}

			if (count($ids) == 0) {
				return $results;
			}

			$dbr = wfGetDB(DB_SLAVE);
			$sql = 'SELECT * FROM search_results WHERE sr_id IN (' . $dbr->makeList($ids) . ')';
			$res = $dbr->query($sql);
			$rows = array();
			while ( $row = $dbr->fetchRow( $res ) ) {
				$rows[ $row['sr_title'] ] = $row;
			}

			foreach ($titles as $title) {
				$key = $title['key'];
				$hasSupplement = isset($rows[$key]);
				if ($hasSupplement) {
					foreach ($rows[$key] as $k => $v) {
						if (preg_match('@^sr_@', $k)) {
							$k = preg_replace('@^sr_@', '', $k);
							$title[$k] = $v;
						}
					}
				}
				$title['has_supplement'] = intval($hasSupplement);
				$isCategory = $title['namespace'] == NS_CATEGORY;
				$title['is_category'] = intval($isCategory);
				$results[] = $title;
			}

			$wgMemc->set($cachekey, $results);
		}
		return $results;
	}

	private function getLoggedOutSearchHtml() {
		global $wgOut, $wgUser, $wgRequest;

		wfLoadExtensionMessages('LSearch');


		$this->mQ = $wgRequest->getVal('search', '');
		$enc_q = htmlspecialchars($this->mQ);
		$me = "/wikiHowTo";
		$sk = $wgUser->getSkin();
		$suggestionLink = $this->getSpellingSuggestion($me);
		$results = $this->mResults['results'] ? $this->mResults['results'] : array();
		$results = $this->cleanLoggedOutResults($results);

		$mw = Title::makeTitle(NS_SPECIAL, "Search");
		$specialPageURL = $mw->getFullURL();

		$total = $this->mResults['totalresults'];

		$vars = array(
			'q' => $this->mQ,
			'enc_q' => $enc_q,
			'sk' => $sk,
			'me' => $me,
			'max_results' => 10,
			'start' => $this->mStart,
			'first' => $this->mStart + 1,
			'last' => $this->mLast,
			'suggestionLink' => $suggestionLink,
			'results' => $results,
			'specialPageURL' => $specialPageURL,
			'total' => $total,
			'BASE_URL' => $wgServer,
		);
		
		EasyTemplate::set_path(dirname(__FILE__).'/');
		$html = EasyTemplate::html('search-results-lo.tmpl.php', $vars);
		return $html;
	}

	private function setMaxAgeHeaders($maxAgeSecs = self::MAXAGE_SECS) {
		global $wgOut, $wgRequest;

        $wgOut->setSquidMaxage( $maxAgeSecs );
        $wgRequest->response()->header( 'Cache-Control: s-maxage=' . $maxAgeSecs . ', must-revalidate, max-age=' . $maxAgeSecs );
        $future = time() + $maxAgeSecs;
        $wgRequest->response()->header( 'Expires: ' . gmdate('D, d M Y H:i:s T', $future) );

        //$wgOut->setArticleBodyOnly(true);
        $wgOut->sendCacheControl();
	}

	/**
	 * Special:LSearch page entry point
	 */
	public function execute($par = '') {
		global $wgUser, $wgOut, $wgScriptPath, $wgRequest, $wgServer;
		global $wgLanguageCode, $wgUseLucene, $IP, $wgHooks;

		// Added this hack to test whether we can stop some usertype:logged(in|out)
		// queries can be removed from index. Remove this code eventually, say 6 mos.
		// from now. Added by Reuben originally on July 30, 2012.
		$queryString = @$_SERVER['REQUEST_URI'];
		if (strpos($queryString, 'usertype') !== false) {
			header('HTTP/1.0 404 Not Found');
			print "Page not found";
			exit;
		}

		$wgHooks['ShowBreadCrumbs'][] = array($this, 'removeBreadCrumbsCallback');
		//$wgHooks['WrapBodyWithArticleInner'][] = array($this, 'wrapBodyWithArticleInner');

		$this->mStart = $wgRequest->getVal('start', 0);
		$this->mQ = $wgRequest->getVal('search');

		// special case search term filtering
		if (strtolower($this->mQ) == 'sat') { // searching for SAT, not sitting
			$this->mQ = "\"SAT\"";
		}

		$enc_q = htmlspecialchars($this->mQ);
		$wgOut->setRobotPolicy( 'noindex,nofollow' );

		// Logged out search test
		if ($wgRequest->getVal('lo', 0)) {
		 	$wgHooks['ShowSideBar'][] = array($this, 'removeSideBarCallback');
			// We want to cache results of searches for 6 hours at the Varnish level
			// since logged out search receives a high volume of queries
			$wgHooks['AllowMaxageHeaders'][] = array($this, 'allowMaxageHeadersCallback');
			$this->setMaxageHeaders();

			$this->loggedOutSearch();

			$wgOut->setHTMLTitle(wfMsg('lsearch_title_q', $enc_q));
 			$wgOut->addStyle(wfGetPad('/extensions/min/?f=/skins/owl/searchresults.css&' . WH_SITEREV));
			$wgOut->addHtml($this->getLoggedOutSearchHtml());
			return;
		}


		if ($wgLanguageCode != 'en' || !$wgUseLucene) {
			require_once("$IP/includes/SpecialSearch.php");
			wfSpecialSearch();
			return;
		}
		if ($wgRequest->getVal('rss') == 1) {
			$results = $this->googleSearchResultTitles($wgRequest->getVal('search'), $this->mStart, self::RESULTS_PER_PAGE, 0, self::SEARCH_RSS);
			$wgOut->disable();
			$pad = "           ";
			header("Content-type: text/xml;");
			echo '<GSP VER="3.2">
<TM>0.083190</TM>
<Q>' . htmlspecialchars($q) . '</Q>
<PARAM name="filter" value="0" original_value="0"/>
<PARAM name="num" value="16" original_value="30"/>
<PARAM name="access" value="p" original_value="p"/>
<PARAM name="entqr" value="0" original_value="0"/>
<PARAM name="start" value="0" original_value="0"/>
<PARAM name="output" value="xml" original_value="xml"/>
<PARAM name="sort" value="date:D:L:d1" original_value="date%3AD%3AL%3Ad1"/>
<PARAM name="site" value="main_en" original_value="main_en"/>
<PARAM name="ie" value="UTF-8" original_value="UTF-8"/>
<PARAM name="client" value="internal_frontend" original_value="internal_frontend"/>
<PARAM name="q" value="' . htmlspecialchars($q) . '" original_value="' . htmlspecialchars($q) . '"/>
<PARAM name="ip" value="192.168.100.100" original_value="192.168.100.100"/>
<RES SN="1" EN="' . sizeof($results) . '">
<M>' . sizeof($results) . '</M>
<XT/>';
			$count = 1;
			foreach ($results as $r) {
				echo "<R N=\"{$count}\">
					<U>{$r->getFullURL()}</U>
					<UE>{$r->getFullURL()}</UE>
					<T>How to " . htmlspecialchars($r->getFullText()) . "{$pad}</T>
					<RK>10</RK>
					<HAS></HAS>
					<LANG>en</LANG>
			</R>";
				$count++;
			}
echo "</RES>
</GSP>";

			return;
		}

		//show the gray article image at the bottom
		if (class_exists('WikihowCSSDisplay'))
			WikihowCSSDisplay::setSpecialBackground(true);

		$fname = "wfSpecialSearch";
		$me = Title::makeTitle(NS_SPECIAL, "LSearch");
		$sk = $wgUser->getSkin();

		if ($wgRequest->getVal('raw') == true) {
			$contents = $this->googleSearchResultTitles($this->mQ, $this->mStart, self::RESULTS_PER_PAGE, 0, self::SEARCH_RAW);
			header("Content-type: text/plain");
			$wgOut->disable(true);
			foreach($contents as $t) {
				echo "{$t->getFullURL()}\n";
			}
			return;
		}

		if ($wgRequest->getVal('mobile') == true) {
			$this->mobileSearch($this->mQ, $this->mStart, $wgRequest->getVal('limit', 20));
			return;
		}

		// Logged in search is only for logged in users
		/*if ($wgUser->isAnon()) {
			$wgOut->setRobotpolicy('noindex,nofollow');
			$wgOut->showErrorPage('nosuchspecialpage', 'nospecialpagetext');
			return;
		}*/

		$contents = $this->googleSearchResults($this->mQ, $this->mStart, self::RESULTS_PER_PAGE, self::SEARCH_LOGGED_IN);
		if ($contents == null) return;

		wfLoadExtensionMessages('LSearch');

		$wgOut->setHTMLTitle(wfMsg('lsearch_title_q', $enc_q));

		$me = Title::makeTitle(NS_SPECIAL, "LSearch");
		$sk = $wgUser->getSkin();
		$suggestionLink = $this->getSpellingSuggestion($me->getPartialUrl());
		$results = $this->mResults['results'] ? $this->mResults['results'] : array();
		$results = $this->makeTitlesUniform($results);
		$results = $this->supplementResults($results);

		$mw = Title::makeTitle(NS_SPECIAL, "Search");
		$specialPageURL = $mw->getFullURL();

		$total = $this->mResults['totalresults'];
		$start = $this->mStart;
		$last = $this->mLast;
		$max_results = self::RESULTS_PER_PAGE;
		$q = $this->mQ;
		
		//buttons
		// - next
		$disabled = !($total > $start + $max_results && $last == $start + $max_results);
		$next_url = '/'.$me.'?search=' . urlencode($q) . '&start=' . ($start + $max_results);
		$next_button = '<a href="'.$next_url.'" class="button buttonright '.($disabled ? 'disabled' : '') .'" '.($disabled ? 'onClick="return false;"' : '').'>'.wfMsg("lsearch_next").'</a>';
		// - previous
		$disabled = !($start - $max_results >= 0);
		$prev_url = '/'.$me.'?search=' . urlencode($q) . ($start - $max_results !== 0 ? '&start=' . ($start - $max_results) : '');
		$prev_button = '<a href="'.$prev_url.'" class="button buttonleft '.($disabled ? 'disabled' : '') .'" '.($disabled ? 'onClick="return false;"' : '').'>'.wfMsg("lsearch_previous").'</a>';
		
		$vars = array(
			'q' => $q,
			'enc_q' => $enc_q,
			'sk' => $sk,
			'me' => $me,
			'max_results' => $max_results,
			'start' => $start,
			'first' => $start + 1,
			'last' => $last,
			'suggestionLink' => $suggestionLink,
			'results' => $results,
			'specialPageURL' => $specialPageURL,
			'total' => $total,
			'BASE_URL' => $wgServer,
			'next_button' => $next_button,
			'prev_button' => $prev_button,
		);
		
		EasyTemplate::set_path(dirname(__FILE__).'/');
		$html = EasyTemplate::html('search-results.tmpl.php', $vars);

		$wgOut->addCSSCode('src');
		$wgOut->addHTML($html);
	}


	private function getSpellingSuggestion($url) {
		global $wgUser;
		$sk = $wgUser->getSkin();

		$spellingResults = $this->mSpelling;
		$suggestionLink = null;
		if ($spellingResults['count'] > 0) {
			$suggestion = $spellingResults['results'][0]['suggestion'];
			$suggestionUrl = "$me?search=" . urlencode($suggestion);
			// A hack for logged out search test
			if (stripos($url, "wikiHowTo")) {
				$suggestionUrl .= "&lo=1";
			}
			$suggestionLink = "<a href='$suggestionUrl'>$suggestion</a>";
		}
		return $suggestionLink;
	}

	/*
	 * Return a json array of articles that includes the title, full url and abbreviated intro text
	 */
	public function mobileSearch($q, $start, $limit = 20) {
			global $wgOut, $wgMemc;

			// Don't return more than 50 search results at a time to prevent abuse
			if ($limit > 50) {
				$limit = 50;
			}

			$key = wfMemcKey("MobileSearch", str_replace(" ", "-", $q), $start, $limit);
			if ($val = $wgMemc->get($key)) {
				return $val;
			}

			$contents = $this->googleSearchResultTitles($q, $start, $limit, 0, self::SEARCH_MOBILE);
			$results = array();
			foreach ($contents as $t) {
				// Only return articles
				if($t->getNamespace() != NS_MAIN) {
					continue;
				}

				$result = array();
				$result['title'] = $t->getText();
				$result['url'] = $t->getFullURL();
				$result['imgurl'] = wfGetPad(SkinWikihowskin::getGalleryImage($t, 103, 80));
				$result['intro'] = null;
				if($r = Revision::newFromid($t->getLatestRevID())) {
					$intro = Wikitext::getIntro($r->getText());
					$intro = trim(Wikitext::flatten($intro));
					$result['intro'] = substr($intro, 0, 180);
					// Put an ellipsis on the end
					$len = strlen($result['intro']);
					$result['intro'] .= substr($result['intro'], $len - 1, $len) == '.' ? ".." : "...";
				}
				if(!is_null($result['intro'])) {
					$results[] = array('article' => $result);
				}
			}

			$searchResults['results'] = $results;
			$json = json_encode($searchResults);
			$wgMemc->set($key, $json, 3600); // 1 hour

			header("Content-type: application/json");
			$wgOut->disable(true);
			echo $json;
	}

	/**
	 * A Mediawiki callback set in contructor of this class to stop the display
	 * of breadcrumbs at the top of the page.
	 */
	public static function removeBreadCrumbsCallback(&$showBreadCrum) {
		$showBreadCrum = false;
		return true;
	}

	/**
	 * Define a Mediawiki callback to make it so that the body doesn't
	 * get wrapped with <div class="article_inner"></div> ...
	 */
	/*public static function wrapBodyWithArticleInner() {
		return false;
	}*/

	public static function allowMaxageHeadersCallback() {
		return false;
	}

	public static function removeSideBarCallback(&$showSideBar) {
		$showSideBar = false;
		return true;
	}
}

