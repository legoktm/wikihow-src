<?php
if ( ! defined( 'MEDIAWIKI' ) )
	die();

$wgExtensionCredits['specialpage'][] = array(
	'name' => 'WikiPhoto Concierge',
	'author' => 'Jordan Small',
	'description' => 'Wikiphoto title reservation system',
);

$wgExtensionCredits['specialpage'][] = array(
	'name' => 'WikiPhoto Concierge Admin',
	'author' => 'Jordan Small',
	'description' => 'Admin interface for Wikiphoto title reservation system',
);


$wgSpecialPages['Concierge'] = 'Concierge';
$wgSpecialPages['ConciergeAdmin'] = 'ConciergeAdmin';

$wgAutoloadClasses['Concierge'] = dirname(__FILE__) . '/Concierge.body.php';
$wgAutoloadClasses['ConciergeAdmin'] = dirname(__FILE__) . '/ConciergeAdmin.body.php';
$wgAutoloadClasses['ConciergeMaintenance'] = dirname(__FILE__) . '/ConciergeMaintenance.class.php';
$wgAutoloadClasses['WAPUIConciergeAdmin'] = dirname(__FILE__) . '/WAPUIConciergeAdmin.class.php';
$wgAutoloadClasses['WAPUIConciergeUser'] = dirname(__FILE__) . '/WAPUIConciergeUser.class.php';
$wgAutoloadClasses['ConciergeArtist'] = dirname(__FILE__) . '/ConciergeArtist.class.php';
$wgAutoloadClasses['ConciergeArticle'] = dirname(__FILE__) . '/ConciergeArticle.class.php';
$wgAutoloadClasses['WAPConciergeConfig'] = dirname(__FILE__) . '/WAPConciergeConfig.class.php';

$wgExtensionMessagesFiles['ConciergeAdmin'] = dirname(__FILE__) . '/Concierge.i18n.php';
$wgExtensionMessagesFiles['Concierge'] = dirname(__FILE__) . '/Concierge.i18n.php';
