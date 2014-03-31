<?php
if ( ! defined( 'MEDIAWIKI' ) )
	die();

$wgExtensionCredits['specialpage'][] = array(
	'name' => 'Babelfish',
	'author' => 'Jordan Small',
	'description' => 'Babelfish translation title reservation system',
);

$wgExtensionCredits['specialpage'][] = array(
	'name' => 'Babelfish Admin',
	'author' => 'Jordan Small',
	'description' => 'Admin interface for Babelfish title reservation system',
);


$wgSpecialPages['Babelfish'] = 'Babelfish';
$wgSpecialPages['BabelfishAdmin'] = 'BabelfishAdmin';

$wgAutoloadClasses['Babelfish'] = dirname(__FILE__) . '/Babelfish.body.php';
$wgAutoloadClasses['BabelfishAdmin'] = dirname(__FILE__) . '/BabelfishAdmin.body.php';
$wgAutoloadClasses['BabelfishMaintenance'] = dirname(__FILE__) . '/BabelfishMaintenance.class.php';
$wgAutoloadClasses['BabelfishDB'] = dirname(__FILE__) . '/BabelfishDB.class.php';
$wgAutoloadClasses['BabelfishArticlePager'] = dirname(__FILE__) . '/BabelfishArticlePager.class.php';
$wgAutoloadClasses['WAPUIBabelfishAdmin'] = dirname(__FILE__) . '/WAPUIBabelfishAdmin.class.php';
$wgAutoloadClasses['WAPUIBabelfishUser'] = dirname(__FILE__) . '/WAPUIBabelfishUser.class.php';
$wgAutoloadClasses['BabelfishUser'] = dirname(__FILE__) . '/BabelfishUser.class.php';
$wgAutoloadClasses['BabelfishArticle'] = dirname(__FILE__) . '/BabelfishArticle.class.php';
$wgAutoloadClasses['BabelfishReport'] = dirname(__FILE__) . '/BabelfishReport.class.php';
$wgAutoloadClasses['WAPBabelfishConfig'] = dirname(__FILE__) . '/WAPBabelfishConfig.class.php';

$wgExtensionMessagesFiles['BabelfishAdmin'] = dirname(__FILE__) . '/Babelfish.i18n.php';
$wgExtensionMessagesFiles['Babelfish'] = dirname(__FILE__) . '/Babelfish.i18n.php';
