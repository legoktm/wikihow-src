<?php

if ( ! defined( 'MEDIAWIKI' ) )
  die();

  $wgExtensionCredits['specialpage'][] = array(
		  'name' => 'Creatives ',
		  'author' => 'Gershon Bialer',
		  'description' => 'Show the creatives'
			);
	  $wgAutoloadClasses['Creatives'] = dirname(__FILE__) . '/Creatives.body.php';
	  $wgHooks["PageHeaderDisplay"][] = 'Creatives::beforeHeaderDisplay';
	
