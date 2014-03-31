<?php
if ( ! defined( 'MEDIAWIKI' ) )
  die();

	$wgExtensionCredits['specialpage'][] = array(
	  'name' => 'NewUserPage',
	  'author' => 'Gershon Bialer',
	  'description' => 'Show a page to new users'
				  );
	$wgAutoloadClasses['NewUserPage'] = dirname(__FILE__) . '/NewUserPage.body.php';
	$wgHooks["NewUserRedirect"][] = 'NewUserPage::onNewUserRedirect';
	$wgSpecialPages['NewUserPage'] = 'NewUserPage';
