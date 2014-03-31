<?php
if ( ! defined( 'MEDIAWIKI' ) )
	die();
    
/**#@+
 * 
 * @package MediaWiki
 * @subpackage Extensions
 *
 * @link http://www.wikihow.com/WikiHow:RateArticle-Extension Documentation
 *
 *
 * @author Travis Derouin <travis@wikihow.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 */

$wgExtensionFunctions[] = 'wfCheckquality';
$wgShowRatings = false; // set this to false if you want your ratings hidden


$wgExtensionCredits['parserhook'][] = array(
	'name' => 'Checkquality',
	'author' => 'Travis Derouin',
	'description' => 'Basic dashboard that gives some summarized information on a page',
	'url' => 'http://www.wikihow.com/WikiHow:RateArticle-Extension',
);

function wfCheckquality() {
	SpecialPage::AddPage(new UnlistedSpecialPage('Checkquality'));
	global $wgMessageCache;
	 $wgMessageCache->addMessages(
			array ('checkquality' => 'Check Article Quality',
			)
		
	);
}



function wfSpecialCheckquality( $par )
{
    global $wgRequest, $wgSitename, $wgLanguageCode;
    global $wgDeferredUpdateList, $wgOut, $wgUser;
    $fname = "wfCheckquality";

	$sk = $wgUser->getSkin();

	$target=$wgRequest->getVal('target');

	if ($target =='') {
		$wgOut->addHTML(wfMsg('checkquality_notitle'));
		return;
	}

	$t = Title::newFromText($target);
	$id = $t->getArticleID();
	if ($id == 0) {
		$wgOut->addHTML(wfMsg("checkquality_titlenonexistant"));
		return;
	}

	$dbr = wfGetDB(DB_SLAVE);

	$related  = $dbr->selectField( "pagelinks",
				"count(*)", 
				array ('pl_from' => $id),
				"wfSpecialCheckquality"
				);
	$inbound = $dbr->selectField ("pagelinks",
				"count(*)", 
				array ('pl_namespace' => $t->getNamespace(), 
						'pl_title' => $t->getDBKey()
					),
				"wfSpecialCheckquality"
                );

	// talk page
	$f = Title::newFromText("Featured", NS_TEMPLATE);

	$tp = $t->getTalkPage();
	$featured = $dbr->selectField("templatelinks",
			"count(*)",
			array('tl_from' => $tp->getArticleID(),
				'tl_namespace' => 10,
                'tl_title' => 'Featured',
				),
			  "wfSpecialCheckquality"
              );
	$fadate = "";
	if ($featured > 0) {
		$rev = Revision::newFromTitle($tp );
		$text = $rev->getText();
		$matches = array();
		preg_match('/{{Featured.*}}/', $text, &$matches);
		$fadate = $matches[0];
		$fadate = str_replace("{{Featured|", "", $fadate);
		$fadate = str_replace("}}", "", $fadate);
		
	}
	$rev = Revision::newFromTitle($t );
	$section = Article::getSection($rev->getText(), 0);
	$intro_photo = preg_match('/\[\[Image:/', $section);
 
	$section = Article::getSection($rev->getText(), 1);
	$num_steps = preg_match_all ('/^#/im', $section, $matches);
	$num_step_photos = preg_match_all('/\[\[Image:/', $section, $matches);

	$linkshere = Title::newFromText("Whatlinkshere", NS_SPECIAL);
	$linksherelink = $sk->makeLinkObj($linkshere, $inbound, "target=" . $t->getPrefixedURL() );
	$articlelink = $sk->makeLinkObj($t, wfMsg('howto', $t->getFullText()));

	$numvotes = $dbr->selectField("rating",
            "count(*)",
            array('rat_page' => $t->getArticleID(),
                ),
              "wfSpecialCheckquality"
              );
    $rating = $dbr->selectField("rating",
            "avg(rat_rating)",
            array('rat_page' => $t->getArticleID(),
					'rat_isdeleted' => 0,
                ),
              "wfSpecialCheckquality"
              );
	$rating = number_format($rating * 100, 0, "", "");

	$a = new Article(&$t);
	$pageviews = number_format($a->getCount(), 0, "", ",");

	$wgOut->addHTML("

<style type='text/css'>

.roundcont {
	width: 450px;
	background-color: #f90;
}

.roundcont p {
	margin: 0 10px;
}

.roundtop { 
	background: url(http://kalsey.com/tools/css/rounded/images/tr.gif) no-repeat top right; 
}

.roundbottom {
	background: url(http://kalsey.com/tools/css/rounded/images/br.gif) no-repeat top right; 
}

img.corner {
   width: 15px;
   height: 15px;
   border: none;
   display: block !important;
}
</style>
		
<div class='roundcont'>
   <div class='roundtop'>
	 <img src='http://kalsey.com/tools/css/rounded/images/tl.gif' alt='' 
	 width='15' height='15' class='corner' 
	 style='display: none' />
   </div>
	<p> $articlelink<br/>
	<table border=0 cellpadding=5>
			<tr><td><b># related wikihows:</td><td> $related <br/></td></tr>
			<tr><td><b># inbound links</td><td>  $linksherelink <br/></td></tr>
			<tr><td><b>featured? </td><td>1=yes,0=no (optional date): $featured ($fadate) <br/></td></tr>
			<tr><td><b>Has intro photo? </td><td>$intro_photo <br/></td></tr>
			<tr><td><b>Number of steps:</td><td> $num_steps <br/></td></tr>
			<tr><td><b>Number of step photos:</td><td> $num_step_photos <br/></td></tr>
			<tr><td><b>page views:</td><td> $pageviews <br/></td></tr>
			<tr><td><b>accuracy :</td><td> $rating% of people found this article accurate (based on $numvotes votes) <br/>			</td></tr>
 	</table> 
	</p>
   <div class='roundbottom'>
	 <img src='http://kalsey.com/tools/css/rounded/images/bl.gif' alt='' 
	 width='15' height='15' class='corner' 
	 style='display: none' />

   </div>
</div>
			has tips? has warnings? should we include this? <br/> </p>
			");
	
	
}

?>
