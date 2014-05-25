<?php

if ( !defined('MEDIAWIKI') ) exit;

class Leaderboard extends SpecialPage {

	/* this whole class could be cpeaned up to be use classes properly
		something akin to SpecialPage and QueryPage */
	function __construct() {
		parent::__construct( 'Leaderboard' );

		$this->limit = 30;
	}

	/**
	 * Query for Articles Written
	 **/
	function getTabs($section, $tab) {
			$tabs = '';
			if ($section == 'Writing') {
				$tab1 = $tab=='articles_written' ? "class='on'" : "";
				$tab2 = $tab=='risingstars_received' ? "class='on'" : "";
				$tab3 = $tab=='requested_topics' ? "class='on'" : "class='tab_129'";
				$tab4 = $tab=='spellchecked' ? "class='on'" : "";

				//will uncomment when spellchecker goes live
				$tabs = " <ul class='sub_tabs'> <li><a href='/Special:Leaderboard/articles_written' $tab1 >Articles Written</a></li> <li><a href='/Special:Leaderboard/risingstars_received' $tab2 >Rising Stars</a></li> <li><a href='/Special:Leaderboard/requested_topics' $tab3>Requests</a></li> <li><a href='/Special:Leaderboard/spellchecked' $tab4 >Spell Checked</a></li></ul>";
				return $tabs;

			} else if ($section == "RCNAB") {
				$tab1 = $tab=='articles_nabed' ? "class='on'" : "";
				$tab2 = $tab=='risingstars_nabed' ? "class='on'" : "";
				$tab3 = $tab=='rc_edits' ? "class='on'" : "";
				$tab4 = $tab=='rc_quick_edits' ? "class='on'" : "";
				$tab5 = $tab=='qc' ? "class='on'" : "";

				$tabs = " <ul class='sub_tabs'> <li><a href='/Special:Leaderboard/articles_nabed' $tab1 >Articles NABed</a></li> <li><a href='/Special:Leaderboard/risingstars_nabed' $tab2 >RS NABed</a></li><li><a href='/Special:Leaderboard/rc_edits' $tab3 >Edits Patrolled</a></li> <li><a href='/Special:Leaderboard/rc_quick_edits' $tab4 >RC Quick Edits</a></li> <li><a href='/Special:Leaderboard/qc' $tab5 >Top Guardians</a></li> </ul>";
				return $tabs;
			} else if ($section == "Other") {
				$tab1 = $tab=='total_edits' ? "class='on'" : "";
				$tab2 = $tab=='thumbs_up' ? "class='on'" : "";
				$tab3 = $tab=='articles_categorized' ? "class='on'" : "";
				$tab1a = $tab=='methodguardian' ? "class='on'" : "";
				$tab1b = $tab=='methodeditor' ? "class='on'" : "";
				$tab1c = $tab=='welcomewagon' ? "class='on'" : "";

				$tabs = " <ul class='sub_tabs'> <li><a href='/Special:Leaderboard/total_edits' $tab1 >All Edits</a></li> <li><a href='/Special:Leaderboard/thumbs_up' $tab2 >Thumbs Up</a></li> <li><a href='/Special:Leaderboard/articles_categorized' $tab3 >Categorization</a></li><li><a href='/Special:Leaderboard/methodguardian' $tab1a >Method Guardian</a></li><li><a href='/Special:Leaderboard/methodeditor' $tab1b >Method Editor</a></li><li><a href='/Special:Leaderboard/welcomewagon' $tab1c >Welcome Wagon</a></li> </ul>";
				return $tabs;
			} elseif ($section == "Greenhouse") {
				$tab1 = $tab=='repair_format' ? "class='on'" : "";
				$tab2 = $tab=='repair_stub' ? "class='on'" : "";
				$tab3 = $tab=='repair_cleanup' ? "class='on'" : "";
				$tab4 = $tab=='repair_copyedit' ? "class='on'" : "";

				$tabs .= "<ul class='sub_tabs'><li><a href='/Special:Leaderboard/repair_format' $tab1 >Formatting</a></li> <li><a href='/Special:Leaderboard/repair_cleanup' $tab3 >Cleanup</a></li> <li><a href='/Special:Leaderboard/repair_copyedit' $tab4 >Copyedit</a></li></ul>";
				return $tabs;
			} elseif ($section == "Imagevideo") {
				$tab1 = $tab=='images_added' ? "class='on'" : "";
				$tab2 = $tab=='videos_reviewed' ? "class='on'" : "";

				$tabs = " <ul class='sub_tabs'><li><a href='/Special:Leaderboard/images_added' $tab1 >Images Added</a></li> <li><a href='/Special:Leaderboard/videos_reviewed' $tab2 >Videos Reviewed</a></li></ul>";
				return $tabs;
			}
		return '';
	}

	/**
	 * Query for Articles Written
	 **/
	function getArticlesWritten($starttimestamp, $lb_user = '', $getArticles = false) {
		global $wgMemc;

		if ($getArticles) {
			$key = "leaderboard:articles_written:$starttimestamp:$lb_user";
		} else {
			$key = "leaderboard:articles_written:$starttimestamp";
		}

		$cachekey = wfMemcKey($key);
		$val = $wgMemc->get($cachekey);
		if (is_array($val)) {
			return $val;
		}

		$dbr = wfGetDB(DB_SLAVE);
		$data = array();

		// DB query new articles
		// Using query from SpecialNewPages per Jack's request
		list( $recentchanges, $page ) = $dbr->tableNamesN( 'recentchanges', 'page' );

		$conds = array();
		$conds['rc_new'] = 1;
		$conds['rc_namespace'] = 0;
		$conds['page_is_redirect'] = 0;
		if ($getArticles) {
			$conds['rc_user_text'] = $lb_user;
		}
		$condstext = $dbr->makeList( $conds, LIST_AND );

		$bots = WikihowUser::getBotIDs();
		$bot = "";

		if(sizeof($bots) > 0) {
			$bot = " AND rc_user NOT IN (" . $dbr->makeList($bots) . ") ";
		}

		$sql =
			"SELECT 'Newpages' as type,
				rc_title AS title,
				rc_cur_id AS cur_id,
				rc_user AS \"user\",
				rc_user_text AS user_text
			FROM $recentchanges,$page
			WHERE rc_cur_id=page_id AND rc_timestamp >= '".$starttimestamp."' AND $condstext" . $bot;
		$res = $dbr->query($sql, __METHOD__);

		// Setup array for new articles
		foreach ($res as $row) {
			$t = Title::newFromID( $row->cur_id );
			if (isset($t)) {
				if ($t->getArticleID() > 0) {
					if ($getArticles) {
						$data[$t->getPartialURL()] = $t->getPrefixedText();
					} else {
						if (!preg_match('/\d+\.\d+\.\d+\.\d+/',$row->user_text))
							$data[$row->user_text]++;
					}
				}
			}
		}

		$wgMemc->set($cachekey, $data, 3600);
		return $data;
	}

	/**
	 * Query for Thunbs Up
	 **/
	function getThumbsUp($starttimestamp) {
		global $wgMemc;

		$key = "leaderboard:thumbsup_received:$starttimestamp";
		$cachekey = wfMemcKey($key);
		$cache = $wgMemc->get($cachekey);
		if (is_array($cache)) {
			return $cache;
		}

		$dbr = wfGetDB(DB_SLAVE);
		$sql = "
			SELECT
				count(thumb_recipient_id) as cnt,
				thumb_recipient_id
			FROM
				thumbs
			WHERE
				thumb_timestamp > '$starttimestamp' AND
				thumb_recipient_id != 0
			GROUP BY
				thumb_recipient_id
			ORDER BY
				cnt DESC
			LIMIT 30";

		$res = $dbr->query($sql, __METHOD__);

		$data = array();
		foreach ($res as $row) {
			$u = User::newFromId($row->thumb_recipient_id);
			$data[$u->getName()] = number_format($row->cnt, 0, "", ',');
		}

		$wgMemc->set($cachekey, $data, 3600);
		return $data;
	}

