<?php

include_once(dirname(__FILE__) . '/RatingArticle.php');
include_once(dirname(__FILE__) . '/RatingSample.php');

/**
 * page that handles the reason for a rating
 */
class RatingReason extends UnlistedSpecialPage {

	function __construct() {
		parent::__construct( 'RatingReason' );
	}

	function execute($par) {
		global $wgRequest, $wgOut, $wgUser;

		$ratrItem = $wgRequest->getVal("item_id");
		$ratrUser = $wgUser->getID();
		$ratrUserText = $wgUser->getName();
		$ratrReason = $wgRequest->getVal('reason');
		$ratrType = $wgRequest->getVal('type');
		$target = intval($target);
		$wgOut->disable();

		wfLoadExtensionMessages('RateItem'); 
		$ratingTool = new RatingSample();
        echo $ratingTool->addRatingReason($ratrItem, $ratrUser, $ratrUserText, $ratrReason, $ratrType);
	}
}
/**
 * The actual special page that displays the list of low accuracy / low
 * rating articles
 */
class AccuracyPatrol extends QueryPage {

	function __construct( $name = 'AccuracyPatrol' ) {
		parent::__construct( $name );
		//is this for articles or samples?
		$this->forSamples = (strpos(strtolower($_SERVER['REQUEST_URI']),'sample')) ? true : false;
		
		list( $limit, $offset ) = wfCheckLimits();
		$this->limit = $limit;
		$this->offset = $offset;
	}

	var $targets = array();
	var $sqlQuery;
	var $forSamples;

	function setSql($sql) {
		$this->sqlQuery = $sql;
	}

	function getName() {
		return 'AccuracyPatrol';
	}

	function isExpensive( ) { return false; }

	function isSyndicated() { return false; }

	function getPageHeader( ) {
		global $wgOut;
		$headname = ($this->forSamples) ? 'Sample Accuracy Patrol' : 'Article Accuracy Patrol';
		$wgOut->setPageTitle($headname);
		return $wgOut->parse( wfMessage( 'listlowratingstext' )->text() );
	}

	function getOrderFields() {
		if ($this->forSamples) {
			$order = array('rsl_avg');
		}
		else {
			$order = array('rl_avg');
		}
		return $order;
	}

	function getSQL() {
		if ($this->forSamples) {
			$minvotes = wfMessage('list_bottom_rated_pages_min_votes');
			$avg = wfMessage('list_bottom_rated_pages_avg');

			$sql = "SELECT rsl_page, rsl_avg, rsl_count FROM ratesample_low WHERE rsl_count >= $minvotes AND rsl_avg <= $avg";
		}
		else {
			$sql = "SELECT page_namespace, page_title, rl_avg, rl_count FROM rating_low, page WHERE rl_page=page_id";
		}
		return $sql;
	}

	function formatResult($skin, $result) {
		if ($this->forSamples) {
			$t = Title::newFromText("sample/$result->rsl_page");
			if ($t == null)
				return "";

			$avg = number_format($result->rsl_avg * 100, 0);
			$cl = SpecialPage::getTitleFor( 'Clearratings', $result->rsl_page );

			//need to tell the linker that the title is known otherwise it adds redlink=1 which eventually breaks the link
			$link = "{$skin->link($t, $t->getFullText(), array(), array(), array('known') )} - ({$result->rsl_count} votes, average: {$avg}% - {$skin->makeLinkObj($cl, 'clear', 'type=sample')})";
		}
		else {
			$t = Title::makeTitle($result->page_namespace, $result->page_title);
			if ($t == null)
				return "";
			$avg = number_format($result->rl_avg * 100, 0);
			$cl = SpecialPage::getTitleFor( 'Clearratings', $t->getText() );
			$link = "{$skin->makeLinkObj($t, $t->getFullText() )} - ({$result->rl_count} votes, average: {$avg}% - {$skin->makeLinkObj($cl, 'clear',  'type=article')})";
		}
		return $link;
	}

