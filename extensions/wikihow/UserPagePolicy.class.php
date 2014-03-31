<?php

class UserPagePolicy
{
	/**
	 * Cache outcome of good user page to allow for multiple calls
	 */
	static $goodUserCache;

	/**
	 * Determine if we want to display this user page or 404
	 * @return True to display, or false to 404
	 */
	public static function isGoodUserPage($name) {
		global $wgUser;
		
		if(isset(self::$goodUserCache[$name])) {
			return(self::$goodUserCache[$name]);
		}

		$user = User::newFromName($name);
		if(!$user || $user->getID() == 0) {
			self::$goodUserCache[$name] = false;
			return(false);	
		}

		// All user pages are good for logged in
		if($wgUser && $wgUser->getID() > 0) {
			self::$goodUserCache[$name] = true;
			return true;	
		}

		$dbr = wfGetDB(DB_SLAVE);

		// User has started an article?
		$res = $dbr->selectRow(array('firstedit'), array('count(*) as ct'), array('fe_user' => $user->getID()));
		if($res->ct > 0) {
			self::$goodUserCache[$name] = true;
			return(true);	
		}

		// User has at least five main namespace edits?
		$res = $dbr->selectRow(array('revision', 'page'), array('count(*) as ct'), array('rev_user'=>$user->getID(),'page_id = rev_page','page_namespace'=> NS_MAIN ));
		if($res->ct >= 5) {
			self::$goodUserCache[$name] = true;
			return(true);	
		}

		self::$goodUserCache[$name] = false;
		return(false);
	}
}
