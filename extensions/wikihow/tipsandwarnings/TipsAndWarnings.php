<?php
if ( ! defined( 'MEDIAWIKI' ) )
	die();

$wgExtensionCredits['specialpage'][] = array(
	'name' => 'Tips/Warnings CTA',
	'author' => 'Bebeth Steudel',
	'description' => '',
);

$wgSpecialPages['TipsAndWarnings'] = 'TipsAndWarnings';
$wgAutoloadClasses['TipsAndWarnings'] = dirname(__FILE__) . '/TipsAndWarnings.body.php';

$wgLogTypes[] = 'newtips';
$wgLogNames['newtips'] = 'newtips';
$wgLogHeaders['newtips'] = 'newtips';
