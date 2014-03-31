<?php

if (!defined('MEDIAWIKI')) die();

/*
 * database schema:
CREATE TABLE hillary_pages (
	hp_pageid int(8) unsigned NOT NULL,
	hp_added timestamp NOT NULL default current_timestamp,
	hp_pos_votes int(8) unsigned NOT NULL default 0,
	hp_neg_votes int(8) unsigned NOT NULL default 0,
	hp_last_voted timestamp NOT NULL default 0,
	hp_action varchar(4) NOT NULL default '',
	PRIMARY KEY(hp_pageid),
	INDEX(hp_last_voted)
);

CREATE TABLE hillary_votes (
	hv_pageid int(8) unsigned NOT NULL,
	hv_userid int(8) NOT NULL,
	hv_vote int(8) NOT NULL,
	hv_added timestamp NOT NULL default current_timestamp,
	PRIMARY KEY(hv_pageid, hv_userid),
	INDEX(hv_userid)
);

CREATE TABLE hillary_votes_archive (
	hv_pageid int(8) unsigned NOT NULL,
	hv_userid int(8) NOT NULL,
	hv_vote int(8) unsigned NOT NULL,
	hv_added timestamp NOT NULL default current_timestamp
);
 *
 */

class HillaryRest extends UnlistedSpecialPage {

	const COOKIE_NAME = 'Hill';

	var $hillary;

	public function __construct() {
		global $wgTitle;
		$this->specialPage = $wgTitle->getPartialUrl();
		parent::__construct($this->specialPage);
	}

	public function execute($par) {
		global $wgOut, $wgRequest, $wgUser;
		global $wgCookiePrefix, $wgCookiePath, $wgCookieDomain, $wgCookieSecure;

		// get user ID
		$userid = $wgUser->getID();

		// if user is anon, assign them a cookie and give them
		// a random, negative user ID
		if ($userid <= 0) {
			$cookieName = $wgCookiePrefix . self::COOKIE_NAME;
			$cookieVal = intval( @$_COOKIE[ $cookieName ] );
			if ($cookieVal <= 0) {
				$newid = mt_rand(1, mt_getrandmax());
				$thirty_days = 90 * 24 * 60 * 60;
				setcookie($cookieName, $newid, time() + $thirty_days,
					$wgCookiePath, $wgCookieDomain, $wgCookieSecure);
				$userid = -$newid;
			} else {
				$userid = -$cookieVal;
			}
		}

		$action = $wgRequest->getVal('action');
		if ($action) {
			$pageid = $wgRequest->getInt('pageid');
			if (!$pageid) exit;
			$hillary = new Hillary($userid, $pageid);

			if ($action == 'fetch') {
				$result = $hillary->getPageDetails();
				if ($result) {
					$result['pageid'] = $pageid;
				} else {
					$result = array('pageid' => 0);
				}

				// find next article to show
				list($next_id, $next_url) = $hillary->getNextPage();
				$result['next_id'] = $next_id;
				$result['next_url'] = $next_url;
			} elseif ($action == 'vote') {
				$vote = $wgRequest->getInt('vote');
				$voted = $hillary->vote($vote);
				$result['voted'] = intval($voted);
			}

			$wgOut->setArticleBodyOnly(true);
			$json = json_encode($result);
			$wgOut->addHTML($json);
		} else {
			// If user has never voted with this tool before, show them
			// a splash page
			$hillary = new Hillary($userid);
			$votes = $hillary->getUserVotes();
			list($next_id, $next_url) = $hillary->getNextPage();

			$utmParamsStr = '';
			if (is_array($_GET)) {
				$utmParams = array();
				foreach ($_GET as $param => $val) {
					if (preg_match('@^utm@', $param)) {
						$utmParams[] = $param . '=' . $val;
					}
				}
				if ($utmParams) {
					$utmParamsStr = '?' . join('&', $utmParams);
				}
			}

			$toolURL = MobileWikihow::getMobileSite() . '/' . $next_url . $utmParamsStr . '#review';

			if (!$votes) {
				$wgOut->setArticleBodyOnly(true);
				$html = $hillary->getSplashPage($toolURL);
				$wgOut->addHTML($html);
			} else {
				$wgOut->redirect($toolURL);
			}
		}
	}

}

class Hillary {

	const VOTE_THRESHOLD = 3;

	const PAGE_SIZE = 1000;

	const USER_VOTES_KEY = 'hi_usrv';
	const USER_VOTES_CACHE_EXPIRY = 3600; // one hour
	const PAGES_KEY = 'hi_pgs';
	const PAGE_CACHE_EXPIRY = 600; // 10 minutes

