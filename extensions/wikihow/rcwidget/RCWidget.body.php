<?php

class RCWidget extends UnlistedSpecialPage {

	private static $mBots = null;

	public function __construct() {
		parent::__construct('RCWidget');
	}

	private static function addRCElement(&$widget, &$count, $obj) {
		global $wgLanguageCode, $wgContLang;
		if ((strlen(strip_tags($obj['text'])) < 100) &&
			 (strlen($obj['text']) > 0)) {
			if($wgLanguageCode == "zh") {
				$obj['text'] = $wgContLang->convert($obj['text']);
				if(isset($obj['ts'])) {
					$obj['ts'] = $wgContLang->convert($obj['ts']);		
				}
			}
			$widget[$count++] = $obj;
		}
	}

	private static function getBotIDs() {
		if (!is_array(self::$mBots)) {
			self::$mBots = WikihowUser::getBotIDs();
		}
		return self::$mBots; 
	}

	private static function filterLog(&$widget, &$count, $row) {

		$bots = self::getBotIDs();
 		if (in_array($row->log_user, $bots)) {
			return;
		}

		$obj = "";
		$real_user = $row->log_user_text;

		if (preg_match('/\d+\.\d+\.\d+\.\d+/',$real_user)){
			$wuser = wfMessage('rcwidget_anonymous_visitor')->text();
			$wuserLink = '/wikiHow:Anonymous';
		} else {
			$wuser = $real_user;
			$wuserLink = '/User:'.$real_user;
		}

		if (preg_match('/\d+\.\d+\.\d+\.\d+/',$row->log_title)){
			$destUser = wfMessage('rcwidget_anonymous_visitor')->text();
			$destUserLink = '/User:'.$row->log_title;
		} else {
			$destUser = $row->log_title;
			$destUserLink = '/'.$row->log_title;
		}

		switch ($row->log_type) {
			case 'patrol':

			$userLink = '<a href="'.$wuserLink.'">'.$wuser.'</a>';
			if ($row->log_namespace == NS_USER) {
					$obj['type'] = 'patrol';
					$obj['ts'] = Misc::getDTDifferenceString($row->log_timestamp);
					$resourceLink = '<a href="/User:'.$row->log_title.'">'.preg_replace('/-/',' ',$destUser).'</a>';
					$obj['text'] = wfMessage('action_patrolled', $userLink, $resourceLink)->text();
				} else if ($row->log_namespace == NS_USER_TALK) {
					$obj['type'] = 'patrol';
					$obj['ts'] = Misc::getDTDifferenceString($row->log_timestamp);
					$resourceLink = '<a href="/User_talk:'.$row->log_title.'">'.preg_replace('/-/',' ',$destUser).'</a>';
					$obj['text'] = wfMessage('action_patrolled', $userLink, $resourceLink)->text();
				} else if ($row->log_namespace == NS_TALK) {
					$obj['type'] = 'patrol';
					$obj['ts'] = Misc::getDTDifferenceString($row->log_timestamp);
					$resourceLink = '<a href="/Discussion:'.$row->log_title.'">'.preg_replace('/-/',' ',$destUser).'</a>';
					$obj['text'] = wfMessage('action_patrolled', $userLink, $resourceLink)->text();
				} else if ($row->log_namespace == NS_MAIN) {
					$obj['type'] = 'patrol';
					$obj['ts'] = Misc::getDTDifferenceString($row->log_timestamp);
					$resourceLink = '<a href="/'.$row->log_title.'">'.preg_replace('/-/',' ',$destUser).'</a>';
					$obj['text'] = wfMessage('action_patrolled', $userLink, $resourceLink)->text();
				}
				self::addRCElement($widget, $count, $obj);
				break;
			case 'nap':
				$obj['type'] = 'nab';
				$obj['ts'] = Misc::getDTDifferenceString($row->log_timestamp);
				$userLink  = '<a href="'.$wuserLink.'">'.$wuser.'</a>';
				$resourceLink = '<a href="/'.$row->log_title.'">'.preg_replace('/-/',' ',$row->log_title).'</a>';
				$obj['text'] = wfMessage('action_boost', $userLink, $resourceLink)->text();
				self::addRCElement($widget, $count, $obj);
				break;
			case 'upload':
				if ( ($row->log_action == 'upload') && ($row->log_namespace == 6)) {
					$obj['type'] = 'image';
					$obj['ts'] = Misc::getDTDifferenceString($row->log_timestamp);
					$userLink = '<a href="'.$wuserLink.'">'.$wuser.'</a>';
					if (strlen($row->log_title) > 25) {
						$resourceLink = '<a href="/Image:'.$row->log_title.'">'.substr($row->log_title,0,25).'...</a>';
					} else {
						$resourceLink = '<a href="/Image:'.$row->log_title.'">'.$row->log_title.'</a>';
					}
					$obj['text'] = wfMessage('action_image', $userLink, $resourceLink)->text();
					self::addRCElement($widget, $count, $obj);
				}
				break;
			case 'vidsfornew':
				if ( ($row->log_action == 'added') && ($row->log_namespace == 0)) {
					$obj['type'] = 'video';
					$obj['ts'] = Misc::getDTDifferenceString($row->log_timestamp);
					$userLink = '<a href="'.$wuserLink.'">'.$wuser.'</a>';
					$resourceLink = '<a href="/'.$row->log_title.'">'.preg_replace('/-/',' ',$row->log_title).'</a>';
					$obj['text'] = wfMessage('action_addedvideo', $userLink, $resourceLink)->text();
					self::addRCElement($widget, $count, $obj);
				}
				break;
		}
	}

