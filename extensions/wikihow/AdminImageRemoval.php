<?php
if ( ! defined( 'MEDIAWIKI' ) )
	die();

$wgExtensionCredits['specialpage'][] = array(
	'name' => 'Image Removal Tool',
	'author' => 'Bebeth Steudel',
	'description' => '',
);

$wgSpecialPages['AdminImageRemoval'] = 'AdminImageRemoval';
$wgAutoloadClasses['AdminImageRemoval'] = dirname(__FILE__) . '/AdminImageRemoval.body.php';