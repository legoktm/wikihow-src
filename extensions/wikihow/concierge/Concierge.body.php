<?
class Concierge extends UnlistedSpecialPage {
	function __construct() {
		parent::__construct('Concierge');
	}

	function execute($par) {
		$controller = new WAPUIConciergeUser(new WAPConciergeConfig());
		$controller->execute($par);
	}
}
