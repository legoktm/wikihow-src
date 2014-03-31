<?

class TweetItForwardWidget extends DashboardWidget {

	public function __construct($name) {
		parent::__construct($name);
	}

	/*
	 * Returns the start link for this widget
	 */
	public function getStartLink($showArrow, $widgetStatus) {
		if ($widgetStatus == DashboardWidget::WIDGET_ENABLED)
			$link = "<a href='/Special:TweetItForward' class='comdash-start'>Start";
		elseif ($widgetStatus == DashboardWidget::WIDGET_LOGIN)
			$link = "<a href='/Special:Userlogin?returnto=Special:TweetItForward' class='comdash-login'>Login";
		elseif ($widgetStatus == DashboardWidget::WIDGET_DISABLED)
			$link = "<a href='/Use-Twitter' class='comdash-start'>Start";
		if ($showArrow)
			$link .= " <img src='" . wfGetPad('/skins/owl/images/actionArrow.png') . "' alt=''>";
		$link .= "</a>";

		return $link;
	}

	public function getMWName() {
		return "tif";
	}

	public function getExtraInternalHTML() {
		return '<img id="tif_twitter_bird" src="' . wfGetPad('/skins/common/images/twitter_newbird_blue.png') . '" />';
	}

	/**
	 * Provides the content in the footer of the widget
	 * for the last contributor to this widget
	 */
	public function getLastContributor(&$dbr) {
		$res = $dbr->select('twitterreplier_reply_tweets', 
			array('wikihow_user_id',
				'UNIX_TIMESTAMP(created_on) AS created'),
			array(),
			__METHOD__,
			array("ORDER BY" => "created_on DESC", "LIMIT" => 1));
		if ($res) {
			$row = $res->fetchObject();
			$res->free();

			$ts = wfTimestamp(TS_MW, $row->created);
			return $this->populateUserObject($row->wikihow_user_id, $ts);
		} else {
			return '';
		}
	}

	/**
	 * Provides the content in the footer of the widget
	 * for the top contributor to this widget
	 */
	public function getTopContributor(&$dbr) {
		$startdate = strtotime("7 days ago");

		$sql = "SELECT wikihow_user_id, 
					UNIX_TIMESTAMP(created_on) AS created, 
					count(*) AS c 
				FROM twitterreplier_reply_tweets 
				WHERE created_on >= FROM_UNIXTIME($startdate) 
				GROUP BY wikihow_user_id 
				ORDER BY c DESC 
				LIMIT 1";
		$res = $dbr->query($sql);
		if ($res) {
			$row = $res->fetchObject();
			$res->free();

			$ts = wfTimestamp(TS_MW, $row->created);
			$userid = $row->wikihow_user_id;
		} else {
			$ts = 0;
			$userid = 0;
		}
		return $this->populateUserObject($userid, $ts);
	}

	/**
	 * Provides names of javascript files used by this widget.
	 */
	public function getJSFiles() {
		return array('TweetItForwardWidget.js');
	}

	/**
	 * Provides names of CSS files used by this widget.
	 */
	public function getCSSFiles() {
		return array('TweetItForwardWidget.css');
	}

	/*
	 * Returns the number of images left to be added.
	 */
	public function getCount(&$dbr) {
		return " ";
	}

	public function getUserCount() {
		global $wgUser;
		if ($wgUser) {
			$userid = $wgUser->getID();
			$startdate = strtotime("7 days ago");
			$sql = "SELECT count(*) as count
					WHERE created_on >= FROM_UNIXTIME($startdate) AND
						wikihow_user_id = '$userid'";
			$dbr = wfGetDB(DB_SLAVE);
			$res = $dbr->query($sql);
			$row = $res->fetchObject();
			$count = $row ? $row->count : 0;
		} else {
			$count = 0;
		}
		return $count;
	}

	public function getAdjustedCount() {
		return " ";
	}

	public function getAverageCount() {
		$sql = "SELECT avg(c) AS avg, count(*) as c
				WHERE created_on >= FROM_UNIXTIME($startdate)
				GROUP BY wikihow_user_id";
		$dbr = wfGetDB(DB_SLAVE);
		$res = $dbr->query($sql);
		$row = $res->fetchObject();
		return $row ? $row->avg : 0;
	}

	/**
	 * Get data from the Leaderboard class for this widget
	 */
	public function getLeaderboardData(&$dbr, $starttimestamp) {
		return array();

	}

	public function getLeaderboardTitle() {
		return "<a href='/Special:TweetItForward'>" . $this->getTitle() . "</a>";
	}

	public function isAllowed($isLoggedIn, $userId=0) {
		return true;
	}

}
