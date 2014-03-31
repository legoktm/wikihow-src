<?php

/**
 create table hydra_trial(
	ht_id int primary key auto_increment,
	ht_experiment int NOT NULL,
	ht_group int NOT NULL,
	ht_percent int(3) NOT NULL,
	index idx_experiment(ht_experiment)
 );

 CREATE TABLE `hydra_group` (
	`hg_id` int(11) NOT NULL auto_increment,
  `hg_name` varchar(200) default NULL,
  `hg_time_started` varchar(14) default NULL,
  `hg_time_ended` varchar(14) default NULL,
  `hg_paused` tinyint(1) default '0',
  `hg_start_edit int default `1`,
   PRIMARY KEY  (`hg_id`)
 );

 create table hydra_cohort_user (
	hcu_user int NOT NULL,
	hcu_group int(11) NOT NULL,
	hcu_experiment int NOT NULL,
	hcu_time_added varchar(14) NOT NULL,
	hcu_main_edits int NOT NULL default 0,
	hcu_notool_main_edits int NOT NULL default 0,
	primary key(hcu_user, hcu_group),
	index idx_time_added(hcu_time_added)
 );
 
 */
class Hydra extends UnlistedSpecialPage {
	public function __construct() {
		parent::__construct("Hydra");
	}
	const EDIT_GOAL=10;
	const EDIT_GOAL2=1;

	/**
	 * Execute function
	 */
	public function execute() {
		global $wgOut, $wgUser, $wgRequest, $wgSharedDB;

		// Only show for staff
		if ($wgUser->isBlocked()) {
			$wgOut->blockedPage();
			return;
		}
		$userGroups = $wgUser->getGroups();
		if ($wgUser->getID() == 0 ||  !in_array('staff', $userGroups))  {
			$wgOut->setRobotpolicy( 'noindex,nofollow' );
			$wgOut->showErrorPage( 'nosuchspecialpage', 'nospecialpagetext' );
			return;
		}

		// Display the list of users in a trial
		$group = $wgRequest->getVal('group', NULL);
		$experiment = $wgRequest->getval('experiment', NULL);
		if($group != NULL && $experiment != NULL) {
			$dbr = wfGetDB(DB_SLAVE);
			$sql = 'select user_name, hcu_main_edits from hydra_cohort_user'
						.' JOIN ' . $wgSharedDB .'.user on user_id=hcu_user'
						.' WHERE hcu_group=' . $dbr->addQuotes($group) . ' AND hcu_experiment=' . $dbr->addQuotes($experiment);
			$res = $dbr->query($sql, __METHOD__);
			$wgOut->addHTML("<table>");
			$wgOut->addHTML("<tr><td>User Name</td><td>Edit Count</td></tr>");
			foreach($res as $row) {
				$wgOut->addHTML("<tr><td><a href=\"/User:" . $row->user_name	 . "\">". $row->user_name . "</a></td><td>" . $row->hcu_main_edits . "</td></tr>\n");
			}
			$wgOut->addHTML("</table>");
			return;
		}

		$dbr = wfGetDB(DB_SLAVE);
		// Get stats for edits by users in a trial group
		$sql = 'select hg_name, hcu_experiment as experiment_name, sum(hcu_main_edits >= ' . self::EDIT_GOAL . ') as success, sum(hcu_main_edits >= ' . self::EDIT_GOAL2 . ') as success2, count(hcu_user) as ct, avg(hcu_main_edits) as avg_edits, hg_id, ht_percent,ht_id from hydra_cohort_user '
					 . ' JOIN hydra_group on hg_id = hcu_group '
					 . ' LEFT JOIN hydra_trial on ht_group = hcu_group AND ht_experiment = hcu_experiment '
					 . ' JOIN ' . $wgSharedDB . '.user on user_id=hcu_user'
							 . ' GROUP BY hcu_group, hcu_experiment';
		$res = $dbr->query($sql, __METHOD__);

		$trials = array();
		foreach($res as $row) {
			$trials[$row->hg_name][] = array('experiment' => $row->experiment_name,'group' => $row->hg_id,  'success' => $row->success, 'success2' => $row->success2, 'count' => $row->ct, 'avg_total' => round($row->avg_edits,2), 'pct' => $row->ht_percent, 'trial' => $row->ht_id);	
		}
		EasyTemplate::set_path( dirname(__FILE__) );
		$vars = array('trials' => $trials);
		$tmpl = EasyTemplate::html("Hydra.experiments.tmpl.php", $vars);
		$wgOut->addHTML($tmpl);
	}

