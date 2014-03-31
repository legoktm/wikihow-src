<?php
if ( ! defined( 'MEDIAWIKI' ) )
	die();
    
/**#@+
 * 
 * @package MediaWiki
 * @subpackage Extensions
 *
 * @link http://www.wikihow.com/WikiHow:Patrolcount-Extension Documentation
 *
 *
 * @author Travis Derouin <travis@wikihow.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 */


$wgExtensionCredits['specialpage'][] = array(
	'name' => 'Patrolcount',
	'author' => 'Travis Derouin',
	'description' => 'Bunches a bunch of edits of 1 user together',
	'url' => 'http://www.wikihow.com/WikiHow:Patrolcount-Extension',
);

$wgExtensionMessagesFiles['Patrolcount'] = dirname(__FILE__) . '/Patrolcount.i18n.php';

$wgSpecialPages['Patrolcount'] = 'Patrolcount';
$wgAutoloadClasses['Patrolcount'] = dirname( __FILE__ ) . '/Patrolcount.body.php';

