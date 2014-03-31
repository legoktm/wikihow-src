<?php
if ( ! defined( 'MEDIAWIKI' ) )
	die();

$wgSpecialPages['FBNuke'] = 'FBNuke';
$wgAutoloadClasses['FBNuke'] = dirname( __FILE__ ) . '/FBNuke.body.php';
$wgExtensionMessagesFiles['FBNuke'] = dirname(__FILE__) . '/FBNuke.i18n.php';
