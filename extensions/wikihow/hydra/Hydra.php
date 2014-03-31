<?php
if ( ! defined( 'MEDIAWIKI' ) )
  die();

$wgExtensionCredits['specialpage'][] = array(
	'name' => 'Hydra',
	'author' => 'Gershon Bialer',
	'description' => 'AB Testing Framework',
	);
$wgAutoloadClasses['Hydra'] = dirname(__FILE__) . '/Hydra.body.php';
$wgHooks['ArticleSaveComplete'][] = 'Hydra::onArticleSaveComplete';
$wgHooks['AddNewAccount'][] = 'Hydra::onNewAccount';
$wgSpecialPages['Hydra'] = 'Hydra';

require_once('experiments/EditRedirect.php');
require_once('experiments/TourRedirect.php');
require_once('experiments/TourPageRedirect.php');
require_once('experiments/Creatives.php');
