<?php

// a special page for reviewing the user completed images uploaded from mobile
class UCIPatrol extends SpecialPage {

	const UCI_ACTION_GOOD = 1;
	const UCI_ACTION_BAD = 2;
	const UCI_ACTION_SKIP = 3;

	const UCI_UPVOTES = 3;
	const UCI_DOWNVOTES = 2;

	const PATROL_THUMB_WIDTH = 670;
	const PATROL_THUMB_HEIGHT = 350;

	const CACHE_TIME = 604800;

	const UCI_THUMB_WIDTH = 152;
	const UCI_THUMB_HEIGHT = 114;

	// how many votes does an admin vote count as?
	const UCI_ADMIN_VOTE_MULT = 2;

	const UCI_CACHE = true;
	var $skipTool;

	function __construct() {
		parent::__construct("UCIPatrol", "ucipatrol");
	}

	function printStatsUploads($uploads) {
		echo("User Completed Images Stats:<br>");
		foreach($uploads as $key => $val) {
			echo($key.", $val<br>");
		}
	}

	function printStatsTitles($titles) {
		global $wgServer;
		echo("<br>Titles, Number of Images<br>");
		foreach($titles as $title => $num) {
			$t = Title::newFromText($title);
			$link = Linker::link($t, $wgServer."/".$t);

			echo($link.", $num<br>");
		}
	}
	function execute($par) {

		global $wgDebugToolbar;

		$request = $this->getRequest();
		$out = $this->getOutput();

		$this->checkPermissions();
		wfLoadExtensionMessages("UCIPatrol");
		if ($request->getVal("stats")) {
			$out->disable();
			$result = $this->getStats();
			if ($request->getVal("format") == "json") {
				echo json_encode($result);
				return;
			}

			UCIPatrol::printStatsUploads($result['uploads']);
			UCIPatrol::printStatsTitles($result['titles']);
			return;
		}

		if ($request->wasPosted()) {
			$out->disable();

			// wrapping it all in try catch to get any database errors
			try {
				$result = array();

				if ($request->getVal('next') ) {
					$result = $this->getNext();
				} elseif ($request->getVal('skip')) {
					$this->skip();
				} elseif ($request->getVal('bad')) {
					$this->downVote();
				} elseif ($request->getVal('good')) {
					$this->upVote();
				} elseif ($request->getVal('undo')) {
					$this->undo();
				} elseif ($request->getVal('error')) {
					$this->error();
					$result = $this->getNext();
				} elseif ($request->getVal('resetskip')) {
					$this->resetSkip();
					$result = $this->getNext();
				} elseif ($request->getVal('flag')) {
					$this->flag();
					$result = UCIPatrol::getImagesHTML($request->getVal('hostPage'));
					echo $result;
					return;
				}

				// if debug toolbar is active, pass logs back in json response
				if ($wgDebugToolbar) {
					$result['debug']['log'] = MWDebug::getLog();
				}

				echo json_encode($result);

			} catch (MWException $e) {
				$result = $result ?: array();
				$result['error'][] = $e->getText();
				echo json_encode($result);
				throw $e;
			}
		} else {
			$out->setHTMLTitle(wfMessage('ucipatrol')->text());
			$out->setPageTitle(wfMessage('ucipatrol')->text());

			$out->addJSCode('mt');  // Mousetrap library
			$out->addCSSCode('ucipc'); // Tips Patrol CSS
			$out->addScript(HtmlSnips::makeUrlTags('js', array('ucipatrol.js'), 'extensions/wikihow/mobile/ucipatrol', false));

			if ($wgDebugToolbar) {
				$out->addScript(HtmlSnips::makeUrlTags('js', array('consoledebug.js'), 'extensions/wikihow/debug', false));
			}

			EasyTemplate::set_path(dirname(__FILE__));
			$anon = $this->getUser()->isAnon();

			$out->addHTML(EasyTemplate::html('UCIPatrol.tmpl.php', $vars));
			$out->addScript("<script>$(document).ready(function(){WH.uciPatrol.init($anon)});</script>");

			$bubbleText = "Help us pick the best user submitted photos to match the article.";
			InterfaceElements::addBubbleTipToElement('uci', 'ucitp', $bubbleText);

			$this->displayLeaderboards();
		}
	}

