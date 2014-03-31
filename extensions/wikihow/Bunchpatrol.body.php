<?

class Bunchpatrol extends SpecialPage {

	function __construct() {
		parent::__construct('Bunchpatrol');
	}

	public function writeBunchPatrolTableContent(&$dbr, $target, $readOnly) {

		global $wgOut, $wgUser;


		$wgOut->addHTML( "<table width='100%' align='center' class='bunchtable'><tr>" );
		if (!$readOnly) {
			$wgOut->addHTML( "<td><b>Patrol?</b></td>" );
		}

		$wgOut->addHTML( "<td align='center'><b>Diff</b></td></tr>" );

		$opts = array ('rc_user_text' =>$target, 'rc_patrolled=0');
		$opts[] = ' (rc_namespace = 2 OR rc_namespace = 3) ';

		$res = $dbr->select ( 'recentchanges',
				array ('rc_id', 'rc_title', 'rc_namespace', 'rc_this_oldid', 'rc_cur_id', 'rc_last_oldid'),
				$opts,
			"wfSpecialBunchpatrol",
				array ('LIMIT' => 15)
			);

		$count = 0;
		while ( ($row = $dbr->fetchObject($res)) != null) {
			$t = Title::makeTitle($row->rc_namespace, $row->rc_title);
			$diff = $row->rc_this_oldid;
			$rcid = $row->rc_id;
			$oldid = $row->rc_last_oldid;
			$de = new DifferenceEngine( $t, $oldid, $diff, $rcid );
			$wgOut->addHTML( "<tr>" );
			if (!$readOnly) {	
				$wgOut->addHTML( "<td valign='middle' style='padding-right:24px; border-right: 1px solid #eee;'><input type='checkbox' name='rc_{$rcid}'></td>" );
			}
			$wgOut->addHTML( "<td style='border-top: 1px solid #eee;'>" );
			$wgOut->addHTML( $wgUser->getSkin()->makeLinkObj($t) );
			$de->showDiffPage(true);
			$wgOut->addHTML("</td></tr>");
			$count++;
		}
		$dbr->freeResult($res);

		$wgOut->addHTML( "</table><br/><br/>" );
		return $count;
	}

	function execute($par) {
		global $wgRequest, $wgOut, $wgUser;
		$target = isset($par) ? $par : $wgRequest->getVal('target');

		if ($target == $wgUser->getName() ) {
			$wgOut->addHTML(wfMsg('bunchpatrol_noselfpatrol'));
			return;
		}

		$wgOut->setHTMLTitle('Bunch Patrol - wikiHow');
		$sk = $wgUser->getSkin();
		$dbr =& wfGetDB(DB_SLAVE);
		$me = Title::makeTitle(NS_SPECIAL, "Bunchpatrol");

		$unpatrolled = $dbr->selectField('recentchanges', array('count(*)'), array('rc_patrolled=0'));
		if ( !strlen( $target ) ) {
			$restrict = " AND (rc_namespace = 2 OR rc_namespace = 3) ";
			$res = $dbr->query("SELECT rc_user, rc_user_text, COUNT(*) AS C
								FROM recentchanges
								WHERE rc_patrolled=0
									{$restrict}
								GROUP BY rc_user_text HAVING C > 2
								ORDER BY C DESC");
			$wgOut->addHTML("<table width='85%' align='center'>");
			while ( ($row = $dbr->fetchObject($res)) != null) {
				$u = User::newFromName($row->rc_user_text);
				if ($u) {
					$bpLink = SpecialPage::getTitleFor( 'Bunchpatrol', $u->getName() );
					$wgOut->addHTML("<tr><td>" . $sk->makeLinkObj($bpLink,$u->getName()) . "</td><td>{$row->C}</td>");
				}
			}
			$dbr->freeResult($res);
			$wgOut->addHTML("</table>");
			return;
		}

		if ($wgRequest->wasPosted() && $wgUser->isAllowed('patrol') ) {
			$values = $wgRequest->getValues();
			$vals = array();
			foreach ($values as $key=>$value) {
				if (strpos($key, "rc_") === 0 && $value == 'on') {
					$vals[] = str_replace("rc_", "", $key);
				}
			}
			foreach ($vals as $val) {
				RecentChange::markPatrolled( $val );
				PatrolLog::record( $val, false );
			}
			$restrict = " AND (rc_namespace = 2 OR rc_namespace = 3) ";
			$res = $dbr->query("SELECT rc_user, rc_user_text, COUNT(*) AS C
								FROM recentchanges
								WHERE rc_patrolled=0
									{$restrict}
								GROUP BY rc_user_text HAVING C > 2
								ORDER BY C DESC");
			$wgOut->addHTML("<table width='85%' align='center'>");
			while ( ($row = $dbr->fetchObject($res)) != null) {
				$u = User::newFromName($row->rc_user_text);
				if ($u)
					$wgOut->addHTML("<tr><td>" . $sk->makeLinkObj($me,$u->getName(), "target=" . $u->getName()) . "</td><td>{$row->C}</td>");
			}
			$wgOut->addHTML("</table>");
			return;
		}

		// don't show main namespace edits if there are < 500 total unpatrolled edits
		$target = str_replace('-', ' ', $target);

		$wgOut->addHTML("
			<script type='text/javascript'>
			function checkall(selected) {
				for (i = 0; i < document.checkform.elements.length; i++) {
					var e = document.checkform.elements[i];
					if (e.type=='checkbox') {
						e.checked = selected;
					}
				}
			}
			</script>
			<form method='POST' name='checkform' action='{$me->getFullURL()}'>
			<input type='hidden' name='target' value='{$target}'>
			");

		if ($wgUser->isSysop()) {
			$wgOut->addHTML("Select: <input type='button' onclick='checkall(true);' value='All'/>
					<input type='button' onclick='checkall(false);' value='None'/>
				");
		}
		
		$count = $this->writeBunchPatrolTableContent($dbr, $target, false);

		if ($count > 0) {
			$wgOut->addHTML("<input type='submit' value='" . wfMsg('submit') . "'>");
		}
		$wgOut->addHTML("</form>");
		$wgOut->setPageTitle(wfMsg('bunchpatrol'));
		if ($count == 0) {
			$wgOut->addWikiText(wfMsg('bunchpatrol_nounpatrollededits', $target));
		}
	}

}