	private static function filterRC(&$widget, &$count, $row) {
		$bots = self::getBotIDs();
 		if (in_array($row->rc_user, $bots)) {
			return;
		}
	
		$obj = "";
		if (preg_match('/\d+\.\d+\.\d+\.\d+/',$row->rc_user_text)){
			$wuser = wfMessage('rcwidget_anonymous_visitor')->text();;
			$wuserLink = '/wikiHow:Anonymous';
		} else {
			$wuser = $row->rc_user_text;
			$wuserLink = '/User:'.$row->rc_user_text;
		}

		if (preg_match('/\d+\.\d+\.\d+\.\d+/',$row->rc_title)){
			$destUser = wfMessage('rcwidget_anonymous_visitor')->text();;
			$destUserLink = '/User:'.$row->rc_title;
		} else {
			$destUser = $row->rc_title;
			$destUserLink = '/'.$row->rc_title;
		}

		switch ($row->rc_namespace) {
			case NS_MAIN: //MAIN
				if (preg_match('/^New page:/',$row->rc_comment)) {
					$obj['type'] = 'newpage';
					$obj['ts'] = Misc::getDTDifferenceString($row->rc_timestamp);
					$userLink = '<a href="'.$wuserLink.'">'.$wuser.'</a>';
					$resourceLink = '<a href="'.$destUserLink.'">'.preg_replace('/-/',' ',$destUser).'</a>';
					$obj['text'] = wfMessage('action_newpage', $userLink, $resourceLink)->text();
					self::addRCElement($widget, $count, $obj);
				} else if (preg_match('/^categorization/',$row->rc_comment)) {
					$obj['type'] = 'categorized';
					$obj['ts'] = Misc::getDTDifferenceString($row->rc_timestamp);
					$userLink = '<a href="'.$wuserLink.'">'.$wuser.'</a>';
					$resourceLink = '<a href="'.$destUserLink.'">'.preg_replace('/-/',' ',$destUser).'</a>';
					$obj['text'] = wfMessage('action_categorized', $userLink, $resourceLink)->text();;
					self::addRCElement($widget, $count, $obj);
				} else if ( (preg_match('/^\/* Steps *\//',$row->rc_comment)) ||
								(preg_match('/^\/* Tips *\//',$row->rc_comment)) ||
								(preg_match('/^\/* Warnings *\//',$row->rc_comment)) ||
								(preg_match('/^\/* Things You\'ll Need *\//',$row->rc_comment)) ||
								(preg_match('/^\/* Ingredients *\//',$row->rc_comment)) ||
								(preg_match('/^$/',$row->rc_comment)) ||
								(preg_match('/^Quick edit/',$row->rc_comment)) ) {
					$obj['type'] = 'edit';
					$obj['ts'] = Misc::getDTDifferenceString($row->rc_timestamp);
					$userLink = '<a href="'.$wuserLink.'">'.$wuser.'</a>';
					$resourceLink = '<a href="'.$destUserLink.'">'.preg_replace('/-/',' ',$destUser).'</a>';
					$obj['text'] .= wfMessage('action_edit', $userLink, $resourceLink)->text();
					self::addRCElement($widget, $count, $obj);
				}
				break;
			case NS_TALK: //DISCUSSION
				if (!preg_match('/^Reverts edits by/',$row->rc_comment)) {
					if (preg_match('/^Marking new article as a Rising Star from From/',$row->rc_comment)) {
						$obj['type'] = 'risingstar';
						$obj['ts'] = Misc::getDTDifferenceString($row->rc_timestamp);
						$userLink= '<a href="'.$wuserLink.'">'.$wuser.'</a>';
						$resourceLink = '<a href="'.$destUserLink.'">'.preg_replace('/-/',' ',$destUser).'</a>';
						$obj['text'] = wfMessage('action_risingstar', $userLink, $resourceLink)->text();
					} else if ($row->rc_comment == '') {
						$obj['type'] = 'discussion';
						$obj['ts'] = Misc::getDTDifferenceString($row->rc_timestamp);
						$userLink = '<a href="'.$wuserLink.'">'.$wuser.'</a>';
						$resourceLink = '<a href="/Discussion:'.$row->rc_title.'">'.preg_replace('/-/',' ',$destUser).'</a>';
						$obj['text'] = wfMessage('action_discussion', $userLink, $resourceLink)->text();
					}
					self::addRCElement($widget, $count, $obj);
				}
				break;
			case NS_USER_TALK: //USER_TALK
				if (!preg_match('/^Revert/',$row->rc_comment)) {
					$obj['type'] = 'usertalk';
					$obj['ts'] = Misc::getDTDifferenceString($row->rc_timestamp);
					$userLink = '<a href="'.$wuserLink.'">'.$wuser.'</a>';
					$resourceLink = '<a href="/User_talk:'.$row->rc_title.'">'.preg_replace('/-/',' ',$destUser).'</a>';
					$obj['text'] = wfMessage('action_usertalk', $userLink, $resourceLink)->text();
					self::addRCElement($widget, $count, $obj);
				}
				break;
			case NS_USER_KUDOS: //KUDOS
				$obj['type'] = 'kudos';
				$obj['ts'] = Misc::getDTDifferenceString($row->rc_timestamp);
				$userLink = '<a href="'.$wuserLink.'">'.$wuser.'</a>';
				$resourceLink = '<a href="/User_kudos:'.$row->rc_title.'">'.preg_replace('/-/',' ',$destUser).'</a>';
				$obj['text'] = wfMessage('action_fanmail', $userLink, $resourceLink)->text();
				self::addRCElement($widget, $count, $obj);
				break;
			case NS_VIDEO: //VIDEO
				// I KNOW I HAVE VIDEO FOR BOTH RC & LOGGING. LOGGING ONLY DOESN'T SEEM TO CATCH EVERYTHING.
				if (preg_match('/^adding video/',$row->rc_comment)) {
					$obj['type'] = 'video';
					$obj['ts'] = Misc::getDTDifferenceString($row->rc_timestamp);
					$userLink = '<a href="'.$wuserLink.'">'.$wuser.'</a>';
					$resourceLink = '<a href="'.$destUserLink.'">'.preg_replace('/-/',' ',$destUser).'</a>';
					$obj['text'] = wfMessage('action_addedvideo', $userLink, $resourceLink)->text();
					self::addRCElement($widget, $count, $obj);
				}
				break;
			case NS_SPECIAL: //OTHER
				if (preg_match('/^New user/',$row->rc_comment)) {
					$obj['type'] = 'newuser';
					$obj['ts'] = Misc::getDTDifferenceString($row->rc_timestamp);
					$userLink = '<a href="/User:'.$row->rc_user_text.'">'.$wuser.'</a>';
					$obj['text'] = wfMessage('action_newuser', $userLink)->text();
					self::addRCElement($widget, $count, $obj);
				}
				break;

		}

		return $obj;
	}