	// guest id only used if user is anon
	private function getUserAvatar($user, $guestId) {
		if ($user->isAnon()) {
			// look for the guest_id cookie value to
			// give them the right avatar image
			$userAvatar = Avatar::getAnonAvatar($guestId);
			return $userAvatar;
		}

		$avatar = Avatar::getPicture($user->getName(), false);

		if ($avatar == '') {
			$avatar = Avatar::getDefaultPicture();
		}

		$userName = Linker::linkKnown($user->getUserPage(), $user->getName());

		$userAvatar = array("name"=>$userName, "image"=>$avatar);

		return $userAvatar;
	}

	private function resetSkip() {
		$cache = wfGetMainCache();
		$key = $this->getSkipCacheKey();
		$cache->delete($key);
	}

	private function undo() {
		$pageId = $this->getRequest()->getVal('pageId');
		$articleTitle = $this->getRequest()->getVal('articleTitle');

		$action = $this->getRequest()->getVal("action");

		$dbw = wfGetDB(DB_MASTER);
		$table = "user_completed_images";
		$conds = array("uci_article_id = $pageId");

		$values = array();
		$val = $this->getVoteMultiplier();
		if ($action == "good") {
			$votes = $dbw->selectField($table, "uci_upvotes", $conds);

			if ($votes == UCIPatrol::UCI_UPVOTES) {
				UCIPatrol::removeFromCache($articleTitle, $pageId);
			}

			$values[] = "uci_upvotes = uci_upvotes - $val";
		} else {
			$values[] = "uci_downvotes = uci_downvotes - $val";
		}

		$dbw->update($table, $values, $conds);

		// record a vote of zero that is equivalent to no vote
		$this->recordImageVote($this->getUser(), $pageId, 0);

		$this->unSkip();
	}

	private function upVote() {
		$pageId = $this->getRequest()->getVal('pageId');

		if (!$pageId) {
			MWDebug::warning("no pageId to upvote");
			return;
		}

		$table = "user_completed_images";
		$conds = array("uci_article_id = $pageId");
		$val = $this->getVoteMultiplier();
		MWDebug::log("val: ".$val);
		$values = array("uci_upvotes = uci_upvotes + $val");

		$dbw = wfGetDB(DB_MASTER);
		$dbw->update($table, $values, $conds);

		$this->recordImageVote($this->getUser(), $pageId, 1);

		// check if the image has enough votes that it will be added to the host page
		$row = $dbw->selectRow(
			$table,
			array("uci_image_name", "uci_article_name", "uci_upvotes"),
			array("uci_article_id" => $pageId),
			__METHOD__
		);

		// title is used for logging purposes
		$title = Title::newFromText($row->uci_article_name);

		if ($row->uci_upvotes < UCIPatrol::UCI_UPVOTES) {
			UCIPatrol::logUCIUpVote($title, $pageId);
		} else {
			UCIPatrol::addImageToCache($pageId, $row->uci_article_name, UCIPatrol::fileFromRow($row));
			UCIPatrol::logUCIAdded($title, $pageId);
		}

		$this->skip();
	}

	private static function logUCIError($title, $pageId) {
		UCIPatrol::logUCI($title, $pageId, "error");
	}

	private static function logUCIFlagged($title, $pageId) {
		UCIPatrol::logUCI($title, $pageId, "flagged");
	}

	private static function logUCIRejected($title, $pageId) {
		UCIPatrol::logUCI($title, $pageId, "rejected");
	}

	private static function logUCIAdded($title, $pageId) {
		UCIPatrol::logUCI($title, $pageId, "approved");
	}

	private static function logUCIUpVote($title, $pageId) {
		UCIPatrol::logUCI($title, $pageId, "upvote");
	}

	private static function logUCIDownVote($title, $pageId) {
		UCIPatrol::logUCI($title, $pageId, "downvote");
	}

	private static function logUCI($title, $pageId, $type) {
		$logPage = new LogPage('ucipatrol', false);
		$logData = array();
		$imageTitle = Title::newFromID($pageId);
		$logMsg = wfMessage("newuci-$type-logentry", $title, $imageTitle)->text();
		$logPage->addEntry("UCI", $title, $logMsg);
	}

