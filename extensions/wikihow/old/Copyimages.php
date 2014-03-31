<?php
if ( ! defined( 'MEDIAWIKI' ) )
	die();
    
/**#@+
 * 
 * @package MediaWiki
 * @subpackage Extensions
 *
 * @link http://www.wikihow.com/WikiHow:Copyimages-Extension Documentation
 *
 *
 * @author Travis Derouin <travis@wikihow.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 */

if ( !defined( 'MEDIAWIKI' ) ) {
    exit(1);
}
$wgCopyimagesEnabled = true;
$wgCopyimagesBaseURL = "http://www.wikihow.com";


$wgExtensionCredits['specialpage'][] = array(
    'name' => 'Copyimages',
    'author' => 'Travis <travis@wikihow.com>',
    'description' => 'Provides a way to import missing images from another wiki',
);

$wgSpecialPages['Copyimages'] = 'Copyimages';
#$wgSpecialPages['CopyimagesComment'] = 'CopyimagesComment';

# Internationalisation file
$dir = dirname(__FILE__) . '/';
$wgExtensionMessagesFiles['Copyimages'] = $dir . 'Copyimages.i18n.php';

$wgAutoloadClasses['Copyimages'] = $dir . 'Copyimages.body.php';

