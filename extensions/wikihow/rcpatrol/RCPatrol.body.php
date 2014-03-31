<?

if (!defined('MEDIAWIKI')) die();

class RCPatrol extends SpecialPage {
	function __construct() {
		global $wgHooks;
		parent::__construct( 'RCPatrol' );
		$wgHooks['OutputPageBeforeHTML'][] = array('RCPatrol::postParserCallback');
		$wgHooks['getToolStatus'][] = array('Misc::defineAsTool');
	}

	// the way the difference engine works now, you need to pass the oldid in as false
	// to ensure that it will display new articles (and use a hook to preserve it to false).
	// if you pass it 0 for oldid, it
	// will compare the new id to the previous revision
	// this function will clean it up
	public static function cleanOldId($oldId) {
		if ($oldId === 0 || $oldId === '0') {
			$oldId = false;
		}
		return $oldId;
	}

	private static function setActiveWidget() {
		$standings = new RCPatrolStandingsIndividual();
		$standings->addStatsWidget();
		$standings = new QuickEditStandingsIndividual();
		$standings->addStatsWidget();
	}

	private static function setLeaderboard() {
		$standings = new QuickEditStandingsGroup();
		$standings->addStandingsWidget();
	}

	function execute() {
		global $wgServer, $wgRequest, $wgOut, $wgUser, $wgLanguageCode;
		wfLoadExtensionMessages('RCPatrol');
		
		$userGroups = $wgUser->getGroups();
		if (!$wgUser->isAllowed('patrol') || in_array('patrolblock', $userGroups)) {
			$wgOut->showErrorPage( 'nosuchspecialpage', 'nospecialpagetext' );
			return;
		}

		self::setActiveWidget();
		// INTL: Leaderboard is across the user database so we'll just enable for English at the moment
		if ($wgLanguageCode == 'en') {
			self::setLeaderboard();
		}

		$wgOut->addJScode('rcpj');
		$wgOut->addCSScode('rcpc');

		$wgOut->addHTML(QuickNoteEdit::displayQuickEdit() . QuickNoteEdit::displayQuickNote());
		$result = self::getNextArticleToPatrol();
		if ($result) {
			$rcTest = null;
			$testHtml = "";
			if (class_exists('RCTest') && RCTest::isEnabled()) {
				$rcTest = new RCTest();
				$testHtml = $rcTest->getTestHtml();
			}
			$wgOut->addHTML("<div id='rct_results'></div>");
			$wgOut->addHTML("<div id='bodycontents2' class='tool sticky'>");
			$titleText = RCTestStub::getTitleText($result, $rcTest);
			$wgOut->addHTML("<div id='articletitle' style='display:none;'>$titleText</div>");
			$wgOut->addHTML("<div id='rc_header' class='tool_header'>");

			// if this was a redirect, the title may have changed so update our context
			$oldTitle = $this->getContext()->getTitle();
			$this->getContext()->setTitle($result['title']);
			$d = RCTestStub::getDifferenceEngine($this->getContext(), $result, $rcTest);
			$d->loadRevisionData();
			$this->getContext()->setTitle($oldTitle);

			$wgOut->addHTML(RCPatrol::getButtons($result, $d->mNewRev, $rcTest));
			$wgOut->addHTML("</div>"); //end too_header
			$d->showDiffPage();
			$wgOut->addHTML($testHtml);
			$wgOut->addHTML("</div>");
		} else {
			$wgOut->addWikiMsg( 'markedaspatrolledtext' );
		}
		$wgOut->setPageTitle("RC Patrol");
	}

	static function getNextArticleToPatrol($rcid = null) {
		global $wgUser;
		while ($result = RCPatrolData::getNextArticleToPatrolInner($rcid)) {
			if (!isset($result['title']) || !$result['title']) {
				if (isset($result['rc_cur_id'])) {
					self::skipArticle($result['rc_cur_id']);
				}
			} else if (isset($result['users'][$wgUser->getName()])) {
				self::skipArticle($result['rc_cur_id']);
			} else {
				break;
			}
		}
		return $result;
	}

	static function skipArticle($id) {
		global $wgCookiePrefix, $wgCookiePath, $wgCookieDomain, $wgCookieSecure;
		// skip the article for now
		$cookiename = "Rcskip";
		$cookie = $id;
		if (isset($_COOKIE[$wgCookiePrefix.$cookiename]))
			$cookie .= "," . $_COOKIE[$wgCookiePrefix.$cookiename];
		$exp = time() + 2*60*60; // expire after 2 hours
		setcookie( $wgCookiePrefix.$cookiename, $cookie, $exp, $wgCookiePath, $wgCookieDomain, $wgCookieSecure );
		$_COOKIE[$wgCookiePrefix.$cookiename] = $cookie;
	}

