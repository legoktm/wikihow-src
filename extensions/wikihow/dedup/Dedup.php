<?php                                                                           
if ( ! defined( 'MEDIAWIKI' ) )
  die();

$wgExtensionCredits['specialpage'][] = array(
    'name' => 'Dedup',
    'author' => 'Gershon Bialer',
    'description' => 'Dedup titles',
);

$wgSpecialPages['Dedup'] = 'Dedup';
$wgAutoloadClasses['Dedup'] = dirname(__FILE__) . '/Dedup.body.php';