	/**
	 * Query for RisingStars Written
	 **/
	function getRisingStar($starttimestamp, $lb_user = '', $getArticles = false) {
		global $wgMemc;

		if ($getArticles) {
			$key = "leaderboard:risingstars_received:$starttimestamp:$lb_user";
		} else {
			$key = "leaderboard:risingstars_received:$starttimestamp";
		}

		$cachekey = wfMemcKey($key);
		$val = $wgMemc->get($cachekey);
		if (is_array($val)) {
			return $val;
		}

		$dbr = wfGetDB(DB_SLAVE);
		$data = array();

		$bots = WikihowUser::getBotIDs();
		$bot = "";

		if(sizeof($bots) > 0) {
			$bot = " AND rc_user NOT IN (" . $dbr->makeList($bots) . ") ";
		}

		$sql = "SELECT distinct(rc_title) ".
				"FROM recentchanges  ".
				"WHERE rc_timestamp >= '$starttimestamp' AND rc_comment like 'Marking new article as a Rising Star from From%'   ". $bot .
				"AND rc_namespace=".NS_TALK." ";
		$res = $dbr->query($sql, __METHOD__);
		foreach ($res as $row) {
			$t = Title::newFromText($row->rc_title);
			$a = new Article($t);
			if ($a->isRedirect()) {
				$t = Title::newFromRedirect( $a->fetchContent() );
				$a = new Article($t);
			}
			$author = $a->getContributors()->current();
			$username = $author ? $author->getName() : '';
			if ($getArticles) {
				if ($lb_user == $username)
					$data[$t->getPartialURL()] = $t->getPrefixedText();
			} else {
				$data[$username]++;
			}
		}

		$wgMemc->set($cachekey, $data, 3600);
		return $data;
	}

	/**
	 * Query for Method Guardian
	 **/
	function getMethodGuardian($starttimestamp, $lb_user = '', $getArticles = false) {
		global $wgMemc, $wgSharedDB;

		if ($getArticles) {
			$key = "leaderboard:methodguardian:$starttimestamp:$lb_user";
		} else {
			$key = "leaderboard:methodguardian:$starttimestamp";
		}

		$cachekey = wfMemcKey($key);
		$val = $wgMemc->get($cachekey);
		if (is_array($val)) {
			return $val;
		}

		$dbr = wfGetDB(DB_SLAVE);
		$data = array();

		if ($getArticles) {
			$u = User::newFromName($lb_user);

			$sql = "SELECT log_title ".
				"FROM logging ".
				"WHERE log_type='methgua' AND log_user = ".$u->getID()."  and log_timestamp >= '$starttimestamp' ".
				"ORDER BY log_timestamp DESC ".
				"LIMIT 30";
			$res = $dbr->query($sql, __METHOD__);

			foreach ($res as $row) {
				$t = Title::newFromText($row->log_title);
				if (isset($t)) {
					$data[$t->getPartialURL()] = $t->getPrefixedText();
				}
			}

		} else {

			$bots = WikihowUser::getBotIDs();
			$bot = "";

			if(sizeof($bots) > 0) {
				$bot = " AND log_user NOT IN (" . $dbr->makeList($bots) . ") ";
			}

			$sql = "SELECT log_user, count(*) as C , user_name
			FROM logging left join $wgSharedDB.user on user_id=log_user
			WHERE log_type='methgua' and log_timestamp >= '$starttimestamp' " . $bot .
				"GROUP BY log_user ORDER BY C desc limit 30;";

			$res = $dbr->query($sql, __METHOD__);

			foreach ($res as $row) {
				$u = User::newFromName( $row->user_name );
				if (isset($u)) {
					$data[$u->getName()] = $row->C;
				} else {
					// uh oh maybe?
				}
			}
		}
		$wgMemc->set($cachekey, $data, 60 * 15);
		return $data;
	}


	function getWelcomeWagon($starttimestamp, $lb_user = '', $getArticles = false) {
		global $wgMemc, $wgSharedDB;

		if ($getArticles) {
			$key = "leaderboard:welcomewagon:$starttimestamp:$lb_user";
		} else {
			$key = "leaderboard:welcomewagon:$starttimestamp";
		}

		$cachekey = wfMemcKey($key);
		$val = $wgMemc->get($cachekey);

		if (is_array($val)) {
		   return $val;
		}

		$dbr = wfGetDB(DB_SLAVE);
		$data = array();

		if ($getArticles) {
			$u = User::newFromName($lb_user);

			$sql = "SELECT ww_revision_id ".
					"FROM welcome_wagon_messages ".
					"WHERE ww_from_user_id = ".$u->getID()."  and ww_timestamp >= '$starttimestamp' ".
					"ORDER BY ww_timestamp DESC ".
					"LIMIT 30";
			$res = $dbr->query($sql, __METHOD__);

			foreach ($res as $row) {
				$r = Revision::newFromId($row->ww_revision_id);

				if ($r) {
					$t = $r->getTitle();
					if ($r->getPrevious()) {
						$data["User_talk:".$t->getPartialUrl()."#".$r->getId()] = str_replace($r->getPrevious()->getText(), '', $r->getText());
					}
				}
			}

		} else {

			$sql = "SELECT user_name, count(*) as C
					FROM welcome_wagon_messages left join $wgSharedDB.user on user_id = ww_from_user_id
					WHERE ww_timestamp >= '$starttimestamp'
					GROUP BY user_name ORDER BY C desc limit 30;";

			$res = $dbr->query($sql, __METHOD__);

			foreach ($res as $row) {
				$u = User::newFromName( $row->user_name );
				if (isset($u)) {
					$data[$u->getName()] = $row->C;
				} else {
					// uh oh maybe?
				}
			}
		}

		$wgMemc->set($cachekey, $data, 60 * 15);
		return $data;
	}
	/**
	 * Query for Method Editor
	 **/
	function getMethodEditor($starttimestamp, $lb_user = '', $getArticles = false) {
		global $wgMemc, $wgSharedDB;

		if ($getArticles) {
			$key = "leaderboard:methodeditor:$starttimestamp:$lb_user";
		} else {
			$key = "leaderboard:methodeditor:$starttimestamp";
		}

		$cachekey = wfMemcKey($key);
		$val = $wgMemc->get($cachekey);
		if (is_array($val)) {
			return $val;
		}

		$dbr = wfGetDB(DB_SLAVE);
		$data = array();

		if ($getArticles) {
			$u = User::newFromName($lb_user);

			$sql = "SELECT log_title ".
				"FROM logging ".
				"WHERE log_type='methedit' AND log_user = ".$u->getID()."  and log_timestamp >= '$starttimestamp' ".
				"ORDER BY log_timestamp DESC ".
				"LIMIT 30";
			$res = $dbr->query($sql, __METHOD__);

			foreach ($res as $row) {
				$t = Title::newFromText($row->log_title);
				if (isset($t)) {
					$data[$t->getPartialURL()] = $t->getPrefixedText();
				}
			}

		} else {

			$bots = WikihowUser::getBotIDs();
			$bot = "";

			if(sizeof($bots) > 0) {
				$bot = " AND mel_user NOT IN (" . $dbr->makeList($bots) . ") ";
			}

			$sql = "SELECT mel_user, count(*) as C , user_name
			FROM " . MethodEditor::LOGGING_TABLE_NAME . " left join $wgSharedDB.user on user_id=mel_user
			WHERE mel_timestamp >= '$starttimestamp' " . $bot .
				"GROUP BY mel_user ORDER BY C desc limit 30;";

			$res = $dbr->query($sql, __METHOD__);

			foreach ($res as $row) {
				$u = User::newFromName( $row->user_name );
				if (isset($u)) {
					$data[$u->getName()] = $row->C;
				} else {
					// uh oh maybe?
				}
			}
		}
		$wgMemc->set($cachekey, $data, 60 * 15);
		return $data;
	}

