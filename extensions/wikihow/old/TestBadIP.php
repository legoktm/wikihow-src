<?php
if ( ! defined( 'MEDIAWIKI' ) )
	die();
    
/**#@+
 * 
 * @package MediaWiki
 * @subpackage Extensions
 *
 * @link http://www.wikihow.com/WikiHow:TestBadIP-Extension Documentation
 *
 *
 * @author Travis Derouin <travis@wikihow.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 */

$wgExtensionCredits['special'][] = array(
	'name' => 'TestBadIP',
	'author' => 'Travis Derouin',
	'description' => 'Testing a bug', 
);


$wgSpecialPages['TestBadIP'] = 'TestBadIP';
$wgAutoloadClasses['TestBadIP'] = dirname( __FILE__ ) . '/TestBadIP.body.php';

