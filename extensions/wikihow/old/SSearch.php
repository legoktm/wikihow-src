<?php

if ( ! defined( 'MEDIAWIKI' ) )
	die();

/**#@+
 * descrip
 * 
 * @package MediaWiki
 * @subpackage Extensions
 *
 * @link http://www.wikihow.com/WikiHow:SSearch-Extension Documentation
 * @author Reuben Smith <reuben@wikihow.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 */


/**#@+
 */
$wgExtensionCredits['specialpage'][] = array(
	'name' => 'SSearch',
	'author' => 'Reuben Smith',
	'description' => 'Search articles on the site',
	'url' => 'http://www.wikihow.com/WikiHow:SSearch-Extension',
);

$wgSpecialPages['SSearch'] = 'SSearch';
$wgAutoloadClasses['SSearch'] = dirname( __FILE__ ) . '/SSearch.body.php';
#$wgExtensionMessagesFiles['SSearch'] = dirname(__FILE__) . '/SSearch.i18n.php';

