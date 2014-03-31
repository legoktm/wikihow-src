<?php
if ( ! defined( 'MEDIAWIKI' ) )
	die();

$wgExtensionCredits['specialpage'][] = array(
	'name' => 'Tips Guardian Tool',
	'author' => 'Bebeth Steudel',
	'description' => '',
);

$wgSpecialPages['TipsPatrol'] = 'TipsPatrol';
$wgAutoloadClasses['TipsPatrol'] = dirname(__FILE__) . '/TipsPatrol.body.php';
$wgExtensionMessagesFiles['TipsPatrol'] = dirname(__FILE__) . '/TipsPatrol.i18n.php';
$wgExtensionMessagesFiles['TipsPatrolAliases'] = __DIR__ . '/TipsPatrol.alias.php';

$wgLogTypes[] = 'newtips';
$wgLogNames['newtips'] = 'newtips';
$wgLogHeaders['newtips'] = 'newtips';

//$wgHooks["ArticleSaveComplete"][] = array("TipsPatrol::articleSaved");
/*****
*  CREATE TABLE IF NOT EXISTS `tipsandwarnings` (
*    `tw_id` int(10) unsigned NOT NULL auto_increment,
*    `tw_page` int(10) unsigned NOT NULL,
*    `tw_tip` varchar(200) collate utf8_unicode_ci default NULL,
*    `tw_user` int(5) NOT NULL default 0,
*    `tw_checkout` varchar(14) collate utf8_unicode_ci NOT NULL,
*    `tw_checkout_user` int(5) NOT NULL,
*    PRIMARY KEY  (`tw_id`),
*    UNIQUE KEY `tw_id` (`tw_id`),
*    UNIQUE KEY `tw_page` (`tw_page`)
*  ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
*
* CREATE TABLE IF NOT EXISTS `tipsandwarnings_log` (
*   `tw_id` int(10) unsigned default NULL,
*   `tw_page` int(10) unsigned NOT NULL,
*   `tw_tip` text collate utf8_unicode_ci,
*   `tw_user` int(5) NOT NULL default '0',
*   `tw_checkout` varchar(14) collate utf8_unicode_ci NOT NULL,
*   `tw_checkout_user` int(5) NOT NULL,
*   `tw_timestamp` varchar(14) collate utf8_unicode_ci NOT NULL default '',
*   `tw_action` tinyint(3) unsigned NOT NULL default '0',
*   KEY `tw_timestamp` (`tw_timestamp`),
*   KEY `tw_id` (`tw_id`)
* ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci |
*
* ALTER TABLE tipsandwarnings_log ADD `tw_rev_this` int(8) DEFAULT NULL;
* ALTER TABLE tipsandwarnings_log ADD `tw_qc_id` int(8) DEFAULT NULL;
*
*
*****/