	/**
	 * Query for RisingStars Boosted
	 **/
	function getRisingStarsNABed($starttimestamp, $lb_user = '', $getArticles = false) {
		global $wgMemc;

		if ($getArticles) {
			$key = "leaderboard:risingstars_nabed:$starttimestamp:$lb_user";
		} else {
			$key = "leaderboard:risingstars_nabed:$starttimestamp";
		}

		$cachekey = wfMemcKey($key);
		$val = $wgMemc->get($cachekey);
		if (is_array($val)) {
			return $val;
		}
		$dbr = wfGetDB(DB_SLAVE);
		$data = array();

		$bots = WikihowUser::getBotIDs();
		$bot = "";

		if(sizeof($bots) > 0) {
			$bot = " AND rc_user NOT IN (" . $dbr->makeList($bots) . ") ";
		}

		$sql = "SELECT rc_title,rc_user_text ".
				"FROM recentchanges  ".
				"WHERE rc_timestamp >= '$starttimestamp' AND rc_comment like 'Marking new article as a Rising Star from From%'   ". $bot .
				"AND rc_namespace=".NS_TALK." AND rc_user_text != 'WRM' ";
		$res = $dbr->query($sql, __METHOD__);

		foreach ($res as $row) {
			$t = Title::newFromText($row->rc_title);
			if ($getArticles) {
				if ($lb_user == $row->rc_user_text)
					$data[$t->getPartialURL()] = $t->getPrefixedText();
			} else {
				$data[$row->rc_user_text]++;
			}
		}

		$wgMemc->set($cachekey, $data, 3600);
		return $data;
	}

	/**
	 * Query for Requested Topics
	 **/
	function getRequestedTopics($starttimestamp, $lb_user = '', $getArticles = false) {
		global $wgMemc;

		if ($getArticles) {
			$key = "leaderboard:requested_topics:$starttimestamp:$lb_user";
		} else {
			$key = "leaderboard:requested_topics:$starttimestamp";
		}

		$cachekey = wfMemcKey($key);
		$val = $wgMemc->get($cachekey);
		if (is_array($val)) {
			return $val;
		}

		$dbr = wfGetDB(DB_SLAVE);
		$data = array();

		if ($getArticles) {
			$sql = "SELECT page_title, fe_user_text ".
					"FROM firstedit left join page on fe_page = page_id left join suggested_titles on page_title= st_title " .
					"WHERE fe_timestamp >= '$starttimestamp' AND fe_user_text = " . $dbr->addQuotes($lb_user) . " AND st_isrequest IS NOT NULL";
		} else {
			$bots = WikihowUser::getBotIDs();
			$bot = "";

			if(sizeof($bots) > 0) {
				$bot = " AND fe_user NOT IN (" . $dbr->makeList($bots) . ") ";
			}

			$sql = "SELECT page_title, fe_user_text ".
					"FROM firstedit left join page on fe_page = page_id left join suggested_titles on page_title= st_title " .
					"WHERE fe_timestamp >= '$starttimestamp' AND st_isrequest IS NOT NULL" . $bot;
		}

		$res = $dbr->query($sql, __METHOD__);

		foreach ($res as $row) {
			if ($getArticles) {
				$t = Title::newFromText($row->page_title);
				if (isset($t))
					$data[$t->getPartialURL()] = $t->getPrefixedText();
			} else {
				if (!preg_match('/\d+\.\d+\.\d+\.\d+/',$row->fe_user_text))
					$data[$row->fe_user_text]++;
			}
		}

		$wgMemc->set($cachekey, $data, 3600);
		return $data;
	}

	/**
	 * Query for Articles Spellchecked
	 **/
	function getSpellchecked($starttimestamp, $lb_user = '', $getArticles = false) {
		global $wgMemc, $wgSharedDB;

		if ($getArticles) {
			$key = "leaderboard:spellchecked:$starttimestamp:$lb_user";
		} else {
			$key = "leaderboard:spellchecked:$starttimestamp";
		}

		$cachekey = wfMemcKey($key);
		$val = $wgMemc->get($cachekey);
		if (is_array($val)) {
			return $val;
		}

		$dbr = wfGetDB(DB_SLAVE);
		$data = array();

		if ($getArticles) {
			$u = User::newFromName($lb_user);

			$sql = "SELECT log_title ".
				"FROM logging ".
				"WHERE log_type='spellcheck' AND log_user = ".$u->getID()."  and log_timestamp >= '$starttimestamp' ".
				"ORDER BY log_timestamp DESC ".
				"LIMIT 30";
			$res = $dbr->query($sql, __METHOD__);

			foreach ($res as $row) {
				$t = Title::newFromText($row->log_title);
				if (isset($t)) {
					$data[$t->getPartialURL()] = $t->getPrefixedText();
				}
			}

		} else {

			$bots = WikihowUser::getBotIDs();
			$bot = "";

			if(sizeof($bots) > 0) {
				$bot = " AND log_user NOT IN (" . $dbr->makeList($bots) . ") ";
			}

			$sql = "SELECT log_user, count(*) as C , user_name
				FROM logging left join $wgSharedDB.user on user_id=log_user
				WHERE log_type='spellcheck' and log_timestamp >= '$starttimestamp' " . $bot .
				"GROUP BY log_user ORDER BY C desc limit 30;";

			$res = $dbr->query($sql, __METHOD__);

			foreach ($res as $row) {
				$u = User::newFromName( $row->user_name );
				if (isset($u)) {
					$data[$u->getName()] = $row->C;
				} else {
					// uh oh maybe?
				}
			}
		}
		$wgMemc->set($cachekey, $data, 60 * 15);
		return $data;
	}


	/**
	 * Query for Articles NABed
	 **/
	function getArticlesNABed($starttimestamp, $lb_user = '', $getArticles = false) {
		global $wgMemc, $wgSharedDB;

		if ($getArticles) {
			$key = "leaderboard:articles_nabed:$starttimestamp:$lb_user";
		} else {
			$key = "leaderboard:articles_nabed:$starttimestamp";
		}

		$cachekey = wfMemcKey($key);
		$val = $wgMemc->get($cachekey);
		if (is_array($val)) {
			return $val;
		}

		$dbr = wfGetDB(DB_SLAVE);
		$data = array();

		if ($getArticles) {
			$u = User::newFromName($lb_user);

			$sql = "SELECT nap_page ".
				"FROM newarticlepatrol ".
				"WHERE nap_patrolled=1 and nap_user_ci = ".$u->getID()."  and nap_timestamp_ci >= '$starttimestamp' ".
				"ORDER BY nap_timestamp_ci DESC ".
				"LIMIT 30";
			$res = $dbr->query($sql, __METHOD__);

			foreach ($res as $row) {
				$t = Title::newFromID($row->nap_page);
				if (isset($t)) {
					$data[$t->getPartialURL()] = $t->getPrefixedText();
				}
			}

		} else {
/*
			$sql = "SELECT nap_user_ci,count(*) as count ".
				"FROM newarticlepatrol ".
				"WHERE nap_patrolled=1 and nap_user_ci != 0  and nap_timestamp_ci >= '$starttimestamp' ".
				"GROUP BY nap_user_ci ".
				"ORDER BY count DESC ".
				"LIMIT 30";
*/
			$bots = WikihowUser::getBotIDs();
			$bot = "";

		if(sizeof($bots) > 0) {
			$bot = " AND log_user NOT IN (" . $dbr->makeList($bots) . ") ";
		}

	   $sql = "SELECT log_user, count(*) as C , user_name
			FROM logging left join $wgSharedDB.user on user_id=log_user
			WHERE log_type='nap' and log_timestamp >= '$starttimestamp' " . $bot .
			"GROUP BY log_user ORDER BY C desc limit 30;";

			$res = $dbr->query($sql, __METHOD__);

			foreach ($res as $row) {
				$u = User::newFromName( $row->user_name );
				if (isset($u)) {
					$data[$u->getName()] = $row->C;
				} else {
					// uh oh maybe?
				}
			}
		}
		$wgMemc->set($cachekey, $data, 60 * 15);
		return $data;
	}


