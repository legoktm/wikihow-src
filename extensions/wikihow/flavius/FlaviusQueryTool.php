<?php

if ( ! defined( 'MEDIAWIKI' ) )
  die();

	$wgExtensionCredits['specialpage'][] = array(
	  'name' => 'Flavius Query Tool',
	  'author' => 'Gershon Bialer',
	  'description' => 'A tool to query the Users',
	);

	$wgSpecialPages['FlaviusQueryTool'] = 'FlaviusQueryTool';
	$wgAutoloadClasses['FlaviusQueryTool'] = dirname(__FILE__) . '/FlaviusQueryTool.body.php';
