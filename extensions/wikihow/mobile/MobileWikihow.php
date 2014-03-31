<?

if (!defined('MEDIAWIKI')) die();

/**#@+
 * An extension that displays a different, simpler edition of the site for
 * m.wikihow.com and mobile browsers.
 * 
 * @package MediaWiki
 * @subpackage Extensions
 *
 * @link http://www.wikihow.com/WikiHow:MobileWikihow-Extension Documentation
 * @author Reuben Smith <reuben@wikihow.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 */


/**#@+
 */
$wgExtensionCredits['specialpage'][] = array(
	'name' => 'Mobile Wikihow',
	'author' => 'Reuben Smith',
	'description' => 'Mobile edition of web site for smart phones like iPhone, Adroid, etc',
	'url' => 'http://www.wikihow.com/WikiHow:MobileWikihow-Extension',
);

$wgSpecialPages['MobileWikihow'] = 'MobileWikihow';
$wgAutoloadClasses['MobileWikihow'] = dirname( __FILE__ ) . '/MobileWikihow.body.php';
//$wgExtensionMessagesFiles['MobileWikihow'] = dirname(__FILE__) . '/MobileWikihow.i18n.php';

