<?

if (!defined('MEDIAWIKI')) die();

global $IP;
require_once("$IP/includes/specials/SpecialContributions.php");

class WelcomeWagonContribsPager extends ContribsPager {

	function formatRow( $row ) {
		wfProfileIn( __METHOD__ );

		global $wgLang, $wgUser, $wgContLang;

		$rev = new Revision( $row );

		$page = Title::makeTitle( $row->page_namespace, $row->page_title );
		$link = Linker::link( $page );
		$difftext = $topmarktext = '';
		if( $row->rev_id == $row->page_latest ) {
			if( !$row->page_is_new ) {
				$difftext .= '(' . Linker::link( $page, $this->messages['diff'], array(), 'diff=0' ) . ')';
			} else {
				$difftext .= $this->messages['newarticle'];
			}

		}
		if( $rev->userCan( Revision::DELETED_TEXT ) ) {
			$difftext = '(' . Linker::link( $page, $this->messages['diff'], array(), 'diff=prev&oldid='.$row->rev_id ) . ')';
		} else {
			$difftext = '(' . $this->messages['diff'] . ')';
		}

		$comment = $wgContLang->getDirMark() . Linker::revComment( $rev );
		$d = $wgLang->timeanddate( wfTimestamp( TS_MW, $row->rev_timestamp ), true );

		if( $this->target == 'newbies' ) {
			$userlink = ' . . ' . Linker::userLink( $row->rev_user, $row->rev_user_text );
			$userlink .= ' (' . Linker::userTalkLink( $row->rev_user, $row->rev_user_text ) . ') ';
		} else {
			$userlink = '';
		}

		if( $rev->isDeleted( Revision::DELETED_TEXT ) ) {
			$d = '<span class="history-deleted">' . $d . '</span>';
		}

		if( $row->rev_minor_edit ) {
			$mflag = '<span class="minor">' . $this->messages['minoreditletter'] . '</span> ';
		} else {
			$mflag = '';
		}

		$ret = "{$d} {$difftext} {$mflag} {$link}{$userlink}{$comment} {$topmarktext}";
		if( $rev->isDeleted( Revision::DELETED_TEXT ) ) {
			$ret .= ' ' . wfMsgHtml( 'deletedrev' );
		}
		$ret = "<li>$ret</li>\n";
		wfProfileOut( __METHOD__ );
		return $ret;
	}
}

/*
	welcome wagon DB Tables

	CREATE TABLE `welcome_wagon_skips` (
	`wws_to_user_id` int(8) unsigned NOT NULL,
	`wws_from_user_id` int(8) unsigned NOT NULL,
	`wws_timestamp` timestamp NOT NULL default CURRENT_TIMESTAMP,
	KEY `wws_to_user_id` (`wws_to_user_id`),
	KEY `wws_from_user_id` (`wws_from_user_id`),
	UNIQUE KEY `wws_from_to` (`wws_to_user_id`,`wws_from_user_id`)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8;

	CREATE TABLE `welcome_wagon_messages` (
	`ww_id` int(8) unsigned NOT NULL auto_increment,
	`ww_from_user_id` int(8) unsigned NOT NULL,
	`ww_to_user_id` int(8) unsigned NOT NULL,
	`ww_revision_id` int(8) unsigned NOT NULL,
	`ww_timestamp` timestamp NOT NULL default CURRENT_TIMESTAMP,
	PRIMARY KEY  (`ww_id`),
	KEY `ww_to_user_id` (`ww_to_user_id`),
	KEY `ww_from_user_id` (`ww_from_user_id`),
	KEY `ww_timestamp` (`ww_timestamp`)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8;

*/

class WelcomeWagon extends UnlistedSpecialPage {

	var $noMoreUsersKey = null;
	var $logTable = 'welcome_wagon_messages';
	var $usersCount = null;

	public function __construct() {
		global $wgHooks;
		parent::__construct('WelcomeWagon');
		
		$wgHooks['getToolStatus'][] = array('Misc::defineAsTool');

		$this->maxMessagesPerUser = 2;
		$this->maxSkipsPerUser = 3;

		//set up the cache variables
		$this->userIdsKey = wfMemcKey("welcomewagon_userids");
		$this->userMessagesKey = wfMemcKey("welcomewagon_usermessages");
		$this->userSkipsKey = wfMemcKey("welcomewagon_userskips");
		$this->cacheOk = wfMemcKey("welcomewagon_cacheok");
	}