	public static function showWidget() {
?>
	<div id='rcwidget_divid'>
		<a class="rc_help rcw-help-icon" title="<?php echo wfMessage('rc_help')->text();?>" href="/<?= wfMessage('rcchange-patrol-article')->text() ?>"></a>
		<h3><span class="weather" onclick="location='/index.php?title=Special:Recentchanges&hidepatrolled=1';" style="cursor:pointer;"></span><span onclick="location='/Special:Recentchanges';" style="cursor:pointer;"><?= wfMessage('recentchanges')->text();?></span></h3>
		<div id='rcElement_list' class='widgetbox'>
			<div id='IEdummy'></div>
		</div>
		<div id='rcwDebug' style='display:none'>
			<input id='testbutton' type='button' onclick='rcTest();' value='test'>
			<input id='stopbutton' type='button' onclick='rcTransport();' value='stop'>
			<span id='teststatus' ></span>
		</div>
	</div>
<?php
	}

	public static function getProfileWidget() {
		$html = "<div id='rcwidget_divid'>
		<h3>My Recent Activity</h3>
		<div id='rcElement_list' class='widgetbox'>
			<div id='IEdummy'></div>
		</div>
		<div id='rcwDebug' style='display:none'>
			<input id='testbutton' type='button' onclick='rcTest();' value='test'>
			<input id='stopbutton' type='button' onclick='rcTransport();' value='stop'>
			<span id='teststatus' ></span>
		</div>
	</div>";

		return $html;
	}

