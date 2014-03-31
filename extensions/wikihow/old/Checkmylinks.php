<?php
if ( ! defined( 'MEDIAWIKI' ) )
	die();
    
/**#@+
 * 
 * @package MediaWiki
 * @subpackage Extensions
 *
 * @link http://www.wikihow.com/WikiHow:Checkmylinks-Extension Documentation
 *
 *
 * @author Travis Derouin <travis@wikihow.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 */

$wgExtensionFunctions[] = 'wfCheckmylinks';
$wgShowRatings = false; // set this to false if you want your ratings hidden

/**#@+
 */
#$wgHooks['AfterArticleDisplayed'][] = array("wfCheckmylinksForm");

$wgExtensionCredits['parserhook'][] = array(
	'name' => 'Checkmylinks',
	'author' => 'Travis Derouin',
	'description' => 'Provides a basic article ratings system',
	'url' => 'http://www.wikihow.com/WikiHow:Checkmylinks-Extension',
);

function wfCheckmylinks() {
	global $wgMessageCache;
	SpecialPage::AddPage(new UnlistedSpecialPage('Checkmylinks'));
	SpecialPage::AddPage(new UnlistedSpecialPage('PreviewPage'));
	 $wgMessageCache->addMessages(
        array(
			'checkmylinks' => 'Check My Links',
			'checkmylinks_summary' => 'This is a simple page that will tell you whether or not your "[[Create Custom Navigation Links on Your Sidebar on wikiHow|My links]]" are too long. Please log out and log back in again after making any changes to see your most recent links.',
			'checkmylinks_notloggedin' => 'You have to be logged in to use this feature.',
			'checkmylinks_error' => 'Your account does not have any links asscociated with it. Please see [[Create Custom Navigation Links on Your Sidebar on wikiHow]].',
			'checkmylinks_size_bad' => 'Your links are $1 bytes long. These are too long, please shorten them to allow them to less than 3,000 to allow them to  work. ',
			'checkmylinks_size_good' => 'Your links are $1 bytes long. These are OK!',
		)
	);
}


function wfSpecialCheckmylinks( $par )
{
    global $wgRequest, $wgSitename, $wgLanguageCode;
    global $wgDeferredUpdateList, $wgOut, $wgUser, $wgServer, $wgParser, $wgTitle;

    $fname = "wfCheckmylinks";
   
	$wgOut->addHTML(wfMsgWikiHtml('checkmylinks_summary')); 
	if ($wgUser->getID() > 0) {
        $t = Title::makeTitle(NS_USER, $wgUser->getName() . "/Mylinks");
        if ($t->getArticleID() > 0) {
            $r = Revision::newFromTitle($t);
            $text = $r->getText();
            if ($text != "") {
                $ret = "<h3>" . wfMsg('mylinks') . "</h3>";
                $options = new ParserOptions();
                $output = $wgParser->parse($text, $wgTitle, $options);
                $ret .= $output->getText();
            }
			$size = strlen($ret);
			if ($size > 3000) {
				$wgOut->addHTML(wfMsgWikiHtml('checkmylinks_size_bad', number_format($size, 0, "", ",")));
			} else {
				$wgOut->addHTML(wfMsgWikiHtml('checkmylinks_size_good', number_format($size, 0, "", ",")));
			}
        } else {
			$wgOut->addHTML(wfMsgWikiHtml('checkmylinks_error'));
		}
    } else {
		$wgOut->addHTML(wfMsgWikiHtml('checkmylinks_notloggedin'));
	}

}
?>
