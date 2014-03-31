<?php
if ( ! defined( 'MEDIAWIKI' ) )
	die();
/**#@+
 * 
 * @package MediaWiki
 * @subpackage Extensions
 *
 * @link http://www.wikihow.com/WikiHow:Docentsettings-Extension Documentation
 *
 *
 * @author Travis Derouin <travis@wikihow.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 */

$wgExtensionCredits['specialpage'][] = array(
    'name' => 'Docentsettings',
    'author' => 'Travis <travis@wikihow.com>',
	'description' => 'Provides a way of administering docent settings',
);

$wgExtensionMessagesFiles['Docentsettings'] = dirname(__FILE__) . '/Docentsettings.i18n.php';

$wgSpecialPages['Docentsettings'] = 'Docentsettings';
$wgAutoloadClasses['Docentsettings'] = dirname( __FILE__ ) . '/Docentsettings.body.php';

$wgLogTypes[]                      = 'doc';
$wgLogNames['doc']    = 'docentadministration';
$wgLogHeaders['doc']          = 'docentadministrationtext';
