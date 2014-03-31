<?php

if ( !defined( 'MEDIAWIKI' ) ) {
    exit(1);
}

$wgExtensionCredits['specialpage'][] = array(
    'name' => 'LoginReminder',
    'author' => 'Bebeth Steudel',
    'description' => 'Tool to retrieve username/password',
);

$wgExtensionMessagesFiles['LoginReminder'] = dirname(__FILE__) . '/LoginReminder.i18n.php';
$wgSpecialPages['LoginReminder'] = 'LoginReminder';
$wgAutoloadClasses['LoginReminder'] = dirname( __FILE__ ) . '/LoginReminder.body.php';
$wgSpecialPages['LoginFacebook'] = 'LoginFacebook';
$wgAutoloadClasses['LoginFacebook'] = dirname( __FILE__ ) . '/LoginReminder.body.php';
$wgSpecialPages['LoginCheck'] = 'LoginCheck';
$wgAutoloadClasses['LoginCheck'] = dirname( __FILE__ ) . '/LoginReminder.body.php';