	var $userid, $pageid, $userVotes;

	public function __construct($userid, $pageid = 0) {
		$this->userid = intval($userid);
		$this->pageid = intval($pageid);
		$this->userVotes = null;
	}

	public static function getContainer() {
		$vars = array();
		$tmpl = new EasyTemplate( dirname(__FILE__) );
		$tmpl->set_vars($vars);
		return $tmpl->execute('container.tmpl.php');
	}

	public static function getSplashPage($firstURL) {
		$vars = array('firstURL' => $firstURL);
		$tmpl = new EasyTemplate( dirname(__FILE__) );
		$tmpl->set_vars($vars);
		return $tmpl->execute('splash.tmpl.php');
	}

	// callback on deleting an article
	public static function onDelete($wikiPage) {
		if ($wikiPage) {
			$pageid = $wikiPage->getId();
			$db = wfGetDB(DB_MASTER);
			// delete the hillary_pages entry, but keep the hillary_votes entries
			$db->delete('hillary_pages', array('hp_pageid' => $pageid), __METHOD__);
		}
		return true;
	}

	private function getDB() {
		static $db = null;
		if (!$db) {
			$db = wfGetDB(DB_SLAVE);
		}
		return $db;
	}

	public function getPageDetails() {
		$db = $this->getDB();

		// get page details
		$row = $db->selectRow('hillary_pages',
			array('hp_neg_votes', 'hp_pos_votes', 'hp_action'),
			array('hp_pageid' => $this->pageid),
			__METHOD__);
		if (!$row) return null;
		
		// make sure page exists
		$title = Title::newFromID($this->pageid);
		if (!$title || !$title->exists()) return null;

		// make sure there are no excluding templates already
		if (self::hasSkipTemplates($this->pageid)) return null;

		// build list of others who voted
		//$voters = $this->buildVoters();
		
		return array(
			'pos' => intval($row->hp_pos_votes),
			'neg' => intval($row->hp_neg_votes),
			'action' => $row->hp_action,
			//'voters' => $voters,
		);
	}

	// Don't show articles that have certain templates
	private function hasSkipTemplates($pageid) {
		$templates = array('Stub', 'Copyvio', 'Copyviobot', 'Inuse', 'Nfd');
		$templateMap = array();
		foreach ($templates as $template) {
			$key = ucfirst( strtolower($template) );
			$templateMap[ $key ] = true;
		}

		$db = $this->getDB();
		$res = $db->select('templatelinks', 'tl_title', 
			array('tl_from' => $pageid), __METHOD__);
		foreach ($res as $row) {
			if ( isset( $templateMap[ $row->tl_title ] ) ) {
				return true;
			}
		}
		return false;
	}

	public function getNextPage() {
		$db = null;
		$userVotes = $this->getUserVotes();
		if (!is_array($userVotes)) {
			$userVotes = array();
		}
		$bundle = $this->buildNextPagesCache();
		if (is_array($bundle)) {
			foreach ($bundle as $pageid) {
				if ($pageid == $this->pageid) continue;
				if (!isset($userVotes[$pageid])) {
					if (!$db) $db = $this->getDB();
					$page_title = $db->selectField('page',
						'page_title',
						array('page_id' => $pageid,
							'page_is_redirect' => 0,
							'page_namespace' => NS_MAIN,
						),
						__METHOD__);
					if ($page_title) {
						$title = Title::newFromDBkey($page_title);
						if ($title
							&& $title->exists()
							&& ! self::hasSkipTemplates($pageid))
						{
							return array($pageid, $title->getPartialURL());
						}
					}
				}
			}
		}
		return array(0, '');
	}

	private function buildNextPagesCache() {
		global $wgMemc;
		$cacheKey = wfMemcKey(self::PAGES_KEY);
		$bundle = $wgMemc->get($cacheKey);
		if (!is_array($bundle)) {
			$db = $this->getDB();
			$res = $db->select('hillary_pages',
				array('hp_pageid', 'hp_pos_votes', 'hp_neg_votes'),
				array("hp_action = ''"),
				__METHOD__,
				array('LIMIT' => self::PAGE_SIZE,
					'ORDER BY' => 'hp_neg_votes, hp_pos_votes, hp_last_voted, hp_pageid DESC'));
			$bundle = array();
			foreach ($res as $row) {
				$bundle[] = intval($row->hp_pageid);
			}
			$wgMemc->set($cacheKey, $bundle, self::PAGE_CACHE_EXPIRY);
		}
		return $bundle;
	}

