<?
require_once('skins/WikiHowSkin.php'); 

class Feed extends UnlistedSpecialPage {

    function __construct() {
        parent::__construct( 'Feed' );
    }

	function getFeedItems($user) {
		//global $wgMemc; 

		$fname = "Feed::getFeedItems";
		wfProfileIn($fname); 


		//$key = "feed_user_" . $user->getID(); 
		//$feed = $wgMemc->get($key);
		//if (!$feed || true) {
			$feed = array(); 
		//}

		// was this feed updated in the last 30 minutes? 
		$old = wfTimestamp(TS_MW, time() - 1800); 
		if (isset($feed['updated']) && $feed['updated'] > $old) {
			return $feed; 
		}

		// get what they are interested in
		$dbr = wfGetDB(DB_SLAVE); 
		$res = $dbr->select('follow', array('*'), array('fo_user' => $user->getID()), $fname, array("ORDER BY" => "fo_weight desc"));
		$follows = array();
		while ($row = $dbr->fetchObject($res)) {
			$feed[] = $row;
		}

		//$wgMemc->set($key, $feed, 600); // store it for 2 days
		wfProfileOut($fname); 
		return $feed;
	}


	function renderFeed($feed) {
		$html = "";
		$index = 0; 
		$item = "";
		foreach ($feed as $f) {
			switch ($f->fo_type) {
				case 'category':
					$item = FeedCategory::generateFeedItem($f);
					break;
				case 'usertalk':
					$item = FeedUsertalk::generateFeedItem($f);
					break;

			}
			$index++; 
			if ($index % 4 == 3) {
				if ($index > 20) {
					$html .= "<div style='display:none;'>" . FeedGeneralActivities::generateFeedItem() . "</div>"; 
				}
			}
			if ($index > 20) {
				$item .= "<div style='display:none;'>{$item}</div>";
			}
			$html .= $item;
		}
		return $html;
	}

    function execute($par) {
		global $wgRequest, $wgOut, $wgUser;
		
		$target = isset( $par ) ? $par : $wgRequest->getVal( 'target' );
		if (!in_array('staff', $wgUser->getGroups())) {
			$wgOut->showErrorPage( 'nosuchspecialpage', 'nospecialpagetext' );
			return;
		}

		$u = $wgUser;
		if ($target) {
			$u = User::newFromName($target); 
		}

		$wgOut->addHTML('<style type="text/css" media="all">/*<![CDATA[*/ @import "' . wfGetPad('/extensions/min/f/extensions/wikihow/feed.css?rev=') . WH_SITEREV . '"; /*]]>*/</style>');
		$wgOut->addScript('<script type="text/javascript" src="' . wfGetPad('/extensions/min/f/extensions/wikihow/feed.js?rev=') . WH_SITEREV . '"></script>');

		if ($target == 'clear') {
			// clear the users old feed or reset?
		}

		$feed = $this->getFeedItems($u); 
		$html = $this->renderFeed($feed); 
		$wgOut->addHTML("<div id='feeditems'>{$html}</div>");
	}
}

class FeedCategory {

	public static $mIDs = array(); 

	// edit, read, thumbs up? 
	public static function generateFeedItem($f) {
		$fname = "FeedCategory:generateFeedItem";
		$dbr = wfGetDB(DB_SLAVE);
		$cat = Title::makeTitle(NS_CATEGORY, $f->fo_target_name);
		$msg = '';
		// TODO: stub? template? needs attention? 
		if (rand(0,1) == 0) {
			// recently created article
			$row = $dbr->selectRow(array('categorylinks', 'page'), array('page_namespace', 'page_title', 'page_touched', 'page_id'),
				array('page_namespace'=>NS_MAIN, "page_id=cl_from", 'cl_to'=>$cat->getDBKey()), $fname, array("ORDER BY" => "page_id desc"));
			$msg = ' was recently created ' . wfTimeAgo($row->page_touched);
		} else {
			// get an article hasn't been touched in a long time
			$row = $dbr->selectRow(array('categorylinks', 'page'), array('page_namespace', 'page_title', 'page_touched', 'page_id'),
				array('page_namespace'=>NS_MAIN, "page_id=cl_from", 'cl_to'=>$cat->getDBKey()), $fname, array("ORDER BY" => "page_touched desc"));
			$msg = ' has not been edited since ' . wfTimeAgo($row->page_touched);
		}
		$html = "";

		if ($row) {
			if (in_array($row->page_id, self::$mIDs)) {
				return "";
			}
			$t = Title::makeTitle($row->page_namespace, $row->page_title);
			if ($t) {
				$img = SkinWikihowskin::getGalleryImage($t, 44, 33);
				if ($img) {
					$img = "<img src='{$img}' style='height: 40px; margin-right: 5px; vertical-align: top;'/>";
				}
				$html = "<div class='feeditem'>
						<div>{$img}<a href='{$t->getFullURL()}'>{$t->getText()}</a> $msg <br clear='all'/>Category: <a href='{$cat->getFullURL()}'>{$cat->getText()}</a>
						</div>
						<div class='actions'>Show me: <a href='#'>More</a> or <a href='#'>Less</a> of this<br/> <a href='{$t->getFullURL()}'>Read</a> | <a href='{$t->getFullURL()}?action=edit'>Edit</a> | <a href='{$t->getFullURL()}?action=watch'>Watch</a></div></div>";
				self::$mIDs[] = $row->page_id;
			}

		}
		wfProfileOut($fname);
		return $html;
	}

}


