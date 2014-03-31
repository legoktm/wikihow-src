<?php
if ( ! defined( 'MEDIAWIKI' ) )
	die();
    
/**#@+
 * 
 * @package MediaWiki
 * @subpackage Extensions
 *
 * @link http://www.wikihow.com/WikiHow:Managepagelist-Extension Documentation
 *
 *
 * @author Travis Derouin <travis@wikihow.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 */


$wgExtensionCredits['specialpage'][] = array(
    'name' => 'Managepagelist',
    'author' => 'Travis <travis@wikihow.com>',
    'description' => 'Manages a list of pages, such as the rising stars feed.',
);

$wgSpecialPages['Managepagelist'] = 'Managepagelist';
$wgAutoloadClasses['Managepagelist'] = dirname( __FILE__ ) . '/Managepagelist.body.php';

$wgHooks['MarkTitleAsRisingStar'][] = array('wfUpdatePageListRisingStar'); 

function wfUpdatePageListRisingStar($t) {
	$dbw = wfGetDB(DB_MASTER);
	$dbw->insert('pagelist', array('pl_page' => $t->getArticleID(), 'pl_list' => 'risingstar'));
	return true;
}

