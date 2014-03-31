<?php
if ( ! defined( 'MEDIAWIKI' ) )
	die();
    
/**#@+
 * An extension that allows users to rate articles. 
 * 
 * @package MediaWiki
 * @subpackage Extensions
 *
 * @link http://www.wikihow.com/WikiHow:YouTubeAuthSub-Extension Documentation
 *
 *
 * @author Travis Derouin <travis@wikihow.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 */


$wgYTAS_UseClientLogin = true; 

# Fill out if you are using $wgUseClientLogin
$wgYTAS_User = "wikihow";
$wgYTAS_Password = "37k97w";
#j$wgYTAS_DeveloperId = "AI39si5X2oN_gVOHrM9UcT3pzZfPvi3rlXNl57m8DAdsIXzlu3Kno-o88v8fN9rRcrprgBafmRkzLcskWlrE66ajX2mVVrNivg";
$wgYTAS_DeveloperId = "AI39si4iy2OURVIAjHloVUR6LrDE1tj2hZ7IxWsnpN6gqc__78nU5yxf2pBk8HHAYEWy-ESbUqBBHLgzYtpys6mfFRP_jGokQQ";
$wgYTAS_ClientId 	="ytapi-wikihow-wikihowvideouplo-t2cpn3tk-0";
$wgYTAS_DefaultCategory = "Howto";

$wgYTAS_EnableLogging = true; 
$wgYTAS_UseNamespace = true; 

define ( 'NS_YOUTUBE' , 20);
define ( 'NS_YOUTUBE_TALK' , 21);
$wgExtraNamespaces[NS_YOUTUBE] = "YouTube";
$wgExtraNamespaces[NS_YOUTUBE_TALK] = "YouTube_talk";

/**#@+
 */
#$wgHooks['AfterArticleDisplayed'][] = array("wfYouTubeAuthSubForm");

$wgExtensionCredits['specialpage'][] = array(
	'name' => 'YouTubeAuthSub',
	'author' => 'Travis Derouin',
	'description' => 'Provides way of uploading videos directly to YouTube through the wiki',
	'url' => 'http://www.mediawiki.org/wiki/Extension:YouTubeAuthSub',
);

# Internationalisation file
$wgExtensionMessagesFiles['YouTubeAuthSub'] = dirname(__FILE__) . '/YouTubeAuthSub.i18n.php';

$wgSpecialPages['YouTubeAuthSub'] = 'YouTubeAuthSub';
$wgAutoloadClasses['YouTubeAuthSub'] = dirname( __FILE__ ) . '/YouTubeAuthSub.body.php';