	public static function showWidgetJS() {
?>
	<script type="text/javascript" >
		var rc_URL = '/Special:RCWidget';
		var rc_ReloadInterval = 60000;

		$(window).load(rcwLoad);
	</script>
<?
	}

	public function execute($par) {
		global $wgOut, $wgRequest, $wgHooks;

		wfLoadExtensionMessages('RCWidget');
		$wgHooks['AllowMaxageHeaders'][] = array('RCWidget::allowMaxageHeadersCallback');

		$maxAgeSecs = 60;
		$wgOut->setSquidMaxage( $maxAgeSecs );
		$wgRequest->response()->header( 'Cache-Control: s-maxage=' . $maxAgeSecs . ', must-revalidate, max-age=' . $maxAgeSecs );
		$future = time() + $maxAgeSecs;
		$wgRequest->response()->header( 'Expires: ' . gmdate('D, d M Y H:i:s T', $future) );

		$wgOut->setArticleBodyOnly(true);
		$wgOut->sendCacheControl();

		$userId = $wgRequest->getVal('userId');

		$data = self::pullData($userId);
		$jsonData = json_encode($data);
		$jsFunc = $wgRequest->getVal('function', '');
		if ($jsFunc) {
			print $jsFunc . '( ' . $jsonData . ' );';
		} else {
			print $jsonData;
		}
	}

	/**
	 *
	 * 
	 */
	public static function getLastPatroller(&$dbr){
		$startdate = strtotime($period);
		$starttimestamp = date('YmdG',$startdate) . floor(date('i',$startdate)/10) . '00000';

		$sql = "SELECT log_user, log_timestamp FROM logging FORCE INDEX (times) WHERE log_type='patrol' ORDER BY log_timestamp DESC LIMIT 1";
		$res = $dbr->query($sql);
		$row = $res->fetchObject();

		$rcuser = array();
		$rcuser['id'] = $row->log_user;
		$rcuser['date'] = wfTimeAgo($row->log_timestamp);

		return $rcuser;
	}

	public static function getTopPatroller(&$dbr, $period='7 days ago'){
		$startdate = strtotime($period);
		$starttimestamp = date('YmdG',$startdate) . floor(date('i',$startdate)/10) . '00000';
		// fix Patrol Recent Changes Votebot showing bug.
		$bots = self::getBotIDs();
		$bot = " AND log_user NOT IN (" . $dbr->makeList($bots) . ")";
		$sql = "SELECT log_user, count(log_user) as rc_count, MAX(log_timestamp) as recent_timestamp FROM logging FORCE INDEX (times) WHERE log_type='patrol' and log_timestamp >= '$starttimestamp' $bot GROUP BY log_user ORDER BY rc_count DESC";
		$res = $dbr->query($sql);
		$row = $res->fetchObject();
		$rcuser = array();
		$rcuser['id'] = $row->log_user;
		$rcuser['date'] = wfTimeAgo($row->recent_timestamp);

		return $rcuser;
	}

