<?php
if ( ! defined( 'MEDIAWIKI' ) )
	die();
    
/**#@+
 * 
 * @package MediaWiki
 * @subpackage Extensions
 *
 * @link http://www.wikihow.com/WikiHow:Netseer-Extension Documentation
 *
 *
 * @author Travis Derouin <travis@wikihow.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 */


$wgExtensionCredits['specialpage'][] = array(
    'name' => 'Netseer',
    'author' => 'Travis <travis@wikihow.com>',
    'description' => 'Netseer landing page',
);

$wgSpecialPages['Netseer'] = 'Netseer';
$wgAutoloadClasses['Netseer'] = dirname( __FILE__ ) . '/Netseer.body.php';

