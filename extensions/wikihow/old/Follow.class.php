<?

class Follow {


	function duplicateCheck() {
		$dbr = wfGetDB(DB_SLAVE);
		return " ON DUPLICATE KEY UPDATE fo_weight = fo_weight + 1, fo_timestamp = " . $dbr->addQuotes(wfTimestampNow());
	}

	function followCat($title, $cat, $user = null) {
       
	   	if (!$user) {
		// who first edited this article?
			$dbr = wfGetDB(DB_SLAVE); 
			$row = $dbr->selectRow('firstedit', array('fe_user', 'fe_user_text'), array('fe_page'=>$title->getArticleID())); 
			if ($row->fe_user == 0) {
				return true; 
			}

			// if the were a registered user, that user is now interestd in article in this category
			// at least by a little bit
			$user = User::newFromID($row->fe_user);	
		}

		$dbw = wfGetDB(DB_MASTER);
	
		$cat = preg_replace("@\[\[Category:|\]\]@i", "", $cat); 
		$t = Title::makeTitle(NS_CATEGORY, $cat); 
	
        $sql = "INSERT INTO follow (fo_user, fo_user_text, fo_type, fo_target_id, fo_target_name, fo_weight, fo_timestamp) "
                . " VALUES ({$user->getID()}, " . $dbw->addQuotes($user->getName()) . ", 'category', {$t->getArticleID()}, "
                . $dbw->addQuotes($t->getText() ) . ", 1, " . $dbw->addQuotes(wfTimestampNow()) . ") " 
				. self::duplicateCheck();
                ;
        $dbw->query($sql);
	}


	function followActivity($activity, $user) {
		if ($user->getID() == 0) {
			return true; 
		}

		$dbw = wfGetDB(DB_MASTER); 
		$sql = "INSERT INTO follow (fo_user, fo_user_text, fo_type, fo_weight, fo_timestamp) VALUES "
			. " ({$user->getID()}, " . $dbw->addQuotes($user->getName()) . ", '$activity', 1, " 
			. $dbw->addQuotes(wfTimestampNow())  . ") "
			. self::duplicateCheck();
		$dbw->query($sql);
		return true;
	}
}
