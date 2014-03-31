<?php

/******
 * Class RatingsTool
 * Abstract class that manages the information regarding ratings.
 * Class must be extended for each new set of ratings that are created.
 */

abstract class RatingsTool {

	protected $tableName;
	protected $tablePrefix;
	protected $ratingType;
	protected $logType;
	protected $lowTable;
	protected $lowTablePrefix;
	protected $mContext;

	protected function __construct() {
        $this->reasonTable = 'rating_reason';
        $this->reasonPrefix = 'ratr_';
	}

	public function setContext($context){
		$this->mContext = $context;
	}

	public function restore($itemId, $user, $hi, $low) {

		$dbw = wfGetDB(DB_MASTER);
		$dbw->update($this->tableName, array("{$this->tablePrefix}isdeleted" => 0), array("{$this->tablePrefix}user_deleted" => $user, "{$this->tablePrefix}page" => $itemId, "{$this->tablePrefix}id <= $hi", "{$this->tablePrefix}id" >= $low));

	}

	public function getUnrestoredCount($itemId) {
		$dbr = wfGetDB(DB_SLAVE);
		$count = $dbr->selectField($this->tableName, 'count(*)', array("{$this->tablePrefix}page" => $itemId, "{$this->tablePrefix}isdeleted" => 1));

		return $count;
	}

	public function getAllRatedItems() {

		$dbr = wfGetDB(DB_SLAVE);

		$res = $dbr->select($this->tableName, "{$this->tablePrefix}page", array(), __FUNCTION__, array("GROUP BY" => "{$this->tablePrefix}page"));

		$results = array();
		foreach ( $res as $item) {
			$results[] = $item->{$this->tablePrefix.'page'};
		}

		return $results;
	}

	public function clearRatings($itemId, $user, $reason = null) {
		global $wgRequest, $wgLanguageCode;
		$dbw = wfGetDB( DB_MASTER );

		$max = $dbw->selectField($this->tableName, "max({$this->tablePrefix}id)", array("{$this->tablePrefix}page" => $itemId, "{$this->tablePrefix}isdeleted" => 0));
		$min = $dbw->selectField($this->tableName, "min({$this->tablePrefix}id)", array("{$this->tablePrefix}page" => $itemId, "{$this->tablePrefix}isdeleted" => 0));
		$count = $dbw->selectField($this->tableName, 'count(*)', array("{$this->tablePrefix}page" => $itemId, "{$this->tablePrefix}isdeleted" => 0));

		$dbw->update($this->tableName, array("{$this->tablePrefix}isdeleted" => 1, "{$this->tablePrefix}deleted_when = now()", "{$this->tablePrefix}user_deleted" => $user->getID()), array("{$this->tablePrefix}page" => $itemId, "{$this->tablePrefix}isdeleted" => 0));

		//FIX
		if ($wgLanguageCode == 'en')
			$dbw->delete($this->lowTable, array("{$this->lowTablePrefix}page" => $itemId));
		if ($reason == null)
			$reason = $wgRequest->getVal('reason');

		$this->logClear($itemId, $max, $min, $count, $reason);
	}

	function getRatingReasonResponse() {
        return wfMsg('ratesample_reason_submitted');
	}

	public function addRatingReason($ratrItem, $ratrUser, $ratrUserText, $ratrReason, $ratrType) {
		$dbw = wfGetDB(DB_MASTER);

        $ratrReason = strip_tags($ratrReason);

		$query = "INSERT into {$this->reasonTable} ({$this->reasonPrefix}item, {$this->reasonPrefix}user, {$this->reasonPrefix}user_text, {$this->reasonPrefix}text, {$this->reasonPrefix}type) VALUES (" .
			$dbw->addQuotes($ratrItem) . ", " . $dbw->addQuotes($ratrUser) . ", " . $dbw->addQuotes($ratrUserText) . ", " . $dbw->addQuotes($ratrReason) . ", " . $dbw->addQuotes($ratrType) . ")";
		$dbw->query($query);

		return $this->getRatingReasonResponse();
    }

	public function deleteRatingReason($ratrItem) {
		$dbw = wfGetDB(DB_MASTER);

		$dbw->delete($this->reasonTable, array('ratr_item' => $ratrItem), __METHOD__);
	}

	public function addRating($itemId, $user, $userText, $rating) {
		$dbw = wfGetDB(DB_MASTER);

		$month = date("Y-m");

		$query = "INSERT into {$this->tableName} ({$this->tablePrefix}page, {$this->tablePrefix}user, {$this->tablePrefix}user_text, {$this->tablePrefix}rating, {$this->tablePrefix}month) VALUES (" .
			$dbw->addQuotes($itemId) . ", " . $dbw->addQuotes($user) . ", " . $dbw->addQuotes($userText) . ", " . $dbw->addQuotes($rating) . ", " . $dbw->addQuotes($month) . ") ON DUPLICATE KEY UPDATE {$this->tablePrefix}rating = " . $dbw->addQuotes($rating);
		$dbw->query($query);

		return $this->getRatingResponse($itemId, $rating);
	}