	/**
	 * Query for RC Edits
	 **/
	function getRCEdits($starttimestamp, $lb_user = '', $getArticles = false) {
		global $wgMemc;

		if ($getArticles) {
			$key = "leaderboard:rc_edits:$starttimestamp:$lb_user";
		} else {
			$key = "leaderboard:rc_edits:$starttimestamp";
		}

		$cachekey = wfMemcKey($key);
		$val = $wgMemc->get($cachekey);
		if (is_array($val)) {
			return $val;
		}

		$dbr = wfGetDB(DB_SLAVE);
		$data = array();

		$sql = '';
		if ($getArticles) {
			$sql = "SELECT log_user, log_title ".
				"FROM logging FORCE INDEX (times) ".
				"WHERE log_type='patrol' and log_namespace = 0 and log_timestamp >= '$starttimestamp'  and log_user != 0 ";
				//"GROUP by log_user,log_title ";
		} else {
			$bots = WikihowUser::getBotIDs();

			$bot = "";

			if(sizeof($bots) > 0) {
				$bot = " AND log_user NOT IN (" . $dbr->makeList($bots) . ") ";
			}

			$sql = "SELECT log_user, count(*) as C ".
				"FROM logging FORCE INDEX (times) ".
				"WHERE log_type='patrol' and log_timestamp >= '$starttimestamp' ". $bot .
				"GROUP BY log_user ORDER BY C DESC LIMIT 30;";
		}

		$res = $dbr->query($sql, __METHOD__);

		foreach ($res as $row) {
			if ($row->log_user > 0) {
				$u = User::newFromID( $row->log_user );
				if ($getArticles) {
					if ( $lb_user == $u->getName() ) {
						$t = Title::newFromText($row->log_title);
						if (isset($t))
							$data[$t->getPartialURL()] = $t->getPrefixedText();
					}
				} else {
					if (!preg_match('/\d+\.\d+\.\d+\.\d+/',$u->getName()))
						//$data[$u->getName()]++;
						$data[$u->getName()] = number_format($row->C, 0, "", ',');
				}
			}
		}
		$wgMemc->set($cachekey, $data, 3600);
		return $data;
	}

	/**
	 * Query for QC patrolling
	 **/
	function getQCPatrols($starttimestamp, $lb_user = '', $getArticles = false) {
		global $wgMemc, $wgSharedDB;

		if ($getArticles) {
			$key = "leaderboard:qc_patrol:$starttimestamp:$lb_user";
		} else {
			$key = "leaderboard:qc_patrol:$starttimestamp";
		}

		$cachekey = wfMemcKey($key);
		$val = $wgMemc->get($cachekey);
		if (is_array($val)) {
			return $val;
		}

		$dbr = wfGetDB(DB_SLAVE);
		$data = array();

		$bots = WikihowUser::getBotIDs();
		$bot = "";

		if(sizeof($bots) > 0) {
			$bot = " AND qcv_user NOT IN (" . $dbr->makeList($bots) . ") ";
		}

		$sql = "SELECT user_name, SUM(C) as C FROM
			( (SELECT user_name, count(*) as C from qc_vote left join $wgSharedDB.user on qcv_user=user_id
				WHERE qc_timestamp > '{$starttimestamp}' $bot group by qcv_user order by C desc limit 25)
			UNION
			(SELECT user_name, count(*) as C from qc_vote_archive left join $wgSharedDB.user on qcv_user=user_id
				WHERE qc_timestamp > '{$starttimestamp}' $bot group by qcv_user order by C desc limit 25) ) t1
			group by user_name  order by C desc limit 25";
		$res = $dbr->query($sql, __METHOD__);

		foreach ($res as $row) {
			$data[$row->user_name] = $row->C;
		}
		$wgMemc->set($cachekey, $data, 300);
		return $data;
	}

	/**
	 * Query for RC Quick Edits
	 **/
	function getRCQuickEdits($starttimestamp, $lb_user = '', $getArticles = false) {
		global $wgMemc;

		if ($getArticles) {
			$key = "leaderboard:rc_quick_edits:$starttimestamp:$lb_user";
		} else {
			$key = "leaderboard:rc_quick_edits:$starttimestamp";
		}

		$cachekey = wfMemcKey($key);
		$val = $wgMemc->get($cachekey);
		if (is_array($val)) {
			return $val;
		}

		$dbr = wfGetDB(DB_SLAVE);
		$data = array();

		$bots = WikihowUser::getBotIDs();
		$bot = "";

		if(sizeof($bots) > 0) {
			$bot = " AND rc_user NOT IN (" . $dbr->makeList($bots) . ") ";
		}

		$sql = "SELECT rc_user_text,rc_title ".
			"FROM recentchanges ".
			"WHERE rc_comment like 'Quick edit while patrolling' and rc_timestamp >= '$starttimestamp'". $bot .
			"GROUP BY rc_user_text,rc_title ";
		$res = $dbr->query($sql, __METHOD__);

		foreach ($res as $row) {
			if ($getArticles) {
				$t = Title::newFromText($row->rc_title);
				if ($row->rc_user_text == $lb_user) {
					$data[$t->getPartialURL()] = $t->getPrefixedText();
				}
			} else {
				$data[$row->rc_user_text]++;
			}

		}


		$wgMemc->set($cachekey, $data, 3600);
		return $data;
	}


	/**
	 * Query for Total Edits
	 **/
	function getTotalEdits($starttimestamp, $lb_user = '', $getArticles = false) {
		global $wgMemc;

		if ($getArticles) {
			$key = "leaderboard:total_edits:$starttimestamp:$lb_user";
		} else {
			$key = "leaderboard:total_edits:$starttimestamp";
		}

		$cachekey = wfMemcKey($key);
		$val = $wgMemc->get($cachekey);
		if (is_array($val)) {
			return $val;
		}

		$dbr = wfGetDB(DB_SLAVE);
		$data = array();

		$bots = WikihowUser::getBotIDs();

		$bot = "";

		if(sizeof($bots) > 0) {
			$bot = "AND rev_user NOT IN (" . $dbr->makeList($bots) . ") ";
		}

		$sql = "SELECT rev_user_text,page_title,page_namespace ".
			"FROM revision,page ".
			"WHERE rev_page=page_id and page_namespace NOT IN (2, 3, 18) and rev_timestamp >= '$starttimestamp' AND rev_user_text != 'WRM' ".
			$bot .
			"ORDER BY rev_timestamp desc";
		$res = $dbr->query($sql, __METHOD__);

		foreach ($res as $row) {
			if ($getArticles) {
				$t = Title::newFromText($row->page_title);
				if ($row->rev_user_text == $lb_user) {
					if ($row->page_namespace == NS_IMAGE) {
						$data['Image:' . $t->getPartialURL()] = $t->getPrefixedText();
					} else {
						$data[$t->getPartialURL()] = $t->getPrefixedText();
					}
				}
			} else {
				$data[$row->rev_user_text]++;
			}
		}

		$wgMemc->set($cachekey, $data, 3600);
		return $data;
	}

	/**
	 * Query for UCIPatrol
	 **/
	function getUCIAdded($starttimestamp, $lb_user = '', $getArticles = false) {
		global $wgMemc;

		if ($getArticles) {
			$key = "leaderboard:uci_tool2:$starttimestamp:$lb_user";
		} else {
			$key = "leaderboard:uci_tool2:$starttimestamp";
		}

		$cachekey = wfMemcKey($key);
		$val = $wgMemc->get($cachekey);
		if (is_array($val)) {
			return $val;
		}

		$dbr = wfGetDB(DB_SLAVE);
		$data = array();

		$bots = WikihowUser::getBotIDs();

		$bot = "";

		if(sizeof($bots) > 0) {
			$bot = " AND log_user NOT IN (" . $dbr->makeList($bots) . ") ";
		}

		$sql = "SELECT log_user, count(*) as C ".
			"FROM logging ".
			"WHERE log_type='ucipatrol' and log_timestamp >= '$starttimestamp' ". $bot.
			"GROUP BY log_user ORDER BY C DESC LIMIT 30";
		$res = $dbr->query($sql, __METHOD__);

		foreach ($res as $row) {
			$u = User::newFromId($row->log_user);
			if ($u) {
				$data[$u->getName()] = number_format($row->C, 0, "", ',');
			}
		}

		$wgMemc->set($cachekey, $data, 3600);
		return $data;
	}

