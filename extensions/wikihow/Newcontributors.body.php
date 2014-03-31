<?php
class Newcontributors extends QueryPage {

	function __construct($name='Newcontributors') {
		parent::__construct($name);
		
		list( $limit, $offset ) = wfCheckLimits();
		$this->limit = $limit;
		$this->offset = $offset;
	}

	function getName() {
		return "Newcontributors";
	}

	function isExpensive() { return false; }
	function isSyndicated() { return false; }

	function getSQL() {
		$dbr = wfGetDB(DB_SLAVE);
		$usertable = $dbr->tableName('user');
		$sql = "SELECT rev_user, COUNT(rev_user) AS numedits, rev_timestamp FROM revision, $usertable WHERE rev_user = user_id AND user_registration is not null GROUP BY rev_user HAVING COUNT(numedits) > 0";
		return $sql;
	}
	
	function getOrderFields() {
		return array('rev_timestamp');
	}

	function formatResult( $skin, $result ) {
		global $wgLang, $wgContLang;
		
		$user = User::newFromID($result->rev_user);
		$ulinks = Linker::userLink( $result->rev_user, $user->getName() );
		$ulinks .= Linker::userToolLinks( $result->rev_user, $user->getName() );
		
		$date = date('h:i, d F Y', wfTimestamp(TS_UNIX, $result->rev_timestamp));
		
		return $ulinks." ".$result->numedits." edits | $date";
	}
	
	function getPageHeader( ) {
		global $wgOut;
		$wgOut->setPageTitle("New Contributors");
		return;
	}

}

// -- doing things completely differently now with QueryPage [scott]
// class Newcontributors extends Specialpage {

	// function __construct() {
		// parent::__construct('Newcontributors');
	// }

	// function execute($par) {
		// global $wgUser, $wgOut, $wgLang, $wgTitle, $wgMemc, $wgDBname;
		// global $wgRequest, $wgSitename, $wgLanguageCode;
		// global $wgFeedClasses;
		// global $IP;

		// //require_once("$IP/includes/SpecialRecentchanges.php");

		// if ($wgUser->getID() == 0) {
			// $wgOut->showErrorPage('nosuchspecialpage', 'nospecialpagetext');
			// return;
		// }

		// $this->setHeaders();
		// $sk = $wgUser->getSkin();
		// $wgOut->setRobotpolicy('index,follow,noarchive');

		// // get query parameters
		// $feedFormat = $wgRequest->getVal('feed');

		// $defaultDays = $wgUser->getOption('rcdays');
		// if (!$defaultDays) {
			// $defaultDays = 7;
		// }

		// $limit = $wgUser->getOption('rclimit');
		// if (!$limit) { $limit = $defaults['limit']; }

		// $limit = $wgRequest->getInt('limit', $limit);
		// $dbr =& wfGetDB(DB_SLAVE);
		// $userTable = $dbr->tableName('user');

		// // we need to grab more records than we use because be filter the
		// // users with 0 edits
		// $dbLimit = 5 * $limit;
		// $sql = "SELECT user_id, user_name FROM $userTable WHERE user_registration IS NOT NULL ORDER BY user_registration DESC LIMIT $dbLimit";
		// $res = $dbr->query($sql, 'WH Newcontributors::execute1');
		// $users = array();
		// $userids = array();
		// while ($row = $res->next()) {
			// $users[] = $row;
			// $userids[] = $row->user_id;
		// }

		// $revision = $dbr->tableName('revision');
		// $sql = "SELECT rev_user, count(*) as numedits, min(rev_timestamp) as mt FROM $revision WHERE rev_user IN (" . $dbr->makeList($userids) . ") GROUP BY rev_user;";
		// $res = $dbr->query($sql, 'WH Newcontributors::execute2');
		// $edits = array();
		// $mts = array();
		// while ($row = $res->next()) {
			// $mts[$row->rev_user] = $row->mt;
			// $edits[$row->rev_user] = $row->numedits;
		// }
		// $count = 0;
		// foreach ($users as $i => &$user) {
			// if ($count < $limit && isset($edits[$user->user_id])) {
				// $user->numedits = $edits[$user->user_id];
				// $user->min = $mts[$user->user_id];
				// $count++;
			// } else {
				// // only include users with >= 1 edits
				// unset($users[$i]);
			// }
		// }

		// if (isset($from)) {
			// $note = wfMsg("rcnotefrom", $wgLang->formatNum($limit),
				// $wgLang->timeanddate($from, true));
		// } else {
			// $note = wfMsg("newcontributorsnote", $wgLang->formatNum($limit), $wgLang->formatNum($days) );
		// }
		// $wgOut->addHTML("\n{$note}\n<br />");

		// //$note = rcDayLimitLinks($days, $limit, "newcontributors", $hideparams, false, $minorLink, $botLink, $liuLink);
		// $note = 'note';
		// $wgOut->addHTML("{$note}\n");

		// $wgOut->setSyndicated(true);
		// $list =& new ChangesList($sk);
		// $s = $list->beginRecentChangesList();
		// $s .= '<br/><br/>';
		// $s .= '<div style="padding-left: 20px;"><ol>';
		// foreach ($users as $user) {
			// $s .= '<li>' . $this->newContributorsLine($user) . '</li>';
			// --$limit;
		// }
		// $s .= '</ol></div>';
		// $s .= $list->endRecentChangesList();
		// $wgOut->addHTML($s);

		// $dbr->freeResult($res);
	// }

	// function newContributorsLine($obj) {
		// global $wgScriptPath, $wgLang, $wgUser;

		// $display = $obj->user_name;
		// $flag = "";
		// $sk = $wgUser->getSkin();
		// $t = Title::makeTitleSafe(NS_USER_TALK, $obj->user_name);
		// $UTLink = $sk->makeLinkObj($t, "Talk");
		// if ($t->getArticleID() == 0 && $obj->numedits > 2) {
			// $flag = '<span class="unpatrolled">!</span>';
		// }
		// $Contribs = $sk->makeKnownLinkObj( Title::makeTitle(NS_SPECIAL, 'Contributions'), "Contributions",
			// 'target=' . urlencode($obj->user_name) );
		// $Block = '';
		// if (in_array('sysop', $wgUser->getGroups()) ) {
			// $Block = "| " . $sk->makeKnownLinkObj( Title::makeTitle(NS_SPECIAL, 'Blockip'), "Block", 'ip=' . urlencode($obj->user_name) );
		// }

		// $timestamp = $wgLang->timeanddate($obj->min, true);
		// return "$flag <a href=\"$wgScriptPath/User:" . $obj->user_name . "\">$display</a> ($UTLink | $Contribs $Block) {$obj->numedits} edits | {$timestamp} <br/>";
	// }

// }