	private static function getMarkAsPatrolledLink($title, $rcid, $hi, $low, $count, $setonload, $new, $old, $vandal) {
		global $wgRequest, $wgUser;
		$sns 	= $wgRequest->getVal('show_namespace');
		$inv	= $wgRequest->getVal('invert');
		$fea	= $wgRequest->getVal('featured');
		$rev 	= $wgRequest->getVal('reverse');
		$token  = $wgUser->getEditToken($rcid);

		$url = "/Special:RCPatrolGuts?target=" . urlencode($title->getFullText())
			. "&action=markpatrolled&rcid={$rcid}"
			. "&invert=$inv&reverse=$rev&featured=$fea&show_namespace=$sns"
			. "&rchi={$hi}&rclow={$low}&new={$new}&old={$old}&vandal={$vandal}&token=" . urlencode($token)
		;

		$class1 = "class='button primary' style='float: right;' ";
		$class2 = "class='button secondary' style='float: left;' ";
		$link =  " <input type='button' $class2 id='skippatrolurl' onclick=\"return skip();\" title='" . wfMsg('rcpatrol_skip_title') .
			"' value='" . wfMsg('rcpatrol_skip_button') . "'/>";
		$link .=  "<input type='button' $class1 id='markpatrolurl' onclick=\"return markPatrolled();\" title='" . wfMsg('rcpatrol_patrolled_title') .
			"' value='" . wfMsg('rcpatrol_patrolled_button') . "'/>";
		if ($setonload) {
			$link .= "<script type='text/javascript'>marklink = '$url';
				skiplink = '$url&skip=1';
				$(document).ready(function() {
					setupTabs();
					preloadNext('$url&grabnext=true');
				});
				</script>";

		}
		# this is kind of dumb, but it works
		$link .= "<div id='newlinkpatrol' style='display:none;'>$url</div><div id='newlinkskip' style='display:none;'>$url&skip=1</div>"
			 . "<div id='skiptitle' style='display:none;'>" . urlencode($title->getDBKey()) . "</div>"
			 . "<input id='permalink' type='hidden' value='" . str_replace("&action=markpatrolled", "&action=permalink", $url)  . "'/>";
		return $link;
	}

	private static function generateRollback($rev, $oldid = 0, &$rcTest) {
		global $wgUser, $wgRequest, $wgTitle, $wgServer;
		
		if (!$rev) return '';
		$title = $rev->getTitle();

		//first rev?
//		if ($oldid == 0) return '';
		if ($oldid == 0 || $oldid == '0' || !$oldid) {
			if ($rcTest && $rcTest->isTestTime()) {
				// wait, this is a test? nevermind then...
			}
			else {
				return '';
			}
		}
		
		$extraRollback = $wgRequest->getBool( 'bot' ) ? '&bot=1' : '';
		$extraRollback .= '&token=' . urlencode(
		$wgUser->editToken( array( $title->getPrefixedText(), $rev->getUserText() ) ) );

		$titleVal = $title->getLocalUrl();
		$titleVal = substr($titleVal, 1, strlen($titleVal));
		// Put urls in /index.php?title= form so we can bypass the varnish redirect rules for mobiel and tables
		if ($oldid) {
			$url = $wgServer . "/index.php?title={$titleVal}&action=rollback&old={$oldid}&from=" . urlencode( $rev->getUserText() ). $extraRollback . "&useajax=true";
		} else {
			$url = $wgServer . "/index.php?title={$titleVal}&action=rollback&from=" . urlencode( $rev->getUserText() ). $extraRollback . "&useajax=true";
		}

		// loop, check it 5 times because I think this changes
		$o = $_SESSION['wsEditToken'];
		for ($i = 0; $i < 5; $i++) {
			$x = $_SESSION['wsEditToken'];
			if ($x != $o) {
				// well here's our problem
				$url .= "&bad1={$o}&bad2={$x}";
				break;
			}
		}

		// debug all of this crap for bug 461
		global $wgCookiePrefix;
		$url .= "&timestamp=" . wfTimestampNow() . "&wsEditToken=" . $_SESSION['wsEditToken'] . "&sidx=" . session_id();
		$url .= '&wsEditToken_set=' . $_SESSION['wsEditToken_set'];
		$url .= '&hostname=' .  $_SESSION['wsEditToken_hostname'];
		$cookiesid = $_COOKIE[$wgCookiePrefix.'_session'];
		$url .= "&cookiesid=" . $cookiesid;
		$url .= "&s_started=" . $_SESSION['started'];
		$url .= '&wsUserName=' .  $_SESSION['wsUserName'];
		$url .= '&cookiesUserName=' .  $_COOKIE[$wgCookiePrefix.'UserName'];
		$url .= '&ip=' . wfGetIP();
		$url .= "&wgUser=" . urlencode($wgUser->getName());

		$class = "class='button secondary' style='float: right;'";

		$s = HtmlSnips::makeUrlTags('js', array('rollback.js'), 'extensions/wikihow', false);
		// useful in debugging:
		//$s = '<script src="/extensions/wikihow/rollback.js"></script>';
		$s .= "
			<script type='text/javascript'>
				var gRollbackurl = \"{$url}\";
				var msg_rollback_complete = \"" . htmlspecialchars(wfMsg('rollback_complete')) . "\";
				var msg_rollback_fail = \"" . htmlspecialchars(wfMsg('rollback_fail')) . "\";
				var msg_rollback_inprogress = \"" . htmlspecialchars(wfMsg('rollback_inprogress')) . "\";
				var msg_rollback_confirm= \"" . htmlspecialchars(wfMsg('rollback_confirm')) . "\";
			</script>
				<a id='rb_button' $class href='' onclick='return rollback();' title='" . wfMsg('rcpatrol_rollback_title') . "'>" . wfMsg('rcpatrol_rollback_button') . "</a>
			</span>";
		$s .= "<div id='newrollbackurl' style='display:none;'>{$url}</div>";
		return $s;

	}

