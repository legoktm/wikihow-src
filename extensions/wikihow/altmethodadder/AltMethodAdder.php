<?php
if ( ! defined( 'MEDIAWIKI' ) )
	die();

$wgExtensionCredits['specialpage'][] = array(
	'name' => 'Alternate Methods CTA',
	'author' => 'Bebeth Steudel',
	'description' => '',
);

$wgSpecialPages['AltMethodAdder'] = 'AltMethodAdder';
$wgAutoloadClasses['AltMethodAdder'] = dirname(__FILE__) . '/AltMethodAdder.body.php';

/*****
CREATE TABLE IF NOT EXISTS `altmethodadder` (
  `ama_id` int(10) unsigned NOT NULL auto_increment,
  `ama_page` int(10) unsigned NOT NULL,
  `ama_method` text collate utf8_unicode_ci default NULL,
  `ama_steps` text collate utf8_unicode_ci default NULL,
  `ama_user` int(5) NOT NULL default 0,
  `ama_checkout` varchar(14) collate utf8_unicode_ci NOT NULL,
  `ama_checkout_user` int(5) NOT NULL,
  `ama_timestamp` varchar(14) collate utf8_unicode_ci NOT NULL,
  PRIMARY KEY  (`ama_id`),
  UNIQUE KEY `ama_id` (`am_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
 
ALTER TABLE `altmethodadder` ADD `ama_patrolled` TINYINT (3) NOT NULL DEFAULT '0';
 
*****/
