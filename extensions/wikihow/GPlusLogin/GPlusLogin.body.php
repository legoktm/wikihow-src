<?
/*
 * create table gplus_connect (
 * gplus_user varchar(255) NOT NULL default '',
 * wh_user mediumint(8),
 * num_login int(11) default 0
 * );
 * create index gplus_index ON gplus_connect(wh_user);
 *
 */

class GPlusLogin extends UnlistedSpecialPage {

	var $userid = 0;
	var $returnto = "";

    function __construct($source = null) {
        parent::__construct( 'GPlusLogin' );
    }

	function execute($par) {
		global $wgRequest, $wgUser, $wgLanguageCode, $wgContLang, $wgOut;
		wfLoadExtensionMessages('GPlusLogin');

		if ( session_id() == '' ) {                                                                                                                                                                                  
			wfSetupSession();
		}

		//disconnecting?
		if ($wgRequest->getVal('disconnect')) {
			self::userDisco();
			return;
		}
		
		//returning to the community dashboard
		$this->returnto = $wgLanguageCode == 'en' ? wfMsg('gpl_returnto') : "/" . $wgContLang->getNSText(NS_PROJECT) . ":" . wfMsg('communityportal');
		
		//set that user (if we can)
		$this->userid = $wgRequest->getVal('gplus_id') ? $wgRequest->getVal('gplus_id') : $wgRequest->getVal('user_id');
		if ($this->userid) $this->setWgUser();
		
		if ($wgRequest->wasPosted() && $wgRequest->getVal('gplus_id')) {
			self::processForm();
			return;
		}
		
		//get user's G+ info
		$gp_id = $wgRequest->getVal('user_id');
		$gp_name = $wgRequest->getVal('user_name');
		$gp_email = $wgRequest->getVal('user_email');
		$gp_avatar = $wgRequest->getVal('user_avatar');

		self::showForm($gp_id,$gp_name,$gp_email,$gp_avatar);
	}

	function setWgUser() {
		global $wgUser, $wgOut, $wgDBname;

		LoginForm::renewSessionId();

		$bNew = true;
		$dbw = wfGetDB(DB_MASTER);
		
		$dbw->selectDB(WH_DATABASE_NAME_SHARED);
		$wh_userid = $dbw->selectField('gplus_connect', array('wh_user'), array('gplus_user' => $this->userid));
		$dbw->selectDB($wgDBname);
		
		// Never here before?  create a new user and log them in
		if ($wh_userid == null) {
			$u = User::createNew('GP_' . $this->userid);
			if (!$u) {
				$u = User::newFromName('GP_' . $this->userid);
			}
			
			$dbw->selectDB(WH_DATABASE_NAME_SHARED);
			$dbw->insert('gplus_connect', array('wh_user' => $u->getID(), 'gplus_user' => $this->userid));
			$dbw->selectDB($wgDBname);
		} else {
			$u = User::newFromID($wh_userid);
			
			$dbw->selectDB(WH_DATABASE_NAME_SHARED);
			$dbw->update('gplus_connect', array('num_login = num_login + 1'), array('wh_user' => $wh_userid));
			$dbw->selectDB($wgDBname);
			$bNew = false;
		}
		$wgUser = $u;
		$wgUser->setCookies();
		
		if (!$bNew) {
			$dbw->selectDB(WH_DATABASE_NAME_SHARED);
			$registered = $dbw->selectField('user', array('user_email'), array('user_id' => $wh_userid));
			$dbw->selectDB($wgDBname);
		
			//pass them to our start page if they're logging in again...	
			if ($registered) $wgOut->redirect($this->returnto);
		}
	}
	
	/*
	 * The form for Google+ login user to select their wikiHow user stuff
	 */
	function showForm($id,$username,$email,$avatar,$error='') {
		global $wgOut;
		
		$origname = $username;
		//make sure we have a good username
		//$username = $username !== null ? $username : $this->getProposedUsername($username);
		$username = $this->getProposedUsername($username);
		
		$tmpl = new EasyTemplate( dirname(__FILE__) );
		$tmpl->set_vars(array(
			'username' => $username,
			'origname' => $origname,
			'avatar' => $avatar,
			'email' => $email,
			'id' => $id,
			'error' => $error,
		));
		$html = $tmpl->execute('gplusform.tmpl.php');
		
		$wgOut->addHeadItem('gpluslogin_css',HtmlSnips::makeUrlTags('css', array('gpluslogin.css'), 'extensions/wikihow/GPlusLogin', false));
		$wgOut->addScript(HtmlSnips::makeUrlTags('js', array('gpluslogin.js'), 'extensions/wikihow/GPlusLogin', false));

		$wgOut->addHtml($html);
	}
	
