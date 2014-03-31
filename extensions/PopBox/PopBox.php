<?php
if ( ! defined( 'MEDIAWIKI' ) )
	die();
    
/**#@+
 * An extension that allows users to rate articles. 
 * 
 * @package MediaWiki
 * @subpackage Extensions
 *
 * @link http://www.mediawiki.org/wiki/InternalLinksPopup_Extension Documentation
 *
 *
 * @author Travis Derouin <travis@wikihow.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 */

$wgILPB_HeaderImage = '/common/images/RelatedArticlesHeader.png';
$wgILPB_NumResults = 8;

$wgExtensionCredits['specialpage'][] = array(
	'name' => 'PopBox',
	'author' => 'Travis Derouin',
	'description' => 'Provides a basic way of adding new entries to the Spam Blacklist from diff pages',
	'url' => 'http://www.mediawiki.org/wiki/InternalLinksPopup_Extension',
);


$wgExtensionMessagesFiles['PopBox'] = dirname(__FILE__) . '/PopBox.i18n.php';

$wgAutoloadClasses['PopBox'] = dirname( __FILE__ ) . '/PopBox.body.php';

