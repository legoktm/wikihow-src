<?php

if ( !defined( 'MEDIAWIKI' ) ) {
    exit(1);
}

$wgExtensionCredits['specialpage'][] = array(
    'name' => 'FBLogin',
    'author' => 'Jordan Small',
    'description' => 'Facebook app login integration to wikihow',
);


$wgSpecialPages['FBLogin'] = 'FBLogin'; 
$wgAutoloadClasses['FBLogin'] = dirname( __FILE__ ) . '/FBLogin.body.php';
$wgExtensionMessagesFiles['FBLogin'] = dirname(__FILE__) . '/FBLogin.i18n.php';

/**
 * Facebook Login debug flag -- always check-in as false and make a
 * local edit.
 */
define('FBLOGIN_DEBUG', false);

