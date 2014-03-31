<?

class FBLogin extends UnlistedSpecialPage {
	var $facebook = null; // Handle to facebook API
	var $userid = 0;
	var $returnto = "";

    function __construct($source = null) {
        parent::__construct( 'FBLogin' );
    }

	function execute($par) {
		global $wgOut, $wgRequest, $wgUser, $wgFBAppId, $wgFBAppSecret, $wgLanguageCode, $wgContLang, $IP;
		require_once("$IP/extensions/wikihow/common/facebook-platform/facebook-php-sdk-771862b/src/facebook.php");

		wfLoadExtensionMessages('FBLogin');
		if ( session_id() == '' ) {
			wfSetupSession();                                                                                                                                                                                        
		}

		$this->returnto = $wgLanguageCode == 'en' ? wfMsg('fbc_returnto') : "/" . $wgContLang->getNSText(NS_PROJECT) . ":" . wfMsg('communityportal');
		//$this->returnto = $_COOKIE['wiki_returnto'] ? $_COOKIE['wiki_returnto'] : "/Special:CommunityDashboard";
		$this->userid = $_COOKIE['wiki_fbuser'];
		$userid = $this->userid;
		if (!$userid) {
			$wgOut->addHTML("An error occurred.<!--" . print_r($_COOKIE, true) . "-->");
			return;
		}
		$this->setWgUser();

		$this->facebook = new Facebook(array('appId' => $wgFBAppId, 'secret' => $wgFBAppSecret));
		$accessToken = $_COOKIE['wiki_fbtoken'];
		$this->facebook->setAccessToken($accessToken);
		
		$result = $this->facebook->api('/me');

		if (!$wgRequest->wasPosted()) {
			// If they still have the FB_* name, show them the registration form with a proposed name
			if (strpos($wgUser->getName(), "FB_") !== false) {
				$this->printRegForm($result);
			} else {
				$this->updateAvatar($result);

				// All logged in. Return them to wherever they're supposed to go
				$this->setCookies();
				$wgOut->redirect($this->returnto);
			}
		} else {
			$this->processRegForm($result);
		}
	}	

	function setCookies() {
		setcookie( 'wiki_returnto', '', time() - 3600);
		setcookie( 'wiki_fbtoken', '', time() - 3600);
		setcookie( 'wiki_fbuser', '', time() - 3600);
	}

	function setWgUser() {
		global $wgUser;

		LoginForm::renewSessionId();
		$dbw = wfGetDB(DB_MASTER);
		$wh_userid = $dbw->selectField('facebook_connect', array('wh_user'), array('fb_user' => $this->userid));
		// Never here before?  create a new user and log them in
		if ($wh_userid == null) {
			$u = User::createNew('FB_' . $this->userid);
			if (!$u) {
				$u = User::newFromName('FB_' . $this->userid);
			}
			$dbw->insert('facebook_connect', array('wh_user' => $u->getID(), 'fb_user' => $this->userid));	
		} else {
			$u = User::newFromID($wh_userid);
			$dbw->update('facebook_connect', array('num_login = num_login + 1'), array('wh_user' => $wh_userid));
		}
		$wgUser = $u;		
		$wgUser->setCookies();
	}


	function printRegForm(&$result, $username = null, $email = '', $error = '') {
		global $wgOut, $_SERVER;
		$username = $username !== null ? $username : $this->getProposedUsername($result['name']);
		$prefill = true;
		if(!$username) {
			$prefill = false;	
		}
		$email = $email ? $email : $result['email'];
		$picture = $this->getPicUrl($result['id'], 'square');
		$friendsHtml = $this->getAppFriendsHtml();
		//$affiliations = $this->getAffiliations($result);
		$affiliations = "";
		if(strlen($affiliations)) {
			$affiliations .= ' &middot;';
		}

		//$numFriends = count($this->facebook->api_client->friends_get());
		$numFriends = count($this->facebook->api('/me/friends'));
		$fbicon = wfGetPad('/skins/WikiHow/images/facebook_share_icon.gif');
		wfLoadExtensionMessages('FBLogin');
		if ($prefill) {
			$html = wfMsg('fbc_form_prefill', $fbicon, $username, $error, $picture, $affiliations, $numFriends, $email, $friendsHtml);
		} else {
			$html = wfMsg('fbc_form_no_prefill', $fbicon, $username, $error, $email, $friendsHtml);
		}
		$tags = HtmlSnips::makeUrlTags('css', array('fblogin.css'), '/extensions/wikihow/fblogin', FBLOGIN_DEBUG);
		$tags .= HtmlSnips::makeUrlTags('js', array('fblogin.js'), '/extensions/wikihow/fblogin', FBLOGIN_DEBUG);
		$wgOut->addHtml($tags);
		$wgOut->addHtml($html);
	}

