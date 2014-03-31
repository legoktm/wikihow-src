<?php

if (!defined('MEDIAWIKI')) die();

/**#@+
 * A simple extension to handle image uploads for articles on m.wikihow.com
 * and mobile browsers.
 *
 * @package MediaWiki
 * @subpackage Extensions
 *
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 */

/**#@+
 */
$wgExtensionCredits['specialpage'][] = array(
	'name' => 'Image Upload Handler',
	'author' => 'George Bahij',
	'description' => 'Simple server-side handler for image uploads through articles on the mobile site',
	'version' => 0,
	'url' => '/Special:ImageUploadHandler-Extension'
);

$wgSpecialPages['ImageUploadHandler'] = 'ImageUploadHandler';
$wgAutoloadClasses['ImageUploadHandler'] = dirname(__FILE__) . '/ImageUploadHandler.class.php';
