<?php
if ( ! defined( 'MEDIAWIKI' ) )
	die();

$wgAutoloadClasses['TextScroller'] = dirname(__FILE__) . '/TextScroller.body.php';
$wgExtensionMessagesFiles['TextScroller'] = dirname(__FILE__) . '/TextScroller.i18n.php';

$wgExtensionCredits['parserhook'][] = array(
    'name'=>'txtscrl',
    'author'=>'Jordan Small',
    'description'=>'Adds a parser function to embed the text scrolling widget', 
);

$wgHooks['LanguageGetMagic'][] = 'TextScroller::languageGetMagic';

if (defined('MW_SUPPORTS_PARSERFIRSTCALLINIT')) {
    $wgHooks['ParserFirstCallInit'][] = 'TextScroller::setParserFunction';
} else {
	$wgExtensionFunctions[] = "TextScroller::setParserFunction";
}
