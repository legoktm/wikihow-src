<?

class RisingStarBoard {

	function __construct() {
	}

	function getRS() {
		$t = Title::newFromText('wikiHow:Rising-star-feed');
		if ($t->getArticleId() > 0) {
			$r = Revision::newFromTitle($t);
			$text = $r->getText();
		} else {
			return false;
		}

		$rsout = array();
		$rs = $text;
		$rs = preg_replace("/==\n/", ',', $rs);
		$rs = preg_replace("/^==/", "", $rs);
		$lines = preg_split("/\r|\n/", $rs, null, PREG_SPLIT_NO_EMPTY);
		$count = 0;
		foreach ($lines as $line) {
			if (preg_match('/^==(.*?),(.*?)$/', $line, $matches)) {

				$dt = $matches[1];
				$pattern = "/$wgServer/";
				$title = preg_replace("/http:\/\/www\.wikihow\.com\//" ,"",$matches[2]);

				$rsout[$title] = $dt;
			}
		}

		return $rsout;
	}
}

class Charityleaderboard extends SpecialPage {

	function __construct() {
		parent::__construct( 'Charityleaderboard' );
	}

	// Custom sort function
	function cmp ($a, $b) {
		$abooks = $a['count'] + $a['rs'] + $a['boost'];
		$bbooks = $b['count'] + $b['rs'] + $b['boost'];

		if ($abooks == $bbooks ) {
			if ($a['name'] == $b['name']) {
				return 0;
			} else {
				return ($a['name'] < $b['name']) ? -1 : 1;
			}
		}
		return ($abooks < $bbooks) ? 1 : -1;
	}

	function execute($par) {
		global $wgRequest, $wgOut, $wgUser, $IP;
		$target = isset( $par ) ? $par : $wgRequest->getVal( 'target' );
		$sk = $wgUser->getSkin();
		$dbr =& wfGetDB(DB_SLAVE);

		$charFile = $IP . "/extensions/wikihow/Charityleaderboard-static.php";
		$fh = fopen($charFile, 'r');
		$chartable = fread($fh, filesize($charFile));
		fclose($fh);

		$wgOut->setHTMLTitle('Charity Leaderboard - wikiHow');
		$wgOut->addHTML( $chartable );

		// the old stuff isn't needed now that we've gone static
		//genLeaderBoard();
	}

	function genLeaderBoard() {
		global $wgRequest, $wgOut, $wgUser, $wgLang, $IP;

		$wgOut->addHTML('  <style type="text/css" media="all">/*<![CDATA[*/ @import "/extensions/wikihow/Authorleaderboard.css"; /*]]>*/</style>');

		$me = Title::makeTitle(NS_SPECIAL, "Charityleaderboard");

		$startdate = strtotime('09/01/2009');
		$enddate = strtotime('09/30/2009');
		$boostenddate = strtotime('10/02/2009');
		$date1 = date('m/d/Y',$startdate);
		$date2 = date('m/d/Y',$enddate);
		$starttimestamp = date('Ymd',$startdate) . '000000';
		$endtimestamp = date('Ymd',$enddate) . '999999';
		$boostendtimestamp = date('Ymd',$boostenddate) . '999999';

		// DB query new articles
		$sqlfe = "SELECT * ".
			"FROM firstedit ".
			"WHERE fe_timestamp >= '$starttimestamp' AND fe_timestamp <= '$endtimestamp' AND fe_user != 0 AND fe_user_text != 'WRM'";
		$resfe = $dbr->query($sqlfe);

		// DB query rising star articles
		$sql2 = "SELECT rc_title,rc_user_text ".
				"FROM recentchanges  ".
				"WHERE rc_timestamp >= '$starttimestamp' AND rc_timestamp <= '$boostendtimestamp' AND rc_comment like 'Marking new article as a Rising Star from From%'   ".
				"AND rc_namespace=".NS_TALK." ";
		$res2 = $dbr->query($sql2);

		$total_newarticles = 0;
		while ( ($row = $dbr->fetchObject($resfe)) != null) {
			$t = Title::newFromID( $row->fe_page );
			if (isset($t)) {
				if ($t->getArticleID() > 0) {
					if (!preg_match('/\d+\.\d+\.\d+\.\d+/',$row->fe_user_text)) {
						$leader_articles[$row->fe_user_text]['count']++;
						$leader_articles[$row->fe_user_text]['name'] = $row->fe_user_text;
						$total_newarticles++;
					}
				}
			}
		}

		$total_risingstar = $res2->numRows();
		// Setup array for rising star articles
		foreach ($res2 as $row) {
			$t = Title::newFromText($row->rc_title);
			$a = new Article($t);
			$author = $a->getContributors(1)->current();
			$username = $author->getName();

			if (!preg_match('/\d+\.\d+\.\d+\.\d+/',$username)){
				if ($username != 'WRM')
				{
					$leader_articles[$username][rs]++;
					$leader_articles[$username]['name'] = $username;
					$leader_articles[$username]['count'] += 0;
					$total_newarticles++;
				}
			}

			$leader_articles[$row->rc_user_text][boost]++;
			$leader_articles[$row->rc_user_text]['name'] = $row->rc_user_text;
			$leader_articles[$row->rc_user_text]['count'] += 0;
			$total_newarticles++;
		}


		// New Articles Table
		$wgOut->addHTML(wfMsg('charityleaderboard_title', number_format($total_newarticles), $date1, $date2 ) . "<br/><br/><center>");

		$wgOut->addHTML("<br/><table width='500px' align='center' class='status'>" );
		// display header
		$index = 1;
		$wgOut->addHTML("<tr>
			<td align='center'>".wfMsg('charityleaderboard_header_contrib')."</td>
			<td align='center'>".wfMsg('charityleaderboard_header_articles')."</td>
			<td align='center'>".wfMsg('charityleaderboard_header_rs')."</td>
			<td align='center'>".wfMsg('charityleaderboard_header_books')."</td>
			</tr>
		");

		// display table
		$maxdisplay = 500000;
		$bookstotal = 0;

		if (count($leader_articles) > 0) {
			uasort($leader_articles, array($this,"cmp"));

			foreach($leader_articles as $key => $value) {

				$u = new User();
				$u->setName($key);
				if ($key != '') {

					$books = $value['count'] + $value['rs'] + $value['boost'];
					$rsnum = $value['rs'] + $value['boost'];
					if ($rsnum == 0) {$rsnum = '';}

					$class = "";
					if ($index % 2 == 1)
						$class = 'class="odd"';
					$wgOut->addHTML("<tr $class>
						<td>" . $sk->makeLinkObj($u->getUserPage(), $u->getName()) . "</td>
						<td align='center'>".$value['count']."</td>
						<!-- <td align='center'>".$rsnum." [".$value['rs']." + ".$value['boost']."]</td> -->
						<td align='center'>".$rsnum."</td>
						<td align='center'>$books</td>
						</tr>
					");
					//$leader_articles[$key] = $value * -1;
					$index++;
				}
				$bookstotal += $books;
				if ($index > $maxdisplay) break;
			}
		}
		$wgOut->addHTML("<tr $class>
			<td>&nbsp;</td>
			<td>&nbsp;</td>
			<td align='right'>Total:</td>
			<td align='center'>$bookstotal</td>
			</tr>
		");
		$wgOut->addHTML("</table><br/><br/>");

	}

}