	// Check if experiment is enabled
	public static function isEnabled($experiment) {
		global $wgUser;

		if(!$wgUser || $wgUser->isAnon()) {
			return false;	
		}
		$dbr = wfGetDB(DB_SLAVE);
		$ct = $dbr->selectField('hydra_cohort_user',array('count(*)'), array('hcu_user' => $wgUser->getId(), 'hcu_experiment' => $experiment));
		if($ct > 0) {
			return(true);	
		}
		else {
			return(false);	
		}
	}

	/*
	 * Check if the edit is done by a tool rather than a regular edit
	 */
	private static function isTool() {
		$referer = $_SERVER["HTTP_REFERER"];
		return(!preg_match("@/index.php@",$referer));
	}

	static function groupUser($uid, $editNo) {
		$dbr = wfGetDB(DB_SLAVE);
		$dbw = wfGetDB(DB_MASTER);

		// Get the percentage we use each experiment in each group
		$query = "select ht_id, ht_percent, ht_group, ht_experiment FROM hydra_trial JOIN hydra_group on hg_id=ht_group WHERE hg_paused=0 AND hg_start_edit=" . $dbr->addQuotes($editNo);
		$res = $dbr->query($query, __METHOD__);
		$cohort = array();
		foreach($res as $row) {
			$cohort[$row->ht_group][] = array('id' => $row->ht_id, 'pct' => $row->ht_percent, 'experiment' => $row->ht_experiment);
		}
		$controls = 0;
		foreach($cohort as $group => $cus) {
			$rn = mt_rand(0,99);
			$control = true;
			$pct = 0;	
			// Assign the user to a trial randomly based upon its odds
			foreach($cus as $cu) {
				$pct += $cu['pct'];
				if($pct > $rn) {
					$sql = 'insert ignore into hydra_cohort_user(hcu_user, hcu_group, hcu_experiment, hcu_time_added) values(' . $dbw->addQuotes($uid) . ',' . $dbw->addQuotes($group) . ',' . $dbr->addQuotes($cu['experiment']) . ',' . $dbr->addQuotes(wfTimestampNow()) . ')';
					$dbw->query($sql, __METHOD__);

					// Run hook with experiment we have activated
					wfRunHooks('HydraStartExperiment', array($cu['experiment']));
					$control = false;
					break;	
				}
			}
			// Assign user to control group
			if($control) {
			$sql = 'insert ignore into hydra_cohort_user(hcu_user, hcu_group, hcu_experiment, hcu_time_added) values(' . $dbw->addQuotes($uid) . "," . $dbw->addQuotes($group) . "," . $dbw->addQuotes('control') . "," . $dbw->addQuotes(wfTimestampNow()) . ')';
				$dbw->query($sql, __METHOD__);
				$controls++;
			}
		}
	}

	// ArticleSaveComplete hook to begin tracking users
	static function onArticleSaveComplete(&$article, &$user, $text, $summary, $minor,$a,$b,&$flags,$revision) {
		global $wgUser, $wgIgnoreNamespacesForEditCount;
		$uid = $wgUser->getId();
	
		// We start tracking on the first non ignored-namespace edit. We wish to be consistent with the edit count
		// We ignore edits that don't change anything by checking for a NULL revision
		if(!in_array($article->getTitle()->getNamespace(), $wgIgnoreNamespacesForEditCount)  && $revision!= NULL && $wgUser->isLoggedIn() && $wgUser->getEditCount() == 0) {
			self::groupUser($uid, 1);
		}
		if($article->getTitle()->getNamespace() == NS_MAIN) {
			$dbw = wfGetDB(DB_MASTER);
			$isTool = self::isTool();

			$updates = array("hcu_main_edits = hcu_main_edits + 1");
			if(!$isTool) {
				$updates[] = "hcu_notool_main_edits = hcu_notool_main_edits + 1";
			}
			$dbw->update('hydra_cohort_user', $updates, array("hcu_user" => $user->getId()));

			$res = $dbw->select('hydra_cohort_user', array('hcu_user','hcu_experiment', 'hcu_notool_main_edits', 'hcu_main_edits'), array('hcu_user' => $user->getId()));

			foreach($res as $row) {
				wfRunHooks('HydraOnMainEdit', array($row->hcu_experiment, $row->hcu_notool_main_edits, $row->hcu_main_edits, $isTool));
			}
		}
		return true;
	}

	static function onNewAccount($user) {
		self::groupUser($user->getId(), 0);
		return(true);
	}
}
