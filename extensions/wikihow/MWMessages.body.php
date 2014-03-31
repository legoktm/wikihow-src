<?

class MWMessages extends UnlistedSpecialPage {

	function __construct() {
		parent::__construct( 'MWMessages' );
	}

	function getExtensionInfo($key) {
		global $wgExtensionCredits;

		foreach ($wgExtensionCredits as $kind=>$args) {
			foreach ($args as  $r) {
				if (stripos($r['name'], $key) !== false) return " - {$r['description']}";
			}
		}
		return '';
	}
	function execute($par) {
		global $wgOut, $wgRequest, $wgUser;
		global $wgExtensionMessagesFiles, $wgMessageCache;


		if ( !in_array( 'sysop', $wgUser->getGroups() ) ) {
			$wgOut->setArticleRelated( false );
			$wgOut->setRobotpolicy( 'noindex,nofollow' );
			$wgOut->showErrorPage( 'nosuchspecialpage', 'nospecialpagetext' );
			return;
		}


		$target = isset( $par ) ? $par : $wgRequest->getVal( 'target' );

		$filename = $wgRequest->getVal('mwextension');
		if ($target || $filename) {

			if ($target && !$filename) {
				foreach ($wgExtensionMessagesFiles as $m) {
					if (stripos($m, $target) !== false) {
						$filename = $m;
						break;
					}
				}
			}
		}

		$wgOut->addHTML('<link rel="stylesheet" href="/extensions/min/f/extensions/wikihow/mwmessages.css,/skins/WikiHow/popupEdit.css" type="text/css" />');
		$wgOut->addScript('<script type="text/javascript" src="/extensions/min/f/skins/WikiHow/popupEdit.js"></script>');
		$wgOut->addHTML("<div class='mwmessages'/><form action='/Special:MWMessages' method='POST' name='mwmessagesform'>");
		$wgOut->addHTML("Browse by Extension<br/><select name='mwextension' onchange='document.mwmessagesform.submit();'>");


		foreach ($wgExtensionMessagesFiles as $m) {
			$key = preg_replace("@.*/@", "", $m);
			$key = preg_replace("@\..*@", "", $key);
			$addinfo = MWMessages::getExtensionInfo($key);
			if ($filename == $m)
				$wgOut->addHTML("<OPTION VALUE='$m' SELECTED>{$key}{$addinfo}</OPTION>\n");
			else
				$wgOut->addHTML("<OPTION VALUE='$m'>{$key}{$addinfo}</OPTION>\n");
		}
		$wgOut->addHTML("</select><input type='submit' value='Go'>");
		$wgOut->addHTML("</form>");

		if ($wgRequest->wasPosted()) {

			$search = $wgRequest->getVal('mwmessagessearch');
			if ($search) {
				$wgMessageCache->mAllMessagesLoaded = false;
				$wgMessageCache->loadAllMessages();
				$lang = 'en';
				$sortedArray = array_merge( Language::getMessagesFor( 'en' ), $wgMessageCache->getExtensionMessagesFor( 'en' ) );
				$wgOut->addHTML("<table class='mwmessages'>
						<tr><td><b>Lang</b></td><td><b>Key</b></td><td><b>Value</b></td></tr>");
				foreach ($sortedArray as $key=>$val) {
					$val = wfMsg($key);
					if (stripos($val, $search) !== false) {
						$t = Title::makeTitle(NS_MEDIAWIKI, $key);
						$qe_url = '<a href="#" onclick="initPopupEdit(\'' . Title::makeTitle(NS_SPECIAL, 'QuickEdit')->getFullURL() . '?type=editform&target=' . $t->getPrefixedURL() . '\') ;">' . $key .'</a>';
						$wgOut->addHTML("<tr><td class='mw_lang'>{$lang}</td><td class='mw_key'>{$qe_url}</td><td class='mw_val'>" . htmlspecialchars($val) ."</td></tr>");
					}
				}

				$dbr = wfGetDB(DB_SLAVE);
				$res = $dbr->select('page', array('page_title', 'page_namespace'), array('page_namespace'=>NS_MEDIAWIKI));
				while ($row = $dbr->fetchObject($res)) {
					$t = Title::makeTitle($row->page_namespace, $row->page_title);
					if (!$t) continue;
					$r = Revision::newFromTitle($t);
					if (!$r) continue;
					$val = $r->getText();
					if (stripos($val, $search) !== false) {
						$qe_url = '<a href="#" onclick="initPopupEdit(\'' . Title::makeTitle(NS_SPECIAL, 'QuickEdit')->getFullURL() . '?type=editform&target=' . $t->getPrefixedURL() . '\') ;">' . $row->page_title .'</a>';
						$wgOut->addHTML("<tr><td class='mw_lang'>{$lang}</td><td class='mw_key'>{$qe_url}</td><td class='mw_val'>" . htmlspecialchars($val) ."</td></tr>");
					}
				}

				$wgOut->addHTML("</table>");
			}

		}

		if ($filename) {
			// reset messages
			$messages = array();
			require_once($filename);
			$wgOut->addHTML("<table class='mwmessages'>
					<tr><td><b>Lang</b></td><td><b>Key</b></td><td><b>Value</b></td></tr>");
			$index = 0;
			foreach ($messages as $lang=>$arrs) {
				foreach ($arrs as $key=>$val) {
					$newval = wfMsg($key);
					if ($newval != "&lt;{$key}&gt;")
						$val = $newval;
					$t = Title::makeTitle(NS_MEDIAWIKI, $key);
					$qe_url = '<a href="#" onclick="initPopupEdit(\'' . Title::makeTitle(NS_SPECIAL, 'QuickEdit')->getFullURL() . '?type=editform&target=' . $t->getPrefixedURL() . '\'); setText(' . $index . ');">' . $key .'</a>';
					$wgOut->addHTML("<tr><td class='mw_lang'>{$lang}</td><td class='mw_key'>{$qe_url}</td><td class='mw_val' id='mw_{$index}'>" . htmlspecialchars($val) ."</td></tr>");
					$index++;
				}
			}
			$wgOut->addHTML("</table>");
		}


		$wgOut->addHTML("</div><br/><br/><div class='mwmessages'>Or search for a message that's not an extension message:<br/><br/>
				<form action='/Special:MWMessages' method='POST' name='mwmessagesform_search'>
				<center>
				<input type='text' name='mwmessagessearch' value=\"" .htmlspecialchars($wgRequest->getVal('mwmessagessearch')) . "\" style='width:300px;font-size:110%;'>
				<input type='submit' value='Search for messages'/>
				</center>
				</form>
			</div>
			");

		$wgOut->addScript('
<script type="text/javascript">
		var gAutoSummaryText = "Updating mediawiki message with MWMessages extensions";
		var gQuickEditComplete = "' . wfMsg('Quickedit-complete')  . '";
		var gId = null;
		var gHTML = null;
		var mw_request = null;

		function setIt() {
			var x = document.getElementById("wpTextbox1");
			if (x && x.value == ""){
				x.value = gHTML;
			} else {
				window.setTimeout("setIt();", 100);
			}
		}

		function mw_handleDecode() {
			if ( mw_request.readyState == 4 && mw_request.status == 200) {
				gHTML = mw_request.responseText;
				setIt();
			}
		}
		function setText(id) {
			gId = id;
			mw_request = sajax_init_object();
			var parameters = "html=" + encodeURIComponent(document.getElementById("mw_" + gId).innerHTML);
			var url = "http://" + window.location.hostname + "/Special:MWMessagesDecode";
			mw_request.open("POST", url);
			mw_request.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
			mw_request.send(parameters);
			mw_request.onreadystatechange = mw_handleDecode;
		}
</script>
<div id="editModalPage">
		<div class="editModalBackground" id="editModalBackground"></div>
		<div class="editModalContainer" id="editModalContainer" style="width: 700px; height: 600px">
				<div class="editModalTitle"><span style="float: left;padding-left: 10px;"><strong></strong></span><a onclick="popupEditClose();">X</a></div>
				<div class="editModalBody">
						<div id="article_contents" style="width:680px;height:560px;overflow:auto">
						</div>
				</div>
		</div>
</div>');
	}
}
class MWMessagesDecode extends UnlistedSpecialPage {

	function __construct() {
		parent::__construct( 'MWMessagesDecode' );
	}

	function execute($par) {
		global $wgRequest, $wgOut;
		$wgOut->disable();
		echo htmlspecialchars_decode($wgRequest->getVal('html'));
	}
}
