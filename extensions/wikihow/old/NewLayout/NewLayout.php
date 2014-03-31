<?
if (!defined('MEDIAWIKI')) die();

/**#@+
 * An extension that displays an article page with a totally radical layout
 */

$wgExtensionCredits['specialpage'][] = array(
	'name' => 'New Layout',
	'author' => 'Scott Cushman',
	'description' => 'Reformats articles into totally radical new layouts',
);

$wgSpecialPages['NewLayout'] = 'NewLayout';
$wgAutoloadClasses['NewLayout'] = dirname( __FILE__ ) . '/NewLayout.body.php';

$wgHooks["PageHeaderDisplay"][] = "NewLayout::header";
$wgHooks['ShowSideBar'][] = array('NewLayout::removeSideBarCallback');