	public function getUserVotes() {
		global $wgMemc;

		if (is_array($this->userVotes)) return $this->userVotes;

		$cacheKey = wfMemcKey(self::USER_VOTES_KEY, $this->userid);
		$pages = $wgMemc->get($cacheKey);
		if (!is_array($pages)) {
			$pages = array();
			$db = $this->getDB();
			$res = $db->select('hillary_votes',
				array('hv_pageid', 'hv_vote'),
				array('hv_userid' => intval($this->userid)),
				__METHOD__);
			foreach ($res as $row) {
				$pages[ $row->hv_pageid ] = $row->hv_vote;
			}
			$wgMemc->set($cacheKey, $pages, self::USER_VOTES_CACHE_EXPIRY);
		}

		$this->userVotes = $pages;
		return $this->userVotes;
	}

	public function vote($vote) {
		global $wgMemc, $wgUser;

		$db = wfGetDB(DB_MASTER);

		$vote = intval($vote);

		// pull all data for this page from the hillary_page
		$posVotes = 0; $negVotes = 0; $ownVote = false;
		$res = $db->select('hillary_votes',
			array('hv_vote', 'hv_userid'),
			array('hv_pageid' => $this->pageid),
			__METHOD__);
		foreach ($res as $row) {
			// don't count a user's own votes so that the counts are accurate
			if ($row->hv_userid == $this->userid) {
				// do nothing and exit if their vote hasn't changed
				if ($row->hv_vote == $vote) return true;
				$ownVote = true;
			} else {
				if ($row->hv_vote > 0) $posVotes++;
				if ($row->hv_vote < 0) $negVotes++;
			}
		}

		// Add new vote vs changing their existing vote
		if (!$ownVote) {
			$row = array(
				'hv_pageid' => $this->pageid,
				'hv_userid' => $this->userid,
				'hv_vote' => $vote);
			$db->insert('hillary_votes', $row, __METHOD__);
		} else {
			// just update the vote if it's a re-vote
			$db->update('hillary_votes', 
				array('hv_vote' => $vote),
				array('hv_pageid' => $this->pageid,
					'hv_userid' => $this->userid),
				__METHOD__);
		}

		if ($vote > 0) $posVotes++;
		if ($vote < 0) $negVotes++;
		// if there are greater than n votes for keep or delete, we
		// take an action
		$enoughPos = !$page->hp_action && $posVotes >= self::VOTE_THRESHOLD;
		$enoughNeg = !$page->hp_action && $negVotes >= self::VOTE_THRESHOLD;

		$oldAction = $db->selectField('hillary_pages',
			array('hp_action'),
			array('hp_pageid' => $this->pageid),
			__METHOD__);
		$action = $oldAction;
		if (!$oldAction) {
			if ($enoughPos) {
				$action = 'keep';
			} elseif ($enoughNeg) {
				$action = 'stub';
			}
		}

		// update hillary_pages
		$db->update('hillary_pages', 
			array('hp_pos_votes' => $posVotes,
				'hp_neg_votes' => $negVotes,
				'hp_last_voted = CURRENT_TIMESTAMP',
				'hp_action' => $action),
			array('hp_pageid' => $this->pageid),
			__METHOD__);

		// do an action if we've received enough votes
		if ($action && !$oldAction) {
			$details = $db->selectRow('page', 
				array('page_title', 'page_counter'),
				array('page_namespace' => NS_MAIN,
					'page_id' => $this->pageid),
				__METHOD__);
			if ($details) {
				$title = Title::newFromDBkey( $details->page_title );
				$views = $details->page_counter;
			} else {
				$title = null;
			}

			if ($title) {

				// Anons can't log in the MW logging infrastructure, so we
				// use the AnonLogBot
				$oldUser = null;
				if (!$wgUser || $wgUser->isAnon()) {
					$oldUser = $wgUser;
					$wgUser = User::newFromName('AnonLogBot');
				}

				// Log action to Mediawiki logging system
				$titleStr = $title->getText();
				$viewsStr = number_format($views);
				$verb = $action == 'keep' ? 'kept' : 'stubbed';
				$posStr = $posVotes == 1 ? '1 person' : "$posVotes people";
				$negStr = $negVotes == 1 ? '1 person' : "$negVotes people";
				$msg = "[[$titleStr]] has been $verb. $negStr voted for the stubbing. $posStr voted keeping as is. It had $viewsStr views at the time of this action.";
				$log = new LogPage('hillary', false);
				$log->addEntry($action, $title, $msg);

				// Add the stub template to wikitext
				if ($action == 'stub') {
					$revision = Revision::newFromTitle($title);
					$article = new Article($title);
					if ($revision && $article) {
						$text = $revision->getText();
						if (!preg_match('@\{\{Stub@i', $text)) {
							$text = "{{Stub}}\n" . $text;
							$article->doEdit($text, "Marking article with stub template after the votes went that way");
						}
					}
				}

				// If anon right now, make it so that further actions are not 
				// under Bot account 
				if ($oldUser) {
					$wgUser = $oldUser;
				}
			}

			// Run a hook
			wfRunHooks('HillaryAfterAction', array(&$this, $action));
		}

		// Fix memcache objects
		$cacheKey = wfMemcKey(self::USER_VOTES_KEY, $this->userid);
		$pages = $wgMemc->get($cacheKey);
		if (is_array($pages)) {
			$this->userVotes = null;
			$pages[ $this->pageid ] = $vote;
			$wgMemc->set($cacheKey, $pages, self::USER_VOTES_CACHE_EXPIRY);
		}

		if ($action) {
			$cacheKey = wfMemcKey(self::PAGES_KEY);
			$bundle = $wgMemc->get($cacheKey);
			if (is_array($bundle)) {
				foreach ($bundle as $i => $pageid) {
					if ($this->pageid == $pageid) {
						unset($bundle[$i]);
						$wgMemc->set($cacheKey, $bundle, self::PAGE_CACHE_EXPIRY);
						break;
					}
				}
			}
		}

		return true;
	}