	private static function getQuickEdit($title, $result) {
		global $wgServer;

		// build the array of users for the quick note link sorted by 
		// the # of bytes changed descending, i.e. more is better
		$users = array();
		$sorted = $result['users_len'];
		if (!$sorted)
			return;
		asort($sorted, SORT_NUMERIC);
		$sorted = array_reverse($sorted);
		foreach ($sorted as $s=>$len) {
			$u = User::newFromName($s);
			if (!$u) {
				// handle anons
				$u = new User();
				$u->setName($s);
			}
			$users[] = $u;
		}

		$editURL = Title::makeTitle(NS_SPECIAL, "QuickEdit")->getFullURL() . '?type=editform&target=' . urlencode($title->getFullText());
		$class = "class='button secondary' style='float: left;'";
		$link = "<script type='text/javascript'>var gQuickEditUrl = \"{$editURL}\";</script>";
		$link .=  "<a id='qe_button' title='" . wfMsg("rcpatrol_quick_edit_title") . "' href='' $class onclick=\"return initPopupEdit(gQuickEditUrl) ;\">" .
			htmlspecialchars( wfMsg( 'rcpatrol_quick_edit_button' ) ) . "</a> ";

		$qn = str_replace("href", " title='" . wfMsg("rcpatrol_quick_note_title") . "' $class href", QuickNoteEdit::getQuickNoteLinkMultiple($title, $users));
		$link = $qn . $link;
		//make sure we load the clientscript here so we can post load those buttons
		$link .= HtmlSnips::makeUrlTags('js', array('clientscript.js'), 'skins/common', false);
		return $link;
	}

	static function getButtons($result, $rev, $rcTest = null) {
		wfLoadExtensionMessages('RCPatrol');
		$t = $result['title'];
		$s = "<table cellspacing='0' cellpadding='0' style='width:100%;'><tr><td style='vertical-align: middle; xborder: 1px solid #999;' class='rc_header'>";
		$u = new User();
		$u->setName($result['user']);
		$s .= "<a id='gb_button' href='' onclick='return goback();' title='" . wfMsg('rcpatrol_go_back_title') . "' class='button button_arrow secondary'></a>";
		$s .= self::getQuickEdit($t, $result);
		$s .= RCTestStub::getThumbsUpButton($result, $rcTest);
		$s .= self::getMarkAsPatrolledLink($result['title'], $result['rcid'], $result['rchi'], $result['rclo'], $result['count'], true, $result['new'], $result['old'], $result['vandal']);
		$s .= self::generateRollback($rev, $result['old'], $rcTest);
		$s .= "</td></tr></table>";
		$s .= "<div id='rc_subtabs'>
			<div id='rctab_advanced'>
				<a href='#'>" . wfMsg('rcpatrol_advanced_tab') . "</a>
			</div>
			<div id='rctab_ordering'>
				<a href='#'>" . wfMsg('rcpatrol_ordering_tab') . "</a>
			</div>
			<div id='rctab_user'>
				<a href='#'>" . wfMsg('rcpatrol_user_tab') . "</a>
			</div>
			<div id='rctab_help'>
				<a href='#'>" . wfMsg('rcpatrol_help_tab') . "</a>
			</div>
			<div style='float:none'></div>
		</div>";
		$s .= "<table style='clear:both;'>";
		$s .= self::getAdvancedTab($t, $result);
		$s .= self::getOrderingTab();
		$s .= self::getUserTab();
		$s .= self::getHelpTab();
		$s .= "</table>";
		$s .= "<div id='rollback-status' style='background-color: #FFFF00;'></div>";
		$s .= "<div id='thumbsup-status' style='background-color: #FFA;display:none;padding:2px;'></div>";
		$s .= "<div id='numrcusers' style='display:none;'>" . sizeof($result['users']) . "</div>";
		$s .= "<div id='numedits' style='display:none;'>". sizeof($result['count']) . "</div>";
		$s .= "<div id='quickedit_response_wrapper'></div>";
		return $s;
	}

	private static function getAdvancedTab($t, $result) {
		$tab = "<tr class='rc_submenu' id='rc_advanced'><td>";
		$tab .= "<a href='{$t->getFullURL()}?action=history' target='new'>" . wfMsg('rcpatrol_page_history') . "</a> -";
		if ($result['old'] > 0) {
			$tab .= " <a href='{$t->getFullURL()}?oldid={$result['old']}&diff={$result['new']}' target='new'>" . wfMsg('rcpatrol_view_diff') . "</a> -";
		}
		$tab .= " <a href='{$t->getTalkPage()->getFullURL()}' target='new'>" . wfMsg('rcpatrol_discuss') . "</a>";
		if ($t->userCan('move')) {
			$tab .= " - <a href='{$t->getFullURL()}?action=delete' target='new'>" . wfMsg('rcpatrol_delete') . "</a> -";
			$mp = SpecialPage::getTitleFor("Movepage", $t);
			$tab .= " <a href='{$mp->getFullURL()}' target='new'>" . wfMsg('rcpatrol_rename') . "</a> ";
		}

		$tab .= "</td></tr>";
		return $tab;
	}

