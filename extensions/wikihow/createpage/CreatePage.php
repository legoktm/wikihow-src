<?php

if ( !defined( 'MEDIAWIKI' ) ) exit(1);

/**#@+
 * A simple extension that allows users to enter a title before creating a 
 * page. 
 * 
 * @package MediaWiki
 * @subpackage Extensions
 *
 * @author Travis Derouin <travis@wikihow.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 */

$wgExtensionCredits['specialpage'][] = array(
	'name' => 'CreatePage',
	'author' => 'Travis Derouin',
	'description' => 'Provides a basic way entering a title and searching for potential duplicate articles before creating a page',
	'url' => 'http://www.wikihow.com/WikiHow:CreatePage-Extension',
);

$wgExtensionMessagesFiles['CreatePage'] = dirname(__FILE__) . '/CreatePage.i18n.php';
$wgExtensionMessagesFiles['CreatePageAliases'] = dirname(__FILE__) . '/CreatePage.alias.php';

$wgSpecialPages['CreatePage'] = 'CreatePage';
$wgSpecialPages['CreatePageTitleResults'] = 'CreatePageTitleResults';
$wgSpecialPages['CreatepageWarn'] = 'CreatepageWarn';
$wgSpecialPages['ProposedRedirects'] = 'ProposedRedirects';
$wgSpecialPages['CreatepageEmailFriend'] = 'CreatepageEmailFriend';
$wgSpecialPages['CreatepageFinished'] = 'CreatepageFinished';
$wgSpecialPages['CreatepageReview'] = 'CreatepageReview';
$wgSpecialPages['SuggestionSearch'] = 'SuggestionSearch';
$wgSpecialPages['ManageSuggestions'] = 'ManageSuggestions';
$wgAutoloadClasses['CreatePage'] = dirname( __FILE__ ) . '/CreatePage.body.php';
$wgAutoloadClasses['CreatePageTitleResults'] = dirname( __FILE__ ) . '/CreatePage.body.php';
$wgAutoloadClasses['CreatepageWarn'] = dirname( __FILE__ ) . '/CreatePage.body.php';
$wgAutoloadClasses['ProposedRedirects'] = dirname( __FILE__ ) . '/CreatePage.body.php';
$wgAutoloadClasses['CreatepageEmailFriend'] = dirname( __FILE__ ) . '/CreatePage.body.php';
$wgAutoloadClasses['CreatepageFinished'] = dirname( __FILE__ ) . '/CreatePage.body.php';
$wgAutoloadClasses['CreatepageReview'] = dirname( __FILE__ ) . '/CreatePage.body.php';
$wgAutoloadClasses['SuggestionSearch'] = dirname( __FILE__ ) . '/CreatePage.body.php';
$wgAutoloadClasses['ManageSuggestions'] = dirname( __FILE__ ) . '/CreatePage.body.php';

$wgHooks['ArticleDelete'][] = array("wfCheckSuggestionOnDelete");
$wgHooks['ArticleSaveComplete'][] = array("wfCheckSuggestionOnSave");
//$wgHooks['ArticleSaveComplete'][] = array("wfSetAnonPopUp");
$wgHooks['ArticleSave'][] = array("wfCheckForCashSpammer");
$wgHooks['TitleMoveComplete'][] = array("wfCheckSuggestionOnMove");
$wgHooks['ArticleJustBeforeBodyClose'][] = array("wfShowFollowUpOnCreation");
//$wgHooks['ArticlePageDataBefore'][] = array("wfShowAnonPopUp");
$wgHooks['ArticleInsertComplete'][] = array("wfProcessNewArticle");
$wgHooks['ArticleDeleteComplete'][] = array("wfRemoveFromFirstEdit");
//$wgHooks['AddNewAccount'][] = array("wfCheckForNewAccountsFromProxy");

$wgLogTypes[] = 'suggestion';
$wgLogNames['suggestion'] = 'suggestionlogpage';
$wgLogHeaders['suggestion'] = 'suggestionlogtext';

$wgLogTypes[] = 'redirects';
$wgLogNames['redirects'] = 'redirects';
$wgLogHeaders['redirects'] = 'redirectstext';
$wgLogActions['redirects/added'] = 'redirects_logsummary';

function wfGetSuggTitlesMemcKey($articleID) {
	return wfMemcKey("suggtitles:" . $articleID);
}

function wfClearSuggestionsCache($t) {
	global $wgMemc;

	$dbr = wfGetDB(DB_SLAVE);
	$res = $dbr->select(array('suggested_links', 'suggested_titles'),
			array('sl_page'),
			array('st_title' => $t->getDBKey(), 'sl_sugg = st_id')
		);
	foreach ($res as $row) {
		$key = wfGetSuggTitlesMemcKey($row->sl_page);
		$wgMemc->delete($key);
	}
	return true;
}

