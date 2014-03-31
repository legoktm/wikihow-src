<?php
if ( ! defined( 'MEDIAWIKI' ) )
	die();

$wgExtensionCredits['specialpage'][] = array(
	'name' => 'Method Editor Tool',
	'author' => 'Bebeth Steudel',
	'description' => '',
);

$wgSpecialPages['MethodEditor'] = 'MethodEditor';
$wgAutoloadClasses['MethodEditor'] = dirname(__FILE__) . '/MethodEditor.body.php';
$wgExtensionMessagesFiles['MethodEditor'] = dirname(__FILE__) . '/MethodEditor.i18n.php';
$wgExtensionMessagesFiles['MethodEditorAlias'] = dirname(__FILE__) . '/MethodEditor.alias.php';

$wgLogTypes[] = 'methedit';
$wgLogNames['methedit'] = 'methedit';
$wgLogHeaders['methedit'] = 'methedit';

/*******
CREATE TABLE IF NOT EXISTS `methodeditorlog` (
`mel_id` int(10) unsigned NOT NULL auto_increment,
`mel_timestamp` varchar(14) NOT NULL default '19700101000000',
`mel_user` int(5) NOT NULL default 0,
PRIMARY KEY  (`mel_id`),
KEY `mel_time_user` (`mel_timestamp`, `mel_user`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
******/
