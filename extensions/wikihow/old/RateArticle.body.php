<?php

/**
 * Query page to list low ratings pages
 */
class ListAccuracyPatrol extends PageQueryPage {

	var $targets = array();

	function getName() {
		return 'AccuracyPatrol';
	}

	function isExpensive( ) { return false; }

	function isSyndicated() { return false; }

	function getPageHeader( ) {
		global $wgOut;
		return $wgOut->parse( wfMsg( 'listlowratingstext' ) );
	}

	function getOrder() {
		return '';
	}

	function getSQL() {
		return "SELECT page_namespace, page_title, rl_avg, rl_count FROM rating_low, page WHERE rl_page=page_id ORDER BY rl_avg";
	}

	function formatResult( $skin, $result ) {
		$t = Title::makeTitle($result->page_namespace, $result->page_title);
		if ($t == null) return "";
		$avg = number_format($result->rl_avg * 100, 0);
		$cl = SpecialPage::getTitleFor( 'Clearratings', $t->getText() );
		return "{$skin->makeLinkObj($t, $t->getFullText() )} - ({$result->rl_count} votes, average: {$avg}% - {$skin->makeLinkObj($cl, 'clear')})";
	}

}

/**
 * The actual special page that displays the list of low accuracy / low
 * rating articles
 */
class AccuracyPatrol extends SpecialPage {

	function __construct() {
		parent::__construct( 'AccuracyPatrol' );
	}

	function execute($par) {
		global $wgOut;
		$wgOut->setHTMLTitle(wfMsg('accuracypatrol'));
		list( $limit, $offset ) = wfCheckLimits();
		$llr = new ListAccuracyPatrol();
		return $llr->doQuery( $offset, $limit );
	}
	
	/**
	 *
	 * This function is used for de-indexing purposes. All articles that show up on the
	 * page Special:AccuracyPatrol are de-indexed.
	 * 
	 */
	static function isInaccurate($articleId, &$dbr) {
		$row = $dbr->selectField('rating_low', 'rl_page', array('rl_page' => $articleId), __METHOD__);

		return $row !== false;
	}

}

/**
 * AJAX call class to actually rate an article.
 */
class RateArticle extends UnlistedSpecialPage {

	function __construct() {
		global $wgMessageCache, $wgLogTypes, $wgLogNames, $wgLogHeaders, $wgHooks;
		parent::__construct( 'RateArticle' );
		$wgHooks['AfterArticleDisplayed'][] = array("RateArticle::showForm");
		$wgHooks['ArticleDelete'][] = array("RateArticle::clearRatingsOnDelete");
		$wgLogTypes[] = 'accuracy';
		$wgLogNames['accuracy'] = 'accuracylogpage';
		$wgLogHeaders['accuracy'] = 'accuracylogtext';
	}

	function ratingsMove($a, $ot, $nt) {
		$dbw =& wfGetDB( DB_MASTER );
		$dbw->query( "UPDATE rating SET rat_page = {$ot->getArticleID()} WHERE rat_page={$nt->getArticleID()} AND rat_isdeleted=0;");
		wfDebug( "UPDATE rating SET rat_page = {$ot->getArticleID()} WHERE rat_page={$nt->getArticleID()} AND rat_isdeleted=0;");
		return true;
	}

	function clearRatingsOnDelete ($article, $user, $reason) {
		RateArticle::clearRatingForPage($article->getID(), $article->getTitle(), $user, "Deleting page");
		return true;
	}