	/**
	 *
	 * This function is used for de-indexing purposes. All articles that show up on the
	 * page Special:AccuracyPatrol are de-indexed. This is only used for
	 *
	 */
	static function isInaccurate($articleId, &$dbr) {
		$row = $dbr->selectField('rating_low', 'rl_page', array('rl_page' => $articleId), __METHOD__);

		return $row !== false;
	}

}

/**
 * AJAX call class to actually rate an item.
 * Currently we can rate: articles and samples
 */
class RateItem extends UnlistedSpecialPage {

	function __construct() {
		global $wgHooks;
		parent::__construct( 'RateItem' );
		$wgHooks['ArticleDelete'][] = array("RateItem::clearRatingsOnDelete");
	}

	/**
	 *
	 * This function can only get called when an article gets deleted
	 *
	 **/
	function clearRatingsOnDelete($wikiPage, $user, $reason) {
		$ratingTool = new RatingArticle();
		$ratingTool->clearRatings($wikiPage->getId(), $user, "Deleting page");
		return true;
	}

	function execute($par) {
		global $wgRequest, $wgOut, $wgUser;

		$ratType = $wgRequest->getVal("type", 'article');

		$rateItem = new RateItem();
		$ratingTool = $rateItem->getRatingTool($ratType);

		$ratId = $wgRequest->getVal("page_id");
		$ratUser = $wgUser->getID();
		$ratUserext = $wgUser->getName();
		$ratRating = $wgRequest->getVal('rating');
		$wgOut->disable();

		// disable ratings more than 5, less than 1
		if ($ratRating > 5 || $ratRating < 0) return;

		wfLoadExtensionMessages('RateItem');

		echo $ratingTool->addRating($ratId, $ratUser, $ratUserext, $ratRating);

	}

	static function showForm($type) {
		$rateItem = new RateItem();
		$ratingTool = $rateItem->getRatingTool($type);

		return $ratingTool->getRatingForm();
	}

	function showMobileForm($type) {
		$ratingTool = $this->getRatingTool($type);

		return $ratingTool->getMobileRatingForm();
	}