	function processForm() {
		global $wgRequest, $wgUser, $wgOut;
		$dbw = wfGetDB(DB_MASTER);
		
		$userOverride = strlen($wgRequest->getVal('requested_username'));
		$newname = $userOverride ? $wgRequest->getVal('requested_username') : $wgRequest->getVal('proposed_username');
		$newname = $dbw->strencode($newname);
		$email = $dbw->strencode($wgRequest->getVal('email'));
		$realname = $userOverride ? '' : $dbw->strencode($wgRequest->getVal('original_username'));
		$avatar = $wgRequest->getVal('avatar_url');
		$show_authorship = (int)($wgRequest->getVal('show_authorship') == 'on');
		
		$newname = User::getCanonicalName($newname);
		
		if (self::usernameExists($newname)) {
			$this->showForm($wgRequest->getVal('gplus_id'),
							$wgRequest->getVal('original_username'),
							$wgRequest->getVal('email'),
							$wgRequest->getVal('avatar_url'),
							wfMsg('gplusconnect_username_inuse', $newname));
			return;
		}
		$dbw->update('user',
			array('user_name' => $newname, 'user_email' => $email, 'user_real_name' => $realname),
			array('user_id' => $wgUser->getID())
			);
		
		//update the avatar
		$this->updateAvatar($avatar);
		
		$wgUser->invalidateCache();
		$wgUser = User::newFromName($newname);
		
		//update authorship settings
		// [for later...] (hard code for now)
		//$wgUser->setOption('show_google_authorship', $show_authorship);
		$wgUser->setOption('show_google_authorship', false);
		
		//add G+ user in user options
		$wgUser->setOption('gplus_uid',$wgRequest->getVal('gplus_id'));
		$wgUser->saveSettings();
		
		$wgUser->setCookies();
		
		wfRunHooks( 'AddNewAccount', array( $wgUser, true ) );
		wfRunHooks( 'GPlusLoginComplete', array($wgUser));

		// All registered. Send them along their merry way
		$wgOut->redirect($this->returnto);	
	}
	
	function usernameExists($username) {
		global $wgUser;
		$exists = User::newFromName($username);
		if ($exists && $exists->getID() > 0 && $exists->getID() != $wgUser->getID()) {
			return true;
		}
		return false;
	}
	
	function getProposedUsername ($fullname) {
		$proposed_name = '';
		
		$u = User::newFromName($fullname);
		if ($u && $u->getID() == 0) {
			return $fullname;
		}
		
		$res = array(
					array('@([\s_]*)@', '$1'),
					array('@([a-z]?)[a-z]*([\s_]*)([a-z]+).*@im', '$1$3'),
					array('@([a-z]+)([\s_]*)([a-z]?).*@im', '$1$3'),
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
		
	//update avatar picture
	function updateAvatar($pic) {
		global $wgLanguageCode, $wgUser;

		if ($wgLanguageCode == 'en') {
			$dbr = wfGetDB(DB_SLAVE);
			$dbw = wfGetDB(DB_MASTER);
			$res = $dbr->select('avatar', array('av_image', 'av_patrol'), array("av_user=" . $wgUser->getID()));
			$row = $dbr->fetchObject($res);
			if ($row && $row->av_image && ($row->av_patrol == 0 || $row->av_patrol == 1)) {
				$dbw->query ("INSERT INTO avatar(av_user, av_image) VALUES ({$wgUser->getID()}, '{$pic}')
								ON DUPLICATE KEY UPDATE av_image = '{$pic}'");
			}

			// Must be registering
			if (!$row) {
				$dbw->query ("INSERT INTO avatar(av_user, av_image, av_patrol) VALUES ({$wgUser->getID()}, '{$pic}', 0)
								ON DUPLICATE KEY UPDATE av_image = '{$pic}, av_patrol = 0'");
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
	
	//disconnect the user from the G+ account
	function userDisco() {
		global $wgOut, $wgUser, $wgDBname;
		if ($wgUser->getID() == 0) return;
		
		//remove from user options and make sure their authorship is off
		$wgUser->setOption('gplus_uid','');
		$wgUser->setOption('show_google_authorship',false);
		$wgUser->saveSettings();
		
		//remove from the connecting table
		$dbw = wfGetDB(DB_MASTER);		
		$dbw->selectDB(WH_DATABASE_NAME_SHARED);
		$dbw->delete('gplus_connect', array('wh_user' => $wgUser->getID()), __METHOD__);
		$dbw->selectDB($wgDBname);
		
		//done? good. now they need a password
		$newpass = AdminResetPassword::resetPassword($wgUser->getName());
		$html = '<p><b>Your disconnected login name:</b> '.$wgUser->getName().'</p>'.
				'<p><b>Your temporary password:</b> '.$newpass.'</p>'.
				'<p>Copy it down and then use it to <a href="/Special:Userlogin">login here</a>.';
		$wgOut->addHTML($html);
	}
}
