<?

class Managepagelist extends UnlistedSpecialPage {

	public function __construct() {
		parent::__construct( 'Managepagelist' );
	}

	private static function checkValidListName($list) {
		return preg_match('@^[-A-Za-z ]+$@', $list) > 0;
	}

	public static function removePostProcessing($title, &$processHTML) {
		$processHTML = false;
		return true;
	}

	public function execute($par) {
		global $wgOut, $wgRequest, $wgUser, $wgHooks;

		$wgHooks['PreWikihowProcessHTML'][] = array('Misc::removePostProcessing');

		if ( !in_array( 'staff', $wgUser->getGroups() )
			&& !in_array( 'newarticlepatrol', $wgUser->getGroups() ))
		{
		 	$wgOut->showErrorPage( 'nosuchspecialpage', 'nospecialpagetext' );
		 	return;
		}

		$list = $wgRequest->getVal('list', 'risingstar');
		if (!self::checkValidListName($list)) {
			$wgOut->addHTML('bad list');
			return;
		}

		$wgOut->addHTML("<div class='wh_block'>");
		$wgOut->addHTML('<link rel="stylesheet" href="' . wfGetPad('/extensions/min/f/extensions/wikihow/pagelist.css') . '" type="text/css" />');

		$dbr = wfGetDB(DB_SLAVE);

		// handle removals
		if ($wgRequest->getVal('a') == 'remove') {
			$articleID = $wgRequest->getInt('id');
			$t = Title::newFromID($articleID);
			$dbw = wfGetDB(DB_MASTER);
			$dbw->delete('pagelist', array('pl_page' => $articleID, 'pl_list' => $list), __METHOD__);
			$wgOut->addHTML("<p style='color:blue; font-weight: bold;'>{$t->getFullText()} has been remove from the list.</p>");

		}

		if ($wgRequest->wasPosted()) {
			$newtitle = strip_tags($wgRequest->getVal('newtitle'));
			if ($newtitle) {
				$url = $newtitle;
				$url = preg_replace("@http://@", "", $url);
				$url = preg_replace("@.*/@U", "", $url);
				$t = Title::newFromURL($url);
				if (!$t || !$t->getArticleID()) {
					$wgOut->addHTML("<p style='color:red; font-weight: bold;'>Error: Couldn't find article id for {$newtitle}</p>");
				} else {
					if ($dbr->selectField("pagelist", array("count(*)"), array('pl_page' => $t->getArticleID(), 'pl_list'=>$list), __METHOD__) > 0) {
						$wgOut->addHTML("<p style='color:red; font-weight: bold;'>Oops! This title is already in the list</p>");
					} else {	
						$dbw = wfGetDB(DB_MASTER);
						$dbw->insert('pagelist', array('pl_page' => $t->getArticleID(), 'pl_list'=>$list), __METHOD__);
						if ($list == 'risingstar') {
							// add the rising star template to the discussion page
							$talk = $t->getTalkPage();
							$a = new Article($talk);
							$text = $a->getContent();
							$min = $dbr->selectField('revision', array("min(rev_id)"), array('rev_page'=>$t->getArticleId()), __METHOD__);
							$name = $dbr->selectField('revision', array('rev_user_text'), array('rev_id'=>$min), __METHOD__);
							$text = "{{Rising-star-discussion-msg-2|[[User:{$name}|{$name}]]|[[User:{$wgUser->getName()}|{$wgUser->getName()}]]}}\n" . $text;
							$a->doEdit($text, wfMsg('nab-rs-discussion-editsummary'));

							// add the comment to the user's talk page
							Newarticleboost::notifyUserOfRisingStar($t, $name);
						}	
						$wgOut->addHTML("<p style='color:blue; font-weight: bold;'>{$t->getFullText()} has been added to the list.</p>");
					}
				}
			}
		}
		$wgOut->setPageTitle( "Manage page list - " . wfMsg('pagelist_' . $list) );
		$wgOut->addHTML("<form name='addform' method='POST' action='/Special:Managepagelist'>
				<table style='width: 100%;'><tr><td style='width: 430px;'>
					Add article to this list by URL or title: 
						<input type='text' name='newtitle' id='newtitle'></td>
					<td style='width: 32px; vertical-align: bottom;'><input type='image' class='addicon' src='" . wfGetPad('/skins/WikiHow/images/plus.png') . "' onclick='javascript:document.addform.submit()'/></td>
		<td style='text-align: right;'>View list:<br/>
					<select onchange='window.location.href=\"/Special:Managepagelist&list=\" + this.value;'>
			");

		$res = $dbr->query("select distinct(pl_list) from pagelist", __METHOD__);
		while ($row = $dbr->fetchObject($res)) {
			if ($row->pl_list == $list) {
				$wgOut->addHTML("<OPTION SELECTED style='font-weight: bold;'>" . wfMsg('pagelist_' . $row->pl_list) . "</OPTION>\n");
			} else {
				$wgOut->addHTML("<OPTION>" . wfMsg('pagelist_' . $row->pl_list) . "</OPTION>\n");
			}
		}
		$wgOut->addHTML("</select></td></tr></table>
				</form>");

		$res = $dbr->select(array('page', 'pagelist'),
			array('page_title', 'page_namespace', 'page_id'),
			array('page_id=pl_page', 'pl_list'=>$list),
			__METHOD__,
			array("ORDER BY" => "pl_page DESC"));

		$wgOut->addHTML("<br/><p>There are " . number_format($dbr->numRows($res), 0, "", ",") . " articles in this list.</p>");
		$wgOut->addHTML("<table class='pagelist'>");
		$index = 0;
		while ($row = $dbr->fetchObject($res)) {
			$t = Title::makeTitle($row->page_namespace, $row->page_title);
			if (!$t) {
				echo "Couldn't make title out of {$row->page_namespace} {$row->page_title}\n";
				continue;
			}
			if ($index % 2 == 0) 
				$wgOut->addHTML("<tr>");
			else
				$wgOut->addHTML("<tr class='shaded'>");
			$wgOut->addHTML("<td class='pagelist_title'><a href='{$t->getFullURL()}' target='new'>{$t->getFullText()}</td>
				<td><a href='/Special:Managepagelist?a=remove&list={$list}&id={$row->page_id}' onclick='return confirm(\"Do you really want to remove this article?\")'><img src='" . wfGetPad('/extensions/wikihow/rcwidget/rcwDelete.png') . "' style='height: 24px; width: 24px;'></a></td>");
			$wgOut->addHTML("</tr>");
			$index++;
		}	
		$wgOut->addHTML("</table>");
		$wgOut->addHTML("</div><!--end wh_block-->");
	}
}
