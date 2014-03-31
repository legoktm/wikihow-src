<?php

if ( ! defined( 'MEDIAWIKI' ) )
  die();

	$wgExtensionCredits['specialpage'][] = array(
	  'name' => 'Optimizely Enabled Tool',
	  'author' => 'Gershon Bialer',
	  'description' => 'Enable certain pages with optimizely',
	);

	$wgSpecialPages['OptimizelyPageSelector'] = 'OptimizelyPageSelector';
	$wgAutoloadClasses['OptimizelyPageSelector'] = dirname(__FILE__) . '/OptimizelyPageSelector.body.php';
