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
	'name' => 'Charityleaderboard',
	'author' => 'Vu Nguyen',
	'description' => 'Shows author count.  Based on patrol count',
);

$wgExtensionMessagesFiles['Charityleaderboard'] = dirname(__FILE__) . '/Charityleaderboard.i18n.php';

$wgSpecialPages['Charityleaderboard'] = 'Charityleaderboard';
$wgAutoloadClasses['Charityleaderboard'] = dirname( __FILE__ ) . '/Charityleaderboard.body.php';

