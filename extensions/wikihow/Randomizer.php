<?php

if ( !defined( 'MEDIAWIKI' ) ) {
    exit(1);
}

$wgExtensionCredits['specialpage'][] = array(
    'name' => 'Randomizer',
    'author' => 'Travis <travis@wikihow.com>',
    'description' => 'A different way of having a random page', 
);

$wgSpecialPages['Randomizer'] = 'Randomizer';

$dir = dirname(__FILE__) . '/';

$wgAutoloadClasses['Randomizer'] = $dir . 'Randomizer.body.php';