	public static function pullData($user = 0) {
		global $wgMemc;

		$cachekey = wfMemcKey('rcwidget', $user);

		//wfLoadExtensionMessages('RCWidget');

		// for logged in users whose requests bypass varnish, this data is
		// cached for $cacheSecs
		$cacheSecs = 15;

		$widget = $wgMemc->get($cachekey);
		if (is_array($widget)) {
			return $widget;
		}

		$widget = array();

		$dbr = wfGetDB(DB_SLAVE);

		$cutoff_unixtime = time() - ( 30 * 86400 ); // 30 days
		$cutoff = $dbr->timestamp( $cutoff_unixtime );
		$currenttime = $dbr->timestamp( time() );

		// QUERY RECENT CHANGES TABLE
		$sql = "SELECT rc_timestamp,rc_user_text,rc_namespace,rc_title,rc_comment,rc_patrolled FROM recentchanges";
		if($user != 0 && $user != null) {
			$sql .= " WHERE rc_user = {$user} ";
		}
		else if (sizeof($bots) > 0) {
			$sql .= " WHERE rc_user NOT IN (" . implode(',', $bots) . ")";
		}
		$sql .= " ORDER BY rc_timestamp DESC";

		// QUERY LOGGING TABLE
		$logsql = "SELECT log_id,log_timestamp,log_user,log_user_text,log_namespace,log_title,log_comment,log_type,log_action 
					FROM logging ";
		if($user != 0 && $user != null) {
			$logsql .= " WHERE log_user = {$user} ";
		}
		$logsql .= "ORDER BY log_id DESC";


		if($user == 0) {
			$widget = self::processDataRCWidget($logsql, $sql, $currenttime);
		}
		else {
			$widget = self::processDataUserActivity($logsql, $sql, $currenttime);
		}

		$wgMemc->set($cachekey, $widget, $cacheSecs);

		return $widget;
	}

	private function processDataUserActivity($logsql, $sql, $currenttime) {
		$dbr = wfGetDB(DB_SLAVE);

		$sql = $dbr->limitResult($sql, 200, 0);
		$res = $dbr->query( $sql, __METHOD__ );

		$logsql = $dbr->limitResult($logsql, 200, 0);
		$logres = $dbr->query( $logsql, __METHOD__ );

		$count = 0;
		$widget['servertime'] = $currenttime;

		// MERGE TABLES and FILTER RESULTS
		$rl = $logres->fetchObject();
		$rr = $res->fetchObject();
		$maxCount = 12;
		$patrol_prevUser = "";
		$patrol_prevTitle = "";
		while (true) {
			if ($rr && $rl) {
				if ($rl->log_timestamp > $rr->rc_timestamp) {
					if ($rl->log_action != 'patrol') {
						self::filterLog($widget, $count, $rl);
					} elseif ($rl->log_action == 'patrol') {
						if ($patrol_prevUser != $rl->log_user
							|| $patrol_prevTitle != $rl->log_title)
						{
							self::filterLog($widget, $count, $rl);
						}
						$patrol_prevUser = $rl->log_user;
						$patrol_prevTitle = $rl->log_title;
					}
					$rl = $logres->fetchObject();
				} else {
					if ($rr->rc_namespace != NS_USER_KUDOS) {
						self::filterRC($widget, $count, $rr);
					} elseif ($rr->rc_namespace == NS_USER_KUDOS) {
						self::filterRC($widget, $count, $rr);
					}
					$rr = $res->fetchObject();
				}
			} else if ($rr) {
				if ($rr->rc_namespace != NS_USER_KUDOS) {
					self::filterRC($widget, $count, $rr);
				} elseif ($rr->rc_namespace == NS_USER_KUDOS) {
					self::filterRC($widget, $count, $rr);
				}
				$rr = $res->fetchObject() ;
			} else if ($rl) {
				if ($rl->log_action != 'patrol') {
					self::filterLog($widget, $count, $rl);
				} elseif ($rl->log_action == 'patrol') {
					if ($patrol_prevUser != $rl->log_user
						|| $patrol_prevTitle != $rl->log_title)
					{
						self::filterLog($widget, $count, $rl);
					}
					$patrol_prevUser = $rl->log_user;
					$patrol_prevTitle = $rl->log_title;
				}
				$rl = $logres->fetchObject();
			} else {
				break;
			}

			if($count > $maxCount)
				break;
		}
		$res->free();
		$logres->free();

		$count = self::getUnpatrolledEdits($dbr);
		$widget['unpatrolled'] = $count;

		return $widget;
	}