	private static function getOrderingTab() {
		global $wgRequest;
		$reverse = $wgRequest->getVal('reverse', 0);
		$tab = "<tr class='rc_submenu' id='rc_ordering'><td>
			<div id='controls' style='text-align:center'>
			<input type='radio' id='reverse_newest' name='reverse' value='0' " . (!$reverse? "checked" : "") . " style='height: 10px;' onchange='changeReverse();'> <label for='reverse_newest'>" . wfMsg('rcpatrol_newest_oldest') . "</label>
			<input type='radio' id='reverse_oldest' name='reverse' value='1' id='reverse' " . ($reverse? "checked" : "") . " style='height: 10px; margin-left:10px;' onchange='changeReverse();'> <label for='reverse_oldest'>" .  wfMsg('rcpatrol_oldest_newest') . "</label>
			&nbsp; &nbsp; - &nbsp; &nbsp; " . wfMsg('rcpatrol_namespace') . ": " .  Html::namespaceselector(array($namespace)) . " <script>     $('#namespace').change(function() { ns = $('#namespace').val(); nextrev = null; }); </script>
			</div></td></tr>";
		return $tab;
	}

	private static function getUserTab() {
		$tab = "<tr class='rc_submenu' id='rc_user'><td>
			<div id='controls' style='text-align:center'>
				" . wfMsg('rcpatrol_username') . ": <input type='text' name='rc_user_filter' id='rc_user_filter' size='30' onchange='changeUserFilter();'/> <script> $('#rc_user_filter').keypress(function(e) { if (e.which == 13) { $('#rc_user_filter_go').click(); return false; } }); </script>
				<input type='button' id='rc_user_filter_go' value='" . wfMsg('rcpatrol_go') . "' onclick='changeUser(true);'/>
				-
				<a href='#' onclick='changeUser(false);'>" . wfMsg('rcpatrol_off') . "</a>
			</div></td></tr>";
		return $tab;
	}

	private static function getHelpTab() {
		global $wgLanguageCode;

		if ($wgLanguageCode == 'en') {
			$helpTop = wfMsg('rcpatrolhelp_top');
		} else {
			$helpTop = wfMsgWikiHtml('rcpatrolhelp_top');
		}

		$tab = "<tr class='rc_submenu' id='rc_help'><td>" . $helpTop . wfMsg('rcpatrolhelp_bottom') . "</td></tr>";
		return $tab;
	}

	static function postParserCallback($outputPage, &$html) {
		//$html = WikihowArticleHTML::processArticleHTML($html, array('no-ads' => true));
		return true;
	}
	static function getNextURLtoPatrol($rcid) {
		global $wgRequest, $wgUser;

		$username = $wgUser->getName();
		$show_namespace = $wgRequest->getVal('show_namespace', null);
		if ($show_namespace === null) $show_namespace = $wgRequest->getVal('namespace', null);
		$invert = $wgRequest->getInt('invert');
		$reverse = $wgRequest->getInt('reverse');
		$featured = $wgRequest->getInt('featured');
		$associated = $wgRequest->getInt('associated');
		$fromrc = $wgRequest->getVal('fromrc') ? 'fromrc=1' : '';
 
		//TODO: shorten this to a selectRow call
		$dbw = wfGetDB( DB_MASTER );
		$sql = "SELECT rc_id, rc_cur_id, rc_new, rc_namespace, rc_title, rc_last_oldid, rc_this_oldid FROM recentchanges " . 
			($featured ? " LEFT OUTER JOIN page on page_title = rc_title and page_namespace = rc_namespace " : "") .
			" WHERE rc_id " . ($reverse == 1 ? " > " : " < ")  . " $rcid and rc_patrolled = 0  " . 
			($featured ? " AND page_is_featured = 1 " : "") 
			. " AND rc_user_text != " . $dbw->addQuotes($username) . " ";

		if ($show_namespace != null && $show_namespace != '') {
			$sql .= " AND rc_namespace " . ($invert ? '!=' : '=') . $show_namespace;
		} else  {
			// avoid the delete logs, etc
			$sql .= " AND rc_namespace NOT IN ( " . NS_VIDEO . ") ";
		}
		$sql .= " ORDER by rc_id " . ($reverse == 1 ? " ASC " : " DESC ") . " LIMIT 1";
//error_log("$sql\n", 3, '/tmp/qs.txt');
		$res = $dbw->query($sql, __METHOD__);
		if ( $row = $dbw->fetchObject( $res ) ) {
			$xx = Title::makeTitle($row->rc_namespace, $row->rc_title);
			//we got one, right?
			if (!$xx) return null;

			$url = $xx->getFullURL() . "?rcid=" . $row->rc_id;
			if ($xx->isRedirect() || $row->rc_new == 1) {
				$url .= '&redirect=no';
			}
			if ($row->rc_new != 1) {
				$url .= "&curid=" . $row->rc_cur_id . "&diff=" 
					. $row->rc_this_oldid . "&oldid=" . $row->rc_last_oldid;
			}
			$url .= "&namespace=$show_namespace&invert=$invert&reverse=$reverse&associated=$associated&$fromrc";
		}
		return $url;
	}