class FeedUsertalk {

	// edit, read, thumbs up? 
	public static function generateFeedItem($f) {
		$fname = "FeedUsertalk:generateFeedItem";
		$dbr = wfGetDB(DB_SLAVE);
		$msg = null;
		$u = User::newFromName($f->fo_target_name);
		if (rand(0,1) == 0) {
			// user's recent edit
			$row = $dbr->selectRow('recentchanges', array('rc_title', 'rc_namespace', 'rc_timestamp'), 
					array('rc_namespace'=>NS_MAIN, 'rc_user'=>$f->fo_target_id), $fname, array("ORDER BY"=>"rc_id DESC"));
			if ($row) {
				$t = Title::makeTitle($row->rc_namespace, $row->rc_title);
				$msg = "<a href='{$u->getUserPage()->getFullURL()}'>{$u->getName()}</a> recently edited <a href='{$t->getFullURL()}'>{$t->getText()}</a> " . wfTimeAgo($row->rc_timestamp) ;
			}
		} else {
			// user's recent article creation
			$row = $dbr->selectRow(array('firstedit','page'), array('page_title', 'page_namespace', 'fe_timestamp'), 
					array('page_namespace'=>NS_MAIN, 'fe_user'=>$f->fo_target_id, 'page_id=fe_page'), $fname, array("ORDER BY"=>"page_id DESC"));
			if ($row) {
				$t = Title::makeTitle($row->page_namespace, $row->page_title);
				$msg = "<a href='{$u->getUserPage()->getFullURL()}'>{$u->getName()}</a> recently started the article <a href='{$t->getFullURL()}'>{$t->getText()}</a> " 
					. wfTimeAgo($row->fe_timestamp);
			}
		}
		$html = "";
		if ($msg) {
			$av = 
			$av = Avatar::getAvatarRaw($u->getName()); 
			$url = $av['url'];
			if (!preg_match("@fbcdn.net@", $url)) {
				$url = wfGetPad("/images/avatarOut/" . $av['url']);
			}
			if ($url) {
				$img = "<img src='{$url}' style='width:50px; margin-right: 5px;'/>";
			}
 			$html = "<div class='feeditem'>{$img}{$msg}
                    <div class='actions'><a href='{$t->getFullURL()}'>Read</a> | "
					. "<a href='{$t->getFullURL()}?action=edit'>Edit</a> | <a href='{$t->getFullURL()}?action=watch'>Watch</a></div></div>";
		}
		wfProfileOut($fname);
		return $html;
	}

}

class FeedGeneralActivities {

	// edit, read, thumbs up? 
	public static function generateFeedItem() {
		$fname = "FeedGeneralActivities:generateFeedItem";
		$dbr = wfGetDB(DB_SLAVE);
		$msg = null;
		$x = rand(0, 4); 
		switch ($x) {
			case 0: 
				$count = $dbr->selectField('imageadder', 'count(*)', array('imageadder_hasimage' => 0));;
				$count = number_format($count, 0, ".", ","); 
				$msg = "There are $count articles with no image in the introduction, start adding images <a href='/Special:Introimageadder'>now.</a>";
				$t = Title::makeTitle(NS_SPECIAL, "Introimageadder");
				break;
			case 1:
				$count = $dbr->selectField('recentchanges', 'count(*)', array('rc_patrolled' => 0));;
				$count = number_format($count, 0, ".", ","); 
				$msg = "There are $count unpatrolled edits in recent changes, start patrolling <a href='/Special:RCPatrol'>now.</a>";
				$t = Title::makeTitle(NS_SPECIAL, "RCPatrol");
				break;
			case 2:
				$count = $dbr->selectField('editfinder', 'count(*)', array());;
				$count = number_format($count, 0, ".", ","); 
				$msg = "There are $count articles in the repair shop, start fixing them <a href='/Special:EditFinder'>now.</a>";
				$t = Title::makeTitle(NS_SPECIAL, "EditFinder");
				break;
			case 3:
				$count = $dbr->selectField('qc', 'count(*)', array('qc_patrolled'=>0));;
				$count = number_format($count, 0, ".", ","); 
				$msg = "There are $count edits in the QG queue, start patrolling them <a href='/Special:QG'>now.</a>";
				$t = Title::makeTitle(NS_SPECIAL, "QG");
				break;
			case 4: 
				$feeds = FeaturedArticles::getFeaturedArticles(1);
				if (sizeof($feeds > 0)) {
					$url = $feeds[0][0];
					$url = preg_replace("@http://www.wikihow.com/@", "", $url); 
					$t = Title::newFromURL(urldecode($url));
					if ($t) {
						$img = SkinWikihowskin::getGalleryImage($t, 44, 33);
						$msg = "<img src='{$img}'/><a href='{$t->getFullURL()}'>{$t->getText()}</a> is today's Featured Article";
					}
				}
				break;
				
		}
		$html = "";
		if ($msg) {
 				$html = "<div class='feeditem'>{$msg}<div class='actions'><a href='{$t->getFullURL()}'>Go there now</a></div></div>";
		}
		wfProfileOut($fname);
		return $html;
	}

}

