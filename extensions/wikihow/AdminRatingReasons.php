<?php

if ( !defined('MEDIAWIKI') ) die();
    
$wgExtensionCredits['specialpage'][] = array(
	'name' => 'AdminRatingReasons',
	'author' =>'Argutier <aaron@wikihow.com>',
	'description' => 'Tool for support personnel to manage items were rating poorly.',
);


$wgSpecialPages['AdminRatingReasons'] = 'AdminRatingReasons';
$wgAutoloadClasses['AdminRatingReasons'] = dirname( __FILE__ ) . '/AdminRatingReasons.body.php';

$wgSpecialPages['AdminRemoveRatingReason'] = 'AdminRemoveRatingReason';
$wgAutoloadClasses['AdminRemoveRatingReason'] = dirname( __FILE__ ) . '/AdminRatingReasons.body.php';