	private function recordImageVote($user, $imagePageId, $vote) {
		$dbw = wfGetDB(DB_MASTER);
		$userId = intval($user->getID());
		if ($userId == 0) {
			$userId = 0 - intval($this->getRequest()->getVal('guestId'));
		}
		$imagePageId = intval($imagePageId);
		$vote = intval($vote);
		$timestamp = wfTimestampNow();
		$dbw->query("INSERT INTO `image_votes` (`iv_userid`, `iv_pageid`, `iv_vote`) VALUES ($userId, $imagePageId, $vote) ON DUPLICATE KEY UPDATE iv_vote = ".$vote.", iv_added = ".$timestamp);
	}

	private function getThumbsCountDB($pageTitle) {
		$dbr = wfGetDB(DB_SLAVE);
		$where = array("uci_article_name"=>$pageTitle);
		return $dbr->selectField('user_completed_images', 'count(*) as count', $where);
	}

	private function getThumbsCount($pageTitle) {
		global $wgMemc;

		if (UCIPatrol::UCI_CACHE == false) {
			return UCIPatrol::getThumbsCountDB($pageTitle);
		}

		$width = UCIPatrol::UCI_THUMB_WIDTH;
		$height = UCIPatrol::UCI_THUMB_HEIGHT;
		$key = UCIPatrol::getUCIThumbsCacheKey($pageTitle, $width, $height);
		$thumbs = $wgMemc->get($key);
		return $thumbs ? count($thumbs) : 0;
	}

	// adds a thumbnail image to the cache of images using default width and height
	private function addImageToCache($pageId, $hostPageTitle, $image) {
		global $wgMemc;
		MWDebug::log("pageid: ".$pageId);
		MWDebug::log("hostPageTitle: ".$hostPageTitle);
		MWDebug::log("image: ".$image->getUrl());

		if (!UCIPatrol::UCI_CACHE ) {
			return;
		}

		$width = UCIPatrol::UCI_THUMB_WIDTH;
		$height = UCIPatrol::UCI_THUMB_HEIGHT;
		$key = UCIPatrol::getUCIThumbsCacheKey($hostPageTitle, $width, $height);
		MWDebug::log("key is ".$key);
		$thumbs = $wgMemc->get($key);
		if (!$thumbs || !is_array($thumbs)) {
			$thumbs = array();
		}

		if (!isset($thumbs[$pageId])) {
			$thumb = UCIPatrol::getUCICacheData($pageId, $image, $width, $height);
			if ($thumb) {
				$thumbs[$pageId] = $thumb;
			}
		}
		$wgMemc->set($key, $thumbs);
	}

	private function error() {
		$pageId = $this->getRequest()->getVal('pageId');

		UCIPatrol::fullDownVote($pageId);
		$this->skip();
		$title = Title::newFromText($this->getRequest()->getVal("articleTitle"));
		UCIPatrol::logUCIError($title, $pageId);
	}

	private function fullDownVote($pageId) {
		UCIPatrol::downVoteItem($pageId, UCIPatrol::UCI_DOWNVOTES, false);
	}

	// returns affected rows
	private function downVoteItem($pageId, $amount=1, $useMultiplier=true) {
		$table = "user_completed_images";
		$conds = array("uci_article_id = $pageId");
		if ($useMultiplier) {
			$amount = $amount * $this->getVoteMultiplier();
		}
		MWDebug::log("amount: ".$amount);
		$values = array("uci_downvotes = uci_downvotes + $amount");

		$dbw = wfGetDB(DB_MASTER);
		$dbw->update($table, $values, $conds);

		return $dbw->affectedRows();
	}

	private function downVote() {
		$pageId = $this->getRequest()->getVal('pageId');
		if (!$pageId) {
			MWDebug::warning("no pageId to downvote");
			return;
		}

		UCIPatrol::downVoteItem($pageId);
		$this->recordImageVote($this->getUser(), $pageId, -1);

		// check if the image has enough votes that it will be removed from queue
		$table = "user_completed_images";
		$dbr = wfGetDB(DB_SLAVE);
		$row = $dbr->selectRow(
			$table,
			array("uci_image_name", "uci_article_name", "uci_downvotes"),
			array("uci_article_id" => $pageId),
			__METHOD__
		);

		$title = Title::newFromText($this->getRequest()->getVal("articleTitle"));

		if ($row->uci_downvotes < UCIPatrol::UCI_DOWNVOTES) {
			UCIPatrol::logUCIDownVote($title, $pageId);
		} else {
			UCIPatrol::logUCIRejected($title, $pageId);
		}

		$this->skip();
	}

