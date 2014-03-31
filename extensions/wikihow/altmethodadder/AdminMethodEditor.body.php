<?
class AdminMethodEditor extends UnlistedSpecialPage {

	var $ts = null;

	function __construct() {
		parent::__construct( 'AdminMethodEditor' );
	}

	function execute($par) {
		global $wgUser, $wgOut, $wgRequest, $wgLang;

		$userGroups = $wgUser->getGroups();
		if ($wgUser->isBlocked() || !in_array('staff', $userGroups)) {
			$wgOut->setRobotpolicy('noindex,nofollow');
			$wgOut->showErrorPage( 'nosuchspecialpage', 'nospecialpagetext' );
			return;
		}
		if ($wgRequest->getVal("csv")) {
			$wgOut->disable();
			header('Content-type: application/force-download');
			header('Content-disposition: attachment; filename="views.csv"');
			$edits = $this->getEdits();
			print("user,url,date\n");
			foreach ($edits as $edit) {
				$time = $wgLang->timeanddate(wfTimestamp( TS_MW, $edit['log_timestamp']), true);
				$output = $edit['log_user_name'].",".
							$edit['title_url'].",".
							str_replace(",", "", $time);
				print "$output\n";
			}
			return;
		}
		$wgOut->setPageTitle('Method Editor Admin Page');
		EasyTemplate::set_path( dirname(__FILE__).'/' );
		$this->ts = wfTimestamp(TS_MW, time() - 24 * 3600 * $wgRequest->getVal("days", 30));
		$this->printReport();
	}

	function printReport() {
		global $wgOut, $wgRequest;

		$vars['results'] = $this->getEdits();
		$vars['days'] = $wgRequest->getVal("days", 30);
		$vars['css'] = HtmlSnips::makeUrlTags('css', array('adminmethodeditor.css'), 'extensions/wikihow/altmethodadder', true);
		$html = EasyTemplate::html('AdminMethodEditor', $vars);
		$wgOut->addHTML($html);
	}

	function getEdits() {
		global $wgRequest, $wgLang;

		$dbr = wfGetDB(DB_SLAVE);
		$res = $dbr->select('logging',
			array("*"),
			array("log_timestamp >= '{$this->ts}'", "log_type = 'methedit'", "log_action='Added'"),
			__METHOD__,
			array('ORDER BY' => 'log_timestamp DESC'));
		$edits = array();
		while ($row = $dbr->fetchObject($res)) {
			$edit = get_object_vars($row);
			$edit['log_user_name'] = User::whoIs($row->log_user);
			$title = Title::newFromText($row->log_title);
			$edit['title_url'] = $title->getFullURL();
			$edit['date'] = $wgLang->timeanddate(wfTimestamp( TS_MW, $row->log_timestamp), true);
			$edits[] = $edit;
		}
		return $edits;
	}

}
