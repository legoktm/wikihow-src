<?
/*
* 
*/
class FBLink extends UnlistedSpecialPage {
	var $facebook = null;

	function __construct() {
		parent::__construct('FBLink');
	}

	function execute($par) {
		global $wgOut, $wgRequest, $wgUser, $wgFBAppId, $wgFBAppSecret, $wgLanguageCode, $IP;

		wfProfileIn(__METHOD__);

		$wgOut->setRobotpolicy( 'noindex,nofollow' );

		if ($wgUser->getId() == 0) {
			$wgOut->showErrorPage( 'nosuchspecialpage', 'nospecialpagetext' );
			return;
		} 

		require_once("$IP/extensions/wikihow/common/facebook-platform/facebook-php-sdk-771862b/src/facebook.php");
		$this->facebook = new Facebook(array('appId' => $wgFBAppId, 'secret' => $wgFBAppSecret));
		$accessToken = $wgRequest->getVal('token', null);
		$this->facebook->setAccessToken($accessToken);
		
		$action = $wgRequest->getVal('a', '');
		switch ($action) {
			case 'confirm':
				$this->showConfirmation();
				break;
			case 'link':
				$this->linkAccounts();
				break;
		}

		wfProfileOut(__METHOD__);
		return;
	}

	function linkAccounts() {
		global $wgUser, $wgDBname;

		$facebook = $this->facebook;
		$result = $facebook->api('/me');

		$dbw = wfGetDB(DB_MASTER);	
		$dbw->selectDB(WH_DATABASE_NAME_SHARED);
		$fbId = $result['id'];
		$whId = $wgUser->getId();
		$sql = "INSERT INTO facebook_connect (fb_user, wh_user) VALUES ($fbId, $whId) ON DUPLICATE KEY UPDATE wh_user = $whId";
		$dbw->query($sql);
		$dbw->selectDB($wgDBname);
	}

	function showConfirmation() {
		global $wgOut;
		EasyTemplate::set_path(dirname(__FILE__).'/');
		$vars = array();
		$this->setVars($vars);
		$html = EasyTemplate::html('FBLink_confirm', $vars);
		$wgOut->setArticleBodyOnly(true);	
		$wgOut->addHtml($html);
	}

	function setVars(&$vars) {
		global $wgUser;

		$vars['js'] = HtmlSnips::makeUrlTags('js', array('fblink.js'), '/extensions/wikihow/fblogin', true);
		$vars['css'] = HtmlSnips::makeUrlTags('css', array('fblink.css'), '/extensions/wikihow/fblogin', true);
		
		$facebook = $this->facebook;
		$result = $facebook->api('/me');
		$vars['fbName'] = $this->truncate($result['name']);
		$vars['fbEmployer'] = $this->truncate($result['work'][0]['employer']['name']);
		$vars['fbSchool'] = $this->truncate($result['education'][0]['school']['name']);
		$vars['fbEmail'] = $this->truncate($result['email']);
		$vars['fbLocation'] = $this->truncate($result['location']['name']);

		$vars['fbPicUrl'] = FBLogin::getPicUrl($result['id'], 'normal');
		$vars['newAcct'] = $wgUser->getName();
		$vars['whPicUrl'] = wfGetPad(Avatar::getAvatarURL($wgUser->getName()));
		$whId = $this->isAlreadyLinked($result['id']);
		$vars['showWarning'] = $whId ? true : false;
		$vars['oldAcct'] = $this->getUsername($whId);
	}


	function truncate($string) {
		$string = trim($string);
		if (strlen($string) > 25) {
			$string = substr($string, 0, 25) . "...";	
		}
		return $string;
	}
	function getInfo(&$result, &$path) {
		$info = "";
		foreach ($path as $node) {
		}
		return $info;
	}


	/*
	* Returns the wh user id if the account is linked, 0 otherwise
	*/
	function isAlreadyLinked($fbId) {
		global $wgDBname;
		$isConnected = 0;
		$dbr = wfGetDB(DB_SLAVE);
		$dbr->selectDB(WH_DATABASE_NAME_SHARED);
		$exists = $dbr->selectField('facebook_connect', 'wh_user', array('fb_user' => $fbId));
		if (!empty($exists)) {
			$isConnected = $exists;
		}
		$dbr->selectDB($wgDBname);
		return $isConnected;
	}

	function getUsername($uid) {
		$u = User::newFromId($uid);
		return $u->getName();
	}

	function showCTAHtml($template = 'FBLink_enable') {
		$html = "";
		if (self::isCompatBrowser()) {
			EasyTemplate::set_path(dirname(__FILE__).'/');
			$vars = array();
			$vars['imgUrl'] = wfGetPad('/skins/WikiHow/images/facebook_48.png');
			$html = EasyTemplate::html($template, $vars);
		}
		return $html;
	}

	// Disabled for IE6 due to css formatting issues
	public static function isCompatBrowser() {
		return !preg_match('@MSIE 6@',$_SERVER['HTTP_USER_AGENT']);
	}
}
