<?php
if ( ! defined( 'MEDIAWIKI' ) )
	die();

$wgExtensionCredits['specialpage'][] = array(
	'name' => 'Article Ratings',
	'author' => 'Jordan Small',
	'description' => 'An extension that allows user to rate articles',
);

$wgSpecialPages['ArticleRating'] = 'ArticleRating';
$wgAutoloadClasses['ArticleRating'] = dirname(__FILE__) . '/ArticleRating.body.php';
$wgExtensionMessagesFiles['ArticleRating'] = dirname(__FILE__) . '/ArticleRating.i18n.php';
