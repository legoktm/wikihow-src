<?php

if ( !defined('MEDIAWIKI') ) die();

$wgSpecialPages['BuildWikihowArticle'] = 'BuildWikihowArticle';
$wgAutoloadClasses['WikihowArticleEditor'] = dirname(__FILE__) . '/WikihowArticle.class.php';
$wgAutoloadClasses['WikihowArticleHTML'] = dirname(__FILE__) . '/WikihowArticle.class.php';
$wgAutoloadClasses['BuildWikihowArticle'] = dirname(__FILE__) . '/WikihowArticle.class.php';

$wgExtensionMessagesFiles['WikihowArticleMagic'] = dirname(__FILE__) . '/WikihowArticle.i18n.magic.php';
$wgHooks['GetDoubleUnderscoreIDs'][] = array("wfAddMagicWords");

//Adding custom magic words for the parser to utilize
function wfAddMagicWords($magic_array) {
	$magic_array[] = 'forceadv';
	$magic_array[] = 'parts';
	$magic_array[] = 'methods';
	$magic_array[] = 'ways';
	return true;
}
