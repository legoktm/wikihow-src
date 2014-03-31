<?php
if (!defined('MEDIAWIKI')) die();

$wgExtensionCredits['specialpage'][] = array(
    'name' => 'GPlusLogin',
    'author' => 'Scott Cushman',
    'description' => 'Google+ app login integration to wikihow',
);


$wgSpecialPages['GPlusLogin'] = 'GPlusLogin'; 
$wgAutoloadClasses['GPlusLogin'] = dirname( __FILE__ ) . '/GPlusLogin.body.php';
$wgExtensionMessagesFiles['GPlusLogin'] = dirname(__FILE__) . '/GPlusLogin.i18n.php';

$wgDefaultUserOptions['show_google_authorship'] = 0;

// global $wgHooks;
// $wgHooks['UserToggles'][] = 'onUserToggles_Goog';

function onUserToggles_Goog( &$extraToggles ) {
	global $wgUser,$wgDefaultUserOptions;
	
	if ($wgUser->isGPlusUser()) {
		$extraToggles[] = 'show_google_authorship';
		
		if( !array_key_exists( "show_google_authorship", $wgUser->mOptions ) && !empty($wgDefaultUserOptions['show_google_authorship']) )
		  $wgUser->setOption("show_google_authorship", $wgDefaultUserOptions['show_google_authorship']);     
	}
	return true;
}
