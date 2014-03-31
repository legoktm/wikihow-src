<?
/*
*
*/
class FBAppContact extends UnlistedSpecialPage {

	function __construct() {
		parent::__construct('FBAppContact');
	}

	function execute($par) {
		global $wgOut, $wgRequest, $wgFBAppId, $wgFBAppSecret, $wgSharedDB, $IP;
		require_once("$IP/extensions/wikihow/common/facebook-platform/facebook-php-sdk-771862b/src/facebook.php");
		$wgOut->setArticleBodyOnly(true);

		$accessToken = $wgRequest->getVal('token', null);
		if (is_null($accessToken)) {
			return;
		}

		$this->facebook = new Facebook(array('appId' => $wgFBAppId, 'secret' => $wgFBAppSecret));
		$this->facebook->setAccessToken($accessToken);
		$result = $this->facebook->api('/me');

		$dbw = wfGetDB(DB_MASTER);
		$dbw->selectDB($wgSharedDB);
		$fields = array ('fc_user_id' => $result['id'], 'fc_first_name' => $result['first_name'], 'fc_last_name' => $result['last_name'], 'fc_email' => $result['email']);
		$dbw->insert('facebook_contacts', $fields, __METHOD__, array( 'IGNORE' ));
		return;
	}
}
