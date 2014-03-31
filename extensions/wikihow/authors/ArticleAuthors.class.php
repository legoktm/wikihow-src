<?

/*
 * Gather info about the authors of an article
 */
class ArticleAuthors {

	// a cache of the authors of $wgTitle
	static $authorsCache;

	static function getLoadAuthorsCachekey($articleID) {
		return wfMemcKey('loadauthors', $articleID);
	}

	static function loadAuthors() {
		global $wgTitle;
		if ($wgTitle) {
			$aid = $wgTitle->getArticleID();
			return self::getAuthors($aid);
		} else {
			return array();
		}
	}
	
	private static function printAuthors(&$authors) {
		global $wgOut;
		$wgOut->addHtml(implode(", ", $authors));
	}

	static function getAuthors($articleID) {
		global $wgMemc;

		$cachekey = self::getLoadAuthorsCachekey($articleID);
		$authors = $wgMemc->get($cachekey);
		if (is_array($authors)) return $authors;

		$authors = array();
		$dbr = wfGetDB(DB_SLAVE);
		// filter out bots
		$bad = WikihowUser::getBotIDs();
		$bad[] = 0;  // filter out anons too, as per Jack
		$opts = array('rev_page'=> $articleID);
		if (sizeof($bad) > 0) {
			$opts[]  = 'rev_user NOT IN (' . $dbr->makeList($bad) . ')';
		}
		$res = $dbr->select('revision',
			array('rev_user', 'rev_user_text'),
			$opts,
			__METHOD__,
			array('ORDER BY' => 'rev_timestamp')
		);
		foreach ($res as $row) {
			if ($row->rev_user == 0) {
				$authors['anonymous'] = 1;
			} elseif (!isset($authors[$row->user_text]))  {
				$authors[$row->rev_user_text] = 1;
			}
		}

		if ($authors) {
			$wgMemc->set($cachekey, $authors);
		}

		return $authors;
	}

	static function getAuthorHeader() {
		global $wgTitle, $wgRequest, $wgUser, $wgLanguageCode;
		if (!$wgTitle
			|| !($wgTitle->getNamespace() == NS_MAIN || $wgTitle->getNamespace() == NS_PROJECT)
			|| $wgRequest->getVal('action', 'view') != 'view'
			|| $wgRequest->getVal('diff') != '') return "";

		ArticleAuthors::loadAuthorsCache();
		$html = "";
		// Logged in users see this
		if ($wgUser->getID() > 0) {
			//$users =  array_slice(self::$authorsCache, 0, min(sizeof(self::$authorsCache), 4));
			$users =  self::$authorsCache;
			if (!empty($users)) {
				$html = wfMessage('originated_by', "<span id='loggedin'>" .  self::formatAuthorList($users, true, true, 4) . "</span>")->text();
			}
		} else {
			//$users = array_slice(self::$authorsCache, 0, min(sizeof(self::$authorsCache), 4));
			$users =  self::$authorsCache;
			if (!empty($users)) {
				$otherUserCount = sizeof(self::$authorsCache) - 4;
				$authorSpan = "<span>" . self::formatAuthorList($users, false, false, 4) . "</span>";
				if ($otherUserCount > 1) {
					$html = wfMessage('originated_by_and_others_anon', $authorSpan, $otherUserCount)->text();
				} elseif ($otherUserCount == 1) {
					$html = wfMessage('originated_by_and_1_other_anon', $authorSpan)->text();
				} else {
					$html = wfMessage('originated_by_anon', $authorSpan)->text();
				}
			}
		}

		if (empty($users)) {
		  $html = "";  //no longer showing <span>&nbsp;</span> as it caused spacing problems - bebeth
		}

		$html = "<p id='originators'>$html</p>";
		return $html;
	}

	static function loadAuthorsCache() {
		if (!is_array(self::$authorsCache)) {
			self::$authorsCache = self::loadAuthors();
		}
	}

	static function getAuthorFooter() {
		global $wgUser;
		ArticleAuthors::loadAuthorsCache();
		if (sizeof(self::$authorsCache) == 0) {
			return '';
		}
		if ($wgUser->getID() > 0) {
			$users = self::$authorsCache;
			$users =  array_slice($users, 0, min(sizeof($users), 100) );
			return "<p class='info'>" . wfMessage('thanks_to_authors')->text() . " " . self::formatAuthorList($users) . "</p>";
		} else {
			$users = array_reverse(self::$authorsCache);
			$users = array_slice($users, 1, min(sizeof($users) - 1, 3));
			if (sizeof($users)) {
				return "<p class='info'>" . wfMessage('most_recent_authors')->text() . " " . self::formatAuthorList($users, false, false) . "</p>";
			} else {
				return '';
			}
		}
	}

	static function formatAuthorList($authors, $showAllLink = true, $link = true, $max = null) {
		global $wgTitle, $wgUser, $wgRequest, $wgMemc;

		if (!$wgTitle || !in_array($wgTitle->getNamespace(), array(NS_MAIN, NS_PROJECT))) {
			return '';
		}

		$action = $wgRequest->getVal('action', 'view');
		if ($action != 'view') return '';

		$articleID = $wgTitle->getArticleId();
		$authors_hash = md5( print_r($authors, true) . print_r($showAllLink,true) . print_r($link,true));
		$cachekey = wfMemcKey('authors', $articleID, $authors_hash);
		$val = $wgMemc->get($cachekey);
		if ($val) return $val;

		$count = 0;
		$gplus_first = false;
		$links = array();
		foreach ($authors as $u => $p) {
			if ($u == 'anonymous') {
				$links[] = $link ? "<a href='/wikiHow:Anonymous'>" . wfMessage('anonymous')->text() . "</a>" : wfMessage('anonymous')->text();
			} else {
				$user = User::newFromName($u);
				if (!$user) continue;
				$name = $user->getRealName();
				if (!$name) $name = $user->getName();
				//Remove trailing spaces 
				$name=preg_replace("/ +$/","", $name);
				//check if G+ user
				if ($user->getOption('show_google_authorship')) {
					//$links_gp[] = $link ? "<a rel='author' href='{$user->getUserPage()->getLocalURL()}'>{$name}</a>" : $name;
					$links_gp[] = "<a rel='author' href='{$user->getUserPage()->getLocalURL()}'>{$name}</a>";
					if ($count == 0) $gplus_first = true;
				}
				else {
					$links[] = $link ? "<a href='{$user->getUserPage()->getLocalURL()}'>{$name}</a>" : $name;
				}
			}
			$count++;
		}
		
		//are we floating G+ authors to the top?
		//if so, keep #1 as #1
		if (count($links_gp) > 0) {
			if ($gplus_first) {
				$links = array_merge($links_gp, $links);
			}
			else {
				$first = array_shift($links);
				$links = array_merge($links_gp, $links);
				if ($first) array_unshift($links, $first);
			}
		}
		
		//let's truncate here if we need to
		if ($max) {
			$links = array_slice($links, 0, min(sizeof($links), $max));
		}
		
		$html = implode(", ", $links);
		if ($showAllLink) {
			$sk = $wgUser->getSkin();
			$html .=  " (" . $sk->makeLinkObj($wgTitle, wfMessage('see_all')->text(), "action=credits")  . ")";
		}
		$wgMemc->set($cachekey, $html);

		return $html;
	}

}

