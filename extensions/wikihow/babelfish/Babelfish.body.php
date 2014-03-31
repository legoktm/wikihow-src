<?
class Babelfish extends UnlistedSpecialPage {
	function __construct() {
		parent::__construct('Babelfish');
	}

	function execute($par) {
		$controller = new WAPUIBabelfishUser(new WAPBabelfishConfig());
		$controller->execute($par);
	}
}
