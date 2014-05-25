<?php

/** 
  * 
  create table user_recommendation (
  	ur_user int NOT NULL,
	ur_page int NOT NULL,
	ur_score int NOT NULL,
	ur_date_recommended varchar(14) NOT NULL,
	ur_deleted int(1) NOT NULL default 0,
	ur_date_used varchar(14) NULL,
	primary key(ur_user, ur_page),
	index idx_deleted(ur_deleted, ur_user)
  );
  create table user_recommendation_reason (
 	urr_user int NOT NULL,
	urr_page int NOT NULL,
	urr_reason int NOT NULL,
	urr_date_added varchar(25) NOT NULL,
	primary key(urr_user,urr_page,urr_reason)
  );
  create table user_recommendation_click_log (
 	urcl_id int primary key auto_increment,
	urcl_page int NOT NULL,
	urcl_user int NOT NULL,
	urcl_date_added varchar(24) NOT NULL
  );
  */
/** 
 * Create a class for deferred updates of the log of clicks
 */
class RecommendationLogUpdate implements DeferrableUpdate {
	private $_pageId;
	private $_userId;

	public function __construct($pageId, $userId) {
		$this->_pageId = $pageId;
		$this->_userId = $userId;
	}

	public function doUpdate() {
		$dbw = wfGetDB(DB_MASTER);
		$dbw->insert('user_recommendation_click_log', array('urcl_page' => $this->_pageId, 'urcl_user' => $this->_userId, 'urcl_date_added' => wfTimestampNow()), __METHOD__);
	}
}

/** 
 * Class encompasses logic for showing recommendations to the user, and tracking user engagement with recommendations
 */
class RecommendationPresenter {
	const CAMPAIGN_NAME = 'user_suggestion'; 
	/**
	 * Add a recommendation
	 */
	public static function addRecommendation($user, $title, $score) {
		global $wgMemc;
		$dbr = wfGetDB(DB_SLAVE);
		$ct = $dbr->selectField('user_recommendation', array('count(*)'), array('ur_user' => $user->getId(), 'ur_page' => $title->getArticleId()));
		if($ct == 0) {
			$dbw = wfGetDB(DB_MASTER);
			$dbw->insert('user_recommendation', array('ur_page' => $title->getArticleId(), 'ur_user' => $user->getId(), 'ur_score' => $score, 'ur_date_recommended' => wfTimestampNow()), __METHOD__);
			$wgMemc->delete(self::getRecKey($user));
		}
	}
	public static function addRecommendationReason($user, $title, $reason) {
		global $wgMemc;
		$dbw = wfGetDB(DB_MASTER);
		$dbw->insert('user_recommendation_reason', array('urr_page' => $title->getArticleId(), 'urr_user' => $user->getId(), 'urr_reason' => $reason, 'urr_date_added' => wfTimestampNow()), __METHOD__, array('ignore'));	
	}
	/**
	 * Remove recommendation when an article has been deleted
	 */
	public static function onArticleDeleteComplete(&$article, User &$user, $reason, $id, $content, $logEntry ) {
		global $wgMemc;
		$dbr = wfGetDB(DB_SLAVE);
		$row = $dbr->selectField('user_recommendation',array('count(*)'), array('ur_page' => $id),__METHOD__);
		if($row) {
			$dbw = wfGetDB(DB_MASTER);
			$dbw->update('user_recommendation', array('ur_deleted' => 1), array('ur_page' => $id), __METHOD__);

		// If we've deleted an article with user recommendations, we want to reset the recommendations for all affected users
			$dbr = wfGetDB(DB_SLAVE);
			$rows = $dbr->select('user_recommendation', array('distinct ur_user'),array('ur_page' => $id), __METHOD__);
			while($row = $dbr->fetchRow($rows)) {
				$u = User::newFromId($row['ur_user']);
				if($u) {
					$wgMemc->delete(self::getRecKey($u));
				}
			}
		}
		return(true);
	}

	/*
	 * Get the key for storing the recommendations
	 */
	private static function getRecKey($user) {
		return(wfMemcKey('rec', $user->getId()));
	}

