<?php
if ( ! defined( 'MEDIAWIKI' ) )
	die();

$wgExtensionCredits['specialpage'][] = array(
	'name' => 'Method Guardian Tool',
	'author' => 'Bebeth Steudel',
	'description' => '',
);

$wgSpecialPages['MethodGuardian'] = 'MethodGuardian';
$wgAutoloadClasses['MethodGuardian'] = dirname(__FILE__) . '/MethodGuardian.body.php';
$wgExtensionMessagesFiles['MethodGuardian'] = dirname(__FILE__) . '/MethodGuardian.i18n.php';
//var_dump($wgExtensionMessagesFiles['MethodGuardian']);

$wgLogTypes[] = 'methgua';
$wgLogNames['methgua'] = 'methgua';
$wgLogHeaders['methgua'] = 'methgua';
