<?php
if ( ! defined( 'MEDIAWIKI' ) )
	die();
    
/**#@+
 * An extension that displays number of new articles and number of rising stars
 * 
 * @package MediaWiki
 * @subpackage Extensions
 *
 *
 * @author Vu Nguyen <vu@wikihow.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 */


$wgExtensionCredits['specialpage'][] = array(
	'name' => 'Authorleaderboard',
	'author' => 'Vu Nguyen',
	'description' => 'Shows author count.  Based on patrol count',
);

$wgExtensionMessagesFiles['Authorleaderboard'] = dirname(__FILE__) . '/Authorleaderboard.i18n.php';

$wgSpecialPages['Authorleaderboard'] = 'Authorleaderboard';
$wgAutoloadClasses['Authorleaderboard'] = dirname( __FILE__ ) . '/Authorleaderboard.body.php';