	function getRatingTool($type) {
		switch(strtolower($type)) {
			case 'article':
				$rTool = new RatingArticle();
				break;
			case 'sample':
				$rTool = new RatingSample();
				break;
		}
		$rTool->setContext($this->getContext());
		return $rTool;
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

	function addClearForm($target, $type, $err) {
		global $wgOut;
		$blankme = Title::makeTitle(NS_SPECIAL, "Clearratings");

		$tmpl = new EasyTemplate( dirname(__FILE__) );
		$tmpl->set_vars(array('actionUrl' => $blankme->getFullURL(), 'target' => htmlspecialchars($target), 'type' => $type, 'err' => $err));

		$wgOut->addHTML($tmpl->execute('selectForm.tmpl.php'));
	}

	function execute($par) {
		global $wgOut, $wgUser, $wgRequest, $wgLang;
		$err = "";
		$target = isset( $par ) ? $par : $wgRequest->getVal( 'target' );
		$restore = $wgRequest->getVal('restore', null);
		$sk = $wgUser->getSkin();

		$wgOut->setHTMLTitle('Clear Ratings - Accuracy Patrol');
		$type = $wgRequest->getVal('type', 'article');

		$rateItem = new RateItem();
		$ratingTool = $rateItem->getRatingTool($type);

		if ($ratingTool) $t = $ratingTool->makeTitle($target);
		if ($t == '') {
			$wgOut->addHTML(wfMsg('clearratings_notitle'));
			$this->addClearForm($target, $type, $err);
			return;
		}
		$me =  SpecialPage::getTitleFor( 'Clearratings', $target );
		if ($wgUser->getID() == 0) {
			return;
		}

		if ($wgRequest->wasPosted()) {
			// clearing ratings
			$clearId = $wgRequest->getVal('clearId', null);

			if ($clearId != null) {
				$ratingTool->clearRatings($clearId, $wgUser);
				$wgOut->addHTML(wfMsg('clearratings_clear_finished') . "<br/><br/>");
			}
		}


		if ($restore != null && $wgRequest->getVal('reason', null) == null) {
			//ask why the user wants to resotre
			$tmpl = new EasyTemplate( dirname(__FILE__) );
			$tmpl->set_vars(array('postUrl' => $me->getFullURL(), 'params' => $_GET,));
			$wgOut->addHTML($tmpl->execute('restore.tmpl.php'));
			return;
		} else if ($restore != null) {
			$user = $wgRequest->getVal('user');
			$page = $wgRequest->getVal('page');
			$reason = $wgRequest->getVal('reason');
			$u = User::newFromId($user);
			$up = $u->getUserPage();
			$hi = $wgRequest->getVal('hi');
			$low = $wgRequest->getVal('low');

			$count = $ratingTool->getUnrestoredCount($page);

			$ratingTool->restore($page, $user, $hi, $low);

			$wgOut->addHTML("<br/><br/>" . wfMsg('clearratings_clear_restored', $sk->makeLinkObj($up, $u->getName()), $when) . "<br/><br/>");

			// add the log entry
			$ratingTool->logRestore($page, $low, $hi, $reason, $count);
		}


		if ($target != null && $type != null) {
			$id = $ratingTool->getId($t);
			if ($id === 0) {
				$err = wfMsg('clearratings_no_such_title', $target);
			} else if ($type == "article" && $t->getNamespace() != NS_MAIN) {
				$err = wfMsg('clearratings_only_main', $target);
			} else {
				// clearing info
				$ratingTool->showClearingInfo($t, $id, $me, $target);
				$ap = Title::makeTitle(NS_SPECIAL, "AccuracyPatrol");
				$wgOut->addHTML($sk->makeLinkObj($ap, "Return to accuracy patrol"));
			}
		}

		$this->addClearForm($target, $type, $err);
	}

}

/**
 * List the ratings of some set of pages
 */
class ListRatings extends QueryPage {

	function __construct( $name = 'ListRatings' ) {
		parent::__construct( $name );
		//is this for articles or samples?
		if (strpos(strtolower($_SERVER['REQUEST_URI']),'sample')) {
			$this->forSamples = true;
			$this->tablePrefix = 'rats_';
			$this->tableName = 'ratesample';
		}
		else {
			$this->forSamples = false;
			$this->tablePrefix = 'rat_';
			$this->tableName = 'rating';
		}
		list( $limit, $offset ) = wfCheckLimits();
		$this->limit = $limit;
		$this->offset = $offset;
	}
	
	var $targets = array();
	var $tablePrefix = '';

	function getName() {
		return 'ListRatings';
	}

	function isExpensive( ) { return false; }

	function isSyndicated() { return false; }

	function getOrderFields() {
		return array('R');
	}

	function getSQL() {
		return "SELECT {$this->tablePrefix}page, AVG({$this->tablePrefix}rating) as R, count(*) as C FROM {$this->tableName} WHERE {$this->tablePrefix}isDeleted = '0' GROUP BY {$this->tablePrefix}page";
	}

	function formatResult($skin, $result) {
		if ($this->forSamples) {
			$t = Title::newFromText('sample/'.$result->rats_page);
		}
		else {
			$t = Title::newFromId($result->rat_page);
		}

		if($t == null)
			return "";

		if($this->forSamples) {
			//need to tell the linker that the title is known otherwise it adds redlink=1 which eventually breaks the link
			return $skin->link($t, $t->getFullText(), array(), array(), array('known') ) . " ({$result->C} votes, {$result->R} average)";
		}
		else {
			return $skin->makeLinkObj($t, $t->getFullText() ) . " ({$result->C} votes, {$result->R} average)";
		}
	}

	function getPageHeader( ) {
		global $wgOut;
		if ($this->forSamples) $wgOut->setPageTitle('List Rated Sample Pages'); 
		return;
	}
}