	private static function skipPatrolled($article) {
		global $wgRequest;
		global $wgCookieExpiration, $wgCookiePath, $wgCookieDomain, $wgCookieSecure, $wgCookiePrefix;

		$hi = $wgRequest->getInt( 'rchi', null );
		$lo = $wgRequest->getInt( 'rclow', null );
		$rcid = $wgRequest->getInt( 'rcid' );

		$dbr = wfGetDB(DB_SLAVE);
		$pageid = $dbr->selectField('recentchanges', 'rc_cur_id', array('rc_id=' . $rcid));
		if ($pageid && $pageid != '')
			$featured = $dbr->selectField('page', 'page_is_featured', array("page_id={$pageid}") );
		if ($featured) {
			// get all of the rcids to ignore
			$ids = array();
			if ($hi != null) {
				$res = $dbr->select('recentchanges', 'rc_id', array("rc_id>={$lo}", "rc_id<={$hi}", "rc_cur_id=$pageid"));
				while ($row = $dbr->fetchObject($res)) {
					$ids[] = $row->rc_id;
				}
				$dbr->freeResult($res);
			} else {
				$ids[] = $rcid;
			}
			$cookiename = "WsSkip_" . wfTimestamp();
			$cookie = implode($ids, ",");
			$_SESSION[$cookiename] = $article->mToken;
			$exp = time() + 5*60*60;
			setcookie( $wgCookiePrefix.$cookiename, $cookie, $exp, $wgCookiePath, $wgCookieDomain, $wgCookieSecure );
		}
	}
}

class RCPatrolData {
	static function getListofEditors($result) {
		$dbr = wfGetDB(DB_SLAVE);
		$users = array();
		$users_len = array();
		$res = $dbr->select('recentchanges',
			array('rc_user', 'rc_user_text', 'rc_new_len', 'rc_old_len'),
			array("rc_id <= " . $result['rchi'],
				"rc_id >= " . $result['rclo'],
				"rc_cur_id" => $result['rc_cur_id']));
		while ($row = $dbr->fetchObject($res)) {
			$u = array();
			if (isset($users[$row->rc_user_text])) {
				$u = $users[$row->rc_user_text];
				$u['edits']++;
				$u['len'] += $row->rc_new_len - $row->rc_old_len;
				$users[$row->rc_user_text] = $u;
				$users_len[$row->rc_user_text] = $u['len'];
				continue;
			}
			$u['id'] = $row->rc_user;
			$u['user_text'] = $row->rc_user_text;
			$u['edits']++;
			$u['len'] = $row->rc_new_len - $row->rc_old_len;
			$users_len[$row->rc_user_text] = $u['len'];
			$users[$row->rc_user_text] = $u;
		}
		$result['users'] = $users;
		$result['users_len'] = $users_len;
		return $result;
	}

