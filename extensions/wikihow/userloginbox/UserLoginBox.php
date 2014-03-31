<?php

if ( !defined('MEDIAWIKI') ) die();
    
$wgExtensionCredits['specialpage'][] = array(
	'name' => 'UserLoginBox',
	'author' => 'Scott Cushman',
	'description' => 'This is the component that displays and processes user login in the header',
);

$wgSpecialPages['UserLoginBox'] = 'UserLoginBox';
$wgAutoloadClasses['UserLoginBox'] = dirname( __FILE__ ) . '/UserLoginBox.body.php';

