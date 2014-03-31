<?
class Vanilla extends UnlistedSpecialPage {

	public static function setUserRole($userid, $role) {
		global $wgVanillaDB;
		$fname = "Vanilla::setAvatar";
		wfProfileIn($fname);
		$db = DatabaseBase::factory('mysql');
		$db->open($wgVanillaDB['host'], $wgVanillaDB['user'], $wgVanillaDB['password'], $wgVanillaDB['dbname']);
	   	// get vanilla user id
		$vid = $db->selectField('GDN_UserAuthentication', array('UserID'), array('ForeignUserKey'=> $userid));
		if (!$vid) return false;
		
		$updates = array("RoleID"=>$role);
		$opts = array('UserID'=>$vid);
		$db->update('GDN_UserRole', $updates, $opts);
		wfProfileOut($fname);
		return true;
	}

	public static function setAvatar($user) {
		global $wgVanillaDB;
		$fname = "Vanilla::setAvatar";
		wfProfileIn($fname);
		$db = DatabaseBase::factory('mysql');
		$db->open($wgVanillaDB['host'], $wgVanillaDB['user'], $wgVanillaDB['password'], $wgVanillaDB['dbname']);
	   	// get vanilla user id
		$vid = $db->selectField('GDN_UserAuthentication', array('UserID'), array('ForeignUserKey'=> $user->getID()));
		if (!$vid) return false;
		
		$updates = array("Photo"=>Avatar::getAvatarURL($user->getName()));
		$opts = array('UserID'=>$vid);
		$db->update('GDN_User', $updates, $opts);
		wfDebug("Vanilla: Updating avatar " . print_r($updates, true) . print_r($opts, true) . "\n");
		wfProfileOut($fname);
		return true;
	}

    function __construct($source = null) {
        parent::__construct( 'Vanilla' );
    }

	function execute($par) {
		global $wgUser, $wgOut;
		if (!$wgUser) {
			return;
		}
		if ($wgUser->getID() == 0) {
			$wgOut->redirect('/Special:Userlogin?returnto=vanilla');
			return;
		}
		if (!$wgUser->getEmail()) {
			$wgOut->addHTML("You are not logged into the forums because you do not have an email address specified in your <a href='/Special:Preferences'>preferences</a>.");
		} else {
			$wgOut->addHTML("A problem happened when we tried to log you into the forums. Try <a href='/Special:Userlogout'>logging out</a> of wikiHow then <a href='/Special:Userlogin'>logging back in</a> again to fix the issue.");
		}
		return;
	}
}