	private function removeFromCache($hostPageTitle, $pageId) {
		global $wgMemc;

		$width = UCIPatrol::UCI_THUMB_WIDTH;
		$height = UCIPatrol::UCI_THUMB_HEIGHT;
		$key = UCIPatrol::getUCIThumbsCacheKey($hostPageTitle, $width, $height);

		$thumbs = $wgMemc->get($key);
		if (!$thumbs || !is_array($thumbs)) {
			return;
		}

		unset($thumbs[$pageId]);
		$wgMemc->set($key, $thumbs);
	}

	private function flag() {
		global $wgMemc;

		$request = $this->getRequest();
		$id = $request->getVal('pageId');
		$hostPageTitle = $request->getVal('hostPage');
		if (!$id) {
			return;
		}
		MWDebug::log("will flag $id");

		UCIPatrol::fullDownVote($id);

		if (UCIPatrol::UCI_CACHE) {
			$key = UCIPatrol::removeFromCache($hostPageTitle, $id);
		}

		//log the action
		$title = Title::newFromText($hostPageTitle);
		UCIPatrol::logUCIFlagged($title, $id);

		//purge from cache since we want these images to go away
		if ($title && $title->exists()) {
			$title->purgeSquid();
		}
	}

	private function unSkip() {
		$request = $this->getRequest();
		$id = $request->getVal('pageId');
		if (!$id) {
			return;
		}
		MWDebug::log("will unskip $id");
		$key = $this->getSkipCacheKey();

		$cache = wfGetMainCache();
		$skipped = $cache->get($key);

		if (!$skipped || count($skipped) == 0) {
			// nothing to unskip we are done
			return;
		}

		$newSkipped = array();
		foreach($skipped as $skip) {
			if ($skip == $id) {
				continue;
			}
			$newSkipped[] = $skip;
		}
		$cache->set($key, $newSkipped, self::CACHE_TIME);
	}

	private function skip() {
		$id = $this->getRequest()->getVal('pageId');
		if (!$id) {
			return;
		}
		$this->skipById($id);
	}

	private function skipById($id) {
		MWDebug::log("will skip $id");
		$key = $this->getSkipCacheKey();
		$cache = wfGetMainCache();
		$val = $cache->get($key) ?: array();
		$val[] = $id;
		$cache->set($key, $val, self::CACHE_TIME);
	}

	private function getSkipCacheKey() {
		$name = $this->getUser()->getName();
		return "UCIPatrol_".$name."_skipped";
	}

	private function getSkipList() {
		$cache = wfGetMainCache();
		$key = $this->getSkipCacheKey();
		$oldSkipped = $cache->get($key);
		if (!$oldSkipped || count($oldSkipped) == 0) {
			return array();
		}

		$where = array("uci_article_id IN ('" . implode("','", $oldSkipped) . "')");
		$where[] = "uci_downvotes < ".self::UCI_DOWNVOTES;
		$where[] = "uci_upvotes < ".self::UCI_UPVOTES;

		$dbr = wfGetDB(DB_SLAVE);
		$res = $dbr->select('user_completed_images', 'uci_article_id', $where, __METHOD__);
		$newSkipped = array();
		foreach ($res as $row) {
			$newSkipped[] = $row->uci_article_id;
		}

		return $newSkipped;
	}

	private function getVoteMultiplier() {
		$value = 1;
		if (UCIPatrol::userInUCIAdminGroup($this->getUser())) {
			$value = 2;
		}
		return $value;
	}