	/**
	 * Query for TipsPatrol
	 **/
	function getTipsAdded($starttimestamp, $lb_user = '', $getArticles = false) {
		global $wgMemc;

		if ($getArticles) {
			$key = "leaderboard:tip_tool2:$starttimestamp:$lb_user";
		} else {
			$key = "leaderboard:tip_tool2:$starttimestamp";
		}

		$cachekey = wfMemcKey($key);
		$val = $wgMemc->get($cachekey);
		if (is_array($val)) {
			return $val;
		}

		$dbr = wfGetDB(DB_SLAVE);
		$data = array();

		$bots = WikihowUser::getBotIDs();

		$bot = "";

		if(sizeof($bots) > 0) {
			$bot = " AND log_user NOT IN (" . $dbr->makeList($bots) . ") ";
		}

		$sql = "SELECT log_user, count(*) as C ".
			"FROM logging ".
			"WHERE log_type='newtips' and log_timestamp >= '$starttimestamp' ". $bot.
			"GROUP BY log_user ORDER BY C DESC LIMIT 30";
		$res = $dbr->query($sql, __METHOD__);

		foreach ($res as $row) {
			$u = User::newFromId($row->log_user);
			if ($u) {
				$data[$u->getName()] = number_format($row->C, 0, "", ',');
			}
		}

		$wgMemc->set($cachekey, $data, 3600);
		return $data;
	}

	/**
	 * Query for Articles Categorized
	 **/
	function getArticlesCategorized($starttimestamp, $lb_user = '', $getArticles = false) {
		global $wgMemc;

		if ($getArticles) {
			$key = "leaderboard:articles_categorized:$starttimestamp:$lb_user";
		} else {
			$key = "leaderboard:articles_categorized:$starttimestamp";
		}

		$cachekey = wfMemcKey($key);
		$val = $wgMemc->get($cachekey);
		if (is_array($val)) {
			return $val;
		}

		$dbr = wfGetDB(DB_SLAVE);
		$data = array();

		$bots = WikihowUser::getBotIDs();

		$bot = "";

		if(sizeof($bots) > 0) {
			$bot = " AND rc_user NOT IN (" . $dbr->makeList($bots) . ") ";
		}

		$sql = "SELECT rc_user_text,rc_title ".
			"FROM recentchanges ".
			"WHERE rc_comment like 'categorization' and rc_timestamp >= '$starttimestamp' ". $bot .
			"GROUP BY rc_user_text,rc_title" ;
		$res = $dbr->query($sql, __METHOD__);

		foreach ($res as $row) {
			if ($getArticles) {
				$t = Title::newFromText($row->rc_title);
				if ($row->rc_user_text == $lb_user) {
					$data[$t->getPartialURL()] = $t->getPrefixedText();
				}
			} else {
				$data[$row->rc_user_text]++;
			}

		}

		$wgMemc->set($cachekey, $data, 3600);
		return $data;
	}

	/**
	 * Query for Images Added
	 **/
	function getImagesAdded($starttimestamp, $lb_user = '', $getArticles = false) {
		global $wgMemc;

		if ($getArticles) {
			$key = "leaderboard:images_added:$starttimestamp:$lb_user";
		} else {
			$key = "leaderboard:images_added:$starttimestamp";
		}

		$cachekey = wfMemcKey($key);
		$val = $wgMemc->get($cachekey);
		if (is_array($val)) {
			return $val;
		}

		$dbr = wfGetDB(DB_SLAVE);
		$data = array();

		$bots = WikihowUser::getBotIDs();
		$bot = "";

		if(sizeof($bots) > 0) {
			$bot = " AND img_user NOT IN (" . $dbr->makeList($bots) . ") ";
		}

		$sql = "SELECT img_user_text,img_name FROM image ".
			"WHERE img_timestamp >= '$starttimestamp'" . $bot;

		$res = $dbr->query($sql, __METHOD__);

		foreach ($res as $row) {
			if ($getArticles) {
				if ($row->img_user_text == $lb_user) {
					$data['Image:'.$row->img_name] = $row->img_name;
				}
			} else {
				$data[$row->img_user_text]++;
			}

		}

		$wgMemc->set($cachekey, $data, 3600);
		return $data;
	}

	/**
	 * Query for Articles Repaired
	 **/
	function getArticlesRepaired($starttimestamp, $templatetype, $lb_user = '', $getArticles = false) {
		global $wgMemc;

		if ($getArticles) {
			$key = "leaderboard:repair_$templatetype:$starttimestamp:$lb_user";
		} else {
			$key = "leaderboard:repair_$templatetype:$starttimestamp";
		}

		$cachekey = wfMemcKey($key);
		$val = $wgMemc->get($cachekey);
		if (is_array($val)) {
			return $val;
		}

		$dbr = wfGetDB(DB_SLAVE);
		$data = array();

		$bots = WikihowUser::getBotIDs();
		$bot = "";

		if(sizeof($bots) > 0) {
			$bot = " AND log_user NOT IN (" . $dbr->makeList($bots) . ") ";
		}

		//log_type can only be 10 chars. Truncate appropriately
		$sql = "SELECT log_user, count(*) as C ".
			"FROM logging ".
			"WHERE log_type='EF_" . substr($templatetype, 0, 7) . "' and log_timestamp >= '$starttimestamp' ". $bot.
			"GROUP BY log_user ORDER BY C DESC LIMIT 30";
		$res = $dbr->query($sql, __METHOD__);

		foreach ($res as $row) {
			if ($row->log_user > 0) {
				$u = User::newFromID( $row->log_user );
				if ($getArticles) {
					if ( $lb_user == $u->getName() ) {
						$t = Title::newFromText($row->log_title);
						if (isset($t))
							$data[$t->getPartialURL()] = $t->getPrefixedText();
					}
				} else {
					if (!preg_match('/\d+\.\d+\.\d+\.\d+/',$u->getName()))
						//$data[$u->getName()]++;
						$data[$u->getName()] = number_format($row->C, 0, "", ',');
				}
			}
		}
		$wgMemc->set($cachekey, $data, 3600);
		return $data;
	}

	/**
	 * Query for NFDs Reviewed
	 **/
	function getNfdsReviewed($starttimestamp, $lb_user = '', $getArticles = false) {
		global $wgMemc;

		if ($getArticles) {
			$key = "leaderboard:nfd:$starttimestamp:$lb_user";
		} else {
			$key = "leaderboard:nfd:$starttimestamp";
		}

		$cachekey = wfMemcKey($key);
		$val = $wgMemc->get($cachekey);
		if (is_array($val)) {
			return $val;
		}

		$dbr = wfGetDB(DB_SLAVE);
		$data = array();

		$bots = WikihowUser::getBotIDs();
		$bot = "";

		if(sizeof($bots) > 0) {
			$bot = " AND log_user NOT IN (" . $dbr->makeList($bots) . ") ";
		}

		$sql = "SELECT log_user, count(*) as C ".
			"FROM logging ".
			"WHERE log_type='nfd' and log_action='vote' and log_timestamp >= '$starttimestamp' ". $bot.
			"GROUP BY log_user ORDER BY C DESC LIMIT 30";
		$res = $dbr->query($sql, __METHOD__);

		foreach ($res as $row) {
			if ($row->log_user > 0) {
				$u = User::newFromID( $row->log_user );
				if ($getArticles) {
					if ( $lb_user == $u->getName() ) {
						$t = Title::newFromText($row->log_title);
						if (isset($t))
							$data[$t->getPartialURL()] = $t->getPrefixedText();
					}
				} else {
					if (!preg_match('/\d+\.\d+\.\d+\.\d+/',$u->getName()))
						//$data[$u->getName()]++;
						$data[$u->getName()] = number_format($row->C, 0, "", ',');
				}
			}
		}
		$wgMemc->set($cachekey, $data, 3600);
		return $data;
	}


