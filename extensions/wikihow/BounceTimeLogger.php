<?php
if ( ! defined( 'MEDIAWIKI' ) )
	die();

$wgExtensionCredits['specialpage'][] = array(
	'name' => 'Bounce Time Logger',
	'author' => 'Ryo',
	'description' => 'AJAX end-point to log simple timing data',
);

$wgSpecialPages['BounceTimeLogger'] = 'BounceTimeLogger';
$wgAutoloadClasses['BounceTimeLogger'] = dirname(__FILE__) . '/BounceTimeLogger.body.php';
$wgExtensionMessagesFiles['BounceTimeLogger'] = dirname(__FILE__) . '/BounceTimeLogger.i18n.php';
