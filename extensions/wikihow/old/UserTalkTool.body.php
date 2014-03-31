<?php

class UserTalkTool extends UnlistedSpecialPage {

    function __construct() {
        parent::__construct( 'UserTalkTool' );
    }
	
    function execute ($par) {
		global $wgRequest, $wgUser, $wgOut, $wgLang, $wgServer;
 
		wfLoadExtensionMessages('UserTalkTool');		

		// CHECK FOR ADMIN STATUS
		if ( !in_array( 'sysop', $wgUser->getGroups() ) ) {
			$wgOut->setArticleRelated( false );
			$wgOut->setRobotpolicy( 'noindex,nofollow' );
			$wgOut->showErrorPage( 'nosuchspecialpage', 'nospecialpagetext' );
			return;
		}

		// MAKE SURE USER IS NOT BLOCKED
		if( $wgUser->isBlocked() ) {
			$wgOut->blockedPage();
			return;
		}
	
		// CHECK FOR TARGET
		$target = isset( $par ) ? $par : $wgRequest->getVal( 'target' );
		if ($target == null || $target == "") {
			$wgOut->addHTML('No target specified');
			return;
		}


		$dbw = wfGetDB(DB_MASTER);
		$dbr = wfGetDB(DB_SLAVE);
		$me  = Title::makeTitle(NS_SPECIAL, "UserTalkTool");

		// PROCESS FORM
		//
		//
		if ($wgRequest->wasPosted()) {

			$wgOut->setArticleBodyOnly(true);

			$utmsg = $wgRequest->getVal('utmessage');

			if ($utmsg != "") {
				#$t = Title::newFromID($aid);
				$ts = wfTimestampNow();
			
				$user = $wgUser->getName();
				$real_name = User::whoIsReal($wgUser->getID());
				if ($real_name == "") {
					$real_name = $user;
				}
				

				//User
				//
				//
				$utitem = $wgRequest->getVal('utuser');
				wfDebug("UTT: posting user: $utitem\n");
				wfDebug("UTT: by admin user: ". $wgUser->getID() ."\n");
				
				if ($utitem != "") {

					// POST USER TALK PAGE
					//
					//
					$text = "";
					$aid = "";
					$a = "";
					$formattedComment = "";

					$u = new User();
					$u->setName($utitem);
					$user_talk = $u->getTalkPage();

			      $dateStr = $wgLang->timeanddate(wfTimestampNow());

					$formattedComment = wfMsg('postcomment_formatted_comment', $dateStr, $user, $real_name, mysql_real_escape_string($utmsg));

					$aid = $user_talk->getArticleId();
					if ($aid > 0) {
						$r = Revision::newFromTitle($user_talk);
						$text = $r->getText();
					} 
					$a = new Article($user_talk);
					$text .= "\n\n$formattedComment\n\n";

     				if ($aid > 0) {
						$a->updateArticle($text, "", true, false, false, '', true);
					} else {
						$a->insertNewArticle($text, "", true, false, true, false, false);
					}

					// MARK CHANGES PATROLLED
					//
					//
					$res = $dbr->select('recentchanges', 'max(rc_id) as rc_id', array('rc_title=\''.mysql_real_escape_string($utitem).'\'', 'rc_user='.$wgUser->getID() ,'rc_cur_id=' . $aid, 'rc_patrolled=0'));

  			      while ($row = $dbr->fetchObject($res)) {
								wfDebug("UTT: mark patroll rcid: ".$row->rc_id ." \n");
								RecentChange::markPatrolled( $row->rc_id );
								PatrolLog::record( $row->rc_id, false );
					}
					$dbr->freeResult($res);

					wfDebug("UTT: done\n");
					wfDebug("UTT: Completed posting for [".$utitem."]\n");
					$wgOut->addHTML( "Completed posting for - ".$utitem);

				} else {
					wfDebug("UTT: No user\n");
					$wgOut->addHTML( "UT_MSG ERROR: No user specified. \n");

				}
			} else {
				wfDebug("UTT: No message to post\n");
				$wgOut->addHTML( "UT_MSG ERROR: No message to post for - ".$utitem."\n");
				return;
			}
			$wgOut->redirect('');
		} else {

			$sk = $wgUser->getSkin();
			$wgOut->addHTML('
<script language="javascript" src="/extensions/wikihow/common/prototype1.8.2/prototype.js"></script>
<script language="javascript" src="/extensions/wikihow/common/prototype1.8.2/effects.js"></script>
<script language="javascript" src="/extensions/wikihow/common/prototype1.8.2/controls.js"></script>
		' . "\n");


			$wgOut->addHTML ( "
			<script type='text/javascript'>
				function utSend () {

					$('formdiv').style.display = 'none';
					$('resultdiv').innerHTML = 'Sending...<br />';

					liArray = document.getElementById('ut_ol').childNodes;
					i=0;
					while(liArray[i]){
						if (document.getElementById(liArray[i].id)) {
							if (liArray[i].getAttribute('id').match(/^ut_li_/)) {

								document.forms['utForm'].utuser.value = liArray[i].getAttribute('id').replace('ut_li_','');
								$('utForm').request({
									asynchronous: false,
									onComplete: function(transport) {
										$('resultdiv').innerHTML += transport.responseText+'<br />';
										if (transport.responseText.match(/Completed posting for - /)){
							
											var u = transport.responseText.replace(/Completed posting for - /,'');
											//$('resultdiv').innerHTML += 'UID: '+u+'<br />';
											$('ut_li_'+u).innerHTML +=  '  <img src=\"/skins/WikiHow/light_green_check.png\" height=\"15\" width=\"15\" />';
										}
									},
									onFailure: function(transport) {
										$('resultdiv').innerHTML += 'Sending returned error for '+liArray[i].id+' <br />';
									}
								});

								//$('resultdiv').innerHTML += 'Sending '+liArray[i].id+'<br />';
						 	}
						}
						i++;

					}
					
				return false;
				}
			</script>
			
			");


			// GET LIST OF RECIPIENTS
			//
			//
			if ($target) {
				$t = Title::newFromUrl($target );
				if ($t->getArticleId() <= 0) {
					$wgOut->addHTML("Target not a valid article.");
					return;
				} else {
					$r = Revision::newFromTitle($t);
					$text = $r->getText();
					#$wgOut->addHTML( $text );

					$utcount = preg_match_all('/\[\[User_talk:(.*?)[#\]\|]/',$text,$matches);
					#print_r($matches);
					$utlist = $matches[1];

				}
			}

			// DISPLAY COUNT OF USER TALK PAGES FOUND
			//
			//
			if (count($utlist) == 0) {
				$wgOut->addHTML(wfMsg('notalkpagesfound'));
				return;
			} else {
				$wgOut->addHTML(count($utlist) .' '.  wfMsg('talkpagesfound')."<br />");
			}
			// TEXTAREA and FORM
			//
			//
			$wgOut->addHTML('
<form id="utForm" method="post">
				');

			// DISPLAY LIST OF USER TALK PAGES
			//
			//
			$wgOut->addHTML('<div id="utlist" style="border: 1px grey solid;margin: 15px 0px 15px 0px;padding: 15px;height:215px;overflow:auto"><ol id="ut_ol">' . "\n");
			foreach ($utlist as $utitem) {
				$wgOut->addHTML('<li id="ut_li_'.preg_replace('/\s/m', '-', $utitem).'"><a href="/User_talk:' . $utitem . '">' . $utitem . '</a></li>' . "\n");
			}
			$wgOut->addHTML('</ol></div>' . "\n");


			// TEXTAREA and FORM
			//
			//
			$wgOut->addHTML('
<div id="formdiv">
'. wfMsg('sendbox') .'<br />
<textarea id="utmessage" name="utmessage" rows="6" style="margin: 5px 0px 5px 0px;"></textarea>
<input id="utuser" type="hidden" name="utuser" value="">

<input tabindex="4" type="button" value="Send" cl1ass="btn" id="postcommentbutton" style="font-size: 110%; font-weight:bold" onclick="utSend(); return false;" />


</form>
</div>
<div id="resultdiv"></div>' . "\n"); 


		}
	}
}
	

