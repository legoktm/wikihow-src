<?php
if ( !defined( 'MEDIAWIKI' ) ) {
    exit(1);
}

$wgExtensionCredits['specialpage'][] = array(
    'name' => 'GenerateJSFeed',
    'author' => 'Travis <travis@wikihow.com>',
    'description' => 'An extension that displays the featured articles in javascript.',
);

$wgSpecialPages['GenerateJSFeed'] = 'GenerateJSFeed';
$wgAutoloadClasses['GenerateJSFeed'] = dirname( __FILE__ ) . '/GenerateJSFeed.body.php';

