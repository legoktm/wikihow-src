<?

$wgSpecialPages['TwitterAccounts'] = 'TwitterAccounts';
$wgSpecialPages['MyTwitter'] = 'MyTwitter';
$wgAutoloadClasses['TwitterAccounts'] = dirname( __FILE__ ) . '/TwitterFeed.body.php';
$wgAutoloadClasses['MyTwitter'] = dirname( __FILE__ ) . '/TwitterFeed.body.php';

$wgHooks["MarkTitleAsRisingStar"][] = "wfNotifyTwitterRisingStar";
$wgHooks["ArticleSaveComplete"][] = "wfNotifyTwitterOnSave";
$wgHooks["NABArticleFinished"][] = "wfNotifyTwitterOnNAB";

$wgExtensionMessagesFiles['MyTwitter'] = dirname(__FILE__) . '/TwitterFeed.i18n.php';

function wfNotifyTwitterOnSave(&$article, &$user, $text, $summary) {

	// ignore rollbacks
	if (preg_match("@Reverted @", $summary)) {
		return true;
	}

	if (MyTwitter::hasBadTemplate($text)) {
		return true;
	}

	// is it in nab? is it patrolled? If unpatrolled, skip this.
	$dbr = wfGetDB(DB_MASTER); 
	$row = $dbr->selectRow('newarticlepatrol', array('*'), array('nap_page'=>$article->getID()));
	if ($row && $row->nap_patrolled == 0) { 
		return true; 
	}
	
	// old categories
	$oldtext = $article->mPreparedEdit->oldText;
	preg_match_all("@\[\[Category:[^\]]*\]\]@", $oldtext, $matches); 
	$oldcats = array();
	if (sizeof($matches[0]) > 0) {
		$oldcats = $matches[0];
	}

	// find new cats - like kittens!
	$newcats = array();
	preg_match_all("@\[\[Category:[^\]]*\]\]@", $text, $matches); 
	$newcats = array();
	if (sizeof($matches[0]) > 0) {
		$newcats= $matches[0];
	}

	// find out what we need to check
	// what's changed?
	$tocheck = array(); 
	foreach ($newcats as $n) {
		if (!in_array($n, $oldcats)) {
			$n = str_replace("[[Category:", "", $n); 
			$n = str_replace("]]", "", $n);
			$cat = Title::makeTitle(NS_CATEGORY, $n);
			$tocheck[] = $cat;
			$tree = $cat->getParentCategoryTree();
		    $flat = wfFlattenArrayCategoryKeys($tree);
			foreach ($flat as $f) {
				$f = str_replace("Category:", "", $f);
				$c = Title::makeTitle(NS_CATEGORY, $f);
				$tocheck[] = $c; 
			}
		}
	}

	$t = $article->getTitle();
	foreach($tocheck as $cat) {
		wfNotifyTwitter($cat, $t); 
	}

	return true;
}


function wfNotifyTwitter($cat, $t) {
	global $wgUser, $IP;
	if (!$cat) {
		return true;
	}
	try {
		$dbr = wfGetDB(DB_SLAVE); 
		// special case for rising star
		$account = $dbr->selectRow(array('twitterfeedaccounts','twitterfeedcatgories'), array('*'), 
			array('tfc_username=tws_username', 'tfc_category'=>$cat->getDBkey()));

		// anything to check?
		if (!$account) {
			return true;
		}

		$msg = TwitterAccounts::getUpdateMessage($t);

		// did we already do this? 
		$count = $dbr->selectField('twitterfeedlog', '*',
				array('tfl_user'=>$wgUser->getID(), 'tfl_message' => $msg, 'tfl_twitteraccount' => $account->tws_username)
			);
		if ($count > 0) {
			return true;
		}

		// set up the API and post the message
		$callback = $wgServer . '/Special:TwitterAccounts/'. urlencode($account->tws_username);
		require_once("$IP/extensions/wikihow/common/twitterapi.php");
		$twitter = new Twitter(WH_TWITTER_CONSUMER_KEY, WH_TWITTER_CONSUMER_SEC);
		$twitter->setOAuthToken($account->tws_token);
		$twitter->setOAuthTokenSecret($account->tws_secret);
		#print_r($twitter); print_r($account);  exit;
		$result = $twitter->statusesUpdate($msg); 
		#print_r($result); echo $msg; exit;

		// log it so we have a paper trail
		$dbw = wfGetDB(DB_MASTER);
		$dbw->insert('twitterfeedlog', array(
					'tfl_user'=>$wgUser->getID(), 
					'tfl_user_text' => $wgUser->getName(), 
					'tfl_message' => $msg, 
					'tfl_twitteraccount' => $account->tws_username,
					'tfl_timestamp' => wfTimestampNow()));
	} catch (Exception $e) {
		#print_r($e); exit;
	}
	return true;

}


