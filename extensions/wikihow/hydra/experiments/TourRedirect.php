<?php                                                                                                                                                                
if ( ! defined( 'MEDIAWIKI' ) )
  die();

$wgExtensionCredits['specialpage'][] = array(
				'name' => 'TourRediret',
				'author' => 'Gershon Bialer',
				'description' => 'Redirect new users to tour');
$wgAutoloadClasses['TourRedirect'] = dirname(__FILE__) . '/TourRedirect.body.php';
$wgHooks["NewUserRedirect"][] = 'TourRedirect::onNewUserRedirect';

