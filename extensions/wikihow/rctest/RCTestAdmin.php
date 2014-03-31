<?php
if ( ! defined( 'MEDIAWIKI' ) )
	die();

$wgSpecialPages['RCTestAdmin'] = 'RCTestAdmin';
$wgAutoloadClasses['RCTestAdmin'] = dirname( __FILE__ ) . '/RCTestAdmin.body.php';
$wgExtensionMessagesFiles['RCTestAdmin'] = dirname(__FILE__) . '/RCTestAdmin.i18n.php';
