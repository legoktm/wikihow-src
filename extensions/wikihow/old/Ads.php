<?php

if ( !defined( 'MEDIAWIKI' ) ) {
    exit(1);
}

$wgExtensionCredits['specialpage'][] = array(
    'name' => 'Ads',
    'author' => 'Travis <travis@wikihow.com>',
    'description' => 'Custom javascript code for displaying ads', 
);

$wgSpecialPages['Ads'] = 'Ads';

# Internationalisation file
$dir = dirname(__FILE__) . '/';

$wgAutoloadClasses['Ads'] = $dir . 'Ads.body.php';
