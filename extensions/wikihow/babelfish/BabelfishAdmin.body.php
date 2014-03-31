<?
class BabelfishAdmin extends UnlistedSpecialPage {
	function __construct() {
		parent::__construct('BabelfishAdmin');
	}

	function execute($par) {
		$controller = new WAPUIBabelfishAdmin(new WAPBabelfishConfig());
		$controller->execute($par);
	}

}
