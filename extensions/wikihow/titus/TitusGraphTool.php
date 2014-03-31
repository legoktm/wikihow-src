<?php
if ( ! defined( 'MEDIAWIKI' ) )
	die();

$wgExtensionCredits['specialpage'][] = array(
	'name' => 'Titus Graph Tool',
	'author' => 'Jordan Small',
	'description' => 'A tool to graph numeric data for articles within the Titus DB',
);

$wgSpecialPages['TitusGraphTool'] = 'TitusGraphTool';
$wgAutoloadClasses['TitusGraphTool'] = dirname(__FILE__) . '/TitusGraphTool.body.php';
$wgExtensionMessagesFiles['TitusGraphTool'] = dirname(__FILE__) . '/TitusGraphTool.i18n.php';
