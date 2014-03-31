<?php
if ( ! defined( 'MEDIAWIKI' ) )
	die();
    
/**#@+
 * 
 * @package MediaWiki
 * @subpackage Extensions
 *
 * @link http://www.wikihow.com/WikiHow:RevisionReel-Extension Documentation
 *
 *
 * @author Travis Derouin <travis@wikihow.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 */

$wgExtensionFunctions[] = 'wfRevisionReel';
$wgShowRatings = false; // set this to false if you want your ratings hidden





/**#@+
 */
$wgExtensionCredits['parserhook'][] = array(
	'name' => 'RevisionReel',
	'author' => 'Travis Derouin',
	'description' => 'Provides a basic article ratings system',
	'url' => 'http://www.wikihow.com/WikiHow:RevisionReel-Extension',
);

function wfRevisionReel() {
	global $wgMessageCache;
	SpecialPage::AddPage(new UnlistedSpecialPage('RevisionReel'));
	 $wgMessageCache->addMessages(
        array(
			'revisionreel' => 'Revision Reel',
			'revisionreel_loggedin' => 'You must be logged in to view this page. ',
		)
	);
}



function wfSpecialRevisionReel( $par )
{
	global $wgOut, $wgParser, $wgRequest, $wgLang, $wgUser;

    $fname = "wfRevisionReel";

	if ($wgUser->getId() == 0) {
		$wgOut->showErrorPage('revisionreel', 'revisionreel_loggedin');
		return;
	}
	

	if ($wgRequest->getVal('rev', null)) {
		$wgOut->setArticleBodyOnly(true);
		$r = Revision::newFromId($wgRequest->getVal('rev'));
		$title = Title::newFromDBKey($wgRequest->getVal('target'));
		$output = $wgParser->parse($r->getText(), $title, new ParserOptions() );
		$wgOut->addHTML($output->getText());
		return;
	}

	// landing page
	if ($wgRequest->getVal('target', null)) {
		
		$title = Title::newFromDBKey($wgRequest->getVal('target'));
		if ($title->getText() == wfMsg('mainpage')) 
			$wgOut->setPageTitle('Now Playing: '. wfMsg('mainpage'));
		else
			$wgOut->setPageTitle('Now Playing: '. wfMsg('howto', $title->getText()));

		$dbr = &wfGetDB(DB_SLAVE);
		$res = $dbr->select('revision', 
			array('rev_id', 'rev_timestamp'),
			array('rev_page=' . $title->getArticleID() ), 
			array('ORDER BY' => 'rev_id')
		);
		$revs = array();
		$rev_timestamps = array();
		while ($row = $dbr->fetchObject($res)) {
			$revs[] = $row->rev_id;
			$rev_timestamps[] = "'" . $wgLang->timeanddate( wfTimestamp( TS_MW, $row->rev_timestamp ), true ). "'";
			//$rev_timestamps[] = "'" .  wfTimestamp( TS_MW, $row->rev_timestamp ) . "'";
		}

		$dbr->freeResult($res);
		$revisions = implode(',', $revs);
		$timestamps = implode(',', $rev_timestamps);
		$size = sizeof($revs);
		$wgOut->addHTML("
	
<script type='text/javascript'>

		var index = 0;
		var stop = 0;
		var size = {$size};
		var revisions = new Array({$revisions});
		var timestamps = new Array({$timestamps});
		var requester = null;
		function showRevision() {
			var box = document.getElementById('output_html');
			if ( requester.readyState == 4) {
        		if ( requester.status == 200) {
					box.innerHTML = requester.responseText;
					revision_date = document.getElementById('revision_date');
					revision_date.innerHTML = timestamps[index] + ' Revision #' + (index + 1) + ' of ' + size;
					index++;
					if (index != size)
						setTimeout(\"showReel()\", 3000);
				}
			}

		}	
		function showReel() {
			if (stop == 1) return;
    		try {
        		requester = new XMLHttpRequest();
    		} catch (error) {
        		try {
          	 		 requester = new ActiveXObject('Microsoft.XMLHTTP');
        		} catch (error) {
					alert(error);
           	 		return false;
        		}
			}
			url = location.href + '&rev=' + revisions[index];
			requester.onreadystatechange = showRevision;
			requester.open('GET', url);
           	requester.send(null);
		}	
		setTimeout(\"showReel()\", 1000);
</script>
		<div style='border: 1px solid #ccc; padding: 5px;'>
			<input type='button' value='Stop!' onclick='stop = 1; this.value=\"Stopped.\";'>
			<input type='button' value='Go back 5' onclick='if (index > 5) index =- 5; else index = 0;'>
			<input type='button' value='Go forward 5' onclick='index += 5'>
<span id='revision_date' style='margin-left:20px; font-weight: bold;'> </span>
		</div>

			<br/>
			<div id='output_html' style='margin-top: 20px;'>
				Generating slideshow....
			</div>
		");
		return;
	}

}

?>
