<?php

if ( !defined('MEDIAWIKI') ) die();
    
$wgExtensionCredits['specialpage'][] = array(
	'name' => 'AdminConfigEditor',
	'author' => 'Reuben Smith',
	'description' => 'Tool for support personnel to edit and store config blobs',
);

$wgSpecialPages['AdminConfigEditor'] = 'AdminConfigEditor';
$wgAutoloadClasses['AdminConfigEditor'] = dirname( __FILE__ ) . '/AdminConfigEditor.body.php';

