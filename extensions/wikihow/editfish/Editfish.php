<?php
if ( ! defined( 'MEDIAWIKI' ) )
	die();

$wgExtensionCredits['specialpage'][] = array(
	'name' => 'Editfish',
	'author' => 'Jordan Small',
	'description' => 'Wikiphoto title reservation system',
);

$wgExtensionCredits['specialpage'][] = array(
	'name' => 'Editfish Admin',
	'author' => 'Jordan Small',
	'description' => 'Admin interface for fellow editor article reservation system',
);


$wgSpecialPages['Editfish'] = 'Editfish';
$wgSpecialPages['EditfishAdmin'] = 'EditfishAdmin';

$wgAutoloadClasses['Editfish'] = dirname(__FILE__) . '/Editfish.body.php';
$wgAutoloadClasses['EditfishAdmin'] = dirname(__FILE__) . '/EditfishAdmin.body.php';
$wgAutoloadClasses['EditfishMaintenance'] = dirname(__FILE__) . '/EditfishMaintenance.class.php';
$wgAutoloadClasses['WAPUIEditfishAdmin'] = dirname(__FILE__) . '/WAPUIEditfishAdmin.class.php';
$wgAutoloadClasses['WAPUIEditfishUser'] = dirname(__FILE__) . '/WAPUIEditfishUser.class.php';
$wgAutoloadClasses['EditfishArtist'] = dirname(__FILE__) . '/EditfishArtist.class.php';
$wgAutoloadClasses['EditfishArticle'] = dirname(__FILE__) . '/EditfishArticle.class.php';
$wgAutoloadClasses['WAPEditfishConfig'] = dirname(__FILE__) . '/WAPEditfishConfig.class.php';

$wgExtensionMessagesFiles['EditfishAdmin'] = dirname(__FILE__) . '/Editfish.i18n.php';
$wgExtensionMessagesFiles['Editfish'] = dirname(__FILE__) . '/Editfish.i18n.php';
