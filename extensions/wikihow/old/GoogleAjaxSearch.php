<?php

if ( !defined( 'MEDIAWIKI' ) ) {
    exit(1);
}

$wgExtensionCredits['specialpage'][] = array(
    'name' => 'GoogleAjaxSearch',
    'author' => 'Travis <travis@wikihow.com>',
    'description' => 'Provides an interface for users to suggest new articles.',
);

$wgSpecialPages['GoogleAjaxSearch'] = 'GoogleAjaxSearch';

# Internationalisation file
$dir = dirname(__FILE__) . '/';
#$wgExtensionMessagesFiles['GoogleAjaxSearch'] = $dir . 'GoogleAjaxSearch.i18n.php';

$wgAutoloadClasses['GoogleAjaxSearch']          = $dir . 'GoogleAjaxSearch.body.php';

$wgGoogleAjaxKey = WH_GOOGLE_AJAX_SEARCH_API_KEY;
$wgGoogleAjaxSig = WH_GOOGLE_AJAX_SEARCH_SIG;