function wfCheckSuggestionOnDelete($wikiPage) {
	try {
		$t = $wikiPage->getTitle();
		if (!$t || $t->getNamespace() != NS_MAIN)
			return true;
		$dbw = wfGetDB(DB_MASTER);
		$key = generateSearchKey(trim($t));
		$dbw->update('suggested_titles',
					array('st_used' => 0),
					array('st_key' => $key), 
					__METHOD__);
		wfClearSuggestionsCache($t);
	} catch (Exception $e) {
		return true;
	}
	return true;
}

function wfCheckSuggestionOnMove( &$ot, &$nt, &$wgUser, $pageid, $redirid) {
	$dbw = wfGetDB(DB_MASTER);
	$dbw->update('suggested_titles',
		array('st_used' => 1, 'st_created' => wfTimestampNow(TS_MW)),
		array('st_title' => $nt->getDBKey()),
		__METHOD__);
	wfClearSuggestionsCache($nt);
	return true;
}

// When a new article is created, mark the suggsted as used in the DB
function wfCheckSuggestionOnSave($article, $user, $text, $summary, $p5, $p6, $p7) {
	try {
		$dbr = wfGetDB(DB_SLAVE);
		$t = $article->getTitle();
		if (!$t || $t->getNamespace() != NS_MAIN)
			return true;
		$num_revisions = $dbr->selectField('revision',
			'count(*)',
			array('rev_page=' . $article->getId()),
			__METHOD__);
		// < 2 for race conditions
		if ($num_revisions < 2) {
			$dbw = wfGetDB(DB_MASTER);
			$key = generateSearchKey(trim($t));
			$dbw->update('suggested_titles',
					array('st_used' => 1,
						'st_created' => wfTimestampNow(TS_MW)), 
					array('st_key' => $key),
					__METHOD__);
			wfClearSuggestionsCache($t);
		}
		if ($num_revisions == 1) {
			$email = $dbw->selectField('suggested_titles',
				array('st_notify'),
				array('st_title' => $t->getDBKey()),
				__METHOD__);
			if ($email) {
				$dbw->insert('suggested_notify',
					array('sn_page' => $article->getId(),
						'sn_notify' => $email,
						'sn_timestamp' => wfTimestampNow(TS_MW)),
					__METHOD__);
			}
		}
	} catch (Exception $e) {
		return true;
	}
	return true;
}

function wfGetTopCategory($title = null) {
	$result = Categoryhelper::getTopCategory($title);
	return Title::makeTitle(NS_CATEGORY, $result);
}

function wfGetSuggestedTitles($t) {
	global $wgUser, $wgMemc;

	$html = "";
	if (!$t) {
		return $html;
	}

	// use memcached to store results
	$key = wfGetSuggTitlesMemcKey( $t->getArticleID() );
	$result = $wgMemc->get($key);
	if ($result) {
		return $result;
	}

	wfProfileIn(__METHOD__);
	$dbr = wfGetDB(DB_SLAVE);
	$group = date("W") % 5;

	$res = $dbr->select('suggested_links',
				array('sl_sugg'),
				array('sl_page' => $t->getArticleID()),
				array('ORDER BY' => 'sl_sort'),
				__METHOD__);
	$ids = array();
	while ($row=$dbr->fetchObject($res)) {
		$ids[] = $row->sl_sugg;
	}

	$randStr = wfRandom();
	if (sizeof($ids) == 0) {
		$top = wfGetTopCategory($t);
		if ($top) {
			$sql = "SELECT st_title FROM suggested_titles
				WHERE st_used = 0 and st_patrolled = 1 
					and st_group = $group
					and st_category = " . $dbr->addQuotes($top->getText()) . " 
					and st_random > $randStr limit 5";
			$res = $dbr->query($sql, __METHOD__);
		}
	} else {
		$sql = "(" . implode(", ", $ids) . ")";
		$sql = "SELECT st_title FROM suggested_titles
			WHERE st_used = 0 and st_patrolled = 1 
				and st_group = $group and st_id
				in $sql limit 5";
		$res = $dbr->query($sql, __METHOD__);
	}

	if ($dbr->numRows($res) == 0) {
		$top = wfGetTopCategory($t);
		if ($top) {
			$sql = "SELECT st_title FROM suggested_titles
				WHERE st_used = 0 and st_patrolled = 1 
					and st_group = $group
					and st_category = " . $dbr->addQuotes($top->getText()) . "
					and st_random > $randStr limit 5";
			$res = $dbr->query($sql, __METHOD__);
		}
	}

	while ($row = $dbr->fetchObject($res)) {
		$title = Title::newFromText($row->st_title);
		if (!$title) continue;
		$sp = SpecialPage::getTitleFor( 'CreatePage', $title->getText() );
		$html .= "<li><a onclick='clickshare(46);' href='{$sp->getLocalUrl()}' class='new'>" . wfMsg('howto', $title->getText()) ."</a></li>\n";
	}
	if ($html != "") {
		$html = "<h2>" . wfMsg('suggested_titles_section') . "</h2><div id='suggested_titles'>". wfMsg('suggested_titles_section_description') 
		. "<br/><br/><ul id='gatSuggestedTitle'>{$html}</ul></div>";
	}
	$wgMemc->set($key, $html);
	wfProfileOut(__METHOD__);
	return $html;
}