	function showClearingInfo($title, $id, $selfUrl, $target) {
		global $wgOut, $wgLang, $wgUser;

		$sk = $wgUser->getSkin();
		$dbr = wfGetDB( DB_SLAVE );

		$wgOut->addHTML(wfMsg('clearratings_previous_clearings') . "<ul>");

		$loggingResults = $this->getLoggingInfo($title);

		foreach($loggingResults as $logItem) {
			$wgOut->addHTML("<li>" . $sk->makeLinkObj($logItem['userPage'], $logItem['userName']) . " ({$logItem['date']}): ");
			$wgOut->addHTML( $logItem['comment']);
			$wgOut->addHTML("</i>");
			if ($logItem['show']) {
				$wgOut->addHTML("(" . $sk->makeLinkObj($selfUrl, wfMsg('clearratings_previous_clearings_restore'),
					"page=$id&type={$this->ratingType}&hi={$logItem['params'][2]}&low={$logItem['params'][1]}&target=$target&user={$logItem['userId']}&restore=1") . ")");
			}
			$wgOut->addHTML("</li>");
		}
		$wgOut->addHTML("</ul>");

		if (count($loggingResults) == 0)
			$wgOut->addHTML(wfMsg('clearratings_previous_clearings_none') . "<br/><br/>");

		$res= $dbr->select($this->tableName,
			array ("COUNT(*) AS C", "AVG({$this->tablePrefix}rating) AS R"),
			array ("{$this->tablePrefix}page" => $id, "{$this->tablePrefix}isdeleted" => "0"),
			__METHOD__);

		if ($row = $dbr->fetchObject($res))  {
			$percent = $row->R * 100;
			$wgOut->addHTML($sk->makeLinkObj($title, $title->getFullText()) . "<br/><br/>"  .
				wfMsg('clearratings_number_votes') . " {$row->C}<br/>" .
				wfMsg('clearratings_avg_rating') . " {$percent} %<br/><br/>
						<form  id='clear_ratings' method='POST' action='{$selfUrl->getFullURL()}'>
							<input type=hidden value='$id' name='clearId'>
							<input type=hidden value='{$this->ratingType}' name='type'>
							<input type=hidden value='" . htmlspecialchars($target) ."' name='target'>
							" . wfMsg('clearratings_reason') . " <input type='text' name='reason' size='40'><br/><br/>
							<input type=submit value='" . wfMsg('clearratings_clear_submit') . "'>
						</form><br/><br/>
						");
		}
		$dbr->freeResult($res);
	}

	function showListRatings() {
		global $wgOut;

		// Just change this if you don't want users seeing the ratings
		$wgOut->setHTMLTitle('List Ratings - Accuracy Patrol');
		$wgOut->setPageTitle('List ' . ucfirst($this->ratingType) . ' Ratings');

		list( $limit, $offset ) = wfCheckLimits();
		$lrs = new ListRatingsPage();
		$lrs->setRatingTool($this);
		$lrs->doQuery( $offset, $limit );
	}

	function getRatings() {
		$dbr = wfGetDB(DB_SLAVE);
		$res = $dbr->select($this->tableName, array("{$this->tablePrefix}page", "AVG({$this->tablePrefix}rating) as R", 'count(*) as C'), array(), __METHOD__, array('GROUP BY' => "{$this->tablePrefix}page", 'ORDER BY' => 'R DESC', "LIMIT" => 50));

		return $res;
	}

	function showAccuracyPatrol() {
		global $wgOut;

		$wgOut->setHTMLTitle(wfMsg('accuracypatrol'));
		$wgOut->setPageTitle(ucfirst($this->ratingType) . " Accuracy Patrol");

		list( $limit, $offset ) = wfCheckLimits();
		$llr = $this->getQueryPage();
		return $llr->doQuery( $offset, $limit );
	}

	function getListRatingsSql() {
		return "SELECT {$this->tablePrefix}page, AVG({$this->tablePrefix}rating) as R, count(*) as C FROM {$this->tableName} WHERE {$this->tablePrefix}isDeleted = '0' GROUP BY {$this->tablePrefix}page ORDER BY R";
	}

	function getTablePrefix() {
		return $this->tablePrefix;
	}

	function getTableName() {
		return $this->tableName;
	}

	function getLowTableName() {
		return $this->lowTable;
	}

	function getLowTablePrefix() {
		return $this->lowTablePrefix;
	}

	protected abstract function logClear($itemId, $max, $min, $count, $reason);
	protected abstract function logRestore($itemId, $low, $hi, $reason, $count);
	protected abstract function getLoggingInfo($title);
	protected abstract function makeTitle($itemId);
	protected abstract function makeTitleFromId($itemId);
	protected abstract function getId($title);
	protected abstract function getRatingResponse($itemId, $rating);
	protected abstract function getRatingForm();
	protected abstract function getMobileRatingForm();
	protected abstract function getQueryPage();

}

/*
Ratings Reason DB Table

	CREATE TABLE `rating_reason` (
	`ratr_id` int(8) unsigned NOT NULL auto_increment,
	`ratr_item` varchar(255) default NULL,
	`ratr_user` int(5) unsigned NOT NULL default '0',
	`ratr_user_text` varchar(255) NOT NULL default '',
	`ratr_timestamp` timestamp NOT NULL default CURRENT_TIMESTAMP,
	`ratr_text` varchar(255) NOT NULL,
	`ratr_type` varchar(10) NOT NULL,
	PRIMARY KEY  (`ratr_id`),
	UNIQUE KEY `ratr_id` (`ratr_id`),
	KEY `ratr_timestamp` (`ratr_timestamp`)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8;

*/
/**
 * moved to ListRatings class
 *
class ListRatingsPage extends PageQueryPage {

	var $targets = array();
	var $ratingTool;

	function setRatingTool($ratingTool) {
		$this->ratingTool = $ratingTool;
	}

	function getName() {
		return 'ListRatings';
	}

	function isExpensive( ) { return false; }

	function isSyndicated() { return false; }

	function getOrder() {
		return '';
	}

	function getSQL() {
		return $this->ratingTool->getListRatingsSql();
	}

	function formatResult($skin, $result) {
		$pageField = "{$this->ratingTool->getTablePrefix()}page";
		$t = $this->ratingTool->makeTitleFromId($result->{$pageField});

		if($t == null)
			return "";

		return $skin->makeLinkObj($t, $t->getFullText() ) . " ({$result->C} votes, {$result->R} average)";
	}

}
*/
