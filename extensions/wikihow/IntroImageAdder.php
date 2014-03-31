<?php
if ( ! defined( 'MEDIAWIKI' ) )
	die();
    
$wgExtensionCredits['specialpage'][] = array(
	'name' => 'IntroImageAdder',
	'author' => 'Vu Nguyen',
	'description' => 'Tool for new users to add intro images to articles that do not have them.',
);

$wgSpecialPages['IntroImageAdder'] = 'IntroImageAdder';
$wgAutoloadClasses['IntroImageAdder'] = dirname( __FILE__ ) . '/IntroImageAdder.body.php';