// article becomes rising star
function wfNotifyTwitterRisingStar($t) {
	$cat = Title::makeTitle(NS_CATEGORY, "Rising Star"); 
	wfNotifyTwitter($cat, $t);
	return true;
}

function wfNotifyTwitterOnNAB($aid) {
	$t = Title::newfromID($aid);
	if (!$t) {
		// could have been deleted
		return true;
	}
	$r = Revision::newFromTitle($t);
	if (!$r) {
		return true;
	}
	$text = $r->getText(); 
	if (MyTwitter::hasBadTemplate($text)) {
		return true;
	}
	
	// find new cats - like kittens!
	$newcats = array();
	preg_match_all("@\[\[Category:[^\]]*\]\]@", $text, $matches); 
	$newcats = array();
	if (sizeof($matches[0]) > 0) {
		$newcats= $matches[0];
	}

	foreach($newcats as $cat) {
		// make it a title object
		$cat = str_replace("[[Category:", "", $cat);
		$cat = str_replace("]]", "", $cat);
		$cat = Title::makeTitle(NS_CATEGORY, $cat);
		wfNotifyTwitter($cat, $t); 
	}

	$cat = Title::MakeTitle(NS_CATEGORY, "New Article Boost"); 
	wfNotifyTwitter($cat, $t);
	return true;
}


/* stuff for user's configured twitter functionality */

$wgHooks['ArticleInsertComplete'][] = array("wfMyTwitterInsertComplete");
$wgHooks["NABArticleFinished"][] = array("wfMyTwitterNAB");
$wgHooks["UploadComplete"][] = array("wfMyTwitterUpload");
$wgHooks["EditFinderArticleSaveComplete"][] = array("wfMyTwitterEditFinder"); 
$wgHooks["ArticleSaveComplete"][] = array("wfMyTwitterOnSave"); 

function wfMyTwitterOnSave(&$article, &$user, $text, $summary) {
	if (preg_match("@Quick edit while patrolling@", $summary) && MyTwitter::userHasOption($user, "quickedit")) {
		MyTwitter::tweetQuickEdit($article->getTitle(), $user);
	}
	return true;
}


function wfMyTwitterEditFinder($a, $text, $sum, $user, $efType) {
	$t = $a->getTitle();	
	if ($t->getNamespace() == NS_MAIN && MyTwitter::userHasOption($user, "editfinder")) {
		MyTwitter::tweetEditFinder($t, $user);
	}
	return true;
}


function wfMyTwitterInsertComplete(&$a, &$user, $text) {
	$t = $a->getTitle();
	if ($t->getNamespace() == NS_MAIN && MyTwitter::userHasOption($user, "createpage")) {
		MyTwitter::tweetNewArticle($t, $user);
	}
	return true;
}
function wfMyTwitterNAB ($aid) {
	global $wgUser;
	$t = Title::newFromID($aid); 
	if ($t && MyTwitter::userHasOption($wgUser, "nab")) {
		MyTwitter::tweetNAB($t, $wgUser);
	}
	return true;
}

function wfMyTwitterUpload(&$uploadForm) {
	global $wgUser; 
	$localFile = $uploadForm->getLocalFile();
	if ($uploadForm && $localFile && MyTwitter::userHasOption($wgUser, "upload")) {
		$t = $localFile->title;
		MyTwitter::tweetUpload($t, $wgUser);
	}
	return true;
}

