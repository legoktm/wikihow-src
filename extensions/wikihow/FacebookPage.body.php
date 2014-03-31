<?
class FacebookPage extends UnlistedSpecialPage {

	function __construct() {
		parent::__construct( 'FacebookPage' );
	}

	function getFacebookHTML($showimages = false) {
		global $wgParser, $wgServer;
	   	$feeds = FeaturedArticles::getFeaturedArticles(1);
		$html = "<fb:title>The How-to Article of the Day</fb:title>
				<fb:subtitle><a href='http://www.wikihow.com'>from wikiHow</a></fb:subtitle>
		";
		$now = time();
		$dbr = wfGetDB(DB_SLAVE);
		foreach( $feeds as $f ) {
			$url = $f[0];
			$d = $f[1];
			if ($d > $now) continue;
			$url = str_replace("http://www.wikihow.com/", "", $url); 
			$url = str_replace("$wgServer/", "", $url); 
			$title = Title::newFromURL(urldecode($url));
			// get last safe id
			$res = $dbr->select('revision', 
					array('rev_user', 'rev_id', 'rev_user_text'), 
					array('rev_page' => $title->getArticleId(), 'rev_user>0'),
					"wfGetFacebookHTML",
					array('ORDER BY' => 'rev_id desc')
				);
	
			$rev_id = 0;
			while ($row = $dbr->fetchObject($res)) {
				$num_edits = $dbr->selectField('revision', 'count(*)', array("rev_user={$row->rev_user}"));
				if ($num_edits > 300) {
					$rev_id = $row->rev_id;
					break;
				}
			}
			
			$dbr->freeResult($res);	
			$revision = null;
			if ($rev_id > 0) {
				$revision = Revision::newFromID($rev_id);	
			} else {
				$revision = Revision::newFromTitle($title);	
			}	
			$summary = Article::getSection($revision->getText(), 0);
			$summary = ereg_replace("\{\{.*\}\}", "", $summary);
		if (!$showimages)
			$summary = preg_replace("/\[\[Image[^\]]*\]\]/", "", $summary); // strip images
			$output = $wgParser->parse($summary, $title, new ParserOptions() );  
			$summary = strip_tags($output->getText(), '<img>');
	
		$img = "";
		$style = 'style="float:right;margin-left:10px;margin-bottom:10px;"'; 
		if (strpos($summary, "<img") !== false && $showimages) {
			$re = '/<img[^>]*>/';
			preg_match_all($re, $summary, $matches);
			$summary = preg_replace($re, '', $summary);
			$img = $matches[0][0];
			preg_match_all('/width="[0-9]*"/', $img, $matches);
			$width = 200;
			if (sizeof($matches[0]) > 0) {
				$s_width = str_replace('width=', '', $matches[0][0]);
				$s_width = str_replace('"', '', $s_width);
				$s_width = intval($s_width);
				if ($s_width < $width)
					$width = $s_width;
			}	
			$src = "";	
			preg_match_all('/src="[^"]*"/', $img, $matches);
			if (sizeof($matches[0]) > 0) {
				$src = str_replace("src=", "", $matches[0][0]);
				$src = str_replace('"', "", $src);
				if (strpos($src, "http://www.wikihow.com") === false)
					$src = "http://www.wikihow.com" . $src;	
			}
			$img = "<img src=\"{$src}\" $style width=\"{$width}\">";
		} else {
			$img = "<img src=\"http://www.wikihow.com/skins/WikiHow/wikiHow.gif\" $style width=\"100\"/>";
		}
			$html .= "<p style=\"font-size:1.2em;margin:2px 0;\"><a href='{$title->getFullURL()}' style=\"font-weight:bold\">" . wfMsg('howto', $title->getText()) . '</a></p>';
			$html .= "<p>
				$img 
				$summary
				</p>
				<p><a href='{$title->getFullURL()}'>Read more...</a></p>";
			$html .= '<table style="clear:both;margin:0 auto;"><tr><td>Do you want to do this? |&nbsp;
				</td><td >' .
				"<fb:share-button class='url' href='{$title->getFullURL()}'/> </td></tr></table>";
			break;
		}	
	
		$html .= '<fb:if-is-own-profile>&nbsp;<fb:else><br/><div style="text-align:right;"><a href="http://apps.facebook.com/howtooftheday">Put this on my profile</a></div></fb:else></fb:if-is-own-profile>';
		return $html;
	}
	
	function updateLocalSessionKeys($user, $session_key) {
		global $wgUser;
		$dbw = &wfGetDB(DB_SLAVE);
		$dbw->query( "INSERT INTO facebook_sessions VALUES ({$wgUser->getID()}, $user, '$session_key', now());");
	}

