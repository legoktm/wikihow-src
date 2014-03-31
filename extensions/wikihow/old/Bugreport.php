<?php
if ( ! defined( 'MEDIAWIKI' ) )
	die();
    
/**#@+
 * 
 * @package MediaWiki
 * @subpackage Extensions
 *
 * @link http://www.wikihow.com/WikiHow:Bugreport-Extension Documentation
 *
 *
 * @author Travis Derouin <travis@wikihow.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 */


/**#@+
 */
#$wgHooks['AfterArticleDisplayed'][] = array("wfBugreportForm");


$wgSpecialPages['Bugreport'] = 'Bugreport';
$wgAutoloadClasses['Bugreport'] = dirname( __FILE__ ) . '/Bugreport.body.php';


