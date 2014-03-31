<?php
if ( ! defined( 'MEDIAWIKI' ) )
	die();
    
$wgExtensionCredits['specialpage'][] = array(
	'name' => 'Starter Tool',
	'author' => 'Scott Cushman',
	'description' => 'Intro tool for new users.',
);

$wgSpecialPages['StarterTool'] = 'StarterTool';
$wgSpecialPages['StarterToolAdmin'] = 'StarterToolAdmin';
$wgAutoloadClasses['StarterTool'] = dirname( __FILE__ ) . '/StarterTool.body.php';
$wgAutoloadClasses['StarterToolAdmin'] = dirname(__FILE__) . '/StarterTool.body.php';
$wgExtensionMessagesFiles['StarterTool'] = dirname(__FILE__) . '/StarterTool.i18n.php';
	
$wgLogTypes[]            = 'starter';
$wgLogNames['starter']   = 'starter';
$wgLogHeaders['starter'] = 'startertext';

$wgStarterPages = array('wikiHow:StarterTool001','wikiHow:StarterTool002','wikiHow:StarterTool003',
						'wikiHow:StarterTool004','wikiHow:StarterTool005','wikiHow:StarterTool006',
						'wikiHow:StarterTool007','wikiHow:StarterTool008','wikiHow:StarterTool009',
						'wikiHow:StarterTool010','wikiHow:StarterTool011','wikiHow:StarterTool012');

$wgHooks["AddNewAccount"][] = "wfCheckStarterRef";

function wfCheckStarterRef($user) {
	global $wgRequest;
	
	if($_COOKIE[StarterTool::COOKIE_NAME] == "2") {
		StarterTool::logInfo("signup");
	}
	else if($_COOKIE[StarterTool::COOKIE_NAME] == "1") {
		StarterTool::logInfo("signup_top");
	}
	
	return true;
}

/**
 * 
 CREATE TABLE `wikidb_112`.`startertool` (
`st_user` INT( 10 ) NOT NULL ,
`st_username` VARCHAR( 255) NOT NULL,
`st_date` VARCHAR( 14 ) NOT NULL ,
`st_action` VARCHAR( 20 ) NOT NULL
) ENGINE = MYISAM ;
 
 * 
 */