    public function getAllowedUsers() {
        return array();
    }

    public function userAllowed() {
        global $wgUser;

        $user = $wgUser->getName();

        $allowedUsers = $this->getAllowedUsers();

        $userGroups = $wgUser->getGroups();

        if ($wgUser->isBlocked() || !(in_array($user, $allowedUsers) ||
										in_array('staff', $userGroups) ||
										in_array('staff_widget', $userGroups) ||
										in_array('welcome_wagon', $userGroups))) {
            return False;
        }

        return True;
    }

	public function writeDiff(&$dbr, $target) {

		global $wgOut, $wgUser;

		$wgOut->addHTML( "<table width='100%' align='center' class='bunchtable'><tr>" );

		$opts = array ('rc_user_text' =>$target);
		$opts[] = ' (rc_namespace = 0) ';

		$res = $dbr->select ( 'recentchanges',
				array ('rc_id', 'rc_title', 'rc_namespace', 'rc_this_oldid', 'rc_cur_id', 'rc_last_oldid'),
				$opts,
				__METHOD__,
				array ('LIMIT' => 15)
			);

		$count = 0;
		foreach ($res as $row) {
			$t = Title::makeTitle($row->rc_namespace, $row->rc_title);
			$diff = $row->rc_this_oldid;
			$rcid = $row->rc_id;
			$oldid = $row->rc_last_oldid;
			$de = new DifferenceEngine( $t, $oldid, $diff, $rcid );
			$wgOut->addHTML( "<tr>" );
			$wgOut->addHTML( "<td>" );
			$wgOut->addHTML( $wgUser->getSkin()->makeLinkObj($t) );
			$de->showDiffPage(true);
			$wgOut->addHTML("</td></tr>");
			$count++;
		}
		$dbr->freeResult($res);

		$wgOut->addHTML( "</table><br/><br/>" );
		return $count;
	}

	function addUserTalkHTML($targetUser) {
		global $wgOut, $wgTitle, $wgArticle;
		// the joys of globals...got this idea from docs/globals.txt
		$oldTitle = $wgTitle;
		$oldArticle = $wgArticle;
		$wgTitle = Title::makeTitle(NS_USER_TALK, $targetUser->getName());
		$wgArticle = new Article($wgTitle);
		if ($wgArticle) {
			$wgOut->addHTML("<div id='content-talkpage' class='ww_content wh_block'> ");
			$wgOut->addHTML($wgArticle->view());
		} else {
			$wgOut->addHTML("no user talk info");
		}

		$wgTitle = $oldTitle;
		$wgArticle = $oldArticle;

		$wgOut->addHTML("</div>");
	}

	function addSummary($targetUser) {
		global $wgOut;
		$wgOut->addHTML("<div id='content-summary' class='ww_content wh_block'>");

		$target = $targetUser->getName();
		$pager = new WelcomeWagonContribsPager($this->getContext(), array("target" => $target));
		if ( !$pager->getNumRows() ) {
			$wgOut->addWikiMsg( 'nocontribs' );
			$wgOut->addHTML("</div>");
			return;
		}
		$wgOut->addHTML($pager->getBody());

		$wgOut->addHTML("</div>");

	}

	function addRecentContributionsHTML($targetUser) {
		global $wgOut;
		$wgOut->addHTML("<div id='content-contributions' class='wh_block'>");

		$target = $targetUser->getName();
		$dbr =& wfGetDB(DB_SLAVE);
		$this->writeDiff($dbr, $target);

		$wgOut->addHTML("</div>");
	}

	function addContentProfileHTML($targetUser) {
		global $wgOut;

		$userArticle = new Article($targetUser->getUserPage());

		$wgOut->addHTML("<div id='content-profile' class='ww_content wh_block'>");

		//$wgOut->addHTML(ProfileBox::displayBox($targetUser, false));
		WikihowUserPage::view($targetUser);

		if ($userArticle->getId() > 0) {
			$wgOut->addHTML($userArticle->getContent());
		}

		$wgOut->addHTML("</div>");
	}

