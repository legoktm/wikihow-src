<?
class Editfish extends UnlistedSpecialPage {
	function __construct() {
		parent::__construct('Editfish');
	}

	function execute($par) {
		$controller = new WAPUIEditfishUser(new WAPEditfishConfig());
		$controller->execute($par);
	}
}
