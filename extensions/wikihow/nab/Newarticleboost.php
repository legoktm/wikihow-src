<?php
if ( ! defined('MEDIAWIKI') ) die();
/**#@+
 *
 * @package MediaWiki
 * @subpackage Extensions
 *
 * @link http://www.wikihow.com/WikiHow:Newarticleboost-Extension Documentation
 *
 *
 * @author Travis Derouin <travis@wikihow.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 */


$wgExtensionCredits['specialpage'][] = array(
	'name' => 'Newarticleboost',
	'author' => 'Travis Derouin',
	'description' => 'Provides a separate way of patrolling new articles',
	'url' => 'http://www.wikihow.com/WikiHow:Newarticleboost-Extension',
);

$wgExtensionMessagesFiles['Newarticleboost'] = dirname(__FILE__) . '/Newarticleboost.i18n.php';
$wgSpecialPages['Newarticleboost'] = 'Newarticleboost';
$wgSpecialPages['NABStatus'] = 'NABStatus';
$wgSpecialPages['Copyrightchecker'] = 'Copyrightchecker';
$wgSpecialPages['Markrelated'] = 'Markrelated';
$wgSpecialPages['NABClean'] = 'NABClean';
$wgAutoloadClasses['Newarticleboost'] = dirname( __FILE__ ) . '/Newarticleboost.body.php';
$wgAutoloadClasses['NABStatus'] = dirname( __FILE__ ) . '/Newarticleboost.body.php';
$wgAutoloadClasses['Copyrightchecker'] = dirname( __FILE__ ) . '/Newarticleboost.body.php';
$wgAutoloadClasses['Markrelated'] = dirname( __FILE__ ) . '/Newarticleboost.body.php';
$wgAutoloadClasses['NABClean'] = dirname( __FILE__ ) . '/Newarticleboost.body.php';

$wgHooks['ArticleDelete'][] = array("wfNewArticlePatrolClearOnDelete");
$wgHooks['ArticleSaveComplete'][] = array("wfNewArticlePatrolAddOnCreation");

$wgAvailableRights[] = 'newarticlepatrol';
$wgGroupPermissions['newarticlepatrol']['newarticlepatrol'] = true;
$wgGroupPermissions['newarticlepatrol']['move'] = true;
$wgGroupPermissions['staff']['newbienap'] = true;

$wgLogTypes[] = 'nap';
$wgLogNames['nap'] = 'newarticlepatrollogpage';
$wgLogHeaders['nap'] = 'newarticlepatrollogpagetext';

// Take the article out of the queue if it's been deleted
function wfNewArticlePatrolClearOnDelete($article, $user, $reason) {
	$dbw = wfGetDB(DB_MASTER);
	$sql = "DELETE FROM newarticlepatrol WHERE nap_page={$article->getId()}";
	$dbw->query($sql, __METHOD__);
	return true;
}

function wfNewArticlePatrolAddOnCreation($article, $user, $text, $summary, $p5, $p6, $p7) {
	global $wgUser;

	$db = wfGetDB(DB_MASTER);
	$t = $article->getTitle();
	if (!$t || $t->getNamespace() != NS_MAIN)  {
		return true;
	}

	if (in_array("bot", $wgUser->getGroups())) {
		// ignore bots
		return true;
	}

	$num_revisions = $db->selectField('revision', 'count(*)', array('rev_page=' . $article->getId()), __METHOD__);
	$min_rev = $db->selectField('revision', 'min(rev_id)', array('rev_page=' . $article->getId()), __METHOD__);
	$ts = $db->selectField('revision', 'rev_timestamp', array('rev_id=' . $min_rev), __METHOD__);
	$userid = $db->selectField('revision', 'rev_user', array('rev_id=' . $min_rev), __METHOD__);
	$nap_count = $db->selectField('newarticlepatrol', 'count(*)', array('nap_page=' . $article->getId()), __METHOD__);

	// filter articles created by bots
	if ($userid > 0) {
		$u = User::newFromID($userid);
		if ($u && in_array("bot", $u->getGroups())) {
			return true;
		}
	}

	if (($min_rev == $article->mRevIdFetched
		 || !$num_revisions
		 || $num_revisions < 5)
		&& $nap_count == 0         // ignore articles already in there.
		&& $ts > '20090101000000') // forget articles before 2009-01-01
	{
		// default to not a newbie
		$nap_newbie = 0;

		// check for newbie feature and processing settings
		$newbie = array('anon'=>1, 'articles'=>5, 'edits'=>10);
		$msg = wfMsg('NSS');
		$lines = preg_split('@(\n|\s)+@', $msg);
		foreach ($lines as $line) {
			list($k, $v) = split("=", $line);
			$k = trim($k);
			$v = trim($v);
			if ($k === '' || $v === '') continue;
			$newbie[$k] = intval($v);
		}

		// only do checks if we the anon flag is set, or the user 
		// is logged in
		if ($newbie['anon'] || $user->getID()) {
			// how many edits?
			if ($newbie['edits'] > 0) {
				$count = $db->selectField(
					array('revision', 'page'),
					'count(*)',
					array('rev_page=page_id',
						'page_namespace' => NS_MAIN,
						'rev_user_text'=>$user->getName()),
					__METHOD__);
				if ($count < $newbie['edits']) {
					$nap_newbie = 1;
				}
			}
			if ($nap_newbie == 0 && $newbie['articles'] > 0) {
				// how many articles created?
				$count = $db->selectField(
					array('firstedit'),
					'count(*)',
					array('fe_user_text' => $user->getName()),
					__METHOD__);
				if ($count < $newbie['articles']) {
					$nap_newbie = 1;
				}
			}
		}

		$min_ts = $db->selectField('revision',
			'min(rev_timestamp)',
			array('rev_page' => $article->getId()),
			__METHOD__);

		$db->insert('newarticlepatrol',
			array(
				'nap_page' => $article->getId(),
				'nap_timestamp' => $min_ts,
				'nap_newbie' => $nap_newbie),
			__METHOD__);
	}

	return true;
}