	// Maintenance task: call this method periodically. It runs for 
	// a while though since there's a JOIN.
	private function archiveVotesTable() {
		$db = wfGetDB(DB_MASTER);
		$res = $db->select(array('hillary_pages hp', 'hillary_votes hv'),
			array('hv.*'),
			array('hp_pageid = hv_pageid',
				"hp_action <> ''",
				'hp_last_voted < FROM_UNIXTIME(' . strtotime('one week ago') . ')'),
			__METHOD__);
		$affected = array();
		foreach ($res as $row) {
			$db->insert('hillary_votes_archive',
				(array)$row,
				__METHOD__);
			$affected[ $row->hv_pageid ] = true;
		}
		foreach (array_keys($affected) as $pageid) {
			$db->delete('hillary_votes', array('hv_pageid' => $pageid), __METHOD__);
		}
	}

	// Used temporarily when we didn't have the data source from Chris
	/*public static function populatePagesUnnabbed() {
		$db = wfGetDB(DB_SLAVE);
		$res = $db->select(array('newarticlepatrol', 'page'),
			array('nap_page'),
			array('nap_page = page_id',
				'page_is_redirect' => 0,
				'page_namespace' => NS_MAIN,
				'nap_patrolled' => 0),
			__METHOD__);

		$ids = array();
		foreach ($res as $row) {
			$pageid = intval($row->nap_page);
			if ($pageid) $ids[] = $pageid;
		}

		self::populatePages($ids);
	}*/

	// Maintenance task: call this to populate the table
	public static function populatePages($ids) {
		$db = wfGetDB(DB_SLAVE);

		// pull all existing hillary pages
		$exists = array();
		$res = $db->select('hillary_pages', array('hp_pageid'), null, __METHOD__);
		foreach ($res as $row) {
			$pageid = intval($row->hp_pageid);
			$exists[$pageid] = 1;
		}

		// check if pages are already in hillary
		$add = array();
		foreach ($ids as $pageid) {
			if (!isset($exists[$pageid])) {
				$add[$pageid] = 1;
			}
		}

		// add any new pages that aren't in hillary
		$added = 0;
		if ($add) {
			$db = wfGetDB(DB_MASTER);

			$pages = array_keys($add);
			foreach ($pages as $pageid) {
				$db->insert('hillary_pages', array('hp_pageid' => $pageid), __METHOD__);
				$added++;
			}
		}
		return $added;
	}

}

class AdminHillary extends UnlistedSpecialPage {

	var $hillary;

	public function __construct() {
		global $wgTitle;
		$this->specialPage = $wgTitle->getPartialUrl();
		parent::__construct($this->specialPage);
	}

