<?php

if ( !defined('MEDIAWIKI') ) die();

$wgExtensionCredits['specialpage'][] = array(
	'name' => 'WelcomeWagon',
	'author' => 'Aaron',
	'description' => 'Tool for support personnel to help welcome new users',
);

$wgExtensionMessagesFiles['WelcomeWagon'] = dirname(__FILE__) .'/WelcomeWagon.i18n.php';

$wgSpecialPages['WelcomeWagon'] = 'WelcomeWagon';
$wgAutoloadClasses['WelcomeWagon'] = dirname( __FILE__ ) . '/WelcomeWagon.body.php';

$wgLogTypes[] = 'welcomewag';
$wgLogNames['welcomewag'] = 'welcomewag';
$wgLogHeaders['welcomewag'] = 'welcomewag_log';
