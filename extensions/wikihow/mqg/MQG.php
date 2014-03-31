<?php
if ( ! defined( 'MEDIAWIKI' ) )
	die();

$wgExtensionCredits['specialpage'][] = array(
	'name' => 'Mobile QG',
	'author' => 'Jordan Small',
	'description' => 'Mobile wrapper for QG tool',
);

$wgSpecialPages['MQG'] = 'MQG';
$wgAutoloadClasses['MQG'] = dirname(__FILE__) . '/MQGTest.body.php';
#$wgAutoloadClasses['MQG'] = dirname(__FILE__) . '/MQG.body.php';
$wgExtensionMessagesFiles['MQG'] = dirname(__FILE__) . '/MQG.i18n.php';
