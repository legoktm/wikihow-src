<?php
if ( ! defined( 'MEDIAWIKI' ) )
	die();
    
/**#@+
 * Provides a user friendly way of adding images while editing articles.
 * 
 * @package MediaWiki
 * @subpackage Extensions
 *
 *
 *
 * @author Travis Derouin <travis@wikihow.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 */
$wgExtensionCredits['specialpage'][] = array(
    'name' => 'UploadPopup',
    'author' => 'Travis <travis@wikihow.com>',
	'description' => 'Provides a user friendly way of adding images while editing articles.',
);


$wgSpecialPages['UploadPopup'] = 'UploadPopup';
$wgAutoloadClasses['UploadPopup'] = dirname( __FILE__ ) . '/UploadPopup.body.php';

