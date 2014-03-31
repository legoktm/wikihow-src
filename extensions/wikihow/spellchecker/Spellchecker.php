<?php
if ( ! defined( 'MEDIAWIKI' ) )
	die();

$wgExtensionCredits['specialpage'][] = array(
	'name' => 'Spellchecker',
	'author' => 'Bebeth Steudel',
	'description' => 'Tool to help users find and correct spelling mistakes',
);

$wgSpecialPages['Spellchecker'] = 'Spellchecker';
$wgSpecialPages['Spellcheckerwhitelist'] = 'Spellcheckerwhitelist';
$wgSpecialPages['SpellcheckerArticleWhitelist'] = 'SpellcheckerArticleWhitelist';
$wgSpecialPages['ProposedWhitelist'] = 'ProposedWhitelist';
$wgAutoloadClasses['Spellchecker'] = dirname(__FILE__) . '/Spellchecker.body.php';
$wgAutoloadClasses['wikiHowDictionary'] = dirname(__FILE__) . '/Spellchecker.body.php';
$wgAutoloadClasses['Spellcheckerwhitelist'] = dirname(__FILE__) . '/Spellchecker.body.php';
$wgAutoloadClasses['SpellcheckerArticleWhitelist'] = dirname(__FILE__) . '/Spellchecker.body.php';
$wgAutoloadClasses['ProposedWhitelist'] = dirname(__FILE__) . '/Spellchecker.body.php';
$wgExtensionMessagesFiles['Spellchecker'] = dirname(__FILE__) . '/Spellchecker.i18n.php';

$wgLogTypes[] = 'spellcheck';
$wgLogTypes[] = 'whitelist';
$wgLogNames['spellcheck'] = 'spellcheck';
$wgLogNames['whitelist'] = 'spellchecker whitelist';
$wgLogHeaders['spellcheck'] = 'spellcheck_log';
$wgLogHeaders['whitelist'] = 'whitelist_log';

$wgHooks["ArticleSaveComplete"][] = "wfCheckspelling";
$wgHooks["ArticleDelete"][] = "wfRemoveCheckspelling";
$wgHooks["ArticleUndelete"][] = "wfUndeleteCheckpelling";

function wfCheckspelling(&$article, &$user, $text, $summary, $minoredit, $watchthis, $sectionanchor, &$flags, $revision) {
	if($article->mTitle->getNamespace() == NS_MAIN)
		Spellchecker::markAsDirty($article->getID());

	return true;
}

function wfRemoveCheckspelling($wikiPage, $user, $reason) {
	if($wikiPage->getTitle()->getNamespace() == NS_MAIN)
		Spellchecker::markAsIneligible($wikiPage->getId());
	
	return true;
}

function wfUndeleteCheckpelling( $title, $create) {
	if(!$create && $title->getNamespace() == NS_MAIN)
		Spellchecker::markAsDirty($title->getArticleID());

	return true;
}
