<?php

if ( !defined('MEDIAWIKI') ) die();
    
$wgExtensionCredits['specialpage'][] = array(
	'name' => 'Pagestats',
	'author' => 'Bebeth Steudel',
	'description' => 'Boring stats on article pages',
);

$wgSpecialPages['Pagestats'] = 'Pagestats';
$wgAutoloadClasses['Pagestats'] = dirname( __FILE__ ) . '/Pagestats.body.php';
$wgExtensionMessagesFiles['Pagestats'] = dirname(__FILE__) . '/Pagestats.i18n.php';