	private function getStats() {

		if(!in_array('sysop',$this->getUser()->getGroups())) {
			return;
		}

		$result = array();
		$dbr = wfGetDB(DB_SLAVE);

		$uploads = array();

		$day = wfTimestamp(TS_MW, time() - 1 * 24 * 3600);
		$where = "uci_timestamp > $day";
		$count = $dbr->selectField('user_completed_images', array('count(*)'), $where, __METHOD__);
		$uploads["last 24 hours"] = $count;

		$week = wfTimestamp(TS_MW, time() - 7 * 24 * 3600);
		$where = "uci_timestamp > $week";
		$count = $dbr->selectField('user_completed_images', array('count(*)'), $where, __METHOD__);
		$uploads["last 7 days"] = $count;

		$month = wfTimestamp(TS_MW, time() - 30 * 24 * 3600);
		$where = "uci_timestamp > $month";
		$count = $dbr->selectField('user_completed_images', array('count(*)'), $where, __METHOD__);
		$uploads["last 30 days"] = $count;

		$count = $dbr->selectField('user_completed_images', array('count(*)'));
		$uploads["allTime"] = $count;

		$averageSelect = "count(*) / count(distinct date(uci_timestamp))";
		$perDay = $dbr->selectField('user_completed_images', array($averageSelect));
		$uploads["average uploads per day"] = $perDay;

		$result["uploads"] = $uploads;

		$where = array();
		$where[] = "uci_downvotes < ".self::UCI_DOWNVOTES;
		$where[] = "uci_upvotes >= ".self::UCI_UPVOTES;
		$where[] = "uci_copyright_violates = 0";
		$where[] = "uci_copyright_checked = 1";

		$res = $dbr->select('user_completed_images', array('uci_article_name'), $where, __METHOD__);

		$titles = array();
		foreach($res as $row) {
			$title = $row->uci_article_name;
			if ($titles[$title] > 0 || UCIPatrol::isUCIAllowed(Title::newFromText($title))) {
				$titles[$title] = ($result[$title] ?: 0) + 1;
			}
		}
		$result['titles'] = $titles;

		return $result;
	}

	private function getNext() {
		$content = array();

		$guestId = $this->getRequest()->getVal('guestId');
		$content['user_voter'] = UCIPatrol::getUserAvatar($this->getUser(), $guestId);

		$content['required_upvotes'] = UCIPatrol::UCI_UPVOTES;
		$content['required_downvotes'] = UCIPatrol::UCI_DOWNVOTES;
		$content['vote_mult'] = $this->getVoteMultiplier();

		$skipped = UCIPatrol::getSkipList();

		$count = UCIPatrol::getCount() - count($skipped);
		$content['uciCount'] = $count;

		$where = array();
		$where[] = "uci_downvotes < ".self::UCI_DOWNVOTES;
		$where[] = "uci_upvotes < ".self::UCI_UPVOTES;
		$where[] = "uci_copyright_violates = 0";
		$where[] = "uci_copyright_checked = 1";

		if($skipped) {
			$where[] = "uci_article_id NOT IN ('" . implode("','", $skipped) . "')";
		}

		$dbr = wfGetDB(DB_SLAVE);
		$row = $dbr->selectRow('user_completed_images', array('*'), $where, __METHOD__, array("LIMIT" => 1));

		$content['pageId'] = $row->uci_article_id;
		$content['upvotes'] = $row->uci_upvotes;
		$content['downvotes'] = $row->uci_downvotes;
		//$content['sql' . $i] = $dbw->lastQuery();
		//$content['row'] = $row;

		if($row === false) {
			MWDebug::log("no more images to patrol");
			return $content;
		}

		$title = Title::newFromText($row->uci_article_name);

		// check page id vs whitelist/blacklist
		if (!UCIPatrol::isUCIAllowed($title)) {
			MWDebug::log("not allowed title: ".$title);
			$this->skipById($row->uci_article_id);
			return $this->getNext();
		}

		$content['articleTitle'] = $title->getText();
		$content['articleURL'] = $title->getPartialUrl();

		if(!$title) {
			MWDebug::log("no title: ".$title);
			$content['error'] = "notitle";
			return $content;
		}

		// get data about the completion image
		$file = UCIPatrol::fileFromRow($row);
		if (!$file) {
			MWDebug::warning("no file with image name ".$row->uci_image_name);
			$content['error'] = "filenotfound";
			return $content;
		}
		$content['uci_image_name'] = $row->uci_image_name;

		// get info about the originating page the image was added for
		$revision = Revision::newFromTitle($title);
		if ($title->isRedirect()) {
			MWDebug::log("is a redirect: ".$title);
			$wtContent = $revision->getContent();
			$title = $wtContent->getUltimateRedirectTarget();

			// edge case if there are just too many redirects, just skip this
			if ($title->isRedirect()) {
				MWDebug::log("too many redirects..skipping".$title);
				$content['error'] = "redirect";
				return $content;
			}

			$revision = Revision::newFromTitle($title);
			$content['articleTitle'] = $title->getText();
			$content['articleURL'] = $title->getPartialUrl();

			UCIPatrol::updateArticleName($row, $title->getText());
		}

		$popts = $this->getOutput()->parserOptions();
		$popts->setTidy(true);
		$parserOutput = $this->getOutput()->parse($revision->getText(), $title, $popts);
		$magic = WikihowArticleHTML::grabTheMagic($revision->getText());
		$content['article'] = WikihowArticleHTML::processArticleHTML($parserOutput, array('no-ads' => true, 'ns' => NS_MAIN, 'magic-word' => $magic));

		$width = $file->getWidth();

		// scale width so that the height is no greater than PATROL_THUMB_HEIGHT
		if ($file->getHeight() > self::PATROL_THUMB_HEIGHT) {
			$ratio = self::PATROL_THUMB_HEIGHT / $file->getHeight();
			$width = floor($width * $ratio);
		}

		// now that we have possibly scaled the width down to fit our max height..
		// we also will potentially scale down the width if it is still larger
		// than will fit on the patrol page
		$width = $width < self::PATROL_THUMB_WIDTH ? $width : self::PATROL_THUMB_WIDTH;
		$thumb = $file->getThumbnail($width);
		$content['thumb_url'] = $thumb->getUrl();
		$content['width'] = $thumb->getWidth();
		$content['height'] = $thumb->getHeight();

		// this is the page id of the image file itself not the same as articleTitle
		// used for skipping

		$voters = UCIPatrol::getVoters($row->uci_article_id);

		$content['voters'] = $voters;

		return $content;
	}

