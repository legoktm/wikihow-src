<?
class EditfishAdmin extends UnlistedSpecialPage {
	function __construct() {
		parent::__construct('EditfishAdmin');
	}

	function execute($par) {
		$controller = new WAPUIEditfishAdmin(new WAPEditfishConfig());
		$controller->execute($par);
	}
}
