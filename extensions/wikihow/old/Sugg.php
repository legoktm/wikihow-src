<?php
if ( ! defined( 'MEDIAWIKI' ) )
	die();
    
/**#@+
 * Customed search backend for Google Mini and wikiHow
 * 
 * @package MediaWiki
 * @subpackage Extensions
 *
 * @link http://www.wikihow.com/WikiHow:Sugg-Extension Documentation
 *
 *
 * @author Travis Derouin <travis@wikihow.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 */


$wgExtensionCredits['specialpage'][] = array(
    'name' => 'Sugg',
    'author' => 'Travis <travis@wikihow.com>',
    'description' => 'Customed search backend for Google Mini and wikiHow',
);

$wgSpecialPages['Sugg'] = 'Sugg';
$wgAutoloadClasses['Sugg'] = dirname( __FILE__ ) . '/Sugg.body.php';

