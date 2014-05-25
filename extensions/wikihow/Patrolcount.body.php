<?

class Patrolcount extends SpecialPage {

    function __construct() {
        parent::__construct( 'Patrolcount' );
    }

	function getPatrolcountWindow() {
		global $wgUser;
		$hrDiff = $minDiff = 0;
		$tz = $wgUser->getOption( 'timecorrection' );
	     if ( strpos( $tz, ':' ) !== false ) {
	            $tzArray = explode( ':', $tz );
	            $hrDiff = intval($tzArray[0]);
	            $minDiff = intval($hrDiff < 0 ? -$tzArray[1] : $tzArray[1]);
	     } else if ($tz !== '') {
	            $hrDiff = intval( $tz );
	     }
		$now =  wfTimestamp(TS_UNIX);	
		$now -= $hrDiff * 3600 + $minDiff * 60;
	
		# start of today GMT
		$local_time = wfTimestamp(TS_UNIX) + ($hrDiff * 3600 + $minDiff * 60);
		$local_midnight = wfTimestamp(TS_UNIX, substr(wfTimestamp(TS_MW, $local_time), 0, 8) . "000000");
	
		// convert a local midnight to a GMT time
		$converted = $local_midnight - ($hrDiff * 3600 + $minDiff * 60);
		$result = array();
		$result[] = wfTimestamp(TS_MW, $converted);
		$result[] = wfTimestamp(TS_MW, $converted + 24*3600);
		return $result;
		
	}
	
    function execute($par) {
		global $wgRequest, $wgOut, $wgUser, $wgLang;

		$target = isset( $par ) ? $par : $wgRequest->getVal( 'target' );
		$sk = $wgUser->getSkin();
		$dbr =& wfGetDB(DB_SLAVE);
		$wgOut->setHTMLTitle('Edits Patrol Count - wikiHow');
	
		$wgOut->addHTML('  <style type="text/css" media="all">/*<![CDATA[*/ @import "/extensions/wikihow/Patrolcount.css"; /*]]>*/</style>');	
		
		$me = Title::makeTitle(NS_SPECIAL, "Patrolcount");
	
		// allow the user to grab the local patrol count relative to their own timezone	
		if ($wgRequest->getVal('patrolcountview', null)) {
			$wgUser->setOption('patrolcountlocal', $wgRequest->getVal('patrolcountview'));
			$wgUser->saveSettings();
		}
		if ($wgUser->getOption('patrolcountlocal', "GMT") != "GMT") {
			$links = "[" . $sk->makeLinkObj($me, wfMsg('patrolcount_viewGMT'), "patrolcountview=GMT") . "] [" . wfMsg('patrolcount_viewlocal') . "]";
			$result = Patrolcount::getPatrolcountWindow();
			$date1 = $result[0];
			$date2 = $result[1];
	//echo "$date1 , $date2";
		} else {
			$links = "[" . wfMsg('patrolcount_viewGMT') . "] [" . $sk->makeLinkObj($me, wfMsg('patrolcount_viewlocal'), "patrolcountview=local") . "]";
			$now = wfTimestamp(TS_UNIX);
			$date1 = substr(wfTimestamp(TS_MW), 0, 8) . "000000";
			$date2 = substr(wfTimestamp(TS_MW, $now + 24*3600), 0, 8) . "000000";
		}
	
		//echo "<h3>date1 $date1 to $date2</h3>";
		//grab the total
		$total = $dbr->selectField('logging', 'count(*)',  array ('log_type'=>'patrol', "log_timestamp>'$date1'", "log_timestamp<'$date2'"));
	
		$wgOut->addHTML("<div id='Patrolcount'>");
		$wgOut->addHTML(wfMsg('patrolcount_summary') ."<br/><br/>" . wfMsg('patrolcount_total', number_format($total, 0, '', ',') ) . "<br/><br/><center>");
		$wgOut->addHTML($links);
		$wgOut->addHTML("<br/><br/><table width='500px' align='center' class='status'>" );
	
		$sql = "SELECT log_user, count(*) as C FROM logging FORCE INDEX (times) WHERE log_type='patrol' AND log_timestamp > '$date1' AND log_timestamp < '$date2' GROUP BY log_user ORDER BY C DESC LIMIT 20";
		$res = $dbr->query($sql, __METHOD__);
		$index = 1;
	        $wgOut->addHTML("<tr>
	                       <td></td>
	                        <td>User</td>
	                        <td  align='right'>" . wfMsg('patrolcount_numberofeditspatrolled') . "</td>
	                        <td align='right'>" . wfMsg('patrolcount_percentangeheader') . "</td>
	                        </tr>
	        ");
		while ( ($row = $dbr->fetchObject($res)) != null) {
			$u = User::newFromID($row->log_user);
			
			//skip auto-patrolled patrols
			if (in_array('bot', $u->getGroups()) || $u->getOption('autopatrol')) continue;
			
			$percent = $total == 0 ? "0" : number_format($row->C / $total * 100, 2);
			$count = number_format($row->C, 0, "", ',');
			$class = "";
			if ($index % 2 == 1)
				$class = 'class="odd"';
			$log = $sk->makeLinkObj(Title::makeTitle( NS_SPECIAL, 'Log'), $count, 'type=patrol&user=' .  $u->getName());
			$wgOut->addHTML("<tr $class>
				<td>$index</td>
				<td>" . $sk->makeLinkObj($u->getUserPage(), $u->getName()) . "</td>
				<td  align='right'>{$log}</td>
				<td align='right'> $percent % </td>
				</tr>
			");
			$index++;	
		}
		$wgOut->addHTML("</table></center>");
		if ($wgUser->getOption('patrolcountlocal', "GMT") != "GMT")  {
			$wgOut->addHTML("<br/><br/><i><font size='-2'>" . wfMsgWikiHtml('patrolcount_viewlocal_info') . "</font></i>");
		}
		$wgOut->addHTML("</div>");
	}
}
