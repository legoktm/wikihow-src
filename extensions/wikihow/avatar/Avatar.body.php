<?php

//for S3 connection
use Aws\Common\Aws;
use Guzzle\Http\EntityBody;

class Avatar extends UnlistedSpecialPage {

	const DEFAULT_PROFILE_OLD = '/skins/WikiHow/images/default_profile.png';
	const DEFAULT_PROFILE = '/skins/WikiHow/images/80x80_user.png';
	const ANON_AVATAR_DIR = '/skins/WikiHow/anon_avatars';
	const AWS_BUCKET = 'image_backups';
	
	static $aws = null;

	function __construct() {
		parent::__construct( 'Avatar' );
	}

	function displayManagement() {
		global $wgOut, $wgUser, $wgRequest;

		if( $wgUser->isBlocked() ) {
			$wgOut->blockedPage();
			return;
		}

		if ( !in_array( 'sysop', $wgUser->getGroups() ) ) {
			$wgOut->showErrorPage( 'nosuchspecialpage', 'nospecialpagetext' );
			return;
		}
		$inappropriate = wfMsg('avatar-rejection-ut-inappropriate');
		$copyright = wfMsg('avatar-rejection-ut-copyright');
		$other = wfMsg('avatar-rejection-ut-other');

		$wgOut->addHTML("
	<script language='javascript' src='" . wfGetPad('/extensions/wikihow/common/cropper/lib/prototype.js?') . WH_SITEREV . "'></script>
	<script type='text/javascript' src='".wfGetPad('/extensions/min/f/skins/common/jquery.md5.js?') . WH_SITEREV ."'></script>
<script language='javascript' src='" . wfGetPad('/extensions/wikihow/avatar/avatar.js?') . WH_SITEREV . "'></script>
<link rel='stylesheet' media='all' href='" . wfGetPad('/extensions/wikihow/common/cropper/cropper.css?') . WH_SITEREV . "' type='text/css' />
<link rel='stylesheet' media='all' href='" . wfGetPad('/extensions/wikihow/avatar/avatar.css?') . WH_SITEREV . "' type='text/css' />
<script type='text/javascript'>
	var msg_inappropriate = '".addslashes(preg_replace('/\n/','',$inappropriate))."';
	var msg_copyright = '".addslashes(preg_replace('/\n/','',$copyright))."';
	var msg_other = '".addslashes(preg_replace('/\n/','',$other))."';
</script>");

		$wgOut->addHTML("<h1>User Picture Management</h1>\n");
		$wgOut->addHTML(wfMsg('avatar-mgmt-instructions') . "<br />\n");

		$dbr = wfGetDB(DB_SLAVE);

		$sql = "SELECT av_patrol, count(av_patrol) AS count FROM avatar GROUP BY av_patrol";
		$res = $dbr->query($sql, __METHOD__);

		$total = 0;
		if ($dbr->numRows($res) > 0) {
			while ($row = $dbr->fetchObject($res)) {
				if ($row->av_patrol == 0) {
					$wgOut->addHTML("Users with pictures to patrol: ". $row->count . "<br />");
					$total += $row->count;
				} else if ($row->av_patrol == 2) {
					$wgOut->addHTML("Users who have removed pictures: ". $row->count . "<br />");
				} else {
					$total += $row->count;
				}
			}
		}
		$wgOut->addHTML("Total user pictures currently in use: ". $total . "<br /><br />");

		$sql = "SELECT * FROM avatar WHERE av_patrol=0 ORDER BY av_dateAdded";
		if ($wgRequest->getVal("reverse"))
			$sql .= " DESC ";
		$sql .= " LIMIT 100";
		$res = $dbr->query($sql, __METHOD__);

		if( $dbr->numRows($res) > 0) {
			while ($row = $dbr->fetchObject($res)) {
				$u = User::newFromID($row->av_user);
				$imgPath = self::getAvatarOutPath($row->av_user . ".jpg");
				$img = "<img src='" . $imgPath . $row->av_user . ".jpg' height=80px width=80px/>";
				//handle Facebook images
				if (!empty($row->av_image))
					$img = "<img src='{$row->av_image}' />";
				$wgOut->addHTML("
<div id='div_".$row->av_user."' style='width:600px'>
	<div style='float:left;margin:10px; width:80px; text-align:center;'>
	{$img}
	</div>

	<div style='padding-top: 40px; width: 350px; float:left;'>
	<span style='margin:30px;text-align: left;'>
		<a href='/".preg_replace('/\s/','-',$u->getUserPage())."'>".$u->getName()."</a>
		(<a href='/".$u->getTalkPage()."' target='_blank'>Talk</a> |
		<a href='/Special:Contributions/".$u->getName()."' target='_blank'>Contributions</a> |
		<a href='/Special:Blockip/".$u->getName()."' target='_blank'>Block</a> )

	</div>
	<div style='float: right; height: 40px; padding-top:30px;'>
		<input type='button' name='accept' value='Accept' onclick=\"avatarAccept('".$row->av_user."');\" />
		<input type='button' name='reject' value='Reject'  onclick=\"avatarReject(this,'".$row->av_user."');\" /><br/>
	</div>


<div style='clear: both;width= 70%;border:1px solid #AAA;'> </div>
</div>
<div style='clear: both;'> </div>

			");

			}

			$wgOut->addHTML("
<div class='avatarModalPage' id='avatarModalPage'>
   <div class='avatarModalBackground' id='avatarModalBackground'></div>
   <div class='avatarModalContainerReject' id='avatarModalContainerReject'>
	  <div class='avatarModalTitle'><span style='float:right;'><a onclick=\"avatarRejectReset();\">X</a></span>".wfMsg('avatar-reject-modal-instructions')."</div>
	  <div class='avatarModalBody'>
			<div id='reasonmodal' >
				<form name='rejectReason' id='rejectReason'>
				<table><tr>
				<td>Reject Reason:</td>
				<td>
				<select name='reason' SIZE=1 onchange='changeMessage();'>
					<option selected value='inappropriate'>Inappropriate or Offensive</option>
					<option value='copyright'>Copyright Violation</option>
					<option value='other'>Other</option>
				</select>
				</td>
				</tr><tr>
				<td valign='top'>Message:</td>
				<td>
				<textarea id='reason_msg' cols='55' rows='5'></textarea>
				</td></tr></table>

				<input type='hidden' name='reasonUID' value='0'>
				</form>
				<div style='clear: both;padding:5px;'> </div>
				<div style='float:right;padding-right:10px;'>
					<input type='button' name='reject' value='Reject'  onclick=\"avatarReject2();\" />
					<a onclick=\"avatarRejectReset();\" >Cancel</a>
				</div>
				<div style='clear: both;'> </div>
			</div>
		</div>
	</div>
</div>
			");

		} else {
			$wgOut->addHTML("No new avatars to patrol.");
		}
	}

	function accept($uid) {
		global $wgUser, $wgOut;

		if( $wgUser->isBlocked() ) {
			$wgOut->blockedPage();
			return;
		}

		if ( !in_array( 'sysop', $wgUser->getGroups() ) ) {
			$wgOut->showErrorPage( 'nosuchspecialpage', 'nospecialpagetext' );
			return;
		}

		$wgOut->setArticleBodyOnly(true);

		$dbw = wfGetDB(DB_MASTER);
		$sql = "UPDATE avatar SET av_patrol=1, av_patrolledBy=" . $wgUser->getID() . ", " .
			"av_patrolledDate='".wfTimestampNow()."' WHERE av_user=" . $dbw->addQuotes($uid);
		$res = $dbw->query($sql, __METHOD__);

		return("SUCCESS");

	}

	function reject($uid, $reason, $message) {
		global $wgUser, $wgOut, $wgLang;

		if( $wgUser->isBlocked() ) {
			$wgOut->blockedPage();
			return;
		}

		if ( !in_array( 'sysop', $wgUser->getGroups() ) ) {
			$wgOut->showErrorPage( 'nosuchspecialpage', 'nospecialpagetext' );
			return;
		}

		$wgOut->setArticleBodyOnly(true);

		$dbw = wfGetDB(DB_MASTER);

		//REMOVE PICTURE
		$ret = $this->removePicture($uid);
		if (preg_match('/FAILED/',$ret,$matches)) {
			wfDebug("Avatar removePicture failed: ".$ret."\n");
		}

		//UPDATE DB RECORD
		$sql = "UPDATE avatar SET av_patrol=-1, av_patrolledBy=" . $wgUser->getID() . ", av_patrolledDate='" . wfTimestampNow() . "', av_rejectReason='" . $dbw->addQuotes($reason) . "' WHERE av_user=" . $dbw->addQuotes($uid);
		$res = $dbw->query($sql, __METHOD__);

		//POST ON TALK PAGE
		$dateStr = $wgLang->timeanddate(wfTimestampNow());

		$user = $wgUser->getName();
		$real_name = User::whoIsReal($wgUser->getID());
		if (!$real_name) $real_name = $user;

		$u = User::newFromID($uid);

		$user_talk = $u->getTalkPage();

		$comment = "";
		$text = "";
		$article = "";
		if ($message) {
			$comment = $message . "\n";
		}

		if ($comment) {
			$formattedComment = wfMsg('postcomment_formatted_comment', $dateStr, $user, $real_name, $comment);

			if ($user_talk->getArticleId() > 0) {
				$r = Revision::newFromTitle($user_talk);
				$text = $r->getText();
			}
			$article = new Article($user_talk);

			$text .= "\n\n$formattedComment\n\n";

			$watch = false;
			if ($wgUser->getID() > 0)
				$watch = $wgUser->isWatched($user_talk);

			if ($user_talk->getArticleId() > 0) {
				$article->updateArticle($text, wfMsg('avatar-rejection-usertalk-editsummary'), true, $watch);
			} else {
				$article->insertNewArticle($text, wfMsg('avatar-rejection-usertalk-editsummary'), true, $watch, false, false, true);
			}

		}

		return("SUCCESS");
	}

	// return the URL of the avatar
	static function getAvatarRaw($name) {
		$u = User::newFromName($name);
		if (!$u) {
			return array('type' => 'df', 'url' => '');
		}
		$u->load();

		$dbr = wfGetDB(DB_SLAVE);
		// check for facebook
		if ($u->isFacebookUser()) {
			$row = $dbr->selectRow('avatar', array('av_image','av_patrol'), array('av_user'=>$u->getID()), __METHOD__);
			if ($row->av_image && ($row->av_patrol == 0 || $row->av_patrol == 1)) {
				return array('type' => 'fb', 'url' => $row->av_image);
			}
		}
		
		//check for Google+
		if ($u->isGPlusUser()) {
			$row = $dbr->selectRow('avatar', array('av_image','av_patrol'), array('av_user'=>$u->getID()), __METHOD__);
			if ($row->av_image && ($row->av_patrol == 0 || $row->av_patrol == 1)) {
				return array('type' => 'gp', 'url' => $row->av_image);
			}
		}

		//checks for redirects for users that go that route
		//rather than just changing the username
		$up = $u->getUserPage();
		$a = new Article($up, 0); //need to put 0 as the oldID b/c Article gets the old id out of the URL
		if ($a->isRedirect()) {
			$t = Title::newFromRedirect( $a->fetchContent() );
			if (!($u = User::newFromName($t->getText()))) {
				return array('type' => 'df', 'url' => '');
			}
		}

		$row = $dbr->selectRow('avatar', array('av_dateAdded'), array('av_user'=>$u->getID(), 'av_patrol'=>0), __METHOD__);
		$filename = $u->getID() .".jpg";
		$cropout = self::getAvatarOutFilePath($filename) . $filename;
		if ($row && $row->av_dateAdded) {
			return array('type' => 'av', 'url' => "$filename?" . $row->av_dateAdded);
		}

		return array('type' => 'df', 'url' => '');
	}

	function getAvatarURL($name) {
		$raw = self::getAvatarRaw($name);
		if ($raw['type'] == 'df') {
			return Avatar::getDefaultProfile();
		} elseif (($raw['type'] == 'fb') || ($raw['type'] == 'gp')) {
			return $raw['url'];
		} elseif ($raw['type'] == 'av') {
			$imgName = explode("?", $raw['url']);
			$imgPath = self::getAvatarOutPath($imgName[0]);
			return wfGetPad($imgPath . $raw['url']);
		}
	}

	function getPicture($name, $raw = false, $fromCDN = false) {
		global $wgUser, $wgTitle;

		$u = User::newFromName($name);
		if (!$u) return;

		// not sure what's going on here, User Designer-WG.de ::newFromName does not work, mId==0
		if ($u->getID() == 0) {
			$dbr = wfGetDB(DB_SLAVE);
			$id = $dbr->selectField('user', array('user_id'), array('user_name'=> $name), __METHOD__);
			$u = User::newFromID($id);
		}

		$filename = $u->getID() . ".jpg";
		$crop_out = self::getAvatarOutFilePath($filename) . $filename;

		$ret = "";
		if (!$raw) {
			$ret = "<link rel='stylesheet' media='all' href='" . wfGetPad('/extensions/wikihow/avatar/avatar.css?') . WH_SITEREV . "' type='text/css' />
			<script type='text/javascript' src='".wfGetPad('/extensions/min/f/skins/common/jquery.md5.js?') . WH_SITEREV ."'></script>
			<script language='javascript' src='" . wfGetPad('/extensions/wikihow/avatar/avatar.js?') . WH_SITEREV . "'></script>\n";
		}

		// handle facebook users
		if ($u->isFacebookUser()) {
			$dbr = wfGetDB(DB_SLAVE);
			$row = $dbr->selectRow('avatar', array('av_image','av_patrol'), array('av_user'=>$u->getID()), __METHOD__);
			if ($row->av_image && ($row->av_patrol == 0 || $row->av_patrol == 1)) {
				$imgUrl = $row->av_image;
				$ret .= "<div id='avatarID' class='avatar_fb'><img id='avatarULimg' src='{$imgUrl}'  height='50px' width='50px' /><br/>";
				if ($u->getID() == $wgUser->getID() && ($wgTitle->getNamespace() == NS_USER))
					$ret .="<a href='#' onclick='removeButton();return false;'>remove</a>";
				$ret .= "</div>";
				return $ret;
			}
		}
		
		//handle Google+ users
		if ($u->isGPlusUser()) {
			$dbr = wfGetDB(DB_SLAVE);
			$row = $dbr->selectRow('avatar', array('av_image','av_patrol'), array('av_user'=>$u->getID()), __METHOD__);
			if ($row->av_image && ($row->av_patrol == 0 || $row->av_patrol == 1)) {
				$imgUrl = $row->av_image;
				$ret .= "<div id='avatarID' class='avatar_gp'><img id='avatarULimg' src='{$imgUrl}'  height='50px' width='50px' /><br/>";
				if ($u->getID() == $wgUser->getID() && ($wgTitle->getNamespace() == NS_USER))
					$ret .="<a href='#' onclick='removeButton();return false;'>remove</a>";
				$ret .= "</div>";
				return $ret;
			}
		}
		
		if (($wgUser->getID() == $u->getID()) && ($wgUser->getID() > 0) && ($wgTitle->getNamespace() == NS_USER)) {
			$ret .= "<div class='avatar' id='avatarID'>";
			$url = self::getAvatarURL($name);
			if (stristr($url, basename(Avatar::getDefaultProfile())) !== false) {
				$ret .= "
				<div id='avatarULaction'><div class='avatarULtextBox'><a class='avatar_upload' onclick='uploadImageLink();return false;' id='gatUploadImageLink' href='#'></a></div></div>";
			} else {
				$ret .= "<img id='avatarULimg' src='" .  $url . "' height='80px' width='80px' />";
				$ret .= "<a href onclick='removeButton();return false;' onhover='background-color: #222;' >remove</a> | <a href onclick='editButton();return false;' onhover='background-color: #222;'>edit</a>";
			}
			$ret .= "</div>";
		} else {
			$dbr = wfGetDB(DB_SLAVE);
		    $row = $dbr->selectRow('avatar', array('av_dateAdded'), array('av_user'=>$u->getID(), 'av_patrol'=>0), __METHOD__);

			if ($row && $row->av_dateAdded) {
				if ($raw) {
					$imgUrl = self::getAvatarURL($name);
					$ret .= "<img src='" . $imgUrl . "' />";
				} else {
					$ret .= "<div id='avatarID' class='avatar'>";
					$ret .= "<img id='avatarULimg' src='" .  self::getAvatarURL($name) . "' height='80px' width='80px' /></div>";
				}
			} else {
				// NOTE: We could return the default image here. But not until 
				// we force profile images.
				$ret = "";
			}
		}

		return $ret;
	}

	function getAnonName($src) {
		if (preg_match('/\_(.*?)\./', $src, $matches)) {
			return "Anonymous " . ucfirst($matches[1]);
		}
	}

	// returns an anon avatar picture and its name
	// hashes on the id if it is non null
	function getAnonImageFileName($id = null) {
		global $IP;
		$images	= glob($IP . Avatar::ANON_AVATAR_DIR . '/80x80*');
		if ($id === null) {
			$i = array_rand($images);
		} else {
			$i = abs($id) % (count($images) - 1);
			MWDebug::log("id: $id, i: $i");
		}
		return basename($images[$i]);
	}

	// gets an avatar from the pool of anonymous avatars
	// uses the id as a hash so you can keep getting the same one if you want
	// passing in a null just gives a completely random avatar
	function getAnonAvatar($id = null) {
		$fileName = Avatar::getAnonImageFileName($id);
		$img = "<img src='" . wfGetPad(Avatar::ANON_AVATAR_DIR . "/" . $fileName) . "'>";
		$name = Avatar::getAnonName($fileName);
		$ret = array("name"=>$name, "image"=>$img);
		return $ret;
	}

	function getDefaultPicture() {
		$ret = "<img src='" . Avatar::getDefaultProfile() . "'>";
		return $ret;
	}

	function getDefaultProfile() {
		global $wgDebugToolbar;

		return wfGetPad(self::DEFAULT_PROFILE);
	}

	public static function removePicture($uid = '') {
		global $wgUser;

		if ($uid == '') {
			$u = $wgUser->getID();
		} else {
			$u = $uid;
		}


		$fileext = array('jpg','png','gif','jpeg');

		$filename = $u . ".jpg";
		$crop_out = self::getAvatarOutFilePath($filename) . $filename;
		if (file_exists($crop_out)) {
			if(unlink($crop_out)) {
				foreach ($fileext as $ext) {
					$filename = "$u.$ext";
					$crop_in = self::getAvatarInFilePath($filename) . $filename;
					if (file_exists($crop_in)) {
						if (!unlink($crop_in)) {
							wfDebug("can't delete $crop_in\n");
						}
					}

					$filename = "tmp_$u.$ext";
					$crop_in2 = self::getAvatarInFilePath($filename) . $filename;
					if (file_exists($crop_in2)) {
						if (!unlink($crop_in2)) {
							wfDebug("can't delete $crop_in2\n");
						}
					}
				}
				$dbw = wfGetDB(DB_MASTER);
				$sql = "UPDATE avatar SET av_patrol=2, av_patrolledBy=" . $dbw->addQuotes($wgUser->getId()) . ", av_patrolledDate='" . wfTimestampNow() . "' WHERE av_user=" . $dbw->addQuotes($u);
				$res = $dbw->query($sql, __METHOD__);
				return "SUCCESS: files removed $crop_out and $crop_in";
			} else {
				wfDebug("FAILED: files exists could not be removed. $crop_out");
				return "FAILED: files exists could not be removed. $crop_out";
			}
		}

		// files don't have to exist if we use av_image
		$dbw = wfGetDB(DB_MASTER);
		$sql = "UPDATE avatar SET av_patrol=2, av_patrolledBy=" . $dbw->addQuotes($wgUser->getId()) . ", av_patrolledDate='" . wfTimestampNow() . "' WHERE av_user=" . $dbw->addQuotes($u);
		$res = $dbw->query($sql, __METHOD__);
		// Remove avatar url (av_image) for FB users
		$user = User::newFromID($u);
		if ($user && $user->isFacebookUser()) {
			$sql = "UPDATE avatar set av_image='' where av_user=" . $dbw->addQuotes($u);
			$res = $dbw->query($sql, __METHOD__);
			return "SUCCESS: Facebook avatar removed";
		}
		// Remove avatar url (av_image) for G+ users
		if ($user && $user->isGPlusUser()) {
			$sql = "UPDATE avatar set av_image='' where av_user=" . $dbw->addQuotes($u);
			$res = $dbw->query($sql, __METHOD__);
			return "SUCCESS: Google+ avatar removed";
		}

		return "FAILED: files do not exist. $crop_out";
	}

	function display() {
		global $wgOut, $wgTitle, $wgUser;

		if ($wgTitle->getNamespace() != NS_USER) {
			return $avatarDisplay;
		}

		$avatarDisplay .= "
	<script>jQuery.noConflict();</script>
	<script language='javascript' src='" . wfGetPad('/extensions/wikihow/common/cropper/lib/prototype.js?') . WH_SITEREV . "'></script>
	<script language='javascript' src='" . wfGetPad('/extensions/wikihow/common/cropper/lib/scriptaculous.js?load=builder,dragdrop&') . WH_SITEREV . "'></script>
	<script language='javascript' src='" . wfGetPad('/extensions/wikihow/common/cropper/cropper.js?') . WH_SITEREV . "'></script>
	<link rel='stylesheet' media='all' href='" . wfGetPad('/extensions/wikihow/common/cropper/cropper.css?') . WH_SITEREV . "' type='text/css' />";

		$imgPath = self::getAvatarInPath($wgUser->getID() . ".jpg");
		$avatarDisplay .= "
<script type='text/javascript'>
		var wgUserID = '".$wgUser->getID()."';
		var nonModal = false;
</script>

<div id='avatarModalPage'>
	<div class='avatarModalBackground' id='avatarModalBackground'></div>
	<div class='avatarModalContainer' id='avatarModalContainer'>
		<img height='10' width='679' src='" . wfGetPad('/skins/WikiHow/images/article_top.png') . "' alt=''/>
		<div class='avatarModalContent'>
		<div class='avatarModalTitle'><span style='float:right;'><a onclick=\"closeButton();\"><img src='" . wfGetPad('/extensions/wikihow/winpop_x.gif') . "' width='21' height='21' alt='close window' /></a></span>". wfMsg('avatar-instructions',$wgUser->getName())."</div>
		<div class='avatarModalBody'>
			<div id='avatarUpload'>
				<form name='avatarFileSelectForm' action='/Special:Avatar?type=upload' method='post' enctype='multipart/form-data' onsubmit=\"getNewPic(); return AIM.submit(this, {'onStart' : startCallback, 'onComplete' : completeCallback})\">
					File: <input type='file' id='uploadedfile' name='uploadedfile' size='40' /> <input type='submit' id='gatAvatarImageSubmit' value='SUBMIT' />
				</form>
				<div id='avatarResponse'></div><br />
			</div>

		<div id='avatarCrop' >
			<div id='avatarCropBorder' >
				<div id='avatarImgBlock' style='width: 490px;margin-left: 50px;'>
					<div id='avatarJS'>
						<img src='" . $imgPath . $wgUser->getID().".jpg' id='avatarIn' />
					</div>
					<div id='avatarPreview'>
					Cropped Preview:<br />
					<div id='avatarPreview2'>
					</div>
					</div>
				</div>

				<div style='clear: both;'> </div>
				</div>
				<div>".wfMsg('avatar-copyright-notice')."</div>
				<div id='cropSubmit' >
				<form name='crop' method='post' >
					<input type='button' value='Crop and Save' id='gatAvatarCropAndSave' onclick='ajaxCropit();' style='font-size:120%;'/>&nbsp;
					<a onclick=\"closeButton();\">Cancel</a>
					<!-- <a onclick=\"alert($('avatarPreview2').innerHTML);\">vutest</a> -->
					<input type='hidden' name='cropflag' value='false' />
					<input type='hidden' name='image' value='".$wgUser->getID().".jpg' />
					<input type='hidden' name='type' value='crop' />
					<input type='hidden' name='x1' id='x1' />
					<input type='hidden' name='y1' id='y1' />
					<input type='hidden' name='x2' id='x2' />
					<input type='hidden' name='y2' id='y2' />
					<input type='hidden' name='width' id='width' />
					<input type='hidden' name='height' id='height' />
				</form>
				</div>
				<div style='clear: both;'> </div>

			</div>
		</div>
		</div><!--end avatarModalContent-->
		<img width='679' src='" . wfGetPad('/skins/WikiHow/images/article_bottom_wh.png') . "' alt=''/>
	</div>
</div> ";

		return $avatarDisplay;
	}

	function displayNonModal() {
		global $wgOut, $wgTitle, $wgUser, $wgRequest;

		$imgname = '';
		$avatarReload = '';
		if ($wgRequest->getVal('reload')) {
			$imgname = "tmp_".$wgUser->getID().".jpg";
			$imgPath = self::getAvatarInPath($imgname);
			$avatarReload = "var avatarReload = true;";
		} else {
			$imgname = $wgUser->getID().".jpg";
			$imgPath = self::getAvatarInPath($imgname);
			$avatarReload = "var avatarReload = false;";
		}
		
		self::purgeS3($imgPath,$imgname);
		
		$avatarCrop = '';
		$avatarNew = "var avatarNew = false;";
		if ($wgRequest->getVal('new')) {
			$avatarCrop = "style='display:none;'";
			$avatarNew = "var avatarNew = true;";
		}

		$wgOut->addHTML("\n<!-- AVATAR CODE START -->\n<link rel='stylesheet' media='all' href='" . wfGetPad('/extensions/wikihow/avatar/avatar.css?') . WH_SITEREV . "' type='text/css' />\n");

		$wgOut->addHTML( "
	<script>jQuery.noConflict();</script>
	<script language='javascript' src='" . wfGetPad('/extensions/wikihow/common/cropper/lib/prototype.js?') . WH_SITEREV . "'></script>
	<script language='javascript' src='" . wfGetPad('/extensions/wikihow/common/cropper/lib/scriptaculous.js?load=builder,dragdrop&') . WH_SITEREV . "'></script>
	<script language='javascript' src='" . wfGetPad('/extensions/wikihow/common/cropper/cropper.js?') . WH_SITEREV . "'></script>
	<script type='text/javascript' src='".wfGetPad('/extensions/min/f/skins/common/jquery.md5.js?') . WH_SITEREV ."'></script>
	<script language='javascript' src='" . wfGetPad('/extensions/wikihow/avatar/avatar.js?') . WH_SITEREV . "'></script>
	<link rel='stylesheet' media='all' href='" . wfGetPad('/extensions/wikihow/common/cropper/cropper.css?') . WH_SITEREV . "' type='text/css' />


<script type='text/javascript'>
		var wgUserID = '".$wgUser->getID()."';
		var nonModal = true;
		var userpage = '".$wgUser->getUserPage()."';
		$avatarReload\n
		$avatarNew\n
</script>

	  <div class='avatarModalBody minor_section'>
	  <div>". wfMsg('avatar-instructions',$wgUser->getName())."</div>
		 <div id='avatarUpload' >
				<form name='avatarFileSelectForm' action='/Special:Avatar?type=upload&reload=1' method='post' enctype='multipart/form-data' >
					File: <input type='file' id='uploadedfile' name='uploadedfile' size='40' /> <input type='submit' id='gatAvatarImageSubmit' value='SUBMIT' class='button primary' />
				</form>
				<div id='avatarResponse'></div>
		 </div>

		 <div id='avatarCrop' $avatarCrop >
			<div id='avatarCropBorder' >
					<div id='avatarImgBlock'>
						<div id='avatarJS'>
							<img src='" . $imgPath . $imgname . "?" . rand() . "' id='avatarIn' />
						</div>
						<div id='avatarPreview'>
						Cropped Preview:<br />
						<div id='avatarPreview2'>
						</div>
						</div>
					</div>
				<div style='clear: both;'> </div>
				</div>

				<div>".wfMsg('avatar-copyright-notice')."</div>

				<div id='cropSubmit' >
				<form name='crop' method='post' >
					<a onclick=\"closeButton();\" class='button'>Cancel</a>
					<input type='button' class='button primary' value='Crop and Save' id='gatAvatarCropAndSave' onclick='ajaxCropit();' />
					<!-- <a onclick=\"alert($('avatarPreview2').innerHTML);\">vutest</a> -->
					<input type='hidden' name='cropflag' value='false' />
					<input type='hidden' name='image' value='".$imgname."' />
					<input type='hidden' name='type' value='crop' />
					<input type='hidden' name='x1' id='x1' />
					<input type='hidden' name='y1' id='y1' />
					<input type='hidden' name='x2' id='x2' />
					<input type='hidden' name='y2' id='y2' />
					<input type='hidden' name='width' id='width' />
					<input type='hidden' name='height' id='height' />
				</form>
				</div>

		 </div>
	  </div>
<script type='text/javascript'>
Event.observe(window, 'load', initNonModal);
</script>");

		$wgOut->addHTML("<!-- AVATAR CODE ENDS -->\n");
	}

	function purgePath($arr) {
		global $wgUseSquid, $wgServer;
		if ($wgUseSquid) {
			$urls = array();
			foreach ($arr as $elem) $urls[] = $wgServer . $elem;
			$u = new SquidUpdate($urls);
			$u->doUpdate();
			wfDebug("Avatar: Purging path of " . print_r($urls, true) . "\n");
		}
		return true;
	}
	
	//aggressive caching is causing bugs
	//remove the S3 image so people can change their avatars
	function purgeS3($img_path, $img_name) {
		if (IS_PROD_EN_SITE) {
			$img_name = trim($img_name);
			if (empty($img_name)) return;
		
			$img_path = preg_replace('@images/@','images_en/',$img_path);
		
			// //dump the S3 file so we're not cached			
			$svc = self::getS3Service();
			if ($svc->doesObjectExist(self::AWS_BUCKET, $img_path.$img_name)) {
				$svc->deleteObject(array(
					'Bucket' => self::AWS_BUCKET,
					'Key' => $img_path.$img_name
				));
			}
		}		
	}

	function crop() {
		global $wgUser, $wgOut, $wgTitle, $wgServer, $wgRequest, $wgImageMagickConvertCommand;

		$imagesize = 80;
		if ($wgRequest->getVal('cropflag') == 'false') {return false;}

		$image = $wgRequest->getVal('image');
		$x1 = $wgRequest->getVal('x1');
		$y1 = $wgRequest->getVal('y1');
		$x2 = $wgRequest->getVal('x2');
		$y2 = $wgRequest->getVal('y2');
		$width = $wgRequest->getVal('width');
		$height = $wgRequest->getVal('height');

		$crop_in = self::getAvatarInFilePath($image) . $image;
		$filename = $wgUser->getID() . ".jpg";
		$crop_in2 = self::getAvatarInFilePath($filename) . $filename;
		$crop_out = self::getAvatarOutFilePath($filename) . $filename;

		if ($crop_in != $crop_in2 && !copy($crop_in, $crop_in2)) {
			wfDebug("Avatar: failed copy $crop_in to $crop_in2\n");
		}

		$doit = "$wgImageMagickConvertCommand -crop {$width}x{$height}+$x1+$y1 $crop_in +repage -strip $crop_out";
		$result = system($doit, $ret);
		wfDebug("Avatar: ran command $doit got result $result and code $ret\n");
		if (!$ret) {
			if ($width > $imagesize) {
				$doit = "$wgImageMagickConvertCommand $crop_out -resize {$imagesize}x{$imagesize} $crop_out";
				$result = system($doit, $ret);
				wfDebug("Avatar: ran command $doit got result $result and code $ret\n");
			}
		} else {
			wfDebug("trace 2: $ret from: $doit");
			return false;
		}

		$paths = array(
			self::getAvatarOutPath($filename) . $filename,
			"/User:" . $wgUser->getName(),
		);
		self::purgePath($paths);
		self::purgeS3(self::getAvatarOutPath($filename),$filename);

		return true;
	}

	function execute($par) {
		global $wgUser, $wgOut, $wgTitle, $wgServer, $wgRequest, $wgImageMagickConvertCommand;
		$dbw = wfGetDB(DB_MASTER);

		$type = $wgRequest->getVal('type');
		if ($type == 'upload') {
			$wgOut->setArticleBodyOnly(true);

			//GET EXT
			$fileext = array('jpg','png','gif','jpeg');
			$f = basename( $_FILES['uploadedfile']['name']);
			$basename = "";
			$extensions = "";

			wfDebug("Avatar: Working with file $f\n");
			$pos = strrpos($f, '.');
			if ($pos === false) { // dot is not found in the filename
				$msg = "Invalid filename extension not recognized filename: $f\n";
				$response['status'] = 'ERROR';
				$response['msg'] = $msg;

				wfDebug("Avatar: Invalid extension no period $f\n");
				echo json_encode($response);
				return;
			} else {
				$basename = substr($f, 0, $pos);
				$extension = substr($f, $pos+1);
				if ( !in_array(strtolower($extension), array_map('strtolower', $fileext)) ) {
					$msg = "Invalid filename extension not recognized filename: $f\n";
					$response['status'] = 'ERROR';
					$response['msg'] = $msg;
					wfDebug("Avatar: $msg");
					echo json_encode($response);
					return;
				}
			}

			$filename = "tmp2_" . $wgUser->getID() . "." . strtolower($extension);
			$target_path = self::getAvatarInFilePath($filename) . $filename;
			$filename = "tmp_" . $wgUser->getID() . ".jpg";
			$target_path2 = self::getAvatarInFilePath($filename) . $filename;

			if (move_uploaded_file($_FILES['uploadedfile']['tmp_name'], $target_path)) {
				wfDebug("Avatar: Moved uploaded file from {$_FILES['uploadedfile']['tmp_name']} to {$target_path}\n");

				//converting filetype
				$count = 0;
				while ($count < 3) {
					$doit = "$wgImageMagickConvertCommand $target_path $target_path2";
					$result = system($doit, $ret);
					wfDebug("Avatar: Converting, $doit result $result code: $ret\n");

					if ($ret != 127) {
						break;
					} else {
						$count++;
					}
				}

				$ratio = 1;
				$maxw = 350;
				$maxh = 225;
				$size = getimagesize($target_path2);
				$width = $size[0];
				$height = $size[1];

				if ($width < $maxw && $height < $maxh) {
					$ratio = 1;
				} else {
					if ($maxh/$height > $maxw/$width) {
						$ratio = $maxw/$width;
					} else {
						$ratio = $maxh/$height;
					}
				}

				$msg = "The file ".  basename( $_FILES['uploadedfile']['name']).  " has been uploaded. ";
				if ($ratio != 1) {
					$newwidth = number_format(($width * $ratio), 0, '.', '');
					$newheight = number_format(($height * $ratio), 0, '.', '');
					$doit = "$wgImageMagickConvertCommand $target_path2 -resize {$newwidth}x{$newheight} $target_path2";
					$result = system($doit, $ret);
					wfDebug("Avatar: Converting, $doit result $result code: $ret\n");
				}
				if ($wgRequest->getVal('reload')) {
					wfDebug("Avatar: Got a reload, returning\n");
					header( 'Location: '.$wgServer.'/Special:Avatar?type=nonmodal&reload=1' ) ;
					return;
				}

				$response['status'] = 'SUCCESS';
				$response['msg'] = $msg;
				$response['basename'] = $basename;
				$response['extension'] = "jpg";
				wfDebug("Avatar: Success, " . print_r($response, true) . "\n");
				$res =  json_encode($response);
				echo $res;
				return;
			} else{
				if ($wgRequest->getVal('reload')) {
					header( 'Location: '.$wgServer.'/Special:Avatar?type=nonmodal' ) ;
					return;
				}
				wfDebug("Avatar: Unable to move uploaded file from {$_FILES['uploadedfile']['tmp_name']} to {$target_path}\n");
				$msg = "There was an error uploading the file, please try again!";
				$response['status'] = 'ERROR';
				$response['msg'] = $msg;
				echo json_encode($response);
				return;
			}
		} else if ($type == 'crop') {
			$wgOut->setArticleBodyOnly(true);
			if ($this->crop()) {

			$sql = "INSERT INTO avatar (av_user, av_patrol, av_dateAdded) " .
				"VALUES ('".$wgUser->getID()."',0,'".wfTimestampNow()."') " .
				"ON DUPLICATE KEY UPDATE av_patrol=0, av_dateAdded='".wfTimestampNow()."'";
			$ret = $dbw->query($sql, __METHOD__);
			wfRunHooks("AvatarUpdated", array($wgUser));

				$wgOut->addHTML('SUCCESS');
			} else {
				$wgOut->addHTML('FAILED');
			}
		} else if ($type == 'unlink') {
			$wgOut->setArticleBodyOnly(true);
			$ret = $this->removePicture();
			self::purgePath(array("/User:" . $wgUser->getName()));
			if (preg_match('/SUCCESS/',$ret)) {
				$wgOut->addHTML('SUCCESS:'.$ret);
			} else {
				$wgOut->addHTML('FAILED:'.$ret);
			}
		} else if ($type == 'accept') {
			$ret = $this->accept($wgRequest->getVal('uid'));
			if (preg_match('/SUCCESS/',$ret)) {
				$wgOut->addHTML('SUCCESS:'.$ret);
			} else {
				$wgOut->addHTML('FAILED:'.$ret);
			}
		} else if ($type == 'reject') {
			$ret = $this->reject($wgRequest->getVal('uid'), $wgRequest->getVal('r'), $wgRequest->getVal('m'));
			if (preg_match('/SUCCESS/',$ret)) {
				$wgOut->addHTML('SUCCESS:'.$ret);
			} else {
				$wgOut->addHTML('FAILED:'.$ret);
			}
		} else if ($type == 'nonmodal') {
			$this->displayNonModal();
		} else {
			//no longer want to show this page
			$wgOut->showErrorPage( 'nosuchspecialpage', 'nospecialpagetext' );
		}

	}

	static function getAvatarInPath($name) {
		global $wgUploadPath;
		// hash level is 2 deep
		$path = "$wgUploadPath/avatarIn/" . self::getHashPath($name);
		return $path;
	}

	static function getAvatarOutPath($name) {
		global $wgUploadPath;
		// hash level is 2 deep
		$path = "$wgUploadPath/avatarOut/" . self::getHashPath($name);
		return $path;
	}

	static function getAvatarInFilePath($name) {
		global $wgUploadDirectory;
		$path = "$wgUploadDirectory/avatarIn/" . self::getHashPath($name);
		return $path;
	}

	static function getAvatarOutFilePath($name) {
		global $wgUploadDirectory;
		// hash level is 2 deep
		$path = "$wgUploadDirectory/avatarOut/" . self::getHashPath($name);
		return $path;
	}

	static function getHashPath($name) {
		return FileRepo::getHashPathForLevel($name, 2);
	}

	static function insertAvatarIntoDiscussion($discussionText) {
		$text = "";
		$parts = preg_split('@(<p class="de_user".*</p>)@im', $discussionText, 0, PREG_SPLIT_DELIM_CAPTURE);
		for ($i = 0; $i < sizeof($parts); $i++) {
			if (preg_match('@(<p class="de_user".*</p>)@im', $parts[$i])) {
				$pos = strpos($parts[$i], 'href="/User:');
				$endpos = strpos($parts[$i], '"', $pos + 12);
				$username = substr($parts[$i], $pos + 12, $endpos - $pos - 12);

				$len = strlen('<p class="de_user">');
				$text .= substr($parts[$i], 0, $len) . "<img src='" . wfGetPad(Avatar::getAvatarURL($username)) . "' />" . substr($parts[$i], $len);
			}
			else {
				$text .= $parts[$i];
			}
		}
		return $text;
	}

	

	public static function getAws() {
		if (is_null(self::$aws)) {
			// Create a service builder using a configuration file
			self::$aws = Aws::factory(array(
				'key'    => WH_AWS_BACKUP_ACCESS_KEY,
				'secret' => WH_AWS_BACKUP_SECRET_KEY,
				'region' => 'us-east-1'
			));
		}
		return self::$aws;
	}
	
	public static function getS3Service() {
		$aws = self::getAws();
		return $aws->get('S3');
	}
}

