<?
class Authorleaderboard extends SpecialPage {

    function __construct() {
        parent::__construct( 'Authorleaderboard' );
    }

    function execute ($par) {
		global $wgRequest, $wgOut, $wgUser, $wgLang, $wgLanguageCode;

		if ($wgLanguageCode != 'en') {
			$wgOut->showErrorPage( 'nosuchspecialpage', 'nospecialpagetext' );
			return;
		}
		$target = isset( $par ) ? $par : $wgRequest->getVal( 'target' );
		$sk = $wgUser->getSkin();
		$dbr = &wfGetDB(DB_SLAVE);
	
		$wgOut->addHTML('  <style type="text/css" media="all">/*<![CDATA[*/ @import "/extensions/wikihow/authors/Authorleaderboard.css"; /*]]>*/</style>');	
		
		$me = Title::makeTitle(NS_SPECIAL, "Authorleaderboard");
	
		if (date('w',time()) == 1) {
			// Special case for the day it switches since strtotime is not consistent
			$startdate = strtotime('monday');
			$nextdate = strtotime('next monday');
		} else {
			$startdate = strtotime('last monday');
			$nextdate = strtotime('next monday');
		}
		$date1 = date('m/d/Y',$startdate);
		$date2 = date('m/d/Y',$nextdate);
		$starttimestamp = date('Ymd',$startdate) . '000000';

		// DB query new articles


		$sqlfe = "SELECT * ".
			"FROM firstedit ".
			"WHERE fe_timestamp >= '$starttimestamp'";
		$resfe = $dbr->query($sqlfe);

		// DB query rising star articles
		$sql2 = "SELECT distinct(rc_title) ".
				"FROM recentchanges  ".
				"WHERE rc_timestamp >= '$starttimestamp' AND rc_comment like 'Marking new article as a Rising Star from From%'   ".
				"AND rc_namespace=".NS_TALK." ";
		$res2 = $dbr->query($sql2);

		$total_newarticles = $dbr->numRows($resfe);
		// Setup array for new articles
		while ( ($row = $dbr->fetchObject($resfe)) != null) {
			$t = Title::newFromID( $row->fe_page );
			if (isset($t)) {
				if ($t->getArticleID() > 0) {
					//if (!preg_match('/\d+\.\d+\.\d+\.\d+/',$row->fe_user_text))
						$leader_articles[$row->fe_user_text]++;
				}
			}
		}

		$total_risingstar = $res2->numRows();
		$leader_rs = array();
		// Setup array for rising star articles
		foreach ($res2 as $row) {
			$t = Title::newFromText($row->rc_title);
			$r = Revision::newFromTitle($t);
			if (preg_match("/#REDIRECT \[\[(.*?)\]\].*?/", $r->getText(), $matches)) {
				$t = Title::newFromText($matches[1]);
			}
			$a = new Article($t);
			$author = $a->getContributors()->current();
			if($author) {
				$username = $author->getName();
				$leader_rs[$username]++;
			}
		}


		/******
 		 * New Articles Table
 		 *
 		 * ****/
		$wgOut->addHTML("\n<div id='Authorleaderboard'>\n");
		$wgOut->addHTML(wfMsg('leaderboard_total', number_format($total_newarticles, 0, '', ','), $date1, $date2 ) . "<br/><br/><center>");

		$wgOut->addHTML("<br/><table width='500px' align='center' class='status'>" );
		// display header
		$index = 1;
	        $wgOut->addHTML("<tr>
	                       <td></td>
	                        <td>User</td>
	                        <td align='right'>" . wfMsg('leaderboard_articleswritten_header') . "</td>
	                        </tr>
	        ");

		//display difference in only new articles
		arsort($leader_articles);
		foreach($leader_articles as $key => $value) {
			$u = new User();
			$u->setName($key);
			if (($value > 0) && ($key != '')) {
				$class = "";
				if ($index % 2 == 1)
					$class = 'class="odd"';
				$wgOut->addHTML("<tr $class>
					<td>$index</td>
					<td>" . $sk->makeLinkObj($u->getUserPage(), $u->getName()) . "</td>
					<td align='right'>$value</td>
					</tr>
				");
				$leader_articles[$key] = $value * -1;
				$index++;	
			}
			if ($index > 20) break;
		}
		$wgOut->addHTML("</table><br/><br/>");

		/******
 		 * Rising Star Table
 		 *
 		 * ****/
		$wgOut->addHTML(wfMsg('leaderboard_rs_total', number_format($total_risingstar, 0, '', ','), $date1, $date2 ) . "<br/><br/><center>");

		$wgOut->addHTML("<br/><table width='500px' align='center' class='status'>" );
		// display header
		$index = 1;
	        $wgOut->addHTML("<tr>
	                       <td></td>
	                        <td>User</td>
	                        <td align='right'>" . wfMsg('leaderboard_risingstar_header') . "</td>
	                        </tr>
	        ");

		arsort($leader_rs);
		foreach ($leader_rs as $key => $value) {
			$u = new User();
			$u->setName($key);
			$class = "";
			if ($index % 2 == 1)
				$class = 'class="odd"';
			$wgOut->addHTML("<tr $class>
				<td>$index</td>
				<td>" . $sk->makeLinkObj($u->getUserPage(), $u->getName()) . "</td>
				<td  align='right'>" . $leader_rs[$key] . "</td>
				</tr>
			");
			$leader_articles[$key] = -1;
			$index++;	
			if ($index > 20) break;
		}
		$wgOut->addHTML("</table>");

		$wgOut->addHTML("</center>");
		$wgOut->addHTML("</div>\n");
	}
}