	static function getNextArticleToPatrolInner($rcid = null) {
		global $wgRequest, $wgUser, $wgCookiePrefix;

		$show_namespace		= $wgRequest->getVal('namespace');
		$invert				= $wgRequest->getVal('invert');
		$reverse			= $wgRequest->getVal('reverse');
		$featured			= $wgRequest->getVal('featured');
		$title				= $wgRequest->getVal('target');
		$skiptitle			= $wgRequest->getVal('skiptitle');
		$rc_user_filter		= trim(urldecode($wgRequest->getVal('rc_user_filter')));

		// assert that current user is not anon
		if ($wgUser->isAnon()) return null;

		// In English, when a user rolls back an edit, it gives the edit a comment
		// like: "Reverted edits by ...", so MediaWiki:rollback_comment_prefix
		// is set to "Reverted" in English wikiHow.
		$rollbackCommentPrefix = wfMessage('rollback_comment_prefix')->plain();
		
		if (empty($rollbackCommentPrefix) || strpos($rollbackCommentPrefix, '&') === 0) {
			die("Cannot use RCPatrol feature until MediaWiki:rollback_comment_prefix is set up properly");
		}

		$t = null;
		if ($title)
			$t = Title::newFromText($title);
		$skip = null;
		if ($skiptitle)
			$skip = Title::newFromText($skiptitle);

		$dbr = wfGetDB(DB_MASTER);
		/*	DEPRECATED rc_moved_to_ns & rc_moved_to_title columns
			$sql = "SELECT rc_id, rc_cur_id, rc_moved_to_ns, rc_moved_to_title, rc_new, 
			  rc_namespace, rc_title, rc_last_oldid, rc_this_oldid 
			FROM recentchanges 
			LEFT OUTER JOIN page ON rc_cur_id = page_id AND rc_namespace = page_namespace 
			WHERE ";*/
		$sql = "SELECT rc_id, rc_cur_id, rc_new, rc_namespace, rc_title, rc_last_oldid, rc_this_oldid 
			FROM recentchanges 
			LEFT OUTER JOIN page ON rc_cur_id = page_id AND rc_namespace = page_namespace 
			WHERE ";

		if (!$wgRequest->getVal('ignore_rcid') && $rcid)
			$sql .= " rc_id " . ($reverse == 1 ? " > " : " < ")  . " $rcid and ";

		// if we filter by user we show both patrolled and non-patrolled edits
		if ($rc_user_filter) {
			$sql .= " rc_user_text = " . $dbr->addQuotes($rc_user_filter);
			if ($rcid)
				$sql .= " AND rc_id < " . $rcid;
		} else  {
			$sql .= " rc_patrolled = 0 ";
		}

		// can't patrol your own edits
		$sql .= " AND rc_user <> " . $wgUser->getID();

		// only featured?
		if ($featured)
			$sql .= " AND page_is_featured = 1 ";

		if ($show_namespace)  {
			$sql .= " AND rc_namespace " . ($invert ? '<>' : '=') . $show_namespace;
		} else  {
			// always ignore video
			$sql .= " AND rc_namespace <> " . NS_VIDEO;
		}

		// log entries have namespace = -1, we don't want to show those, hide bots too
		$sql .= " AND rc_namespace >= 0 AND rc_bot = 0 ";

		if ($t) {
			$sql .= " AND rc_title <> " . $dbr->addQuotes($t->getDBKey());
		}
		if ($skip) {
			$sql .= " AND rc_title <> " . $dbr->addQuotes($skip->getDBKey());
		}

		$sa = $wgRequest->getVal('sa');
		if ($sa) {
			$sa = Title::newFromText($sa);
			$sql .= " AND rc_title = " . $dbr->addQuotes($sa->getDBKey());
		}

		// has the user skipped any articles?
		$cookiename = $wgCookiePrefix."Rcskip";
		$skipids = "";
		if (isset($_COOKIE[$cookiename])) {
			$cookie_ids = array_unique(split(",", $_COOKIE[$cookiename]));
			$ids = array(); //safety first
			foreach ($cookie_ids as $id) {
				$id = intval($id);
				if ($id > 0) $ids[] = $id;
			}
			if ($ids) {
				$skipids = " AND rc_cur_id NOT IN (" . implode(",", $ids) . ") ";
			}
		}
		$sql .= "$skipids ORDER BY rc_timestamp " . ($reverse == 1 ? "" : "DESC ") . "LIMIT 1";

		$res = $dbr->query($sql, __METHOD__);
		$row = $res->fetchObject();
/*$show=true;
if ($show){
var_dump($_GET);
var_dump($_POST);
echo $sql;
var_dump($row);
exit;
}*/

		if ($row) {
			$result = array();
			$t = Title::makeTitle($row->rc_namespace, $row->rc_title);
			if ($t->isRedirect()) {
				$wp = new WikiPage($t);
				$t = $wp->getRedirectTarget();
			}

			// if title has been deleted set $t to null so we will skip it
			if (!$t->exists()) {
				MWDebug::log("$t does not exist");
				$t = null;
			}

			$result['rc_cur_id'] = $row->rc_cur_id;

			if ($rc_user_filter) {
				$result['rchi'] = $result['rclo'] = $row->rc_id;
				$result['new']		= $dbr->selectField('recentchanges', array('rc_this_oldid'), array('rc_id' => $row->rc_id));
			} else {
				// always compare to current version
				$result['new']		= $dbr->selectField('revision', array('max(rev_id)'), array('rev_page' => $row->rc_cur_id));
				$result['rchi']		= $dbr->selectField('recentchanges', array('rc_id'), array('rc_this_oldid' => $result['new']));
				$result['rclo']		= $dbr->selectField('recentchanges', array('min(rc_id)'), array('rc_patrolled'=>0,"rc_cur_id"=>$row->rc_cur_id));

				// do we have a reverted edit caught between these 2?
				// if so, only show the reversion, because otherwise you get the reversion trapped in the middle
				// and it shows a weird diff page.
				$hi = isset($result['rchi']) ? $result['rchi'] : $row->rc_id;

				if ($hi) {
					$reverted_id = $dbr->selectField('recentchanges',
						array('min(rc_id)'),
						array('rc_comment like ' . $dbr->addQuotes($rollbackCommentPrefix . '%'), 
							"rc_id < $hi" ,
							"rc_id >= {$result['rclo']}",
							"rc_cur_id"=>$row->rc_cur_id));
					if ($reverted_id) {
						$result['rchi'] = $reverted_id;
						$result['new'] = $dbr->selectField('recentchanges',
							array('rc_this_oldid'),
							array('rc_id' => $reverted_id));
						$row->rc_id = $result['rchi'];
					}
				//} else {
				//	$email = new MailAddress("alerts@wikihow.com");
				//	$subject = "Could not find hi variable " . date("r");
				//	$body = print_r($_SERVER, true) . "\n\n" . $sql . "\n\n" . print_r($result, true) . "\n\n\$hi: " . $hi;
				//	UserMailer::send($email, $email, $subject, $body);
				}

				if (!$result['rclo']) $result['rclo'] = $row->rc_id;
				if (!$result['rchi']) $result['rchi'] = $row->rc_id;

				// is the last patrolled edit a rollback? if so, show the diff starting at that edit
				// makes it more clear when someone has reverted vandalism
				$result['vandal'] = 0;
				$comm = $dbr->selectField('recentchanges', array('rc_comment'), array('rc_id'=>$result['rclo']));
				if (strpos($comm, $rollbackCommentPrefix) === 0) {
					$row2 = $dbr->selectRow('recentchanges', array('rc_id', 'rc_comment'),
						array("rc_id < {$result['rclo']}", 'rc_cur_id' => $row->rc_cur_id),
						__METHOD__,
						array("ORDER BY" => "rc_id desc", "LIMIT"=>1));
					if ($row2) {
						$result['rclo'] = $row2->rc_id;
					}
					$result['vandal'] = 1;
				}
			}
			$result['user']		= $dbr->selectField('recentchanges', array('rc_user_text'), array('rc_this_oldid' => $result['new']));
			$result['old']      = $dbr->selectField('recentchanges', array('rc_last_oldid'), array('rc_id' => $result['rclo']));
			$result['title']	= $t;
			$result['rcid']		= $row->rc_id;
			if ($result['rchi'] == $result['rclo']) {
				$conds = array('rc_id' => $result['rchi']);
			} else {
				$conds = array(
					'rc_id <= ' . $result['rchi'],
					'rc_id >= ' . $result['rclo']);
			}
			$result['count'] = $dbr->selectField('recentchanges',
				array('count(*)'),
				array("rc_id <= " . $result['rchi'],
					"rc_id >= " . $result['rclo'],
					"rc_patrolled" => 0,
					"rc_cur_id" => $row->rc_cur_id));
			$result = self::getListofEditors($result);
			return $result;
		} else {
			return null;
		}
	}
}

