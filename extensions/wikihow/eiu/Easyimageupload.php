<?php

if ( ! defined( 'MEDIAWIKI' ) )
	die();

/**#@+
 * An extension that allows users to upload an image while on the edit page
 * without leaving that page.
 * 
 * @package MediaWiki
 * @subpackage Extensions
 *
 * @link http://www.wikihow.com/WikiHow:Easyimageupload-Extension Documentation
 * @author Reuben Smith <reuben@wikihow.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 */

/**#@+
 */
$wgExtensionCredits['specialpage'][] = array(
	'name' => 'Easy Image Upload',
	'author' => 'Reuben Smith',
	'description' => 'Provides an easy way of uploading and adding images to articles',
	'url' => 'http://www.wikihow.com/WikiHow:Easyimageupload-Extension',
);

$wgSpecialPages['Easyimageupload'] = 'Easyimageupload';
$wgAutoloadClasses['Easyimageupload'] = dirname( __FILE__ ) . '/Easyimageupload.body.php';
$wgExtensionMessagesFiles['Easyimageupload'] = dirname(__FILE__) . '/Easyimageupload.i18n.php';

//$wgSpecialPages['Recentuploads'] = 'Recentuploads';
//$wgAutoloadClasses['Recentuploads'] = dirname( __FILE__ ) . '/Easyimageupload.body.php';

$wgSpecialPages['Findimages'] = 'Findimages';
$wgAutoloadClasses['Findimages'] = dirname( __FILE__ ) . '/Easyimageupload.body.php';

//$wgHooks['ArticleAfterFetchContent'][] = array('Easyimageupload::preParserIntroImageNotFoundHook');
$wgHooks['EditPageToolbarPost'][] = array('Easyimageupload::postDisplayAdvancedToolbarHook');