function wfGetTitlesToImprove($t) {
	global $wgUser;

	$html = "";
	if (!$t)
		return $html;
	$dbr = wfGetDB(DB_SLAVE);
	$sk = $wgUser->getSkin();
	$res = $dbr->select('improve_links',
			array('il_namespace', 'il_title'), 
			array('il_from'=>$t->getArticleID()),
			__METHOD__, 
			array('LIMIT' => 5));

	while ($row = $dbr->fetchObject($res)) {
		$title = Title::makeTitle($row->il_namespace, $row->il_title);
		if (!$title) continue;
		$html .= "<li><a onclick='clickshare(45);' href='{$title->getFullUrl()}' rel='nofollow'>" . wfMsg('howto', $title->getText()) ."</a></li>\n";
	}
	if ($html != "") {
		$html = "<p><br /></p>
<div id='suggested_titles'>
<div class='SecL'></div>
<div class='SecR'></div>
<h2>" . wfMsg('titles_to_improve_section') . "</h2>"
. wfMsg('titles_to_improve_description') . "
<br/><br/>
<ul>{$html}</ul>
</div> ";
	}
	return $html;
}

// update the first edit table and set the cookie that will show the 
// follow up dialog for the user
function wfProcessNewArticle(&$article, &$user, $text) {	
	global $wgCookieExpiration, $wgCookiePath, $wgCookieDomain, $wgCookieSecure, $wgCookiePrefix;

	// set the follow up cookie
	$ids = array();
	if ( isset( $_COOKIE[$wgCookiePrefix.'ArticlesCreated'] ) ) {
		$ids = explode(",", $_COOKIE[$wgCookiePrefix.'ArticlesCreated']);
	}

	$title = $article->getTitle();
	if (!$title || $title->getNamespace() != NS_MAIN) {
		return true;
	}

	$id = $title->getArticleID();
	$ids[]  = $id;
	$exp = time() + $wgCookieExpiration;
	setcookie( $wgCookiePrefix.'ArticlesCreated', implode(",", $ids), $exp, $wgCookiePath, $wgCookieDomain, $wgCookieSecure );

	if (preg_match("@#REDIRECT@", $text)) {
		return true;
	}

	// update the first edit table
	$dbw = wfGetDB(DB_MASTER); 
	$dbw->insert('firstedit', array('fe_page'=>$id, 'fe_user'=>$user->getID(), 'fe_user_text'=>$user->getName(), 'fe_timestamp' => wfTimestampNow()));

	return true;
}

function wfRemoveFromFirstEdit($wikiPage, $user, $reason, $id) {
	$dbw = wfGetDB(DB_MASTER);
	$dbw->delete('firstedit', array('fe_page'=>$id), __METHOD__);
	return true;
}

