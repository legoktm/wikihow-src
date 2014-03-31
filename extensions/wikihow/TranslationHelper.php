<?php
if ( ! defined( 'MEDIAWIKI' ) )
	die();
    
/**#@+
 * 
 * @package MediaWiki
 * @subpackage Extensions
 *
 * @link http://www.wikihow.com/WikiHow:TranslationHelper-Extension Documentation
 *
 *
 * @author Travis Derouin <travis@wikihow.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 */

$wgExtensionFunctions[] = 'wfTranslationHelper';
$wgTH_SourceDBName = 'wikidb_16';
$wgTH_LocalDomain = "de.wikihow.com";
$wgTH_SourceDomain = "www.wikihow.com";
$wgTH_SourceLang = "en";

$wgExtensionCredits['other'][] = array(
	'name' => 'TranslationHelper',
	'author' => 'Travis Derouin',
	'description' => 'Provides a basic tool for keeping messages up to date',
	'url' => 'http://www.wikihow.com/WikiHow:TranslationHelper-Extension',
);

function wfTranslationHelper() {
	global $wgMessageCache, $wgLogTypes, $wgLogNames, $wgHooks;


	SpecialPage::AddPage(new SpecialPage('TranslationHelper'));
	 $wgMessageCache->addMessages(
        array(
			'translationhelper' => 'Translation Helper',
			'translationhelper_view' => 'view',
			'translationhelper_extensionmessages' => 'Extension messages',
			'translationhelper_mediawikimessages' => 'Missing/Out of date Mediawiki messages',
		),
		'en'
	);
}
function wfSpecialTranslationHelperFormatRow($sk, $t, $raw = false) {
	global $wgTH_LocalDomain, $wgTH_SourceDomain;
	$link = str_replace($wgTH_LocalDomain, $wgTH_SourceDomain, $t->getFullURL()) . ($raw ? "?action=raw" : "");
	return ("<li>{$sk->makeKnownLinkObj($t, $t->getFullText(),  $sk->editUrlOptions(), '', '', 'target=new', 'class=new'  )} - <a href=\"$link\" target=\"new\">" . wfMsg('translationhelper_view') . "</a></li>\n");
}

function wfSpecialTranslationHelper() {
	global $wgTH_SourceDBName, $wgDBname ;
	global $wgMessageCache, $wgTH_SourceLang, $wgLanguageCode, $wgTH_LocalDomain, $wgTH_SourceDomain, $wgUser, $wgOut;
	$source = $wgMessageCache->getExtensionMessagesFor($wgTH_SourceLang);
	$sk = $wgUser->getSkin();
	$local = array(); ///= $wgMessageCache->getExtensionMessagesFor($wgLanguageCode);
	foreach ($source as $key => $value) {
		if (!isset($local[$key]) || $source[$key] == $local[$key]) {
			$diff[$key] = $value;
		}
	}
	$wgOut->addHTML("<h2> " . wfMsg('translationhelper_extensionmessages') . "</h2>");
	$wgOut->addHTML("<ol>");
	$diff = array_diff($source, $local);

	foreach ($diff as $key => $value) {
		$t = Title::makeTitle(NS_MEDIAWIKI, $key);
		$wgOut->addHTML(wfSpecialTranslationHelperFormatRow($sk, $t));
	}
	$wgOut->addHTML("</ol>");

	$wgOut->addHTML("<h2> " . wfMsg('translationhelper_mediawikimessages') . "</h2>");
	$wgOut->addHTML("<ol>");

	$sql = "SELECT p1.page_title as page_title, p1.page_namespace as page_namespace, p1.page_touched as page_touched, p2.page_title as local_page_title FROM $wgTH_SourceDBName.page p1 LEFT OUTER JOIN $wgDBname.page p2 
				ON p1.page_title=p2.page_title AND p1.page_namespace=p2.page_namespace 
			WHERE p1.page_namespace=8 AND (p2.page_touched IS NULL OR p1.page_touched > p2.page_touched) ORDER BY p1.page_touched ";
	$dbr = wfGetDB(DB_SLAVE);
	$res = $dbr->query($sql);
	while ($row = $dbr->fetchObject($res)) {
		$t = Title::makeTitle($row->page_namespace, $row->page_title);
		$wgOut->addHTML(wfSpecialTranslationHelperFormatRow($sk, $t, true));
	}
	$dbr->freeResult($res);
	$wgOut->addHTML("</ol>");

}



?>