	private function processDataRCWidget($logsql, $sql, $currenttime) {
		$dbr = wfGetDB(DB_SLAVE);

		$sql = $dbr->limitResult($sql, 200, 0);
		$res = $dbr->query( $sql, __METHOD__ );

		$logsql = $dbr->limitResult($logsql, 200, 0);
		$logres = $dbr->query( $logsql, __METHOD__ );

		$count = 0;
		$widget['servertime'] = $currenttime;

		// MERGE TABLES and FILTER RESULTS
		$rl = $logres->fetchObject();
		$rr = $res->fetchObject();
		$patrol_limit = 5;
		$patrol_count = 0;
		$patrol_prevUser = "";
		$patrol_prevTitle = "";
		$kudos_count = 0;
		$kudos_limit = 3;
		while (true) {

			if ($rr && $rl) {
				if ($rl->log_timestamp > $rr->rc_timestamp) {
					if ($rl->log_action != 'patrol') {
						self::filterLog($widget, $count, $rl);
					} elseif ($rl->log_action == 'patrol'
						&& $patrol_count < $patrol_limit)
					{
						if ($patrol_prevUser != $rl->log_user
							|| $patrol_prevTitle != $rl->log_title)
						{
							self::filterLog($widget, $count, $rl);
						}
						$patrol_prevUser = $rl->log_user;
						$patrol_prevTitle = $rl->log_title;
						$patrol_count++;
					}
					$rl = $logres->fetchObject();
				} else {
					if ($rr->rc_namespace != NS_USER_KUDOS) {
						self::filterRC($widget, $count, $rr);
					} elseif ($rr->rc_namespace == NS_USER_KUDOS
						&& $kudos_count < $kudos_limit)
					{
						self::filterRC($widget, $count, $rr);
						$kudos_count++;
					}
					$rr = $res->fetchObject();
				}
			} else if ($rr) {
				if ($rr->rc_namespace != NS_USER_KUDOS) {
					self::filterRC($widget, $count, $rr);
				} elseif ($rr->rc_namespace == NS_USER_KUDOS
					&& $kudos_count < $kudos_limit)
				{
					self::filterRC($widget, $count, $rr);
					$kudos_count++;
				}
				$rr = $res->fetchObject();
			} else if ($rl) {
				if ($rl->log_action != 'patrol') {
					self::filterLog($widget, $count, $rl);
				} elseif ($rl->log_action == 'patrol'
					&& $patrol_count < $patrol_limit)
				{
					if ($patrol_prevUser != $rl->log_user
						|| $patrol_prevTitle != $rl->log_title)
					{
						self::filterLog($widget, $count, $rl);
					}
					$patrol_prevUser = $rl->log_user;
					$patrol_prevTitle = $rl->log_title;
					$patrol_count++;
				}
				$rl = $logres->fetchObject();
			} else {
				break;
			}
		}
		$res->free();
		$logres->free();

		$count = self::getUnpatrolledEdits($dbr);
		$widget['unpatrolled'] = $count;

		return $widget;
	}

	public static function getUnpatrolledEdits(&$dbr) {
		// Query table for unpatrolled edits
		$count = $dbr->selectField('recentchanges',
                array('count(*)'),
                array('rc_patrolled=0'));
		return $count;
	}
		
	public static function allowMaxageHeadersCallback() {
		return false;
	}

}


