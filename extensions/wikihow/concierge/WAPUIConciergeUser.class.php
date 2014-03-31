<?
class WAPUIConciergeUser extends WAPUIUserController {
	protected function handleOtherActions() {
		global $wgRequest, $wgOut;

		$action = $wgRequest->getVal('a');
		switch($action) {
			default:
				$wgOut->addHtml("Invalid action");
		}
	}
}
