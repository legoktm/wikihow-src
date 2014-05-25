<?php

class RecommendationAdmin extends UnlistedSpecialPage {
	public function __construct() {
		parent::__construct('RecommendationAdmin');	
        $GLOBALS['wgHooks']['ShowSideBar'][] = array('TitusQueryTool::removeSideBarCallback');
    }

	/**
	 * 
	 *
	 */
    static function removeSideBarCallback(&$showSideBar) {
        $showSideBar = false;
        return true;
	}

	/** 
	 * 
	 */
	public function execute() {
		global $wgOut, $wgUser, $wgRequest;

		$userGroups = $wgUser->getGroups();
		if (!in_array('staff', $userGroups)) {
			$wgOut->setRobotpolicy('noindex,nofollow');
			$wgOut->showErrorPage('nosuchspecialpage', 'nospecialpagetext');
			return;
		}
		$userId = $wgRequest->getVal('user_id','');
		$pageId = $wgRequest->getVal('page_id','');
		if($userId && $pageId) {
			$user = User::newFromId($userId);
			$title = Title::newFromId($pageId);
			if($user && $title) {
				$wgOut->setArticleBodyOnly(true);
				$wgOut->addHTML($this->showReasons($user,$title));
			}
		}
		else {
			$wgOut->setArticleBodyOnly(true);
			$wgOut->addHTML($this->showUsers());
		}

	}

	/**
	 * Return HTML for showing the reasons for the recommendations of an article */
	public function showReasons($user, $title) {
		$dbr = wfGetDB(DB_SLAVE);
		$res = $dbr->query('select urr_reason, page_title from user_recommendation_reason join page on page_id=urr_reason and page_namespace=0 where urr_user=' . $dbr->addQuotes($user->getId()) . ' and urr_page=' . $dbr->addQuotes($title->getArticleId()), __METHOD__); 
		$pageTitles = array();
		foreach($res as $row) {
			$pageTitles[] = $row->page_title;
		}
		$vars = array('title' => $title, 'user'=> $user, 'pageTitles' => $pageTitles);

		EasyTemplate::set_path(dirname(__FILE__).'/');
		return EasyTemplate::html('Reasons.tmpl.php', $vars);
	}

	/**
	 * Return HTML for showing all the user with their recommendations
	 */
	public function showUsers() {
		$dbr = wfGetDB(DB_SLAVE);
		$sql = "select user_id, user_name, page_title, page_id, ur_score, ur_deleted,count(urcl_id) as views, ur_date_used from user_recommendation join wiki_shared.user on ur_user=user_id join page on page_id=ur_page and page_namespace=0 left join user_recommendation_click_log on ur_user=urcl_user and ur_page=urcl_page group by user_id,page_id order by user_id,ur_score desc";
		$res = $dbr->query($sql, __METHOD__);
		$curUser = false;
		$outRows = array();
		$outRow = false;

		foreach($res as $row) {
			if($curUser != $row->user_id) {
				if($outRow) {
					$outRows[] = $outRow;	
				}
				$outRow = array();
				$outRow['user_id'] = $row->user_id;
				$outRow['user_name'] = $row->user_name;
				$outRow['recommendations'] = array();
				$curUser = $row->user_id;
			}
			$outRow['recommendations'][] = array('title' => str_replace('-',' ',$row->page_title) , 'page_id'=> $row->page_id, 'score' => $row->ur_score, 'views' => $row->views, 'date_used' => $row->ur_date_used);
		}
		if($outRow) {
			$outRows[] = $outRow;
		}

		EasyTemplate::set_path(dirname(__FILE__).'/');
		$vars = array('rows' => $outRows);
		return EasyTemplate::html('UserRecs.tmpl.php', $vars);
	}
}
