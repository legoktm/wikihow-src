<?php
if ( ! defined( 'MEDIAWIKI' ) )
	die();

/**#@+
 * The wikiHow homepage with based on 2013 redesign.
 *
 * @package MediaWiki
 * @subpackage Extensions
 *
 * @author Bebeth Steudel <bebeth@wikihow.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 */

$wgAutoloadClasses['WikihowHomepage'] = dirname( __FILE__ ) . '/WikihowHomepage.body.php';

$wgHooks['ArticleFromTitle'][] = array('WikihowHomepage::onArticleFromTitle');
