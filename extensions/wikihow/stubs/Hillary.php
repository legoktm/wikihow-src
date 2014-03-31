<?php

if ( !defined('MEDIAWIKI') ) die();
    
$wgExtensionCredits['specialpage'][] = array(
	'name' => 'Hillary',
	'author' => 'wikiHow',
	'description' => 'Hillary is a tool, initially created for mobile web, which allows users to vote on whether articles should have certain templates applied',
);

$wgSpecialPages['HillaryRest'] = 'HillaryRest';
$wgSpecialPages['Hillary'] = 'HillaryRest';
$wgSpecialPages['ArticleQualityGuardian'] = 'HillaryRest';
$wgSpecialPages['AdminHillary'] = 'AdminHillary';
$wgAutoloadClasses['HillaryRest'] = dirname( __FILE__ ) . '/Hillary.body.php';
$wgAutoloadClasses['Hillary'] = dirname( __FILE__ ) . '/Hillary.body.php';
$wgAutoloadClasses['AdminHillary'] = dirname( __FILE__ ) . '/Hillary.body.php';

$wgHooks['ArticleDelete'][] = array('Hillary::onDelete');

$wgLogTypes[] = 'hillary';
$wgLogNames['hillary'] = 'hillary';
$wgLogHeaders['hillary'] = 'hillary_log';