	function execute($par) {
		global $wgRequest, $wgSitename, $wgLanguageCode;
		global $wgDeferredUpdateList, $wgOut, $wgUser;

		$fname = "wfRateArticle";

		$rat_page = $wgRequest->getVal("page_id");
		$rat_user = $wgUser->getID();
		$rat_user_text = $wgUser->getName();
		$rat_rating = $wgRequest->getVal('rating');
		$wgOut->disable();

		// disable ratings more than 5, less than 1
		if ($rat_rating > 5 || $rat_rating < 0) return;

		$dbw =& wfGetDB( DB_MASTER );
		$ts = wfTimestampNow(TS_MW);
		$month = substr($ts, 0, 4) . "-" . substr($ts, 4, 2);

		$dbw->query("INSERT INTO rating (rat_page, rat_user, rat_user_text, rat_rating, rat_month)
			VALUES (" . $dbw->addQuotes($rat_page) . ",
				$rat_user, "
				. $dbw->addQuotes($rat_user_text) . ", "
				. $dbw->addQuotes($rat_rating) . ", '$month'
			)
			ON DUPLICATE KEY UPDATE rat_rating=" .  $dbw->addQuotes($rat_rating));
	}

	function showForm() {
		global $wgOut, $wgArticle, $wgTitle, $wgShowRatings, $wgStylePath, $wgRequest;
		wfLoadExtensionMessages('RateArticle');

		$img_path =  $wgStylePath . "/common/images/rating";

		if ($wgArticle == null) return;
		$page_id = $wgArticle->getID();
		if ($page_id <= 0 ) return;
		$action = $wgRequest->getVal('action');
		if ($action != null &&  $action != 'view') return;
		if ($wgRequest->getVal('diff', null) != null) return;
		/* use this only for (Main) namespace pages that are not the main page - feel free to remove this... */
		$mainPageObj = Title::newMainPage();
		if ($wgTitle->getNamespace() != NS_MAIN
			|| $mainPageObj->getFullText() == $wgTitle->getFullText())
		{
			return;
		}

		$dbr = wfGetDB(DB_SLAVE);

		$images = array(0, 0, 0, 0, 0);

		// change this if you don't want people seeing the ratings
		if ($wgShowRatings) {
			$res = $dbr->query("SELECT AVG(rat_rating) AS R FROM rating WHERE rat_page=$page_id", __METHOD__);
			$avg = -1;
				while ( $row = $dbr->fetchObject( $res ) ) {
				$avg = $row->R;
			}
				$dbr->freeResult( $res );
			for ($x = 0; $x < 5; $x++) {
				// $avg = 3.1
				if ($avg >= ($x+1)) $images[$x] = 10;
				else if ($avg -$x < 0)  $images[$x] = 0;
				else $images[$x] = floor(( $avg - $x ) * 10);
			}
		}
		$target = Title::newFromText("RateArticle", NS_SPECIAL);
		$dt = $wgTitle->getTalkPage();

		$langKeys = array('ratearticle_rated', 'ratearticle_notrated', 'ratearticle_talkpage');
		$js = Wikihow_i18n::genJSMsgs($langKeys);

		$s .="$js <p>" . wfMsg('ratearticle_question') . "</p>
			<table style='width:100%;'><tr><td align='right'><a href='javascript:rateArticle(1);' id='gatAccuracyYes' class='button white_button' onmouseover='button_swap(this);' onmouseout='button_unswap(this);'>" . wfMsg('ratearticle_yes_button') . "</a></td><td align='left'><a href='javascript:rateArticle(0)' id='gatAccuracyNo' class='button white_button' onmouseover='button_swap(this);' onmouseout='button_unswap(this);'>" . wfMsg('ratearticle_no_button') . "</a></td></tr></table>";
		return $s;
	}

	function showFormRedesign() {
		global $wgOut, $wgArticle, $wgTitle, $wgShowRatings, $wgStylePath, $wgRequest;
		wfLoadExtensionMessages('RateArticle');

		$img_path =  $wgStylePath . "/common/images/rating";

		if ($wgArticle == null) return;
		$page_id = $wgArticle->getID();
		if ($page_id <= 0 ) return;
		$action = $wgRequest->getVal('action');
		if ($action != null &&  $action != 'view') return;
		if ($wgRequest->getVal('diff', null) != null) return;
		/* use this only for (Main) namespace pages that are not the main page - feel free to remove this... */
		$mainPageObj = Title::newMainPage();
		if ($wgTitle->getNamespace() != NS_MAIN
			|| $mainPageObj->getFullText() == $wgTitle->getFullText())
		{
			return;
		}

		$dbr = wfGetDB(DB_SLAVE);

		$images = array(0, 0, 0, 0, 0);

		// change this if you don't want people seeing the ratings
		if ($wgShowRatings) {
			$res = $dbr->query("SELECT AVG(rat_rating) AS R FROM rating WHERE rat_page=$page_id", __METHOD__);
			$avg = -1;
				while ( $row = $dbr->fetchObject( $res ) ) {
				$avg = $row->R;
			}
				$dbr->freeResult( $res );
			for ($x = 0; $x < 5; $x++) {
				// $avg = 3.1
				if ($avg >= ($x+1)) $images[$x] = 10;
				else if ($avg -$x < 0)  $images[$x] = 0;
				else $images[$x] = floor(( $avg - $x ) * 10);
			}
		}
		$target = Title::newFromText("RateArticle", NS_SPECIAL);
		$dt = $wgTitle->getTalkPage();

		$langKeys = array('ratearticle_rated', 'ratearticle_notrated', 'ratearticle_talkpage');
		$js = Wikihow_i18n::genJSMsgs($langKeys);

		$s .="$js <p id='page_rating'>" . wfMsg('ratearticle_question').
			"<a href='javascript:rateArticle(1);' id='gatAccuracyYes' class='button'>" . strtoupper(wfMsg('ratearticle_yes_button')) . "</a>
			<a href='javascript:rateArticle(0)' id='gatAccuracyNo' class='button'>" . strtoupper(wfMsg('ratearticle_no_button')) . "</a></p>";
		return $s;
	}

	function clearRatingForPage($id, $title, $user, $reason = null) {
		global $wgRequest, $wgLanguageCode;
		$dbw =& wfGetDB( DB_MASTER );

		$max = $dbw->selectField('rating', 'max(rat_id)', array("rat_page=$id", "rat_isdeleted=0"));
		$min = $dbw->selectField('rating', 'min(rat_id)', array("rat_page=$id", "rat_isdeleted=0"));
		$count = $dbw->selectField('rating', 'count(*)', array("rat_page=$id", "rat_isdeleted=0"));

		$dbw->query( 'update rating set rat_isdeleted = 1 , rat_deleted_when=now(), rat_user_deleted = ' . $user->getID() . " where rat_page=$id and rat_isdeleted=0;");
		if ($wgLanguageCode == 'en')
			$dbw->query( "delete from rating_low where rl_page=$id;");
		if ($reason == null)
			$reason = $wgRequest->getVal('reason');

		$params = array($id, $min, $max);
		$log = new LogPage( 'accuracy', true );
		$log->addEntry( 'accuracy', $title, wfMsg('clearratings_logsummary', $reason, $title->getFullText(), $count), $params );
	}
}

/**
 * Special page to clear the ratings of an article. Accessed via the list
 * of low ratings pages.
 */
class Clearratings extends SpecialPage {

