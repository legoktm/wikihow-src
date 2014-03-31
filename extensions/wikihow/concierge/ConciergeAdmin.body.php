<?
class ConciergeAdmin extends UnlistedSpecialPage {
	function __construct() {
		parent::__construct('ConciergeAdmin');
	}

	function execute($par) {
		$controller = new WAPUIConciergeAdmin(new WAPConciergeConfig());
		$controller->execute($par);
	}
}
