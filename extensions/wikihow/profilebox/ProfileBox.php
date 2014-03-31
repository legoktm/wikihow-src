<?php

if ( !defined( 'MEDIAWIKI' ) ) {
    exit(1);
}

$wgExtensionCredits['specialpage'][] = array(
    'name' => 'ProfileBox',
    'author' => 'Vu <vu@wikihow.com>',
    'description' => 'Magic word used in profile to display user data and stats', 
);

$wgSpecialPages['ProfileBox'] = 'ProfileBox'; 
$wgAutoloadClasses['ProfileBox'] = dirname( __FILE__ ) . '/ProfileBox.body.php';
$wgAutoloadClasses['ProfileStats'] = dirname( __FILE__ ) . '/ProfileBox.body.php';
$wgExtensionMessagesFiles['ProfileBox'] = dirname( __FILE__ ) . "/ProfileBox.i18n.php";

$wgHooks['AddNewAccount'][] = array("wfCreateProfileBox");

function wfCreateProfileBox($user){
	ProfileBox::initProfileBox($user);
	return true;
}

