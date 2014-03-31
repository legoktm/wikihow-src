<?

/*******************
 *
 * Contains all the specific information relating
 * to ratings of samples. Article ratings happen on
 * only on desktop.
 *
 ******************/

class RatingSample extends RatingsTool {

	var $titlePrefix;

	public function __construct() {
        parent::__construct();

		$this->ratingType = 'sample';
		$this->tableName = "ratesample";
		$this->tablePrefix = "rats_";
		$this->logType = "acc_sample";
		$this->titlePrefix = "Sample/";
		$this->lowTable = "ratesample_low";
		$this->lowTablePrefix = "rsl_";
	}

	function logClear($itemId, $max, $min, $count, $reason){
		$title = $this->makeTitle($itemId);

		if($title) {
			$params = array($itemId, $min, $max);
			$log = new LogPage( $this->logType, true );
			$log->addEntry( $this->logType, $title, wfMsg('clearratings_logsummary', $reason, $title->getFullText(), $count), $params );
		}
	}

	function getLoggingInfo($title) {
		global $wgLang, $wgOut;

		$dbr = wfGetDB( DB_SLAVE );

		//  get log
		$res = $dbr->select ('logging',
			array('log_timestamp', 'log_user', 'log_comment', 'log_params'),
			array ('log_type' => $this->logType, "log_title"=>$title->getDBKey() ),
			__METHOD__
		);

		$results = array();
		foreach($res as $row) {
			$item = array();
			$item['date'] = $wgLang->date($row->log_timestamp);
			$u = User::newFromId($row->log_user);
			$item['userId'] = $row->log_user;
			$item['userName'] = $u->getName();
			$item['userPage'] = $u->getUserPage();
			$item['params'] = explode("\n", $row->log_params);
			$item['comment'] = preg_replace('/<?p>/', '', $wgOut->parse($row->log_comment) );
			$item['show'] = (strpos($row->log_comment, wfMsg('clearratings_restore')) === false);

			$results[] = $item;
		}

		return $results;
	}

	function logRestore($itemId, $low, $hi, $reason, $count) {
		$title = $this->makeTitle($itemId);
		$params = array($itemId, $low, $hi);
		$log = new LogPage( $this->logType, true );
		$log->addEntry( $this->logType, $title, wfMsg('clearratings_logrestore', $reason, $title->getFullText(), $count), $params );
	}

	function makeTitle($itemId) {
		return Title::newFromText("Sample/$itemId");
	}

	function makeTitleFromId($itemId) {
		return $this->makeTitle($itemId);
	}

	function getId($title) {
		$dbKey = $title->getDBKey();
		$name = substr($dbKey, strlen($this->titlePrefix));

		$dbr = wfGetDB(DB_SLAVE);
		$res = $dbr->selectField('dv_sampledocs', 'dvs_doc', array('dvs_doc' => $name));

		if($res === false)
			return 0;
		else
			return $name;
	}

	function getRatingResponse($itemId, $rating) {
        if($rating == 0)
            return $this->getRatingReasonForm($itemId);
        else
            return wfMsg('ratesample_rated');
	}

	function getRatingReasonForm($itemId) {
		$html = "<h4>Thanks for letting us know.  What can we do to make this sample better?</h4>
			<div id='sample_accuracy_form'>
                <form id='rating_feeback' name='rating_reason' method='GET'>
                    <textarea class='input_med' name=submit></textarea>
                    <input type='button' class='rating_submit button primary' value='Submit' onClick='ratingReason(this.form.elements[\"submit\"].value, \"{$itemId}\", \"sample\");'>
                </form>
			</div>";
		return $html;
	}

	function getRatingForm() {
		wfLoadExtensionMessages('RateItem');

		$html = "<div id='sample_rating'>
			<h4>" . wfMessage('ratesample_question')->text() . "</h4>
			<div id='sample_accuracy_buttons'>
				<a href='#' id='sampleAccuracyYes' class='button secondary'>Yes</a>
				<a href='#' id='sampleAccuracyNo' class='button secondary'>No</a>
			</div>
			</div>";
		return $html;
	}

	function getMobileRatingForm() {
		//nothing yet, we don't show on mobile
	}

	function getQueryPage() {
		return new ListSampleAccuracyPatrol();
	}
}

/*****
 *
	CREATE TABLE `ratesample` (
	`rats_id` int(8) unsigned NOT NULL auto_increment,
	`rats_page` varchar(255) default NULL,
	`rats_user` int(5) unsigned NOT NULL default '0',
	`rats_user_text` varchar(255) NOT NULL default '',
	`rats_month` varchar(7) NOT NULL default '',
	`rats_timestamp` timestamp NOT NULL default CURRENT_TIMESTAMP,
	`rats_rating` tinyint(1) unsigned NOT NULL default '0',
	`rats_isdeleted` tinyint(3) unsigned NOT NULL default '0',
	`rats_user_deleted` int(10) unsigned default NULL,
	`rats_deleted_when` timestamp NOT NULL default '0000-00-00 00:00:00',
	PRIMARY KEY  (`rats_page`,`rats_id`),
	UNIQUE KEY `rats_id` (`rats_id`),
	UNIQUE KEY `user_month_id` (`rats_page`,`rats_user_text`,`rats_month`),
	KEY `rat_timestamp` (`rats_timestamp`)
	) ENGINE=InnoDB DEFAULT CHARSET=latin1

	CREATE TABLE `ratesample_low` (
	`rsl_page` varchar(255) default NULL,
	`rsl_avg` double NOT NULL default '0',
	`rsl_count` tinyint(4) NOT NULL default '0'
    ) ENGINE=InnoDB DEFAULT CHARSET=latin1
	ALTER TABLE ratesample_low CHANGE rsl_count rsl_count int not null default '0';
    
 */

/*
 * moved directly to the AccuracyPatrol class
 *
class ListSampleAccuracyPatrol extends PageQueryPage {

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
		$minvotes = wfMsg('list_bottom_rated_pages_min_votes');
		$avg = wfMsg('list_bottom_rated_pages_avg');

		return "SELECT rsl_page, rsl_avg, rsl_count FROM ratesample_low WHERE rsl_count >= $minvotes AND rsl_avg <= $avg ORDER BY rsl_avg";
	}

	function formatResult($skin, $result) {

		$t = Title::newFromText("sample/$result->rsl_page");
		if ($t == null)
			return "";

		$avg = number_format($result->rsl_avg * 100, 0);
		$cl = SpecialPage::getTitleFor( 'Clearratings', $result->rsl_page );

		return "{$skin->makeLinkObj($t, $t->getFullText() )} - ({$result->rsl_count} votes, average: {$avg}% - {$skin->makeLinkObj($cl, 'clear', 'type=sample')})";
	}


}
*/