	/**
	 * Query for Videos Reviewed
	 **/
	function getVideosReviewed($starttimestamp, $lb_user = '', $getArticles = false) {
		global $wgMemc;

		if ($getArticles) {
			$key = "leaderboard:videos_reviewed:$starttimestamp:$lb_user";
		} else {
			$key = "leaderboard:videos_reviewed:$starttimestamp";
		}

		$cachekey = wfMemcKey($key);
		$val = $wgMemc->get($cachekey);
		if (is_array($val)) {
			return $val;
		}

		$dbr = wfGetDB(DB_SLAVE);
		$data = array();

		$bot = "";
		$bots = WikihowUser::getBotIDs();

		if(sizeof($bots) > 0) {
			$bot = " AND va_user NOT IN (" . $dbr->makeList($bots) . ") ";
		}

		$sql = "SELECT va_user, va_user_text, count(*) as C ".
			"FROM videoadder ".
			"WHERE va_timestamp >= '$starttimestamp' ". $bot .
			"AND va_skipped_accepted IN ('0','1') ".
			"GROUP BY va_user ORDER BY C desc ";
		if ($getArticles) {
			$u = User::newFromName($lb_user);
			$u->load();
			$id = $u->getID();
			$sql = "SELECT va_user, page_title, page_namespace ".
				"FROM videoadder left join page on page_id=va_page ".
				"WHERE va_timestamp >= '$starttimestamp' and va_user={$id}";
		}

		$res = $dbr->query($sql, __METHOD__);

		foreach ($res as $row) {
			if ($getArticles) {
				$t = Title::makeTitle($row->page_namespace, $row->page_title);
				if ($t)
					$data[$t->getPartialURL()] = $t->getPrefixedText();
			} else {
				//$data[$row->va_user_text]++;
				$data[$row->va_user_text] = number_format($row->C, 0, "", ',');
			}
		}

		$wgMemc->set($cachekey, $data, 3600);
		return $data;
	}