function wfShowFollowUpOnCreation() {
	global $wgTitle, $wgRequest, $wgOut, $wgUser, $wgCookiePrefix, $wgCookiePath, $wgCookieDomain, $wgCookieSecure;

	try {
		$t = $wgTitle;
		if (!$t || $t->getNamespace() != NS_MAIN) {
			return true;        
		}
		
		$article = new Article($t);

		// short circuit the database look ups because they are frigging slow
		if ( isset( $_COOKIE[$wgCookiePrefix.'ArticlesCreated'] ) ) {
			$ids = explode(",", $_COOKIE[$wgCookiePrefix.'ArticlesCreated']);
		} else {
			// they didn't create any articles
			return true;
		}
		if (!in_array($t->getArticleID(), $ids)) {
			// they didn't create this article
			return true;
		}

		// all of this logic could be cleaned up and HTML moved to a template
		$dbr = wfGetDB(DB_SLAVE);
		$num_revisions = $dbr->selectField('revision', 'count(*)', array('rev_page=' . $article->getId()));
		if ($num_revisions > 1) return true;
		$user_name  = $dbr->selectField('revision', 'rev_user_text', array('rev_page=' . $article->getId()));
		if ((strpos($_SERVER['HTTP_REFERER'], 'action=edit') !== false
			 || strpos($_SERVER['HTTP_REFERER'], 'action=submit2') !== false)
			&& $wgUser->getName() == $user_name
			&& (!isset($_SESSION["aen_dialog"][$article->getId()]))
			)
		{

			print('<script type="text/javascript" language="javascript" src="' . wfGetPad('/extensions/min/f/extensions/wikihow/winpop.js?rev=') . WH_SITEREV . '"></script>
								<script type="text/javascript" language="javascript" src="' . wfGetPad('/extensions/min/f/extensions/wikihow/authors/authoremails.js?rev=') . WH_SITEREV . '"></script>
						 <link rel="stylesheet" href="' . wfGetPad('/extensions/min/f/extensions/wikihow/winpop.css?rev=') . WH_SITEREV . '" type="text/css" />
						 <link rel="stylesheet" href="' . wfGetPad('/extensions/min/f/extensions/wikihow/createpage/createpage.css?rev=') . WH_SITEREV . '" type="text/css" />');

			if ($wgUser->getID() == 0) {

				setcookie('aen_anon_newarticleid',$article->getId(),time()+3600, $wgCookiePath, $wgCookieDomain, $wgCookieSecure); 
				setcookie('aen_dialog_check',$article->getId(),time()+3600); 

				print('
					<script type="text/javascript">
						var whNewLoadFunc = function() {
							if ( getCookie("aen_dialog_check") != "" ) {
								var url = "/extensions/wikihow/common/jquery-ui-1.9.2.custom/js/jquery-ui-1.9.2.custom.min.js";
								$.getScript(url, function() {
									$("#dialog-box").load("/Special:CreatepageFinished");
									$("#dialog-box").dialog({
										width: 600,
										height: 600,
										modal: true,
										closeText: "Close",
										title: "' . wfMsg('createpage_congratulations') . '"
									});
									deleteCookie("aen_dialog_check");
								});
							}
						};
						// during move to jquery...
						if (typeof document.observe == "function") {
							document.observe("dom:loaded", whNewLoadFunc);
						} else {
							$(document).ready(whNewLoadFunc);
						}
					</script>
				');
			} else {

				if ($wgUser->getOption( 'enableauthoremail' )) {
					setcookie('aen_dialog_check',$article->getId(),time()+3600);

					print('
						<script type="text/javascript">
						var whNewLoadFunc = function() {
							if ( getCookie("aen_dialog_check") != "" ) {
								var url = "/extensions/wikihow/common/jquery-ui-1.9.2.custom/js/jquery-ui-1.9.2.custom.min.js";
								$.getScript(url, function() {
									$("#dialog-box").load("/Special:CreatepageFinished");
									$("#dialog-box").dialog({
										width: 600,
										height: 600,
										modal: true,
										closeText: "Close",
										title: "' . wfMsg('createpage_congratulations') . '"
									});
									deleteCookie("aen_dialog_check");
								});
							}
						};
						// during move to jquery...
						if (typeof document.observe == "function") {
							document.observe("dom:loaded", whNewLoadFunc);
						} else {
							$(document).ready(whNewLoadFunc);
						}
						</script>
					');
				}
			}
			$_SESSION["aen_dialog"][$article->getId()] = 1;
		}
	} catch (Exception $e) {
	}
	return true;
}

function wfCheckForCashSpammer($article, $user, $text, $summary, $flags, $p1, $p2, $flags) {
	if ($text) {
		if ($article->getTitle()->getText() == "Yrt291x"
			|| $article->getTitle()->getText() == "Spam Blacklist") 
				return true;
		$msg = preg_replace('@<\![-]+-[\n]+|[-]+>@U', '', wfMsg('yrt291x'));
		$msgs = split("\n", $msg);
		foreach ($msgs as $m) {
			$m = trim($m);
			if ($m == "") continue;
			if (stripos($text, $m) !== false) {
				return false;
			}
		}
	}
	return true;
}

function wfTrackEditToken($user, $token, $title, $guided) {
	global $wgLanguageCode;
	if ($wgLanguageCode != 'en');
		return true;

	$dbw = wfGetDB(DB_MASTER);
	$dbw->insert('edit_track', 
			array(	'et_user' 			=> $user->getID(), 
					'et_user_text'		=> $user->getName(),
					'et_token' 			=> $token,
					'et_page' 			=> $title->getArticleID(),
					'et_timestamp_start'=> wfTimestampNow(), 
					'et_guided' 		=> ($guided?1:0)
				),
			__METHOD__); 
}

// wfProxyCheck is gone
// function wfCheckForNewAccountsFromProxy($user) {
	// wfProxyCheck();
	// return true;
// }