	private function updateArticleName($row, $newArticleName) {

		$dbw = wfGetDB(DB_MASTER);
		$table = "user_completed_images";
		$conds = array("uci_article_id" => $row->uci_article_id);
		$values = array("uci_article_name" => $newArticleName);
		MWDebug::log("will update ".$row->uci_article_id . " with ". $newArticleName);
		$dbw->update($table, $values, $conds);
	}

	private function userInUCIAdminGroup($user) {

		if(in_array('sysop',$user->getGroups())) {
			return true;
		}
		return false;
	}

	private function getVoters($pageId) {
		$result = array();

		$dbr = wfGetDB(DB_SLAVE);
		$table = "image_votes";
		$vars = array("iv_userid", "iv_vote");
		$where = array("iv_pageid"=>$pageId);

		$res = $dbr->select($table, $vars, $where);
		foreach ($res as $row) {

			// special case for anons that have voted..we stored their 'id' as negative
			if ($row->iv_userid < 0) {
				$voter = User::newFromId(0);
			} else {
				$voter = User::newFromId($row->iv_userid);
			}

			$admin = UCIPatrol::userInUCIAdminGroup($voter);
			$avatar = UCIPatrol::getUserAvatar($voter, $row->iv_userid);
			$result[] = array(
					"name"=>$avatar['name'],
					"vote"=>$row->iv_vote,
					"image"=>$avatar['image'],
					"admin_vote"=>$admin
					);
		}
		return $result;
	}

	public static function getCount() {
		$dbr = wfGetDB(DB_SLAVE);
		$where = array();
		$where[] = "uci_downvotes < ".self::UCI_DOWNVOTES;
		$where[] = "uci_upvotes < ".self::UCI_UPVOTES;
		$where[] = "uci_copyright_violates = 0";
		$where[] = "uci_copyright_checked = 1";
		return $dbr->selectField('user_completed_images', 'count(*) as count', $where);
	}

	function displayLeaderboards() {
		$stats = new UCIPatrolStandingsIndividual();
		$stats->setContext($this->getContext());
		$stats->addStatsWidget();
		$standings = $stats->getGroupStandings();
		$standings->setContext($this->getContext());
		$standings->addStandingsWidget();
	}

	// gets the list of user competed image files for a given page
	private static function getUCIForPage($pageTitle) {
		if (!$pageTitle) {
			return array();
		}

		$dbr = wfGetDB(DB_SLAVE);

		$res = $dbr->select(
			'user_completed_images',
			'*',
			array(
			"uci_article_name" => $pageTitle,
			"uci_upvotes >= ".self::UCI_UPVOTES,
			"uci_downvotes < ". self::UCI_DOWNVOTES,
			"uci_copyright_violates = 0",
			"uci_copyright_checked = 1"
			),
			__METHOD__
		);

		$files = array();
		foreach ( $res as $row ) {
			$files[$row->uci_article_id] = UCIPatrol::fileFromRow($row);
		}
		return $files;
	}