	function showArticles($page, $starttimestamp, $user) {
		global $wgOut;

		$wgOut->setArticleBodyOnly(true);
		$wgOut->addHTML("  <style type='text/css' media='all'>/*<![CDATA[*/ @import '" . wfGetPad('/extensions/min/f/extensions/wikihow/Leaderboard.css?rev=') . WH_SITEREV . "'; /*]]>*/</style>
			<script type='text/javascript' src='" . wfGetPad('/extensions/min/f/extensions/wikihow/Leaderboard.js?rev=') . WH_SITEREV . "'></script>");

		//$wgOut->addHTML(" TEST: $page $starttimestamp $user <br/>\n");

		$data = array();
		switch ($page) {
			case ('articles_written'):
				$data = $this->getArticlesWritten($starttimestamp, $user, true);
				break;
			case ('risingstars_received'):
				$data = $this->getRisingStar($starttimestamp, $user, true);
				break;
			case('requested_topics'):
				$data = $this->getRequestedTopics($starttimestamp, $user, true);
				break;
			case('articles_nabed'):
				$data = $this->getArticlesNABed($starttimestamp, $user, true);
				break;
			case('risingstars_nabed'):
				$data = $this->getRisingStarsNABed($starttimestamp, $user, true);
				break;
			case('rc_edits'):
				$data = $this->getRCEdits($starttimestamp, $user, true);
				break;
			case('rc_quick_edits'):
				$data = $this->getRCQuickEdits($starttimestamp, $user, true);
				break;
			case('qc'):
				$data = $this->getRCQuickEdits($starttimestamp, $user, true);
				break;
			case('total_edits'):
				$data = $this->getTotalEdits($starttimestamp, $user, true);
				break;
			case('articles_categorized'):
				$data = $this->getArticlesCategorized($starttimestamp, $user, true);
				break;
			case('images_added'):
				$data = $this->getImagesAdded($starttimestamp, $user, true);
				break;
			case('videos_reviewed'):
				$data = $this->getVideosReviewed($starttimestamp, $user, true);
				break;
			case('repair_format'):
				$data = $this->getArticlesRepaired($starttimestamp, 'format', $user, true);
				break;
			case('repair_stub'):
				$data = $this->getArticlesRepaired($starttimestamp, 'stub', $user, true);
				break;
			case('repair_topic'):
				$data = $this->getArticlesRepaired($starttimestamp, 'topic', $user, true);
				break;
			case('repair_cleanup'):
				$data = $this->getArticlesRepaired($starttimestamp, 'cleanup', $user, true);
				break;
			case('repair_copyedit'):
				$data = $this->getArticlesRepaired($starttimestamp, 'copyedit', $user, true);
				break;
			case('nfd'):
				$data = $this->getNfdsReviewed($starttimestamp, $user, true);
				break;
			case('spellchecked'):
				$data = $this->getSpellchecked($starttimestamp, $user, true);
				break;
			case('altmethodguardian'):
				$data = $this->getMethodGuardian($starttimestamp, $user, true);
				break;
			case('methodeditor'):
				$data = $this->getMethodEditor($starttimestamp, $user, true);
				break;
			case('welcomewagon'):
				$data = $this->getWelcomeWagon($starttimestamp, $user, true);
				break;
			default:
				return;
		}


		$wgOut->addHTML("<ul>\n");
		foreach ($data as $key => $value) {
			$wgOut->addHTML("<li><a href='/$key' onClick='window.location=\"/$key\";'>$value</a></li>\n");
		}
		$wgOut->addHTML("</ul>\n");

		return;
	}


	function showArticlesPage($page, $period, $starttimestamp, $user) {
		global $wgOut, $wgUser;;

		$wgOut->addHTML("  <style type='text/css' media='all'>/*<![CDATA[*/ @import '" . wfGetPad('/extensions/min/f/extensions/wikihow/Leaderboard.css?rev=') . WH_SITEREV . "'; /*]]>*/</style>
			<script type='text/javascript' src='" . wfGetPad('/extensions/min/f/extensions/wikihow/Leaderboard.js?rev=') . WH_SITEREV . "'></script>");

		$wgOut->addHTML("  <script type='text/javascript'>
				var lb_page = '$target';
				var lb_period = '$period';
			</script>\n");


		//$wgOut->addHTML(" TEST: $page $starttimestamp $user <br/>\n");

		$data = array();
		$subtitle = '';
		switch ($page) {
			case ('articles_written'):
				$data = $this->getArticlesWritten($starttimestamp, $user, true);
				$subtitle = 'Articles Written by ';
				break;
			case ('risingstars_received'):
				$data = $this->getRisingStar($starttimestamp, $user, true);
				$subtitle = 'Articles that received a Risingstar by ';
				break;
			case('requested_topics'):
				$data = $this->getRequestedTopics($starttimestamp, $user, true);
				$subtitle = 'Articles from requested topics by ';
				break;
			case('articles_nabed'):
				$data = $this->getArticlesNABed($starttimestamp, $user, true);
				$subtitle = 'New Articles Boosted by ';
				break;
			case('risingstars_nabed'):
				$data = $this->getRisingStarsNABed($starttimestamp, $user, true);
				$subtitle = 'New Articles nominated for Risingstar by ';
				break;
			case('rc_edits'):
				$data = $this->getRCEdits($starttimestamp, $user, true);
				$subtitle = 'Articles Patrolled - ';
				break;
			case('rc_quick_edits'):
				$data = $this->getRCQuickEdits($starttimestamp, $user, true);
				$subtitle = 'Quick Edits made while patrolling - ';
				break;
			case('total_edits'):
				$data = $this->getTotalEdits($starttimestamp, $user, true);
				$subtitle = 'Total Edits - ';
				break;
			case('articles_categorized'):
				$data = $this->getArticlesCategorized($starttimestamp, $user, true);
				$subtitle = 'Articles Categorized - ';
				break;
			case('images_added'):
				$data = $this->getImagesAdded($starttimestamp, $user, true);
				$subtitle = 'Images Added - ';
				break;
			case('videos_reviewed'):
				$data = $this->getVideosReviewed($starttimestamp, $user, true);
				$subtitle = 'Videos Added - ';
				break;
			case('repair_format'):
				$data = $this->getArticlesRepaired($starttimestamp, 'format', $user, true);
				$subtitle = 'Formats Fixed - ';
				break;
			case('repair_stub'):
				$data = $this->getArticlesRepaired($starttimestamp, 'stub', $user, true);
				$subtitle = 'Stubs Fixed - ';
				break;
			case('repair_topic'):
				$data = $this->getArticlesRepaired($starttimestamp, 'topic', $user, true);
				$subtitle = 'Fixed by Topic - ';
				break;
			case('repair_copyedit'):
				$data = $this->getArticlesRepaired($starttimestamp, 'copyedit', $user, true);
				$subtitle = 'Copyedit Fixed - ';
				break;
			case('repair_cleanup'):
				$data = $this->getArticlesRepaired($starttimestamp, 'cleanup', $user, true);
				$subtitle = 'Cleanup Fixed - ';
				break;
			case ('nfd'):
				$data = $this->getNfdsReviewed($starttimestamp, $user, true);
				$subtitle = 'NFDs Reviewed - ';
				break;
			case('spellchecked'):
				$data = $this->getSpellchecked($starttimestamp, $user, true);
				$subtitle = 'Articles Spell Checked - ';
				break;
			case('methodguardian'):
				$data = $this->getMethodGuardian($starttimestamp, $user, true);
				$subtitle = 'Methods Guarded - ';
				break;
			case('methodeditor'):
				$data = $this->getMethodEditor($starttimestamp, $user, true);
				$subtitle = 'Methods Edited - ';
				break;
			case('welcomewagon'):
				$data = $this->getWelcomeWagon($starttimestamp, $user, true);
				$subtitle = 'Welcome Wagon Messages Sent - ';
				break;
			default:
				return;
		}
		switch($period){
			case(7):
				$subtitle .= $user ." in the last week";
				break;
			case(31):
				$subtitle .= $user ." in the last month";
				break;
			default:
				$subtitle .= $user ." in the last day";
				break;
		}

		$sk = $wgUser->getSkin();

		$u = User::newFromName( $user );
		if (isset($u))
			$u->load();

		$userlink = $sk->makeLinkObj($u->getUserPage(), $u->getName()) ;
		$regdate = "Jan 1, 1970" ;
		if ($u->getRegistration() != '') {
			$regdate = gmdate('M d, Y',wfTimestamp(TS_UNIX,$u->getRegistration()));
		} else {
			$regdate = gmdate('M d, Y',wfTimestamp(TS_UNIX,'20060725043938'));
		}
		$contributions = number_format(WikihowUser::getAuthorStats($u->getName()), 0, "", ",");

		$contribsPage = SpecialPage::getTitleFor( 'Contributions', $user );
		$contriblink = $sk->makeLinkObj( $contribsPage , 'contrib' );
		$talkpagelink = $sk->makeLinkObj($u->getTalkPage(), 'talk');
		$otherlinks = "($contriblink | $talkpagelink)";


		$wgOut->addHTML("\n<div id='Leaderboard'>\n");
		$wgOut->addHTML("<br />$subtitle<br/>
			". wfMsg('leaderboard_articlespage_msg', $userlink, $regdate, $contributions, $otherlinks) ."<br/>
			\n");

		$wgOut->addHTML("<table class='leader_table' style='width:475px; margin:0 auto;'>" );

		$index = 1;
		$wgOut->addHTML("<tr>
			<td class='leader_title'>Article</td>
			</tr>
		");
		$index = 1;
		foreach ($data as $key => $value) {
			$class = "";
			if ($index % 2 == 1)
				$class = 'class="odd"';
			$t = Title::newFromText( $value );
			if ($page == 'images_added_NOT_SETUP_YET') {
				//In the future we can display the actual image added on this page.
				$wgOut->addHTML("<tr $class><td style='text-align:left;'><img src='/$key' /><a href='/$key' >$value</a></td</tr>\n");
			} else if ($page == 'welcomewagon') {
				$wgOut->addHTML("<tr><td class='leader_image'><a href='/$key' >$value</a></td</tr>\n");
			} else {
				$wgOut->addHTML("<tr><td class='leader_image'><a href='/$key' >$value</a> (<a href='".$t->getLocalURL( 'action=history' )."' >history</a>)</td</tr>\n");
			}
			$index++;
		}
		$wgOut->addHTML("</table></center>");

		$wgOut->addHTML("<br /><a href='/Special:Leaderboard/$page?period=$period' >Back</a></div>\n");

		return;
	}

	function execute($par) {
		global $wgRequest, $wgOut, $wgUser, $wgLang;
		$target = isset( $par ) ? $par : $wgRequest->getVal( 'target' );
		$action = $wgRequest->getVal( 'action' );

		if ($target == '') {
			$wgOut->redirect( $wgServer .  "/Special:Leaderboard/articles_written");
			return;
		}

		$wgOut->setPageTitle( wfMsg('leaderboard_title') );
		$wgOut->setRobotpolicy('noindex,nofollow');

		$wgHooks["pageTabs"][] = "wfLeaderboardTabs";

		$sk = $wgUser->getSkin();
		$dbr = wfGetDB(DB_SLAVE);

		$me = Title::makeTitle(NS_SPECIAL, "Leaderboard");

		$period = $wgRequest->getVal('period');
		$startdate = '000000';
		if ($period == 31) {
			$startdate = strtotime('31 days ago');
			$period31selected = 'SELECTED';
		} else if ($period == 7) {
			$startdate = strtotime('7 days ago');
			$period7selected = 'SELECTED';
		} else {
			$period = 24;
			$startdate = strtotime('24 hours ago');
			$period24selected = 'SELECTED';
		}
		//$starttimestamp = date('YmdG',$startdate) . '000000';
		$starttimestamp = date('YmdG',$startdate) . floor(date('i',$startdate)/10) . '00000';

		if ($action == 'articles') {
			$this->showArticles( $target, $starttimestamp, $wgRequest->getVal( 'lb_name' ) );
			return;
		}

		$wgOut->addScript(HtmlSnips::makeUrlTags('css', array('Leaderboard.css'), 'extensions/wikihow', false));
		$wgOut->addScript(HtmlSnips::makeUrlTags('js', array('Leaderboard.js'), 'extensions/wikihow', false));
/*
		if ($action == 'articlelist') {
			$u = User::newFromName( $wgRequest->getVal( 'lb_name' ) );
			if (isset($u)) {
				$this->showArticlesPage( $target, $period, $starttimestamp, $u->getName() );
			}
			return;
		}
*/

		$data = array();
		// WHICH LB TO SHOW
		switch( $target ) {
			case('total_edits'):
				$section = 'Other';
				$learnlink = '/wikiHow:LB-Total-Edits';
				$columnHeader = 'TotalEdits';
				$data = $this->getTotalEdits($starttimestamp);
				break;
			case('articles_categorized'):
				$section = 'Other';
				$learnlink = '/wikiHow:LB-Articles-Categorized';
				$columnHeader = 'Articles Categorized';
				$data = $this->getArticlesCategorized($starttimestamp);
				break;
			case('images_added'):
				$section = 'Imagevideo';
				$learnlink = '/wikiHow:LB-Images-Added';
				$columnHeader = 'Images Added';
				$data = $this->getImagesAdded($starttimestamp);
				break;
			case('videos_reviewed'):
				$section = 'Imagevideo';
				$learnlink = '/wikiHow:LB-Videos-Reviewed';
				$columnHeader = 'Videos Reviewed';
				$data = $this->getVideosReviewed($starttimestamp);
				break;
			case('articles_nabed'):
				$section = 'RCNAB';
				$learnlink = '/wikiHow:LB-Articles-NABed';
				$columnHeader = 'Articles NABed';
				$data = $this->getArticlesNABed($starttimestamp);
				break;
			case('risingstars_nabed'):
				$section = 'RCNAB';
				$learnlink = '/wikiHow:LB-Rising-Stars-NABed';
				$columnHeader = 'Rising Stars NABed';
				$data = $this->getRisingStarsNABed($starttimestamp);
				break;
			case('rc_edits'):
				$section = 'RCNAB';
				$learnlink = '/wikiHow:LB-RC-Edits';
				$columnHeader = 'RC Edits';
				$data = $this->getRCEdits($starttimestamp);
				break;
			case('rc_quick_edits'):
				$section = 'RCNAB';
				$learnlink = '/wikiHow:LB-RC-Quick-Edits';
				$columnHeader = 'RC Quick Edits';
				$data = $this->getRCQuickEdits($starttimestamp);
				break;
			case('qc'):
				$section = 'RCNAB';
				$learnlink = '/wikiHow:Top-Guardians';
				$columnHeader = 'Top Guardians';
				$data = $this->getQCPatrols($starttimestamp);
				break;
			case('requested_topics'):
				$section = 'Writing';
				$learnlink = '/wikiHow:LB-Requested-Topics';
				$columnHeader = 'Requested Topics';
				$data = $this->getRequestedTopics($starttimestamp);
				break;
			case('risingstars_received'):
				$section = 'Writing';
				$learnlink = '/wikiHow:Rising-Star';
				$columnHeader = 'Rising Stars Received';
				$data = $this->getRisingStar($starttimestamp);
				break;
			case('articles_written'):
				$section = 'Writing';
				$learnlink = '/wikiHow:LB-Articles-Written';
				$columnHeader = 'Articles Written';
				$data = $this->getArticlesWritten($starttimestamp);
				break;
			case('repair_format'):
				$section = 'Greenhouse';
				$learnlink = '/wikiHow:LB-Repair_Format';
				$columnHeader = 'Formats Fixed';
				$data = $this->getArticlesRepaired($starttimestamp,'format');
				break;
			case('repair_stub'):
				$section = 'Greenhouse';
				$learnlink = '/wikiHow:LB-Repair_Stub';
				$columnHeader = 'Stubs Fixed';
				$data = $this->getArticlesRepaired($starttimestamp,'stub');
				break;
			case('repair_topic'):
				$section = 'Greenhouse';
				$learnlink = '/wikiHow:LB-Repair_Topic';
				$columnHeader = 'Cultivated by Topic';
				$data = $this->getArticlesRepaired($starttimestamp,'topic');
				break;
			case('repair_cleanup'):
				$section = 'Greenhouse';
				$learnlink = '/wikiHow:LB-Repair_Cleanup';
				$columnHeader = 'Cleanups Fixed';
				$data = $this->getArticlesRepaired($starttimestamp,'cleanup');
				break;
			case('repair_copyedit'):
				$section = 'Greenhouse';
				$learnlink = '/wikiHow:LB-Repair_Copyedit';
				$columnHeader = 'Copyedits Fixed';
				$data = $this->getArticlesRepaired($starttimestamp,'copyedit');
				break;
			case('nfd'):
				$section = 'Other';
				$learnlink = '/wikiHow:NFD-Guardian';
				$columnHeader = 'NFDs Reviewed';
				$data = $this->getNfdsReviewed($starttimestamp);
				break;
			case('thumbs_up'):
				$section = 'Other';
				$learnlink = '/wikiHow:Thumbs-Up';
				$columnHeader = 'Thumbs Up';
				$data = $this->getThumbsUp($starttimestamp);
				break;
			case('spellchecked'):
				$section = 'Writing';
				$learnlink = '/wikiHow:Spellchecker';
				$columnHeader = 'Articles Spell Checked';
				$data = $this->getSpellchecked($starttimestamp);
				break;
			case('methodguardian'):
				$section = 'Other';
				$learnlink = '/wikiHow:LB-Method-Guardian';
				$columnHeader = 'Methods Guarded';
				$data = $this->getMethodGuardian($starttimestamp);
				break;
			case('methodeditor'):
				$section = 'Other';
				$learnlink = '/wikiHow:LB-Method-Editor';
				$columnHeader = 'Methods Edited';
				$data = $this->getMethodEditor($starttimestamp);
				break;
			case('welcomewagon'):
				$section = 'Other';
				$learnlink = '/wikiHow:LB-Welcome-Wagon';
				$columnHeader = 'Welcome Wagon';
				$data = $this->getWelcomeWagon($starttimestamp);
				break;
			default:
				$wgOut->redirect( $wgServer .  "/Special:Leaderboard/articles_written");
				return;
		}

		switch ($section) {
			case('Other'):
				$sectionStyleOther = "class='on'";
				break;
			case('RCNAB'):
				$sectionStyleRCNAB = "class='on'";
				break;
			case('Writing'):
				$sectionStyleWriting = "class='on'";
				break;
			case('Greenhouse'):
				$sectionStyleGreenhouse = "class='on'";
				break;
			case("Imagevideo"):
				$sectionStyleImagevideo = "class='on'";
				break;
		}


		// Vu Note: Due to the reskin adding elements above the articl_tab_line, I had use javascript to inject the tabs above the article_inner.  hacky i know, but otherwise it would have to go into the skin which is worse.
		$dropdown = " <span style='float:right;'>In the last <select id='period' onChange='changePeriod(this);'> <option $period24selected value='24'>24 hours</option> <option $period7selected value='7'>7 days</option> <option $period31selected value='31'>31 days</option> </select> </span>";
		$tabs_main = "<ul id='tabs'><li><a href='/Special:Leaderboard/articles_written' $sectionStyleWriting >Writing</a></li><li><a href='/Special:Leaderboard/articles_nabed' $sectionStyleRCNAB >RC and NAB</a></li><li><a href='/Special:Leaderboard/repair_format' $sectionStyleGreenhouse >Greenhouse</a></li><li><a href='/Special:Leaderboard/images_added' $sectionStyleImagevideo >Images/Videos</a></li><li><a href='/Special:Leaderboard/total_edits' $sectionStyleOther >Other</a></li></ul>";
		$tabs_sub = $this->getTabs($section, $target);
		if ($action != 'articlelist') {
			$tabs_sub .= "<div class='clearall'></div>";
		}
		$tab_sub .= "<div style='clear:both;'></div>";

		$wgOut->addHTML("<div id='leaderboard_tabs'>{$dropdown}{$tabs_main}{$tabs_sub}</div>");

		$wgOut->addHTML("  <style type='text/css' media='all'>/*<![CDATA[*/ @import '" . wfGetPad('/extensions/min/f/extensions/wikihow/winpop.css?rev=') . WH_SITEREV . "'; /*]]>*/</style>
			<script type='text/javascript' language='javascript' src='" . wfGetPad('/extensions/min/f/extensions/wikihow/winpop.js?rev=') . WH_SITEREV . "'></script>");

		$wgOut->addHTML("  <script type='text/javascript'>
				var lb_page = '$target';
				var lb_period = '$period';
			</script>\n");

		//
		//MAIN PAGE SECTION
		//
		if ($action == 'articlelist') {
			$u = User::newFromName( $wgRequest->getVal( 'lb_name' ) );
			if (isset($u)) {
				$this->showArticlesPage( $target, $period, $starttimestamp, $u->getName() );
			} else {
				echo wfMsg('leaderboard-invalid-user');
			}
			return;
		}

		$wgOut->addHTML("<div id='Leaderboard' class='section_text'>
			<p class='leader_head'>Leaders: $columnHeader</p> <span class='leader_learn'><img src='" . wfGetPad('/skins/WikiHow/images/icon_help.jpg') . "'><a href='$learnlink'>Learn about this activity</a></span>
		");

		$wgOut->addHTML("
			<table class='leader_table'>
				<tr> <td colspan='3' class='leader_title'>$columnHeader:</td> </tr> ");

		$index = 1;

		//display difference in only new articles
		//don't sort nfd b/c numbers can be big and include "," so don't sort nicely
		if ($target != 'rc_edits' && $target != 'nfd') {
			arsort($data);
		}
		foreach($data as $key => $value) {
			$u = new User();
			$u->setName($key);
			if (($value > 0) && ($key != '') && ($u->getName() != "WRM")) {
				$class = "";
				if ($index % 2 == 1)
					$class = 'class="odd"';

				$img = Avatar::getPicture($u->getName(), true);
				if ($img == '') {
					$img = Avatar::getDefaultPicture();
				}

				$wgOut->addHTML("
				<tr $class>
					<td class='leader_image'>" . $img . "</td>
					<td class='leader_user'>" . $sk->makeLinkObj($u->getUserPage(), $u->getName()) . "</td>
					<td class='leader_count'><a href='/Special:Leaderboard/$target?action=articlelist&period=$period&lb_name=".$u->getName() ."' >$value</a> </td>
				</tr> ");
				$data[$key] = $value * -1;
				$index++;
			}
			if ($index > 20) break;
		}
		$wgOut->addHTML("</table></div>");
	}
}
