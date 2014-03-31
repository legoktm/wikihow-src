<?php
if ( ! defined( 'MEDIAWIKI' ) )
	die();

$wgSpecialPages['CheckG'] = 'CheckG';
$wgAutoloadClasses['CheckG'] = dirname( __FILE__ ) . '/CheckG.body.php';