	function __construct() {
		parent::__construct( 'Clearratings' );
	}

	function addClearForm($target) {
		global $wgOut;
		$blankme = Title::makeTitle(NS_SPECIAL, "Clearratings");
		$wgOut->addHTML("<b><font color=red>$err</font></b>
				<hr size='1'/><br/><form id='ratings' method='GET' action='{$blankme->getFullURL()}'>
				" . wfMsg('clearratings_input_title') . " <input type='text' name='target' value='" . htmlspecialchars($target) . "'><input type=submit>
				</form>");
	}

	function execute($par) {
		global $wgOut, $wgUser, $wgShowRatings, $wgRequest, $wgLang;
		$err = "";
		$target = isset( $par ) ? $par : $wgRequest->getVal( 'target' );
		$restore = $wgRequest->getVal('restore', null);
		$sk = $wgUser->getSkin();

		$wgOut->setHTMLTitle('Clear Ratings - Accuracy Patrol');
		$t = Title::newFromText($target);

		if ($t == '') {
			$wgOut->addHTML(wfMsg('clearratings_notitle'));
			$this->addClearForm($target);
			return;
		}
		$me =  SpecialPage::getTitleFor( 'Clearratings', $t->getText() );

		if ($wgUser->getID() == 0) {
			return;
		}

		if ($wgRequest->wasPosted()) {
			// clearing ratings
			$clear = $wgRequest->getVal('clear', null);
			$confirm = $wgRequest->getVal('confirm', null);
			if ($clear != null && $confirm == null && false) {
				$id = $t->getArticleID();
				$wgOut->addHTML(wfMsg('clearratings_clear_confirm_prompt', $sk->makeLinkObj($t, $t->getFullText())) . "
						<br/><br/>
						<form  id='clear_ratings' method='POST'>
							<input type=hidden value='$id' name='clear'>
							<input type=hidden value='true' name='confirm'>
							<input type=hidden value='" . htmlspecialchars($target) ."' name='target'>
							<input type=submit value='" . wfMsg('clearratings_clear_confirm') . "'>
						</form>");
				return;
			} else if ($clear != null) {
				RateArticle::clearRatingForPage($clear, $t,  $wgUser);
				$wgOut->addHTML(wfMsg('clearratings_clear_finished') . "<br/><br/>");
			}
		}

		if ($restore != null && $wgRequest->getVal('reason', null) == null) {
			$wgOut->addHTML(wfMsg('clearreating_reason_restore') . "<br/><br/>");
			$wgOut->addHTML("<form  id='clear_ratings' method='POST' action='{$me->getFullURL()}'>");
			$wgOut->addHTML(wfMsg('clearratings_reason') . " <input type='text' name='reason' size='40'><br/><br/>");
			foreach ($_GET as $k=>$v) {
				$wgOut->addHTML("<input type='hidden' value='$v' name='$k'/>");
			}
			$wgOut->addHTML("<input type='submit' value='" . wfMsg('clearratings_submit') . "'/>");
			$wgOut->addHTML("</form>");
			return;
		} else if ($restore != null) {
			$dbw =& wfGetDB( DB_MASTER );
			$user = $wgRequest->getVal('user');
			$page= $wgRequest->getVal('page');
			$u = new User();
			$u->setID($user);
			$up = $u->getUserPage();
			$hi = $wgRequest->getVal('hi');
			$low = $wgRequest->getVal('low');
			$count = $dbw->selectField('rating', 'count(*)', array("rat_page=$page", "rat_isdeleted=1"));
			$sql = "update rating set rat_isdeleted= 0 where rat_user_deleted = $user and rat_page=$page and rat_id <= $hi and rat_id >= $low;";
			$dbw->query($sql);
			$wgOut->addHTML("<br/><br/>" . wfMsg('clearratings_clear_restored', $sk->makeLinkObj($up, $u->getName()), $when) . "<br/><br/>");

			// add the log entry
			$t = Title::newFromId($page);
			$params = array($page, $min, $max);
			$log = new LogPage( 'accuracy', true );
			$reason = $wgRequest->getVal('reason');
			$log->addEntry( 'accuracy', $t, wfMsg('clearratings_logrestore', $reason, $t->getFullText(), $count), $params );
		}

		if ($target != null) {
			$t = Title::newFromText($target);
			$id = $t->getArticleID();

			if ($id == 0) {
				$err = wfMsg('clearratings_no_such_title', $wgRequest->getVal('target'));
			} else if ($t->getNamespace() != NS_MAIN) {
				$err = wfMsg('clearratings_only_main', $wgRequest->getVal('target'));
			} else {
				// clearing info
				$dbr =& wfGetDB( DB_MASTER );

				//  get log
				$res = $dbr->select (array('logging'),
						array('log_timestamp', 'log_user', 'log_comment', 'log_params'),
						array ('log_type' => 'accuracy', "log_title"=>$t->getDBKey() ),
						"wfSpecialClearratings"
					);
				$count = 0;
				$wgOut->addHTML(wfMsg('clearratings_previous_clearings') . "<ul>");
				while ($row = $dbr->fetchObject($res)) {
					$d = $wgLang->date($row->log_timestamp);
					$u = new User();
					$u->setID($row->log_user);
					$up = $u->getUserPage();
					$params = split("\n", $row->log_params);
					$wgOut->addHTML("<li>" . $sk->makeLinkObj($up, $u->getName()) . " ($d): ");
					$wgOut->addHTML( preg_replace('/<?p>/', '', $wgOut->parse($row->log_comment) ));
					$wgOut->addHTML("</i>");
					if (strpos($row->log_comment, wfMsg('clearratings_restore')) === false) {
						$wgOut->addHTML("(" . $sk->makeLinkObj($me, wfMsg('clearratings_previous_clearings_restore'),
							"page=$id&hi={$params[2]}&low={$params[1]}&target=$target&user={$row->log_user}&restore=1") . ")");
					}
					$wgOut->addHTML("</li>");
					$count++;
				}
				$wgOut->addHTML("</ul>");
				if ($count == 0)
					$wgOut->addHTML(wfMsg('clearratings_previous_clearings_none') . "<br/><br/>");

				$dbr->freeResult($res);

				$res= $dbr->select( array ("rating"),
					array ("COUNT(*) AS C", "AVG(rat_rating) AS R"),
					array ("rat_page" => $id, "rat_isdeleted" => "0"),
					__METHOD__);
				if ($row = $dbr->fetchObject($res))  {
					$percent = $row->R * 100;
					$wgOut->addHTML($sk->makeLinkObj($t, $t->getFullText()) . "<br/><br/>"  .
						wfMsg('clearratings_number_votes') . " {$row->C}<br/>" .
						wfMsg('clearratings_avg_rating') . " {$percent} %<br/><br/>
						<form  id='clear_ratings' method='POST' action='{$me->getFullURL()}'>
							<input type=hidden value='$id' name='clear'>
							<input type=hidden value='" . htmlspecialchars($target) ."' name='target'>
							" . wfMsg('clearratings_reason') . " <input type='text' name='reason' size='40'><br/><br/>
							<input type=submit value='" . wfMsg('clearratings_clear_submit') . "'>
						</form><br/><br/>
						");
				}
				$dbr->freeResult($res);

				$ap = Title::makeTitle(NS_SPECIAL, "AccuracyPatrol");
				$wgOut->addHTML($sk->makeLinkObj($ap, "Return to accuracy patrol"));
			}
		}
		$this->addClearForm($target);
	}

}

/**
 * List the ratings of some set of pages
 */
class ListRatings extends SpecialPage {

	function __construct() {
		parent::__construct( 'ListRatings' );
	}

	function execute($par) {
		global $wgOut, $wgUser, $wgShowRatings;

		// Just change this if you don't want users seeing the ratings
		$wgOut->setHTMLTitle('List Ratings - Accuracy Patrol');
		$wgOut->addHTML("<ol>");
		$sk = $wgUser->getSkin();
		//TODO add something for viewing ratings 51-100, 101-150, etc
		$dbr = wfGetDB(DB_SLAVE);
		$res = $dbr->query("SELECT rat_page, AVG(rat_rating) AS R,
								COUNT(*) AS C 
							FROM rating
							GROUP BY rat_page
							ORDER BY R DESC
							LIMIT 50", __METHOD__);
		 while ( $row = $dbr->fetchObject( $res ) ) {
			 $t = Title::newFromID($row->rat_page);
			 if ($t == null) continue; $wgOut->addHTML("<li>" . $sk->makeLinkObj($t, $t->getFullText() ) . " ({$row->C}, {$row->R})</li>");
		 }
		$dbr->freeResult( $res );
		$wgOut->addHTML("</ol>");
	}

}
