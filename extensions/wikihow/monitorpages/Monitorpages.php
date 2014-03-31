<?php
if ( ! defined( 'MEDIAWIKI' ) )
	die();
    
/**#@+
 * 
 * @package MediaWiki
 * @subpackage Extensions
 *
 * @link http://www.wikihow.com/WikiHow:Monitorpages-Extension Documentation
 *
 *
 * @author Travis Derouin <travis@wikihow.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 */

$wgExtensionCredits['specialpage'][] = array(
    'name' => 'Monitorpages',
    'author' => 'Travis <travis@wikihow.com>',
);

$wgExtensionMessagesFiles['Monitorpages'] = dirname(__FILE__) . '/Monitorpages.i18n.php';

$wgSpecialPages['Monitorpages'] = 'Monitorpages';
$wgAutoloadClasses['Monitorpages'] = dirname( __FILE__ ) . '/Monitorpages.body.php';