	function execute( $par ) {
		global $wgRequest, $wgSitename, $wgLanguageCode, $IP;
		global $wgDeferredUpdateList, $wgOut, $wgUser, $wgServer;
		global $wgParser;
	
		$wgOut->setArticleBodyOnly(true);
wfDebug("Before require\n");
		require_once("$IP/extensions/wikihow/common/facebook-platform/appinclude.php");
wfDebug("After require\n");
	
		$user		= isset($facebook->fb_params['user'])		? $facebook->fb_params['user'] : null;
		$session_key = isset($facebook->fb_params['session_key']) ? $facebook->fb_params['session_key'] : null;

		if ($user != "" && $session_key != "") 	
			$this->updateLocalSessionKeys($user, $session_key);
	
		$sk = $wgUser->getSkin();
		$target = isset($par) ? $par : $wgRequest->getVal( 'target' );
		if ($target == 'recordInvites') {
	
			$sendTo=array();
			$count = 0;
			foreach ($_POST as $key=>$value) {
				if (substr($key,0,7)=='invite_') {
					$toFbUid=substr($key,7,25);
					// insert list of users into invites with action invited
					//$fb->addToInviteList($facebook->user,$toFbUid,"invited");
					$sendTo[] = $toFbUid;
					$count++;
				}
				if ($count == 10) break;
			}
			
		  	$title = "Add the How to of the Day application!";
		  	$content = "<fb:req-choice url=\"http://apps.facebook.com/howtooftheday/\" label=\"Check out How to of the Day\" />Come check out the How to of the Day, it puts a new how-to article on your profile each day!";
		  	$request = "invitation";
			$image = 'http://www.wikihow.com/skins/WikiHow/wikiHow.gif';
		  	$confirmURL = $facebook->api_client->notifications_sendRequest($sendTo, $title, $content, $image, $request);
			if ($confirmURL == '')
				#$wgOut->addHTML("We are unable to send your invitations at this time. Perhaps you've already sent more than 10 invitations for this application?");	
				$facebook->redirect('http://apps.facebook.com/howtooftheday');
			else 
		  		$facebook->redirect($confirmURL.'&canvas');
			return;
		}
		if ($target == 'invite') {
				// use just signed up, set their profile HTML yo
				$html = $this->getFacebookHTML(true);
				$facebook->api_client->profile_setFBML($html,$user);
	
				// get all friends
				$allFriends=$facebook->api_client->friends_get($fbUid);
				// get list of those already invited by user
				//$invitedList=$this->getInviteList($fbUid);
				//$invitedArray=explode(",",$invitedList); // convert id list to array
				//$potentialFriends=array_diff($allFriends,$invitedArray); // allFriends - previously invitedFriends
				$potentialFriends = $allFriends;
				// get list of friends who have installed the application
				$preInstalled=$facebook->api_client->friends_getAppUsers($fbUid);
				// build block of friends using NewsClouda
				if (is_array($preInstalled))	
				$finalFriends=array_diff($potentialFriends,$preInstalled); // remaining potential list - existing app users
				else
				$finalFriends = $potentialFriends;
				$code='<fb:header icon="false" decoration="add_border">Invite Your Friends</fb:header>';
				if ($isRepeat)
					$code.='<p>Thanks for sharing wikiHow with your friends. You can either return to the <a href="http://apps.facebook.com/wikihow">wikiHow Application home page</a> or invite more friends below (Facebook allows you to invite ten at a time):</p>';
				else
					$code.='<p>Please help us spread the word. Share wikiHow with up to ten of your friends at a time. </p>';
				$code.='<form id="inviteFriends" name="inviteFriends" method="post" action="http://www.wikihow.com/Special:FacebookPage?target=recordInvites">';
				$friendList='';
				if (count($finalFriends)>0) {
					$cnt=0;
					foreach ($finalFriends as $uid) {
						$code.='<div style="float:left;margin:5px 10px 10px 5px;"><input style="float:left;" type="checkbox" name="invite_'.$uid.'" ><a href="http://www.facebook.com/profile.php?id='.$uid.'" style="float:left;margin:5px 0px 0px 5px;"><fb:profile-pic size="q" uid="'.$uid.'" style="width:40px;height:40px;"/></a><br clear="all" /><a href="http://www.facebook.com/profile.php?id='.$uid.'" style="text-align:right;"><fb:name uid='.$uid.' firstnameonly="true" style="font-size:80%;" /></a> </div>'; // title="'.$item['first_name'].'"
						$cnt+=1;
						$friendList.=','.$uid;
						if ($cnt > 0 && $cnt % 5 == 0) $code.='<br clear ="all" />';
							//if ($cnt==10) break;
					}
				}	   
				$friendList=trim($friendList,',');
				if ($friendList=='') return false; // no friends left to invite
				$code.='<input type="hidden" name="friendList" value="'.$friendList.'">';
				$code.='<br clear ="all" /><input name="submit" type="submit" value="Send invites" style="font-size:100%;font-weight:bold;"></form>';
				$code.='<br clear="all" />'; 
				$wgOut->addHTML( $code );
				return;
		}
		$html = $this->getFacebookHTML(true);
		//$facebook->api_client->profile_setFBML($html,$user);
		$wgOut->addHTML("<div style='margin:50px;'>$html</div>");
	}
}
