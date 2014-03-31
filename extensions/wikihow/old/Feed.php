<?php
if ( ! defined( 'MEDIAWIKI' ) )
	die();
    
/**#@+
 * Provides a list of activities that a user may be interested in doing on the website
 * 
 * @package MediaWiki
 * @subpackage Extensions
 *
 * @link http://www.wikihow.com/WikiHow:Feed-Extension Documentation
 *
 *
 * @author Travis Derouin <travis@wikihow.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 */

$wgExtensionCredits['special'][] = array(
	'name' => 'Feed',
	'author' => 'Travis Derouin',
	'description' => 'Provides a list of activities that a user may be interested in doing on the website', 
	'url' => 'http://www.wikihow.com/WikiHow:Feed-Extension',
);

#$wgExtensionMessagesFiles['Feed'] = dirname(__FILE__) . '/Feed.i18n.php';

$wgSpecialPages['Feed'] = 'Feed';
$wgAutoloadClasses['Feed'] = dirname( __FILE__ ) . '/Feed.body.php';

