<?php
if (!defined('MEDIAWIKI')) die();
    
/**#@+
 * Tweets helpful articles
 * 
 * @package MediaWiki
 * @subpackage Extensions
 *
 * @link http://www.wikihow.com/Special:TweetItForward Documentation
 *
 * @author Mark Steudel <msteudel@gmail.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 */

$wgExtensionCredits['special'][] = array(
	'name' => 'TwitterReplier',
	'author' => 'Mark Steudel',
	'description' => 'Tweet helpful wikiHow articles',
	'url' => 'http://www.wikihow.com/Special:TweetItForward',
);

$wgSpecialPages['TwitterReplier'] = 'TwitterReplier';
$wgSpecialPages['TweetItForward'] = 'TwitterReplier';
$wgAutoloadClasses['TwitterReplier'] = dirname( __FILE__ ) . '/TwitterReplier.body.php';
$wgExtensionMessagesFiles['TwitterReplier'] = dirname(__FILE__) . '/TwitterReplier.i18n.php';

// $wgCookiePrefix isn't available yet here because it's defined in Setup.php
define( 'TRCOOKIE', 'wiki_sharedTwitterReplier' );

function twitterReplierOnLogout() {
	global $wgCookiePath, $wgCookieDomain;
	setcookie( TRCOOKIE, '', time() - 604800, $wgCookiePath, $wgCookieDomain, false, true );
	if ($_SESSION && $_SESSION['hash']) {
		unset($_SESSION['hash']);
	}
	return true;
}
$wgHooks['UserLogout'][] = array('twitterReplierOnLogout');

