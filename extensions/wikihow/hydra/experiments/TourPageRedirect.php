<?php                                                                                                                                                                
if ( ! defined( 'MEDIAWIKI' ) )
  die();

$wgExtensionCredits['specialpage'][] = array(
				'name' => 'TourPageRediret',
				'author' => 'Gershon Bialer',
				'description' => 'Redirect users from  wikiHow:tour to wikiHow:tour2');
$wgAutoloadClasses['TourPageRedirect'] = dirname(__FILE__) . '/TourPageRedirect.body.php';
$wgHooks["ArticleFromTitle"][] = 'TourPageRedirect::onArticleFromTitle';

