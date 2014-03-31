<?php

if ( !defined('MEDIAWIKI') ) die();
    
$wgExtensionCredits['specialpage'][] = array(
	'name' => 'AdminTitles',
	'author' => 'Reuben Smith',
	'description' => 'Tool for support personnel to upload/download lists of custom titles',
);

$wgSpecialPages['AdminTitles'] = 'AdminTitles';
$wgAutoloadClasses['AdminTitles'] = dirname( __FILE__ ) . '/AdminTitles.body.php';

