<?php
if ( ! defined( 'MEDIAWIKI' ) )
	die();

$wgSpecialPages['AdminMethodEditor'] = 'AdminMethodEditor';
$wgAutoloadClasses['AdminMethodEditor'] = dirname( __FILE__ ) . '/AdminMethodEditor.body.php';