class RCPatrolGuts extends UnlistedSpecialPage {
	function __construct() {
		global $wgHooks;
		parent::__construct('RCPatrolGuts');
		$wgHooks['OutputPageBeforeHTML'][] = array('RCPatrol::postParserCallback');

		// Reuben 1/26/2014: we were seeing JSON output broken in RCP by things
		// like trim() warnings, and this was easier to fix in short term. In long
		// term, RCP's javascript should deal with errors or connection problems
		// in the JSON responses!
		ini_set('display_errors', 0);
	}

	static function getUnpatrolledCount() {
		$dbr = wfGetDB(DB_SLAVE);
		$count = $dbr->selectField('recentchanges', array('count(*)'), array('rc_patrolled'=>0));
		$count = number_format($count, 0, ".", ",");
		$count .= wfMsg('rcpatrol_helplink');
		return $count;
	}

	function execute($par) {
		global $wgRequest, $wgOut;

		$t = Title::newFromText($wgRequest->getVal('target'));

		$wgOut->setArticleBodyOnly(true);
		if ($wgRequest->getVal('action') == 'permalink') {
			$result = array();
			$result['title'] = $t;
			$result['rchi'] = $wgRequest->getVal('rchi');
			$result['rclo'] = $wgRequest->getVal('rclow');
			$result['rcid'] = $wgRequest->getVal('rcid');
			$result['old'] = $wgRequest->getVal('old');
			$result['new'] = $wgRequest->getVal('new');
			$result['vandal'] = $wgRequest->getVal('vandal');
			$result['rc_cur_id'] = $t->getArticleID();
			$result = RCPatrolData::getListofEditors($result);
			$wgOut->addHTML("<div id='articletitle' style='display:none;'><a href='{$t->getLocalURL()}'>{$t->getFullText()}</a></div>");
			$oldTitle = $this->getContext()->getTitle();
			$this->getContext()->setTitle($result['title']);
			$d = new DifferenceEngine($this->getContext(), RCPatrol::cleanOldId($wgRequest->getVal('old')), $wgRequest->getVal('new'), $wgRequest->getVal('rcid'));
			$d->loadRevisionData();
			$this->getContext()->setTitle($oldTitle);
			$wgOut->addHTML("<div id='rc_header' class='tool_header'>");
			$wgOut->addHTML(RCPatrol::getButtons($result, $d->mNewRev));
			$wgOut->addHTML("</div>");
			$d->showDiffPage();
			$wgOut->disable();
			$response['html'] = $wgOut->getHTML();
			print_r(json_encode($response));
			return;
		}
		$a = new Article($t);
		if (!$wgRequest->getVal('grabnext')) {
			if (class_exists('RCTest') && RCTest::isEnabled() && $wgRequest->getVal('rctest')) {
				// Don't do anything if it's a test
			} elseif (!$wgRequest->getVal('skip') && $wgRequest->getVal('action') == 'markpatrolled') {
				$this->markRevisionsPatrolled($a);
			} elseif ($wgRequest->getVal('skip')) {
				// skip the article for now
				RCPatrol::skipArticle($t->getArticleID());
			}
		}

		$wgOut->clearHTML();
		$wgOut->redirect('');
		$result = RCPatrol::getNextArticleToPatrol($wgRequest->getVal('rcid'));
		$response = array();
		if ($result) {
			$rcTest = null;
			$testHtml = "";
			if (class_exists('RCTest') && RCTest::isEnabled()) {
				$rcTest = new RCTest();
				$testHtml = $rcTest->getTestHtml();
				/* Uncomment to debug rctest
				$response['testtime'] = $rcTest->isTestTime() ? 1 : 0;
				$response['totpatrol'] = $rcTest->getTotalPatrols();
				$response['adjpatrol'] = $rcTest->getAdjustedPatrolCount();
				global $wgCookiePrefix;
				$response['testcookie'] = $_COOKIE[$wgCookiePrefix . '_rct_a'];
				*/
			}
			$t = $result['title'];
			$wgOut->addHTML("<div id='bodycontents2'>");
			$titleText = RCTestStub::getTitleText($result, $rcTest);
			$wgOut->addHTML("<div id='articletitle' style='display:none;'>$titleText</div>");

			// Initialize the RCTest object. This is use to inject
			// tests into the RC Patrol queue.

			$d = RCTestStub::getDifferenceEngine($this->getContext(), $result, $rcTest);
			$d->loadRevisionData();
			$wgOut->addHTML("<div id='rc_header' class='tool_header'>");
			$wgOut->addHTML(RCPatrol::getButtons($result, $d->mNewRev, $rcTest));
			$wgOut->addHTML("</div>");
			$d->showDiffPage();
			$wgOut->addHtml($testHtml);

			$wgOut->addHTML("</div>");
			$response['unpatrolled'] = self::getUnpatrolledCount();
		} else {
			$wgOut->addWikiMsg( 'markedaspatrolledtext' );
			$response['unpatrolled'] = self::getUnpatrolledCount();
		}
		$wgOut->disable();
		header('Vary: Cookie');
		$response['html'] = $wgOut->getHTML();
		print_r(json_encode($response));
		return;
	}