	private function getMessagesSentForUserIds($userIds) {
		global $wgSharedDB;

		if (count($userIds) < 1) {
			return array();
		}
		$dbr = wfGetDB(DB_SLAVE);
		$result = $dbr->query("SELECT user_id, count(ww_to_user_id) as ww_messages
								FROM $wgSharedDB.user
								LEFT OUTER JOIN welcome_wagon_messages ON user_id = ww_to_user_id
								WHERE user_id in (".implode(",", array_keys($userIds)).")
								GROUP BY user_id;");
		$messages = array();
		while( $row = $dbr->fetchObject( $result ) ) {
			$messages[$row->user_id] = $row->ww_messages;
		}

		return $messages;
	}

	private function getSkipsForUserIds($userIds) {
		global $wgSharedDB;

		if (count($userIds) < 1) {
			return array();
		}
		$dbr = wfGetDB(DB_SLAVE);
		$result = $dbr->query("SELECT user_id, count(wws_to_user_id) as skips
								FROM $wgSharedDB.user
								LEFT OUTER JOIN welcome_wagon_skips ON user_id = wws_to_user_id
								WHERE user_id in (".implode(",", array_keys($userIds)).")
								GROUP BY user_id;");
		$skips = array();
		while( $row = $dbr->fetchObject( $result ) ) {
			$skips[$row->user_id] = $row->skips;
		}

		return $skips;
	}

	private function getUserIds() {
		global $wgSharedDB;

		$dbr = wfGetDB(DB_SLAVE);
		$beginTime = wfTimestamp( TS_MW, time() - 60 * 60 * 24 * 7 );

		$initialUserIds = array();

		$done = false;
		$batchSize = 100;
		$offset = 0;
		while($done == false) {
			$sql = "SELECT user_id, user_registration as registration
					FROM $wgSharedDB.user
					ORDER BY user_id DESC limit $batchSize OFFSET $offset";
			$result = $dbr->query($sql, __METHOD__);

			// don't query forever
			if ($result->numRows() < 1) {
					$done = true;
					break;
			}

			while ($row = $dbr->fetchObject($result)) {
				if (intval($row->registration) < intval($beginTime)) {
					$done = true;
					break;
				}
				$initialUserIds[] = $row->user_id;
			}
			$offset+=$batchSize;
		}

		if (count($initialUserIds) < 1) {
			return array();
		}
		$userIds = array();
		$namespaces = array("0");
		$revision = $dbr->tableName('revision');
		$sql = "SELECT t1.rev_user, count(*) as numedits
				FROM $revision t1
				LEFT JOIN page t2 ON t1.rev_page = t2.page_id
				WHERE rev_user IN (" . $dbr->makeList($initialUserIds) . ") AND t2.page_namespace IN (" . $dbr->makeList($namespaces) . ")
				GROUP BY rev_user;";

		$res = $dbr->query($sql, __METHOD__);
		$edits = array();
		while ($row = $res->next()) {
			$edits[$row->rev_user] = $row->numedits;
		}
		foreach ($initialUserIds as $i => $userId) {
			if (!isset($edits[$userId])) {
				unset($initialUserIds[$i]);
			}
		}
		foreach($initialUserIds as $i => $userId) {
			$userIds[$userId]  = true;
		}

		return $userIds;
	}

	function skipUser($userId) {
		global $wgUser;
		$dbw = wfGetDB(DB_MASTER);
		$dbw->insert("welcome_wagon_skips", array("wws_from_user_id"=>$wgUser->getId(), "wws_to_user_id"=>$userId), __METHOD__, array('IGNORE'));

		global $wgMemc;

		$skips = $wgMemc->get($this->userSkipsKey);
		$key = intval($userId);
		if (is_array($skips) && $skips[$key] != null) {
			$skips[$key] = $skips[$key] + 1;
			$wgMemc->set($this->userSkipsKey, $skips);

			if ($skips[$key] > $this->maxSkipPerUser) {
				$userIds = $wgMemc->get($this->userIdsKey);
				if (is_array($userIds) && $userIds[$key] == 1) {
					$userIds[$key] = 0;
					$wgMemc->set($this->userIdsKey, $userIds);
				}
			}
		}
	}

	function messagedUser($toId) {
		global $wgMemc;
		$key = intval($toId);
		$sent = $wgMemc->get($this->userMessagesKey);
		if (is_array($sent) && $sent[$key] != null) {
			$sent[$key] = $sent[$key] + 1;
			$wgMemc->set($this->userMessagesKey, $sent);

			if ($sent[$key] > $this->maxMessagesPerUser) {
				$userIds = $wgMemc->get($this->userIdsKey);
				if (is_array($userIds) && $userIds[$key] == 1) {
					$userIds[$key] = 0;
					$wgMemc->set($this->userIdsKey, $userIds);
				}
			}
		}
	}

	function resetCache() {
		global $wgMemc;
		$userIds = $this->getUserIds();
		$messagesSent = $this->getMessagesSentForUserIds($userIds);
		$skips = $this->getSkipsForUserIds($userIds);

		foreach($userIds as $id => $val) {
			if ($messagesSent[$id] > $this->maxMessagesPerUser) {
				$userIds[$id] = false;
			}
			if ($skips[$id] > $this->maxSkipsPerUser) {
				$userIds[$id] = false;
			}
		}

		$wgMemc->set($this->userMessagesKey, $messagesSent);
		$wgMemc->set($this->userSkipsKey, $skips);
		$wgMemc->set($this->userIdsKey, $userIds);
		$wgMemc->set($this->cacheOk, true, 60*15);
	}

	function isMessaged($userId) {
		global $wgUser;
		$dbr = wfGetDB(DB_SLAVE);
		$count = $dbr->selectField("welcome_wagon_messages",
									array('count(*)'),
									array('ww_from_user_id'=>$wgUser->getId(), 'ww_to_user_id'=>$userId),
									__METHOD__);
		if ($count > 0) {
			return true;
		} else {
			return false;
		}
	}

	function isSkipped($userId) {
		global $wgUser;

		$dbr = wfGetDB(DB_SLAVE);
		$count = $dbr->selectField("welcome_wagon_skips",
									array('count(*)'),
									array('wws_from_user_id'=>$wgUser->getId(), 'wws_to_user_id'=>$userId),
									__METHOD__);
		if ($count > 0) {
			return true;
		} else {
			return false;
		}
	}

	function getNextUserId() {
		global $wgMemc;

		$userIds = $wgMemc->get($this->userIdsKey);
		if (!is_array($userIds)) $userIds = array();
		$messages = $wgMemc->get($this->userMessagesKey);
		if (!is_array($messages)) $messages = array();
		$skips = $wgMemc->get($this->userSkipsKey);
		if (!is_array($skips)) $skips = array();

		// reorganize the list of ids by messages sent
		$idsByMessages = array();
		for ($i = 0; $i < $this->maxMessagesPerUser; $i++) {
			$idsByMessages[$i] = array();
		}

		$this->usersCount = 0;
		foreach($userIds as $id => $val) {
			$messageCount = $messages[$id];
			$skipsCount = $skips[$id];
			if ($val == true && ($skipsCount == 0 || $this->isSkipped($id) == false) && ($messageCount == 0 || $this->isMessaged($id) == false)) {
				$this->usersCount++;
				$idsByMessages[$messageCount][] = $id;
			}
		}

		// return first result
		foreach($idsByMessages as $idsArray) {
			foreach($idsArray as $id) {
				return $id;
			}
		}
	}

	function getStats() {
		global $wgOut;
		$wgOut->disable();

		$stats = new WelcomeWagonStandingsIndividual();
		return $stats->getStandingsTable();

	}
	function displayLeaderboards() {
		$stats = new WelcomeWagonStandingsIndividual();
		$stats->addStatsWidget();
		$standings = new WelcomeWagonStandingsGroup();
		$standings->addStandingsWidget();
	}

	function getLastArticle($userName) {
		global $wgUser;

		$lastArticle = null;
		$res = ProfileBox::fetchEditedData($userName, 1);
		foreach($res as $row) {
			$t = Title::newFromId($row->page_id);
			if ($t && $t->exists()) {
				$lastArticle = '[['.$t->getFullText().']]';
			}
		}
		return $lastArticle;
	}

	function tabSwitch() {
		global $wgOut, $wgRequest;

		$wgOut->disable();

		$target = $wgRequest->getVal('userName');
		$id = $wgRequest->getVal('userId');
		$user = User::newFromName($target);

		$tab = $wgRequest->getVal('tabName');
		switch($tab) {
			case 'contributions':
				$this->addRecentContributionsHTML($user);
				break;

			case 'summary':
				$this->addSummary($user);
				break;

			case 'profile':
				$this->addContentProfileHTML($user);
				break;

			case 'talkpage':
				$this->addUserTalkHTML($user);
				break;

			default:
				break;
		}

		echo json_encode(array('html' => $wgOut->getHTML()));
		return;
	}

	function nextUser() {
		global $wgOut, $wgRequest;
		$wgOut->disable();

		// first remove or skip the user
		$id = $wgRequest->getVal('userId');
		if ($id) {
			if($wgRequest->getVal('skip') == 'true') {
				$this->skipUser($id);
			} else {
				$this->messagedUser($id);
			}
		}

		$userId = $this->getNextUserId();
		if ($userId == null) {
			return;
		}

		$user = User::newFromId($userId);
		echo json_encode($this->getOutputVariables($user));
	}

	function getOutputVariables($user) {
		global $wgUser;

		$userLink = $wgUser->getSkin()->makeLinkObj($user->getUserPage(), $user->getName());
		$lastArticle = $this->getLastArticle($user->getName());

		$output = array( 'userLink' => $userLink,
						'userName' => $user->getName(),
						'userRealName' => $user->getRealName(),
						'userId' => $user->getId(),
						'lastArticleLink' => $lastArticle);

		if ($this->usersCount) {
			$output['usersCount'] = $this->usersCount;
		}
		return $output;
	}

	function logMessage($fromId, $toId, $revId, $message) {
		wfLoadExtensionMessages("WelcomeWagon");

		$dbw = wfGetDB(DB_MASTER);
		$rev = Revision::newFromId($revId);
		if ($rev) {
			$dbw = wfGetDB(DB_MASTER);
			$dbw->insert($this->logTable, array("ww_from_user_id" => $fromId, "ww_to_user_id" => $toId, "ww_revision_id" => $revId), __METHOD__);

			$log = new LogPage( 'welcomewag', false );
			$fromUser = User::newFromId($fromId);
			$toUser = User::newFromId($toId);
			if (strlen($message) > 150) {
				$message = substr($message, 0, 149);
			}
			$msg = wfMsg( "welcomewag_log_message",
							"[[User:{$fromUser->getName()}|{$fromUser->getName()}]]",
							"[[User:{$toUser->getName()}|{$toUser->getName()}]]",
							$message );
			$log->addEntry('message', $rev->getTitle(), $msg, array("fromId"=>$fromId, "toId"=>$toId, "revId"=>$revId));
		}
	}

	/**
	 * Execute special page.  Only available to wikihow staff.
	 */
	public function execute() {
		global $wgUser, $wgRequest, $wgOut, $wgLang, $wgMemc;
		if (!$this->userAllowed()) {
			$wgOut->setRobotpolicy('noindex,nofollow');
			$wgOut->showErrorPage('nosuchspecialpage', 'nospecialpagetext');
			return;
		}

		if ($wgRequest->wasPosted()) {
			switch($wgRequest->getVal('action')) {
				case 'nextUser':
					$this->nextUser();
					break;
				case 'logMessage':
					$wgOut->disable();
					$toId = $wgRequest->getVal('toId');
					$revId = $wgRequest->getVal('revId');
					$message = $wgRequest->getVal('message');
					$this->logMessage($wgUser->getId(), $toId, $revId, $message);
					echo json_encode(array('stats'=>$this->getStats()));
					break;
				case 'switchTab':
					$this->tabSwitch();
					break;
				default:
					break;

			}
			return;
		}

		$target = $wgRequest->getVal('target');
		if ($target) {
			$targetUser = User::newFromName($target);
			if ($targetUser->getId() > 0) {
				InterfaceElements::addJSVars($this->getOutputVariables($targetUser));
			}
		}
		if (!$wgMemc->get($this->cacheOk)) {
			$this->resetCache();
		}
		$wgOut->setPageTitle('Welcome Wagon');
		$wgOut->setHTMLTitle('Welcome Wagon');

        $tmpl = new EasyTemplate(dirname(__FILE__));

        $wgOut->addHTML($tmpl->execute('WelcomeWagon.tmpl.php'));
		$wgOut->addCSScode('diffc');
        $wgOut->addHTML(HtmlSnips::makeUrlTags('css', array('welcomewagon.css'), 'extensions/wikihow/WelcomeWagon', false));
        $wgOut->addHTML(HtmlSnips::makeUrlTags('js', array('welcomewagon.js'), 'extensions/wikihow/WelcomeWagon', false));

		InterfaceElements::addBubbleTipToElement('form-header', 'wwagon', 'No matter what happens keep the message positive and personalized.');
		$this->displayLeaderboards();
	}
}

