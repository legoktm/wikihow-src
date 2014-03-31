<?
class FBNuke extends UnlistedSpecialPage {

	function __construct() { 
		parent::__construct( 'FBNuke' );
	}
	
	function execute($par) {
		global $wgOut, $wgRequest, $wgUser, $wgDBname;
		if ($wgUser->getName() != 'Jordansmall') {
			$wgOut->showErrorPage( 'nosuchspecialpage', 'prefsnologintext' );
			return;
		}

		$wgOut->addHTML("<h1>Remove Facebook Account</h1><br /><br />");

		$userName = $wgRequest->getVal('uname');
		$removeWikiHowAcct = $wgRequest->getVal('whremove', '') == 'on' ? true : false;
		if ($userName) {
			$this->removeAccount($userName, $removeWikiHowAcct);
		}
		$this->showForm();
		$dbw = wfGetDB(DB_MASTER);	
		$dbw->selectDB($wgDBname);
	}

	function showForm() {
		global $wgOut;
		
		$html = "<form action='/Special:FBNuke' method='get'>";
		$html .= "<p><label for='uname'>Username: </label>";
		$html .= "<input type='text' name='uname' /></p>";
		$html .= "<p><input type='checkbox' name='whremove' /> <label for='whremove'>Remove wikiHow account in addition to FB Account</label></p>";
		$html .= "<input type='submit' name='remove' value='remove'/>";
		$html .= "</form>";
		$wgOut->addHTML($html);
	}

	function removeAccount($userName, $removeWikiHowAcct = false) {
		global $wgSharedDB, $wgOut, $wgUser;

		$dbw = wfGetDB(DB_MASTER);	
		$dbw->selectDB($wgSharedDB);

		$userName = $dbw->strEncode($userName);
		if(strtolower($wgUser->getName()) == strtolower($userName)) {
			$wgOut->addHTML("<h4>Error: Can't remove your own username</h4><br /><br />");
			return;
		}

		$userId = $dbw->selectField('user', array('user_id'), array('user_name'=>$userName));
		if (!$userId) {
			$wgOut->addHTML("<h4>$userName not found in the database</h4><br /><br />");
			return;
		}

		$sql = "DELETE FROM facebook_connect where wh_user = $userId";
		$dbw->query($sql);

		if ($removeWikiHowAcct) {
			$sql = "DELETE FROM user where user_id = $userId";
			$dbw->query($sql);
		}

		$wgOut->addHTML("<h4>$userName removed from the database</h4><br /><br />");

	}
}
