<?php
if ( ! defined( 'MEDIAWIKI' ) )
	die();
    
/**#@+
 * 
 * @package MediaWiki
 * @subpackage Extensions
 *
 * @link http://www.wikihow.com/WikiHow:Radlinks-Extension Documentation
 *
 *
 * @author Travis Derouin <travis@wikihow.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later */


/**#@+
 */
#$wgHooks['AfterArticleDisplayed'][] = array("wfRadlinksForm");

$wgExtensionCredits['specialpage'][] = array(
	'name' => 'Radlinks',
	'author' => 'Travis Derouin',
	'description' => 'Displays RAD links landing page',
);

$wgSpecialPages['Radlinks'] = 'Radlinks';
$dir = dirname(__FILE__) . '/';
$wgAutoloadClasses['Radlinks'] = $dir . 'Radlinks.body.php';