	/*
	 * Refresh recemmendation list for user
	 */
	private static function refreshRecList($user) {
		global $wgMemc;

		$key = self::getRecKey($user);
		$dbr = wfGetDB(DB_SLAVE);

		$recs = array();
		$rows = $dbr->select('user_recommendation', array('ur_user', 'ur_page', 'ur_date_recommended', 'ur_date_used'),array('ur_user' => $user->getId(), 'ur_date_used' => NULL, 'ur_deleted' => 0), __METHOD__, array('ORDER BY' => 'ur_score desc'));
		while($row = $dbr->fetchRow($rows)) {
			$recs[] = $row['ur_page'];
		}
		$wgMemc->set($key,$recs);
		return($recs);

	}

	/*
	 * Get the recommendation list for user
	 */
	private static function getRecList($user) {
		global $wgMemc;
	
		$key = self::getRecKey($user);
		$recs = $wgMemc->get($key);
		if($recs) {
			return($recs);	
		}
		$recs = self::refreshRecList($user);
		return($recs);
	}

	/**
	 * Get rotating article recommendation for user from database
	 * @return False if no recommendation and a title for the article recommended otherwise
	 */
	public static function getRecommendation($user) {
		global $wgCookiePrefix, $wgCookiePath, $wgCookieDomain, $wgCookieSecure, $wgMemc;

		if($user->getId() == 0) {
			return(false);	
		}

		// We use a session track where in the rotation we are for the article recommendation
		$cookiename = $wgCookiePrefix . 'rcrt';
		$pos = 0;
		if(isset($_COOKIE[$cookiename])) {
			$pos = $_COOKIE[$cookiename];
		}
		setcookie( $cookiename, intVal($pos) + 1,0 , $wgCookiePath, $wgCookieDomain, $wgCookieSecure );
		
		$dbr = wfGetDB(DB_SLAVE);

		$recs = self::getRecList($user); 
		$articles = sizeof($recs);
		if($articles > 0) {
			$t = Title::newFromId($recs[$pos % $articles]);
			//Extra check if article has been deleted and wasn't taken out of system, somehow
			if(!$t) {
				return(false);	
			}
			// Generate URL to edit page with special tracking parameter
			$url = $t->getLocalURL(array('utm_campaign' => self::CAMPAIGN_NAME, 'action' => 'edit'));
			return(array('url' => $url, 'page_title' => $t->getText()));
		}
		return(false);
	}

	/**
	 * When a user edits a recommended article mark it
	 */
	public static function onArticleSaveComplete(&$article, &$user, $text, $summary,
	        $minoredit, $watchthis, $sectionanchor, &$flags, $revision) {
		
		$t = $article->getTitle();
		if($t->getNamespace() != NS_MAIN) {
			return(true);	
		}
		$dbr = wfGetDB(DB_SLAVE);
		$row = $dbr->selectField('user_recommendation', array('count(*)'), array('ur_page' => $t->getArticleId(), 'ur_user' => $user->getId(),'ur_date_used' => NULL ), __METHOD__);
		if($row) {
			$dbw = wfGetDB(DB_MASTER);
			$dbw->update('user_recommendation', array('ur_date_used' => wfTimestampNow()), array('ur_page' => $article->getId(), 'ur_user' => $user->getId(), 'ur_date_used' => NULL), __METHOD__);
			// If we have turned off recommendations for a user refresh recommendations shown
			self::refreshRecList($user);
		}
		return true;
	}

	/** 
	 * Intercept hook before page intialization to see if we tracking a recommendation
	 */
	public static function onBeforeInitialize(&$title, &$article, &$output, &$user, $request, $mediaWiki) {
		// Make sure we exclude anons
		if($user->getId() == 0) {
			return(true);
		}
		// If they came with a recommendation URL we track it
		$track = $request->getVal("utm_campaign","");
		if($track == self::CAMPAIGN_NAME) {
			// Use deferred insert for tracking clicks 
			$rlu = new RecommendationLogUpdate($title->getArticleId(), $user->getId());
			DeferredUpdates::addUpdate($rlu);
		}
		return(true);
	
	}

}
