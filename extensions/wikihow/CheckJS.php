<?php

if ( ! defined( 'MEDIAWIKI' ) ) die();
    
/**#@+
 * 
 * @package MediaWiki
 * @subpackage Extensions
 *
 * @link http://www.wikihow.com/WikiHow:CheckJS-Extension Documentation
 *
 *
 * @author Travis Derouin <travis@wikihow.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 */

$wgExtensionCredits['specialpage'][] = array(
    'name' => 'CheckJS',
    'author' => 'Travis <travis@wikihow.com>',
    'description' => 'Customed search backend for Google Mini and wikiHow',
);

$wgSpecialPages['CheckJS'] = 'CheckJS';
$wgAutoloadClasses['CheckJS'] = dirname( __FILE__ ) . '/CheckJS.body.php';

