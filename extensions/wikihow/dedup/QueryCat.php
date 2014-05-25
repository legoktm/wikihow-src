<?php                                                                           
if ( ! defined( 'MEDIAWIKI' ) )
  die();

$wgExtensionCredits['specialpage'][] = array(
    'name' => 'QueryCat',
    'author' => 'Gershon Bialer',
    'description' => 'Find the categories on queries',
);

$wgSpecialPages['QueryCat'] = 'QueryCat';
$wgAutoloadClasses['QueryCat'] = dirname(__FILE__) . '/QueryCat.body.php';

