<?php 
if ( !defined( 'MEDIAWIKI' ) ) {
    exit(1);
}

$wgExtensionCredits['parserhook'][] = array(
    'name'=>'WHVid',
    'author'=>'Jordan Small',
    'description'=>'Adds a parser function to embed wikihow-created videos', 
);

$wgExtensionMessagesFiles['WHVid'] = dirname(__FILE__) . '/WHVid.i18n.php';
$wgAutoloadClasses['WHVid'] = dirname(__FILE__) . '/WHVid.body.php';
$wgHooks['LanguageGetMagic'][] = 'WHVid::languageGetMagic';

if (defined('MW_SUPPORTS_PARSERFIRSTCALLINIT')) {
    $wgHooks['ParserFirstCallInit'][] = 'WHVid::setParserFunction';
} else {
	$wgExtensionFunctions[] = "WHVid::setParserFunction";
}
