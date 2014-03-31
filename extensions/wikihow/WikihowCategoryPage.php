<?php
if ( ! defined( 'MEDIAWIKI' ) )
	die();
	
/**#@+
 * The wikiHow category page with tiled layout and infinite scrolling.
 * 
 * @package MediaWiki
 * @subpackage Extensions
 *
 * @link http://www.wikihow.com/WikiHow:Categoryhelper-Extension Documentation
 *
 *
 * @author Reuben Smith <reuben@wikihow.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 */

$wgAutoloadClasses['WikihowCategoryPage'] = dirname( __FILE__ ) . '/WikihowCategoryPage.body.php';
//$wgAutoloadClasses['WikihowCategoryStream'] = dirname( __FILE__) . '/WikihowCategoryPage.body.php';

$wgHooks['ArticleFromTitle'][] = array('WikihowCategoryPage::newFromTitle');