	function markRevisionsPatrolled($article) {
		global $wgOut;
		$request = $this->getRequest();

		// some sanity checks
		$rcid = $request->getInt( 'rcid' );
		$rc = RecentChange::newFromId( $rcid );
		if ( is_null( $rc ) ) {
			throw new ErrorPageError( 'markedaspatrollederror', 'markedaspatrollederrortext' );
		}

		$user = $this->getUser();
		if ( !$user->matchEditToken( $request->getVal( 'token' ), $rcid ) ) {
			throw new ErrorPageError( 'sessionfailure-title', 'sessionfailure' );
		}

		// check if skip has been passed to us
		if ($request->getInt('skip') != 1) {
			// find his and lows
			$rcids = array();
			$rcids[] = $rcid;
			if ($request->getVal('rchi', null) && $request->getVal('rclow', null)) {
				$hilos = wfGetRCPatrols($rcid, $request->getVal('rchi'), $request->getVal('rclow'), $article->mTitle->getArticleID());
				$rcids = array_merge($rcids, $hilos);
			}
			$rcids = array_unique($rcids);
			foreach ($rcids as $id) {
				RecentChange::markPatrolled( $id, false);
			}
			wfRunHooks( 'MarkPatrolledBatchComplete', array(&$article, &$rcids, &$user));
		} else {
			RCPatrol::skipPatrolled($article);
		}
	}
}

class RCTestStub {
	// Inject the test diff if it's RCPatrol is supposed to show a test
	static function getDifferenceEngine($context, $result, &$rcTest) {
		if (class_exists('RCTest') && RCTest::isEnabled()) {
			if ($rcTest && $rcTest->isTestTime()) {
				$result = $rcTest->getResultParams();
				
				// okay, so let's blow away this cookie so that if 
				// the test fails to load (RC Patrol bug) the user
				// isn't cut off from another test
				$rcTest->setTestActive(false);
			}
		}

		return new DifferenceEngine($context, RCPatrol::cleanOldId($result['old']), $result['new']);
	}

	// Change the title to the test Title if RCPatrol is supposed to show a test
	static function getTitleText($result, &$rcTest) {
		if (class_exists('RCTest') && RCTest::isEnabled()) {
			if ($rcTest && $rcTest->isTestTime()) {
				$result = $rcTest->getResultParams();
			}
		}
		$t = $result['title'];
		return "<a href='{$t->getLocalURL()}'>" . $t->getFullText() . "</a>";
	}

	static function getThumbsUpButton($result, &$rcTest) {
		$button = "";
		if (class_exists('RCTest') && RCTest::isEnabled()) {
			if ($rcTest && $rcTest->isTestTime()) {
				$result = $rcTest->getResultParams();
			}
		}
		if (class_exists('ThumbsUp')) {
			//-1 is a secret code to our thumbs up function
			$result['old'] = ($result['old'] != 0) ? $result['old'] : -1;
			$button = ThumbsUp::getThumbsUpButton($result);
		}
		return $button;
	}
}

