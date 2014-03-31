<?php
if ( ! defined( 'MEDIAWIKI' ) )
	die();

$wgSpecialPages['CatSearchUI'] = 'CatSearchUI';
$wgAutoloadClasses['CatSearchUI'] = dirname( __FILE__ ) . '/CatSearchUI.body.php';
$wgExtensionMessagesFiles['CatSearchUI'] = dirname(__FILE__) . '/CatSearchUI.i18n.php';
