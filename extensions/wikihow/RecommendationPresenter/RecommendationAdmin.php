<?php

if ( ! defined( 'MEDIAWIKI' ) )
  die();

  $wgExtensionCredits['specialpage'][] = array(
		  'name' => 'Recommendation Admin Tool',
		  'author' => 'Gershon Bialer',
		  'description' => 'A tool to get info about the recommendations',
		  );

$wgSpecialPages['RecommendationAdmin'] = 'RecommendationAdmin';
$wgAutoloadClasses['RecommendationAdmin'] = dirname(__FILE__) . '/RecommendationAdmin.body.php';

