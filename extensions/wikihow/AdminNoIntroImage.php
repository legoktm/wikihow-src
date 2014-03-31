<?php

if ( !defined('MEDIAWIKI') ) die();
    
$wgExtensionCredits['specialpage'][] = array(
	'name' => 'AdminNoIntroImage',
	'author' => 'Scott Cushman',
	'description' => 'Tool to remove intro images in articles given a list of wikiHow URLs',
);

$wgSpecialPages['AdminNoIntroImage'] = 'AdminNoIntroImage';
$wgAutoloadClasses['AdminNoIntroImage'] = dirname( __FILE__ ) . '/AdminNoIntroImage.body.php';