	function processRegForm(&$result) {
		global $wgRequest, $wgUser, $wgOut;
		$dbw = wfGetDB(DB_MASTER);
		$userOverride = strlen($wgRequest->getVal('requested_username'));
		$newname = $userOverride ? $wgRequest->getVal('requested_username') : $wgRequest->getVal('proposed_username');
		$newname = $dbw->strencode($newname);
		$email = $dbw->strencode($wgRequest->getVal('email'));
		$realname = $userOverride ? '' : $dbw->strencode($result['name']);

		$newname = User::getCanonicalName($newname);
		$exist = User::newFromName($newname);
		if ($exist->getID() > 0 && $exist->getID() != $wgUser->getID()) {
			$this->printRegForm($result, $newname, $email, wfMsg('fbconnect_username_inuse', $newname));
			return;
		}
		$authenticatedTimeStamp = wfTimestampNow();
		$dbw->update('user',
			array('user_name' => $newname, 'user_email' => $email, 'user_real_name' => $realname, 'user_email_authenticated' => $authenticatedTimeStamp),
			array('user_id' => $wgUser->getID())
			);
		if (!$userOverride) {
			$this->updateAvatar($result);
		}
		$wgUser->invalidateCache();
		$wgUser->loadFromID();
		$wgUser->setCookies();
		wfRunHooks( 'AddNewAccount', array( $wgUser, true ) );
		wfRunHooks( 'FBLoginComplete', array($wgUser));
		
		// All registered. Send them along their merry way
		$this->setCookies();
		$wgOut->redirect($this->returnto);
	}

	function userNameIsFacebookUser($name) {
		$dbr = wfGetDB(DB_SLAVE);
		return $dbr->selectField( array('facebook_connect', 'user'),
				array('count(*)'),
				array('user_id=wh_user', 'user_name'=>$name)
			) > 0;
	}

	//update avatar picture
	function updateAvatar(&$result) {
		global $wgLanguageCode, $wgUser;

		if ($wgLanguageCode == 'en') {
			$dbr = wfGetDB(DB_SLAVE);
			$dbw = wfGetDB(DB_MASTER);
			$res = $dbr->select('avatar', array('av_image', 'av_patrol'), array("av_user=" . $wgUser->getID()));
			$row = $dbr->fetchObject($res);
			$picUrl = $this->getPicUrl($result['id']);
			if ($row && $row->av_image && ($row->av_patrol == 0 || $row->av_patrol == 1)) {
				$dbw->query ("INSERT INTO avatar(av_user, av_image) VALUES ({$wgUser->getID()}, '{$picUrl}')
								ON DUPLICATE KEY UPDATE av_image = '{$picUrl}'");
			}

			// Must be registering
			if (!$row) {
				$dbw->query ("INSERT INTO avatar(av_user, av_image, av_patrol) VALUES ({$wgUser->getID()}, '{$picUrl}', 0)
								ON DUPLICATE KEY UPDATE av_image = '{$picUrl}, av_patrol = 0'");
			}	
		}
	}
	
	function removeAvatar($userID) {
		global $wgLanguageCode;

		if ($wgLanguageCode == 'en') {
			$u = User::newFromId($userID);
			$dbr = wfGetDB(DB_SLAVE);
			$dbw = wfGetDB(DB_MASTER);
			$res = $dbr->select('avatar', array('av_image', 'av_patrol'), array("av_user=" . $u->getID()));
			$row = $dbr->fetchObject($res);
			if ($row && $row->av_image && ($row->av_patrol == 0 || $row->av_patrol == 1)) {
				$dbw->delete('avatar', array('av_user' => $u->getID()), __METHOD__);
			}
		}
	}

	function getProposedUsername ($fullname) {
		$proposed_name = '';
		
		$res = array(
					array('@([\s]*)@', '$1'),
					array('@([a-z]?)[a-z]*([\s]*)([a-z]+).*@im', '$1$3'),
					array('@([a-z]+)([\s]*)([a-z]?).*@im', '$1$3'),
				);
		foreach ($res as $re) {
			$name = preg_replace($re[0], $re[1], $fullname);
			$u = User::newFromName($name);
			if ($u && $u->getID() == 0) {
				$proposed_name = $name;
				break;
			}
		}
		return $proposed_name;
	}

	function getAppFriendsHtml() {
		global $wgOut;
		$html = "";
		$friends = $this->facebook->api('/me/friends');
		$numFriends = count($friends);
		if($friends === '' || $numFriends == 0) {
			return $html;
		}
		$friendsToDisplay = 3;

		for($i = 0; $i < $numFriends && $i < $friendsToDisplay; $i++) {
			$pic = $this->getPicUrl($friends['data'][$i]['id']);
			$html .= "<img class='fbc_avatar' src='$pic'/> ";
		}
		$html .= "$numFriends ";
		$html .= $numFriends > 1 ? " of your friends have registered" : "friend has registered";
		return $html;
	}
	
	function getAffiliations(&$result) {
		$affiliations = $result[0]['affiliations'];
		$affCnt = count($affiliations);
		$affStr = "";
		if ($affiliations === '' || $affCnt == 0) {
			return $affStr;
		}

		for($i = 0; $i < 2 && $i < $affCnt; $i++) {
			$affStr .= $affiliations[$i]['name'] . ", ";
		}
		return substr($affStr, 0, strlen($affStr) - 2);
	}

	public static function getPicUrl($id, $type = 'square') {
		return "http://graph.facebook.com/$id/picture?type=$type";
	}
}
