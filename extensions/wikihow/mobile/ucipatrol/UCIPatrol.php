<?php
if ( ! defined( 'MEDIAWIKI' ) )
	die();

$wgExtensionCredits['specialpage'][] = array(
	'name' => 'user completed images patrol tool',
	'author' => 'Aaron G',
	'description' => 'special page to review user completion images for articles',
);

$wgSpecialPages['UCIPatrol'] = 'UCIPatrol';
$wgAutoloadClasses['UCIPatrol'] = dirname(__FILE__) . '/UCIPatrol.body.php';
$wgExtensionMessagesFiles['UCIPatrol'] = dirname(__FILE__) . '/UCIPatrol.i18n.php';
$wgExtensionMessagesFiles['UCIPatrolAliases'] = __DIR__ . '/UCIPatrol.alias.php';

$wgLogTypes[] = 'ucipatrol';
$wgLogNames['ucipatrol'] = 'ucipatrol';
$wgLogHeaders['ucipatrol'] = 'ucipatrol';

$wgAvailableRights[] = 'ucipatrol';

// sql:
// alter table user_completed_images add index (uci_article_name);
// alter table user_completed_images add `uci_upvotes` int(8) DEFAULT 0;
// alter table user_completed_images add `uci_downvotes` int(8) DEFAULT 0;

//CREATE TABLE `image_votes` (
//		`iv_pageid` int(8) unsigned NOT NULL,
//		`iv_userid` int(8) NOT NULL,
//		`iv_vote` int(8) NOT NULL,
//		`iv_added` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
//		PRIMARY KEY (`iv_pageid`,`iv_userid`)
//		)