	private static function getUCIThumbsCacheKey($pageTitle, $width, $height) {
		$pageTitle = str_replace( ' ', '-', $pageTitle );
		return wfMemcKey('ucithumbs', $pageTitle, $width, $height);
	}

	private static function getUCICacheData($pageId, $image, $width, $height) {
		$thumb = $image->getThumbnail($width, $height, true, true, true);
		if (!$thumb) {
			return null;
		}

		$data = array(
				"url"=>$thumb->getUrl(),
				"width"=>$width,
				"height"=>$height,
				);

		return $data;
	}

	private static function getUCIThumbs($pageTitle, $width, $height, $purge = false) {
		global $wgMemc;

		if (!$pageTitle) {
			return array();
		}

		$key = UCIPatrol::getUCIThumbsCacheKey($pageTitle, $width, $height);

		$thumbs = $wgMemc->get($key);
		if (UCIPatrol::UCI_CACHE && is_array($thumbs) && !$purge) {
			return $thumbs;
		}

		$images = UCIPatrol::getUCIForPage($pageTitle);
		$thumbs = array();
		foreach ($images as $pageId=>$image) {
			$thumb = UCIPatrol::getUCICacheData($pageId, $image, $width, $height);
			$thumbs[$pageId] = $thumb;
		}

		//$wgMemc->set($key, $thumbs, strtotime("+2 hour"));
		$wgMemc->set($key, $thumbs);

		return $thumbs;
	}

	// gets an image given the user_completed_images row
	private static function fileFromRow($row) {
		MWDebug::log("will look for file: ".$row->uci_image_name);
		return wfFindFile("User-Completed-Image-".$row->uci_image_name);
	}

	public static function getHTMLForArticle($context) {
		$title = $context->getTitle();
		$purge = false;
		if ($context->getRequest()->getVal("purgeuci") == "true") {
			$purge = true;
		}

		return UCIPatrol::getImagesHTML($title, $purge);
	}

	public static function getImagesHTML($title, $purge = false) {
		$width = UCIPatrol::UCI_THUMB_WIDTH;
		$height = UCIPatrol::UCI_THUMB_HEIGHT;
		$thumbs = UCIPatrol::getUCIThumbs($title, $width, $height, $purge);

		if (!$thumbs || count($thumbs) == 0) {
			return;
		}

		$i = 0;
		foreach ($thumbs as $pageId => $thumb) {
			// not very sophisticated but will prevent too many images from being shown
			// TODO implement this show more link
			if ($i > 19) {
				$html .= "<div class='uci_more'><a href='#'>Show more</a></div>";
				break;
			}
			$i++;

			$html .= "<div class='uci_thumbnail' pageid='$pageId'><img src='" . $thumb['url'] . "' alt='' /></div>";
		}

		return $html;
	}

	public static function isUCIAllowed($title) {

		$id = intval($title->getArticleId());
		$blacklist = __DIR__.'/../uci_blacklist.txt';

		$list = file($blacklist);
		foreach($list as $line) {
			if ($id == intval($line)) {
				return false;
			}
		}

		$whitelist = __DIR__.'/../uci_whitelist.txt';
		$list = file($whitelist);
		foreach($list as $line) {
			if ($id == intval($line)) {
				return true;
			}
		}

		return false;

	}

	function showUCI($title) {
		global $wgDebugToolbar;
		$result = false;

		if (!$title->exists() || $title->getNamespace() != NS_MAIN) {
			return false;
		}

		$width = UCIPatrol::UCI_THUMB_WIDTH;
		$height = UCIPatrol::UCI_THUMB_HEIGHT;
		$thumbs = UCIPatrol::getUCIThumbs($title, $width, $height);
		if (!$thumbs || count($thumbs) == 0) {
			$result = false;
		} else {
			$result = true;
		}

		// reads the blacklist and whitelist files to see if images are allowed on this page
		// do this last since it might take more processing time
		if (!UCIPatrol::isUCIAllowed($title)) {
			$result = false;
		}

		return $result;
	}
}