	public function execute() {
		global $wgOut, $wgUser;

		// Only allow staff to this page
		$wgOut->setRobotpolicy('noindex,nofollow');
		$userGroups = $wgUser->getGroups();
		if ($wgUser->isBlocked() || !in_array('staff', $userGroups)) {
			$wgOut->showErrorPage('nosuchspecialpage', 'nospecialpagetext');
			return;
		}

		$wgOut->setPageTitle('Rate our Articles Admin Tool');
		$wgOut->addHTML(<<<EOHTML
		<style>
		.hillary-table * tr:nth-child(even) {background: #EEE}
		.hillary-table * tr:nth-child(odd) {background: #FFF}
		h3 {font-size: 14px}
		</style>
EOHTML
);

		$articles = $this->getActionArticles();
		$rows = '';
		$countActions = $this->getCountActions();
		$countProgress = $this->getCountProgress();
		$countVotes = $this->getCountVotes();
		$totalVotes = $countVotes['pos'] + $countVotes['neg'];
		$wgOut->addHTML(<<<EOHTML
<h3>Actions Stats</h3>
<p>
	<i>keep</i>: {$countActions['keep']} <i>stub</i>: {$countActions['stub']} <br/>
	<i>in progress</i>: {$countProgress['progress']} <i>without votes</i>: {$countProgress['novotes']}<br/>
	<i>total votes</i>: {$totalVotes} <i>negative votes</i>: {$countVotes['neg']}<br/>
</p>
<br/>
EOHTML
);

		foreach ($articles as $ar) {
			$action = $ar['action'] == 'stub' ? '<b>STUB</b>' : '<i>keep</i>';
			$rows .= <<<EOHTML
<tr>
<td><a href="{$ar['url']}" target="_blank">{$ar['title']}</a></td>
<td>{$action}</td>
<td>{$ar['votes']}</td>
<td>{$ar['last_voted']}</td>
</tr>
EOHTML;
		}
		$html = <<<EOHTML
		<h3>Actions Taken</h3>
		<table width="100%" class="hillary-table">
			<tr><th>Title</th><th>Action</th><th>Votes</th><th>Last Vote</th></tr>
			{$rows}
		</table>
EOHTML;
		$wgOut->addHTML($html);

		$articles = $this->getProgressArticles();
		$rows = '';
		foreach ($articles as $ar) {
			$rows .= <<<EOHTML
<tr>
<td><a href="{$ar['url']}" target="_blank">{$ar['title']}</a></td>
<td>{$ar['votes']}</td>
<td>{$ar['last_voted']}</td>
</tr>
EOHTML;
		}
		$html = <<<EOHTML
		<br>
		<br>
		<h3>Still in Voting</h3>
		<table width="100%" class="hillary-table">
			<tr><th>Title</th><th>Votes</th><th>Last Vote</th></tr>
			{$rows}
		</table>
EOHTML;
		$wgOut->addHTML($html);
	}

	private function getCountActions() {
		$db = wfGetDB(DB_SLAVE);
		$res = $db->select('hillary_pages',
			array('hp_action', 'count(*) AS count'),
			array("hp_action <> ''"),
			__METHOD__,
			array('GROUP BY' => 'hp_action'));
		$output = array();
		foreach ($res as $row) {
			$output[ $row->hp_action ] = $row->count;
		}
		return $output;
	}

	private function getActionArticles() {
		$db = wfGetDB(DB_SLAVE);
		$res = $db->select('hillary_pages',
			array('*'),
			array("hp_action <> ''"),
			__METHOD__,
			array('ORDER BY' => 'hp_last_voted DESC',
				'LIMIT' => 200));
		return self::extractArticles($res);
	}

	private function getCountProgress() {
		$db = wfGetDB(DB_SLAVE);
		$progress = $db->selectField('hillary_pages',
			array('count(*)'),
			array('hp_action' => '', 'hp_last_voted != 0'),
			__METHOD__);
		$novotes = $db->selectField('hillary_pages',
			array('count(*)'),
			array('hp_action' => '', 'hp_last_voted = 0'),
			__METHOD__);
		return array('progress' => $progress, 'novotes' => $novotes);
	}

	private function getCountVotes() {
		$db = wfGetDB(DB_SLAVE);
		$votes = $db->selectRow('hillary_pages',
			array('SUM(hp_pos_votes) AS pos', 'SUM(hp_neg_votes) AS neg'),
			'',
			__METHOD__);
		return (array)$votes;
	}

	private function getProgressArticles() {
		$db = wfGetDB(DB_SLAVE);
		$res = $db->select('hillary_pages',
			array('*'),
			array("hp_action = ''"),
			__METHOD__,
			array('ORDER BY' => 'hp_last_voted DESC',
				'LIMIT' => 200));
		return self::extractArticles($res);
	}

	private function extractArticles(&$res) {
		$articles = array();
		foreach ($res as $row) {
			$title = Title::newFromID($row->hp_pageid);
			if ($title && $title->exists()) {
				$votes = "{$row->hp_pos_votes}+ / {$row->hp_neg_votes}-";
				$articles[] = array(
					'last_voted' => $row->hp_last_voted,
					'title' => $title->getText(),
					'url' => $title->getPartialURL(),
					'action' => $row->hp_action,
					'votes' => $votes,
				);
			}
		}
		return $articles;
	}

}

