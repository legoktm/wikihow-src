<?php
if ( ! defined( 'MEDIAWIKI' ) )
	die();

$wgSpecialPages['CategoryInterests'] = 'CategoryInterests';
$wgAutoloadClasses['CategoryInterests'] = dirname( __FILE__ ) . '/CategoryInterests.body.php';
$wgExtensionMessagesFiles['CategoryInterests'] = dirname(__FILE__) . '/CategoryInterests.i18n.php';
