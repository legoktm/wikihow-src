<?php
if ( ! defined( 'MEDIAWIKI' ) )
	die();

$wgExtensionCredits['specialpage'][] = array(
	'name' => '<extname>',
	'author' => '<author>',
	'description' => '<description>',
);

$wgSpecialPages['<classname>'] = '<classname>';
$wgAutoloadClasses['<classname>'] = dirname(__FILE__) . '/<classname>.body.php';
$wgExtensionMessagesFiles['<classname>'] = dirname(__FILE__) . '/<classname>.i18n.php';
