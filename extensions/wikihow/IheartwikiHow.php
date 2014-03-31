<?php

if ( !defined( 'MEDIAWIKI' ) ) {
    exit(1);
}

$wgExtensionCredits['specialpage'][] = array(
    'name' => 'IheartwikiHow',
    'author' => 'Bebeth Steudel',
    'description' => 'Tool to allow badges on other sites',
);

$wgSpecialPages['IheartwikiHow'] = 'IheartwikiHow';
$wgAutoloadClasses['IheartwikiHow'] = dirname( __FILE__ ) . '/IheartwikiHow.body.php';
$wgSpecialPages['IheartwikiHowAdmin'] = 'IheartwikiHowAdmin';
$wgAutoloadClasses['IheartwikiHowAdmin'] = dirname( __FILE__ ) . '/IheartwikiHow.body.php';
