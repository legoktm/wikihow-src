<?php

if ( ! defined( 'MEDIAWIKI' ) ) die();
	
/**#@+
 * Creates customized feed for users based on activities
 * 
 * @package MediaWiki
 * @subpackage Extensions
 *
 * @link http://www.wikihow.com/WikiHow:Follow-Extension Documentation
 *
 *
 * @author Travis Derouin <travis@wikihow.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 */

$wgExtensionCredits['special'][] = array(
	'name' => 'Follow',
	'author' => 'Travis Derouin',
	'description' => 'Creates customized feed for users based on activities, topics and users they engage with on the site',
	'url' => 'http://www.wikihow.com/WikiHow:Follow-Extension',
);

#$wgExtensionMessagesFiles['Follow'] = dirname(__FILE__) . '/Follow.i18n.php';

#$wgSpecialPages['Follow'] = 'Follow';
$wgAutoloadClasses['Follow'] = dirname( __FILE__ ) . '/Follow.class.php';

$wgHooks['ArticleSaveComplete'][] = array("wfTrackThingsToFollow");
$wgHooks['MarkPatrolledComplete'][] = array("wfTrackMarkPatrolled");
$wgHooks['IntroImageAdderUploadComplete'][] = array("wfTrackIntroImageUpload");
$wgHooks['QCVoted'][] = array("wfTrackQCVoted");
$wgHooks['EditFinderArticleSaveComplete'][] = array("wfTrackEditFinder");

function wfTrackMarkPatrolled(&$rcid, &$user) {
	if (rand(0, 25) == 12) {
		Follow::followActivity('rcpatrol', $user); 
	}
	return true;
}

function wfTrackIntroImageUpload($title, $imgtag, $user) {
	if (rand(0, 10) == 7) {
		Follow::followActivity('introimage', $user); 
	}
	return true;
}

function wfTrackQCVoted($user, $title, $vote) {
	if (rand(0, 25) == 12) {
		Follow::followActivity('qcvote', $user); 
	}
	return true;
}

function wfTrackEditFinder($article, $text, $summary, $user, $type) {
	if (rand(0, 5) == 3) {
		Follow::followActivity('editfinder', $user); 
	}
	return true;
}

function wfTrackThingsToFollow(&$article, &$user, $text, $summary) {
	if ($user->getID() == 0 || preg_match("@Reverted edits by@", $summary)) {
		// anons can't follow things, for now, and ignore rollbacks
		return true; 
	}

	$t = $article->getTitle();

	$last_rev = $article->getRevisionFetched(); 
	$this_rev = $article->mRevision;
	if ($t->getNamespace() == NS_USER_TALK) {
		// did the user post a non-talk page message?
		$follow = false;
		if (!$last_rev && !preg_match("@\{\{@", $text)) {
			$follow = true;
		} elseif ($last_rev) {
			$oldtext = $last_rev->loadText();
			// how many templates in the old one? 
			$oldcount = preg_match_all("@\{\{[^\}]*\}\}@U", $oldtext, $matches);
			$newcount = preg_match_all("@\{\{[^\}]*\}\}@U", $text, $matches); 
			if ($newcount <= $oldcount) {
				$follow = true;
			} else {
				return true;
			}
		} 
		$u = User::newFromName($t->getText()); 
		if ($u && $u->getID() > 0) {
			$follow = true;
		} else {
			return true; 
		}

		if (!$follow) {
			return true;
		}

		$dbw = wfGetDB(DB_MASTER);
		$sql = "INSERT INTO follow (fo_user, fo_user_text, fo_type, fo_target_id, fo_target_name, fo_weight, fo_timestamp) "
				. " VALUES ({$user->getID()}, " . $dbw->addQuotes($user->getName()) . ", 'usertalk', {$u->getID()}, " 
				. $dbw->addQuotes($u->getName() ) . ", 1, " . $dbw->addQuotes(wfTimestampNow()) . ") ON DUPLICATE KEY UPDATE  "
				. " fo_weight = fo_weight + 1, fo_timestamp = " . $dbw->addQuotes(wfTimestampNow())
				;
		#echo $sql; exit;
		$dbw->query($sql);		
		
		#print_r($article); exit;
	} else if ($t->getNamespace() == NS_MAIN) {
		// check for change in categories
		preg_match_all("@\[\[Category:.*\]\]@Ui", $text, $newcats); 
		$oldtext = $last_rev->loadText();
		if ($oldtext) {
			preg_match_all("@\[\[Category:.*\]\]@Ui", $oldtext, $oldcats); 
			#print_r($newcats); print_r($oldcats); exit;
			foreach ($newcats[0] as $n) {
				if (!in_array($n, $oldcats[0])) {
					Follow::followCat($t, $n);
				}
			}
		} else {
			foreach ($newcats as $n) {
				Follow::followCat($t, $n);
			}
		}
	}


	return true;
}

