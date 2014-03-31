<?php

if ( ! defined( 'MEDIAWIKI' ) ) die();
    
/**#@+
 * 
 * @package MediaWiki
 * @subpackage Extensions
 *
 * @link http://www.wikihow.com/WikiHow:ManageRelated-Extension Documentation
 *
 *
 * @author Travis Derouin <travis@wikihow.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 */

$wgExtensionCredits['specialpage'][] = array(
	'name' => 'ManageRelated',
	'author' => 'Travis Derouin',
	'description' => 'Provides a way of searching, previewing and adding links to an existing article',
	'url' => 'http://www.wikihow.com/WikiHow:ManageRelated-Extension',
);

$wgSpecialPages['ManageRelated'] = 'ManageRelated';
$wgSpecialPages['RelatedArticle'] = 'ManageRelated';
$wgAutoloadClasses['ManageRelated'] = dirname( __FILE__ ) . '/ManageRelated.body.php';

$wgSpecialPages['PreviewPage'] = 'PreviewPage';
$wgAutoloadClasses['PreviewPage'] = dirname( __FILE__ ) . '/ManageRelated.body.php';

$wgExtensionMessagesFiles['RelatedArticleAlias'] = dirname( __FILE__ ) . '/RelatedArticle.alias.php';
