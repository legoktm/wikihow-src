<?php

if ( !defined( 'MEDIAWIKI' ) ) {
    exit(1);
}

$wgExtensionCredits['specialpage'][] = array(
    'name' => 'Avatar',
    'author' => 'Vu <vu@wikihow.com>',
    'description' => 'Avatar profile picture for user page', 
);


$wgSpecialPages['Avatar'] = 'Avatar'; 
$wgAutoloadClasses['Avatar'] = dirname( __FILE__ ) . '/Avatar.body.php';
