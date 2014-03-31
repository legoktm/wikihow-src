<?

class APIAppAdmin extends UnlistedSpecialPage {
	var $ts = null;
	const DIFFICULTY_EASY = 1;
	const DIFFICULTY_MEDIUM = 2;
	const DIFFICULTY_HARD = 3;

	function __construct() {
		parent::__construct( 'APIAppAdmin' );
	}

	function execute($par) {
		global $wgUser, $wgOut, $wgRequest;

		$userGroups = $wgUser->getGroups();
		if ($wgUser->isBlocked() || !in_array('staff', $userGroups)) {
			$wgOut->setRobotpolicy('noindex,nofollow');
			$wgOut->showErrorPage( 'nosuchspecialpage', 'nospecialpagetext' );
			return;
		}
		if ($wgRequest->wasPosted()) {
			$wgOut->disable();
			$result = array();
			$result['debug'][] = "posted to apiappadmin";
			if ($wgRequest->getVal("action") == "default") {
				$this->testQuery(&$result);
			} else if ($wgRequest->getVal("action") == "getpage") {
				//nothing yet
			}
			echo json_encode($result);
			return;
		}

		$wgOut->setPageTitle('APIAppAdmin');
		EasyTemplate::set_path( dirname(__FILE__).'/' );

		$vars['css'] = HtmlSnips::makeUrlTags('css', array('apiappadmin.css'), 'extensions/wikihow/apiappsupport', true);
		$wgOut->addScript(HtmlSnips::makeUrlTags('js', array('apiappadmin.js'), 'extensions/wikihow/apiappsupport', true));
		$html = EasyTemplate::html('APIAppAdmin', $vars);
		$wgOut->addHTML($html);
	}

	private function testQuery($result) {
		$result['debug'][] =  "hi";
	}

}
