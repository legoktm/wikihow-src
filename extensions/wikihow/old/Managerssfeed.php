<?php

if ( !defined( 'MEDIAWIKI' ) ) {
exit(1);
}

/**#@+
 * A simple extension that allows users to enter a title before creating a page. 
 * 
 * @package MediaWiki
 * @subpackage Extensions
 *
 *
 *
 * @author Travis Derouin <travis@wikihow.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 */
$wgExtensionCredits['specialpage'][] = array(
	'name' => 'Managerssfeed',
	'author' => 'Travis Derouin',
	'description' => 'Provides a basic way entering a title and searching for potential duplicate articles before creating a page',
	'url' => 'http://www.wikihow.com/WikiHow:Managerssfeed-Extension',
);
$wgSpecialPages['Managerssfeed'] = 'Managerssfeed';
$wgAutoloadClasses['Managerssfeed'] = dirname( __FILE__ ) . '/Managerssfeed.body.php';

$wgLogTypes[]                   = 'rssfeed';
$wgLogNames['rssfeed']   		= 'rssfeed';
$wgLogHeaders['rssfeed'] 		= 'rssfeedtext';
$wgLogActions['rssfeed/added'] 	= 'rssfeed_logsummary';
$wgLogActions['rssfeed/removed']= 'rssfeed_removed';

