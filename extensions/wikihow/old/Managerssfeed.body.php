<?php

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
class Managerssfeed extends SpecialPage {
	function __construct() {
        parent::__construct( 'Managerssfeed');
    }

	function getButtons($index, $approved) {
		$s = "<input type='image' src='" . wfGetPad('/extensions/wikihow/arrow-up.png') . "' height='16px' onclick='moveUp($index);'> 
			<input type='image' src='" . wfGetPad('/extensions/wikihow/arrow-down.png') . "' height='16px' onclick='moveDown($index);'>
			<input type='image' src='" . wfGetPad('/extensions/wikihow/edit-icon.png') . "' height='16px' onclick='renameTitle($index);'>
			<input type='image'id='minus_{$index}' onclick='removefeeditem({$index});' src='" . wfGetPad('/extensions/wikihow/minus.png') . "' height='16px'/>
			<img id='check_{$index}' src='" . wfGetPad("/extensions/wikihow/CheckMark" . (!$approved ? "Grey" : "") . ".png") . "' height='16px' onclick='approve($index);'/>
	";
		if ($approved) 
				$s .= "<div class='approved' id='approved_{$index}'>1</div>";	
		else
				$s .= "<div class='approved' id='approved_{$index}'>0</div>";	
		return $s;
	}

	function getRow($t, $index, $approved) {
		return "<tr id='row_{$index}'><td id='title_{$index}'><a href='{$t->getFullURL()}'>{$t->getText()}</a></td>
                        <td class='options'>{$this->getButtons($index, $approved)}</td></tr>";
	}
	function execute($par) {
		global $wgRequest, $wgOut, $wgUser;

		if ( !in_array( 'newarticlepatrol', $wgUser->getRights() ) ) {
         	$wgOut->showErrorPage( 'nosuchspecialpage', 'nospecialpagetext' );
         	return;
      	}

		$wgOut->addHTML("  <script type=\"text/javascript\" src=\"" . wfGetPad('/extensions/min/f/skins/common/clientscript.js,/skins/common/ac.js,/extensions/wikihow/managerssfeed.js&rev=') . WH_SITEREV . "\"></script> ");
		$wgOut->addHTML('<link rel="stylesheet" type="text/css" href="' . wfGetPad('/extensions/min/f/extensions/wikihow/managerssfeed.css') . '" />');
		$wgOut->addScript('<script class="jsbin" src="http://ajax.googleapis.com/ajax/libs/jquery/1/jquery.min.js"></script>');

		$month = $wgRequest->getVal('month', gmdate("n"));
		// build the month tab
		$wgOut->addHTML("<center>");
		for ($i = 1; $i <= 12; $i++) {
			$link = "<a href='/Special:Managerssfeed?month={$i}'>";
			if ($i < 10) 
				$now = wfTimestamp(TS_UNIX, date("Y") . "0". $i . "01000000");
			else
				$now = wfTimestamp(TS_UNIX, date("Y") . $i . "01000000");
			if ($i == $month)
				$wgOut->addHTML("<div class='month selected'>" .  gmdate("M", $now) . "</div>");
			else
				$wgOut->addHTML("<div class='month'>{$link}" .  gmdate("M", $now) . "</a></div>");
		}

		$now = wfTimestamp(TS_UNIX, gmdate("Y") . ($month < 10 ? "0" . $month : $month) . "01000000");

		// get the feeds
		$dbr = wfGetDB(DB_SLAVE);
		$ts1 = wfTimestamp(TS_MW, $now);
		$tsunix = wfTimestamp(TS_UNIX, $now) + 31*3600*24; // jump ahead 31 days
		$ts2 = wfTimestamp(TS_MW, date("Y", $tsunix) . date("m", $tsunix) . "01000000");
		$res = $dbr->select(array('rssfeed','page'),
				array('page_namespace', 'page_title', 'rss_timestamp', 'rss_approved'),
				array("rss_timestamp >= '{$ts1}'",  "rss_timestamp < '{$ts2}'", 'page_id=rss_page'));
		$map = array();
		while ($row = $dbr->fetchObject($res)) {
			$day = substr($row->rss_timestamp, 0, 8);
			$t = Title::makeTitle($row->page_namespace, $row->page_title);
			if (!isset($map[$day]))
				$map[$day] = array();
			$map[$day][] = array($t, $row->rss_approved);
		}
		$wgOut->addHTML("</center><br clear='all'><table class='days'>");
		$index = 0;
		while (gmdate("n", $now) == $month) {
			$t = Randomizer::getRandomTitle();
			$d = gmdate("j", $now);
			$wgOut->addHTML("<tr class='day'>
				<td class='date'><span class='dow'>" . gmdate("D",$now) . "</span><br/>" . gmdate("d", $now) . "
				<table class='addicon'><tr><td>	<input type='image' src='" . wfGetPad('/extensions/wikihow/plus.png') . "' height='24px' onclick='add($d)'/></tr></td></table>
				</td>
				<td class='feeditem'>
				<table width='100%' class='feeditem' id='feed_{$d}'>
			");

			if (isset($map[gmdate("Ymd", $now)])) {
				foreach ($map[gmdate("Ymd", $now)] as $tmap) {
					$t 			= $tmap[0];
					$approved 	= $tmap[1] == 1;
					$wgOut->addHTML($this->getRow($tmap[0], $t->getArticleID(), $approved));
				}		
			}

			$wgOut->addHTML("</table>
					</td>");
			
			$wgOut->addHTML("</tr>");
			$now += 3600* 24;
			$index++;
		}
		$wgOut->addHTML("</table>");
	}
}
