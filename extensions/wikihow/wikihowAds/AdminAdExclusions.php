<?php
if ( ! defined( 'MEDIAWIKI' ) )
	die();

$wgExtensionCredits['specialpage'][] = array(
	'name' => 'Ad Exclusions Tool',
	'author' => 'Bebeth Steudel',
	'description' => '',
);

$wgSpecialPages['AdminAdExclusions'] = 'AdminAdExclusions';
$wgAutoloadClasses['AdminAdExclusions'] = dirname(__FILE__) . '/AdminAdExclusions.body.php';

/****
CREATE TABLE IF NOT EXISTS `adexclusions` (
`ae_page` int(10) unsigned NOT NULL,
UNIQUE KEY  (`ae_page`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
****/