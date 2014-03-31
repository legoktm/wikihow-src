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

}
