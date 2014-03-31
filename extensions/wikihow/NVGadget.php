<?php

if ( !defined( 'MEDIAWIKI' ) ) {
    exit(1);
}

$wgExtensionCredits['specialpage'][] = array(
    'name' => 'NVGadget',
    'author' => 'Vu <vu@wikihow.com>',
    'description' => 'Google Gadget Page', 
);


$wgSpecialPages['NVGadget'] = 'NVGadget'; 
$wgAutoloadClasses['NVGadget'] = dirname( __FILE__ ) . '/NVGadget.body.php';
