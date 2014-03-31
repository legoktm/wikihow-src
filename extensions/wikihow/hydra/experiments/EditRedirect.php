<?php
if ( ! defined( 'MEDIAWIKI' ) )
  die();

	$wgExtensionCredits['specialpage'][] = array(
	  'name' => 'EditRedirect',
	  'author' => 'Gershon Bialer',
	  'description' => 'Do a redirect on the edit',
				  );
	$wgAutoloadClasses['EditRedirect'] = dirname(__FILE__) . '/EditRedirect.body.php';
	$wgHooks['HydraOnMainEdit'][] = 'EditRedirect::onHydraMainEdit';
	$wgHooks["PageHeaderDisplay"][] = 'EditRedirect::beforeHeaderDisplay';

	$wgExtensionCredits['specialpage'][] = array(
	  'name' => 'EditRedirect2',
	  'author' => 'Gershon Bialer',
	  'description' => 'Do a redirect on the edit',
				  );
	$wgAutoloadClasses['EditRedirect2'] = dirname(__FILE__) . '/EditRedirect2.body.php';
	$wgHooks['HydraStartExperiment'][] = 'EditRedirect2::onHydraRunExperiment';
	$wgHooks["PageHeaderDisplay"][] = 'EditRedirect2::beforeHeaderDisplay';
	$wgSpecialPages['EditRedirect2'] = 'EditRedirect2';
