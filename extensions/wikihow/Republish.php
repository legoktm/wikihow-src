<?php
if ( ! defined( 'MEDIAWIKI' ) )
	die();
    
/**#@+
 * 
 * @package MediaWiki
 * @subpackage Extensions
 *
 * @link http://www.wikihow.com/WikiHow:RateArticle-Extension Documentation
 *
 *
 * @author Travis Derouin <travis@wikihow.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 */

/**#@+
 */
$wgExtensionCredits['specialpage'][] = array(
	'name' => 'Republish',
	'author' => 'Travis Derouin',
	'description' => 'Quick and easy way to copy an article to a blog or personal website',
);


$wgExtensionMessagesFiles['Republish'] = dirname(__FILE__) . '/Republish.i18n.php';

$wgSpecialPages['Republish'] = 'Republish';
$wgAutoloadClasses['Republish'] = dirname( __FILE__ ) . '/Republish.body.php';

