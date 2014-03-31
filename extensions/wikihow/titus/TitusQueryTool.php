<?php
if ( ! defined( 'MEDIAWIKI' ) )
	die();

$wgExtensionCredits['specialpage'][] = array(
	'name' => 'Titus Query Tool',
	'author' => 'Jordan Small',
	'description' => 'A tool to query the Titus DB',
);

$wgSpecialPages['TitusQueryTool'] = 'TitusQueryTool';
$wgAutoloadClasses['TitusQueryTool'] = dirname(__FILE__) . '/TitusQueryTool.body.php';
$wgExtensionMessagesFiles['TitusQueryTool'] = dirname(__FILE__) . '/TitusQueryTool.i18n.php';
