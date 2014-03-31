<?php

if ( !defined( 'MEDIAWIKI' ) ) {
    exit(1);
}

$wgExtensionCredits['specialpage'][] = array(
    'name' => 'GoogGadget',
    'author' => 'Vu <vu@wikihow.com>',
    'description' => 'Google Gadget Page', 
);


$wgSpecialPages['GoogGadget'] = 'GoogGadget'; 
$wgAutoloadClasses['GoogGadget'] = dirname( __FILE__ ) . '/GoogGadget.body.php';
