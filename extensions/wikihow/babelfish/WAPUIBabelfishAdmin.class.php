<?
class WAPUIBabelfishAdmin extends WAPUIAdminController {
	function execute($par) {
		$this->handleRequest($par);
	}

	public function getDefaultVars() {
		$vars = parent::getDefaultVars();
		$vars['css'] .= HtmlSnips::makeUrlTags('css', array('babelfish.css'), '/extensions/wikihow/babelfish', false); 
		$vars['js'] .= HtmlSnips::makeUrlTags('js', array('babelfish.js'), '/extensions/wikihow/babelfish', false); 
		return $vars;
	}

	function addUser() {
		global $wgRequest, $IP;
		if ($this->wapDB->addUser($wgRequest->getVal('url'), $wgRequest->getVal('powerUser'))) {
			$message = 'User added';
		} else {
			$message = 'User not found';
		}
		$this->outputSuccessHtml($message);
	}

	function outputCompletedReportHtml() {
		global $wgOut;
		$wgOut->setPageTitle('Completed Report Generator');
		global $wgOut;
		$vars = $this->getDefaultVars($this->dbType);
		if ($this->cu->isAdmin()) {
			$vars['langs'][] = 'all';
		}
		$vars['buttonId'] = 'rpt_completed_articles_admin';
		$tmpl = new WAPTemplate($this->dbType);
		$wgOut->addHtml($tmpl->getHtml('completed_report.tmpl.php', $vars));
	}


}
