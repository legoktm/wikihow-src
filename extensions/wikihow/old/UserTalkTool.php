<?php 

if ( !defined( 'MEDIAWIKI' ) ) {
    exit(1);
}

$wgExtensionCredits['specialpage'][] = array(
    'name' => 'UserTalkTool',
    'author' => 'Vu Nguyen',
    'description' => 'UserTalkTool will allow admin to post to multiple user talk pages at once',
);

$wgExtensionMessagesFiles['UserTalkTool'] = dirname(__FILE__) . '/UserTalkTool.i18n.php';
$wgSpecialPages['UserTalkTool'] = 'UserTalkTool';
$wgAutoloadClasses['UserTalkTool'] = dirname( __FILE__ ) . '/UserTalkTool.body.php';


