<?
class WAPUIBabelfishUser extends WAPUIUserController {
	public function getDefaultVars() {
		$vars = parent::getDefaultVars();
		$vars['css'] .= HtmlSnips::makeUrlTags('css', array('babelfish.css'), '/extensions/wikihow/babelfish', false); 
		return $vars;
	}

	protected function handleOtherActions() {
		global $wgRequest, $wgOut;

		$action = $wgRequest->getVal('a');
		switch($action) {
			default:
				$wgOut->addHtml("Invalid action");
		}
	}

	function execute($par) {	
		parent::execute($par);
	}
}
