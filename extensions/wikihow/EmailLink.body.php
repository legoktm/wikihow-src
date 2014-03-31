<?php

class EmailLink extends SpecialPage {

	var $stylelink = "/extensions/wikihow/common/jquery-ui-themes/jquery-ui.css";

	function __construct() {
		parent::__construct( 'EmailLink' );
	}

	function reject() {
		global $wgOut, $wgUser;
		$dbw = wfGetDB(DB_MASTER);
		$dbw->insert('rejected_email_links',
			array(
				'rel_text' => "REJECTED\nuserid: " . $wgUser->getID() . "\n"
				. wfReportTime() . "\nReferer:" . $_SERVER["HTTP_REFERER"] . "\n"
				. wfGetIP() . "\n" . print_r($_POST, true)
			),
			__METHOD__);
		//be coy
		$this->thanks();
	}

	function thanks() {
		global $wgOut, $wgRequest;
		$wgOut->addHTML("<link type='text/css' rel='stylesheet' href='".wfGetPad('/extensions/wikihow/common/jquery-ui-themes/jquery-ui.css?rev='. WH_SITEREV)."' />");
		$wgOut->addHTML("<br/><br/>".wfMsg('thank-you-sending-article')."<br/><br/>");
		if (!$wgRequest->getVal('fromajax')) {
			$wgOut->returnToMain( false );
		}
		return;
	}

	function getToken1() {
		global $wgRequest, $wgUser;
		$target  = urldecode($wgRequest->getVal('target'));
		//$s = $wgUser->getID() . $_SERVER['HTTP_X_FORWARDED_FOR'] . $_SERVER['REMOTE_ADDR'] . $target  . date ("YmdH");
		$s = $wgUser->getID() .  wfGetIP() . $target  . date ("YmdH");
		return md5($s);
	}

	function getToken2() {
		global $wgRequest, $wgUser;
		$target  = urldecode($wgRequest->getVal('target'));
		//$s = $wgUser->getID() . $_SERVER['HTTP_X_FORWARDED_FOR'] . $_SERVER['REMOTE_ADDR'] . $target . date ("YmdH", time() - 40 * 40);
		$s = $wgUser->getID() . wfGetIP() . $target . date ("YmdH", time() - 40 * 40);
		return md5($s);
	}

	function execute($par) {
		global $wgUser, $wgOut, $wgLang, $wgTitle, $wgMemc, $wgDBname, $wgScriptPath;
		global $wgRequest, $wgSitename, $wgLanguageCode;
		global $wgScript;
		$fname = "wfSpecialEmailLink";

		if ($wgRequest->getVal('fromajax')) {
			$wgOut->setArticleBodyOnly(true);
		}

		$this->setHeaders();
		$me = Title::makeTitle(NS_SPECIAL, "EmailLink");
		$action = $me->getFullURL();

		$fc = new FancyCaptcha();
		$pass_captcha = true;

		$name = $from = $r1 = $r2 = $r3 = $m = "";
		if ($wgRequest->wasPosted())  {
			$pass_captcha 	= $fc->passCaptcha();
			$email 			= $wgRequest->getVal("email");
			$name 			= $wgRequest->getVal("name");
			$recipient1 	= $wgRequest->getVal('recipient1');
			$recipient2 	= $wgRequest->getVal('recipient2');
			$recipient3 	= $wgRequest->getVal('recipient3');
			if(preg_match("@kittens683\@aol.com@",$recipient1) || preg_match("@kittens683\@aol.com@",$recipient2) || preg_match("@kittens683\@aol.com@",$recipient3)) {
				return;
			}
			$message 		= $wgRequest->getVal('message');
		}

		if (!$wgRequest->wasPosted() || !$pass_captcha) {
			if( $wgUser->getID() > 0 && !$wgUser->canSendEmail() ) {
				$userEmail = $wgUser->getEmail();
				// If there is no verification time stamp and no email on record, show initial message to have a user input a valid email address
				if ( empty($userEmail) ) {
					wfDebug( "User can't send.\n" );
					$wgOut->showErrorPage( "mailnologin", "mailnologintext" );
				} else {	// When user does have an email on record, but has not verified it yet
					wfDebug( "User can't send without verification.\n" );
					$wgOut->showErrorPage( "mailnologin", "mailnotverified" );
				}
				$wgOut->addHTML("<link type='text/css' rel='stylesheet' href='".wfGetPad($this->stylelink.'?rev='. WH_SITEREV)."' />");
				return;

			}


			$titleKey = isset( $par ) ? $par : $wgRequest->getVal( 'target' );

			if ($titleKey == "") {
				$wgOut->addHTML ("<br/></br><font color=red>".wfMsg('error-no-title')."</font>");
				return;
			}

			$titleObj = Title::newFromURL($titleKey);
			if (!$titleObj) $titleObj = Title::newFromURL(urldecode($titleKey));
			if (!$titleObj || $titleObj->getArticleID() < 0) {
				$wgOut->addHTML ("<br/></br><font color=red>".wfMsg('error-article-not-found')."</font>");
				return;
			} else {
				$titleKey = $titleObj->getDBKey();
			}

			$articleObj = new Article($titleObj);
			$subject = $titleObj->getText();
			$titleText = $titleObj->getText();
			if (WikihowArticleEditor::articleIsWikiHow($articleObj)) {
				$subject = wfMsg('howto', $subject);
				$titleText = wfMsg('howto',$titleText);
			}
			$subject = wfMsg('wikihow-article-subject',$subject);
			if ($titleObj->getText() == wfMsg('mainpage'))
				$subject = wfMsg('wikihow-article-subject-main-page');

			// add the form HTML
			$article_title = wfMsg('article').":";
			if ($titleObj->getNamespace() == NS_ARTICLE_REQUEST) {
				$wgOut->addHTML ( "<br/><br/>".wfMsg('know-someone-answer-topic-request') );
				$article_title = wfMsg('topic-requested').":";
			}

			if ($titleObj->getNamespace() != NS_MAIN
				&& $titleObj->getNamespace() != NS_ARTICLE_REQUEST
				&& $titleObj->getNamespace() != NS_PROJECT)
			{
				$wgOut->showErrorPage('emaillink', 'emaillink_invalidpage');
				$wgOut->addHTML("<link type='text/css' rel='stylesheet' href='".wfGetPad($this->stylelink.'?rev='. WH_SITEREV)."' />");
				return;
			}

			if ($titleObj->getText() == "Books For Africa") {
				$message = wfMsg('friend-sends-article-email-africa-body');
			}

			$titleKey = urlencode($titleKey);
			$token = $this->getToken1();
			$wgOut->addHTML ( "
<link type='text/css' rel='stylesheet' href='".wfGetPad($this->stylelink.'?rev='.WH_SITEREV)."' />
<form id=\"emaillink\" method=\"post\" action=\"{$action}\">
<input type=\"hidden\" name=\"target\" value=\"$titleKey\">
<input type=\"hidden\" name=\"token\" value=\"$token\">
<table border=\"0\">
<tr>
<td valign=\"top\" colspan=\"1\" class='mw-label'>$article_title</td>
<td valign=\"top\" colspan=\"2\">$titleText</td>
</tr>
");
			if ($wgUser->getID() <= 0) {
				$wgOut->addHTML("
<tr>
<td valign=\"top\" colspan=\"1\" class='mw-label'>".wfMsg('your-name').":</td>
<td valign=\"top\" colspan=\"2\"><input type=text size=\"40\" name=\"name\" value=\"{$name}\" class='input_med'></td>
</tr>
<tr>
<td valign=\"top\" colspan=\"1\" class='mw-label'>".wfMsg('your-email').":</td>
<td valign=\"top\" colspan=\"2\"><input type=text size=\"40\" name=\"email\" value=\"{$email}\" class='input_med'></td>
</tr>");

			}
			$wgOut->addHTML("
<tr>
<td valign=\"top\" width=\"300px\" colspan=\"1\" rowspan='3' class='mw-label'>".wfMsg('recipient-emails').":</td>
<td valign=\"top\" colspan=\"2\"><input type=text size=\"40\" name=\"recipient1\" value=\"{$recipient1}\" class='input_med'></td>
</tr>
<tr>
<td valign=\"top\" colspan=\"2\"><input type=text size=\"40\" name=\"recipient2\" value=\"{$recipient2}\" class='input_med'></td>
</tr>
<tr>
<td valign=\"top\" colspan=\"2\"><input type=text size=\"40\" name=\"recipient3\" value=\"{$recipient3}\" class='input_med'></td>
</tr>
<!--<tr>
<td valign=\"top\" colspan=\"1\">".wfMsg('emailsubject').":</td>
<td valign=\"top\" colspan=\"2\"><input type=text size=\"40\" name=\"subject\" value=\"$subject\" class='input_med'></td>
</tr>-->
<tr>
<td colspan=\"1\" valign=\"top\" class='mw-label'>".wfMsg('emailmessage').":</td>
<td colspan=\"2\"><TEXTAREA rows=\"5\" cols=\"55\" name=\"message\">{$message}</TEXTAREA></td>
</tr>
<tr>
<TD>&nbsp;</TD>
<TD colspan=\"2\"><br/>
"  . wfMsgWikiHTML('emaillink_captcha') . "
"  . ($pass_captcha ? "" : "<br><br/><font color='red'>Sorry, that phrase was incorrect, try again.</font><br/><br/>") . "
" . $fc->getForm('') . "
</TD>
</tr>
<tr>
<TD>&nbsp;</TD>
<TD colspan=\"2\"><br/>
<input type='submit' name=\"wpEmaiLinkSubmit\" value=\"".wfMsg('submit')."\" class=\"button primary\" />
</td>
</tr>
<tr>
<TD colspan=\"3\">
<br/><br/>
".wfMsg('share-message-three-friends')."
</TD>
</TR>

");

			// do this if the user isn't logged in
			$wgOut->addHTML("</table> </form>");
		} else {

			if ( $wgUser->pingLimiter('emailfriend') ) {
				$wgOut->rateLimited();
				wfProfileOut( "$fname-checks" );
				wfProfileOut( $fname );
				return false;
			}

			$usertoken = $wgRequest->getVal('token');
			$token1 = $this->getToken1();
			$token2 = $this->getToken2();
			if ($usertoken != $token1 && $usertoken != $token2) {
				$this->reject();
				echo "token $usertoken $token1 $token2\n";
				exit;
				return;
			}

			// check referrer
			$good_referer = Title::makeTitle(NS_SPECIAL, "EmailLink")->getFullURL();
			$referer = $_SERVER["HTTP_REFERER"] ;
			if (strpos($refer, $good_referer) != 0) {
				$this->reject();
				echo "referrer bad\n";
				exit;
			}

			// this is a post, accept the POST data and create the Request article
			$recipient1 = $_POST['recipient1'];
			$recipient2 = $_POST['recipient2'];
			$recipient3 = $_POST['recipient3'];
			$titleKey = $_POST['target'];
			$message = $_POST['message'];

			if ($titleKey == "Books-For-Africa")
				$titleKey = "wikiHow:" . $titleKey;

			$titleKey = urldecode($titleKey);
			$titleObj = Title::newFromDBKey($titleKey);

			if ($titleObj->getArticleID() <= 0) {
				$this->reject();
				echo "no article id\n";
				exit;
			}
			$dbkey = $titleObj->getDBKey();

			$articleObj = new Article($titleObj);
			$subject = $titleObj->getText();
			$how_to = $subject;
			if (WikihowArticleEditor::articleIsWikiHow($articleObj)) {
				$subject = wfMsg("howto", $subject);
			}
			$how_to = $subject;
			if ($titleObj->getNamespace() == NS_ARTICLE_REQUEST) {
				$subject = wfMsg('subject-requested-howto').": ".wfMsg("howto", $subject);
			} else if ($titleObj->getNamespace() == NS_PROJECT) {
				$subject = wfMsg('friend-sends-article-email-africa-subject');
			} else {
				$subject = wfMsg('wikihow-article-subject',$subject);
			}
			if ($titleObj->getNamespace() != NS_MAIN
				&& $titleObj->getNamespace() != NS_ARTICLE_REQUEST
				&& $titleObj->getNamespace() != NS_PROJECT)
			{
				$wgOut->showErrorPage('emaillink', 'emaillink_invalidpage');
				$wgOut->addHTML("<link type='text/css' rel='stylesheet' href='".wfGetPad($this->stylelink.'?rev='. WH_SITEREV)."' />");
				return;
			}

			// for the body of the email
			$titleText = $titleObj->getText();
			if ($titleText != wfMsg('mainpage')) {
				$summary = Article::getSection($articleObj->getContent(true), 0);
				// trip out all MW and HTML tags
				$summary = ereg_replace("<.*>", "", $summary);
				$summary = ereg_replace("\[\[.*\]\]", "", $summary);
				$summary = ereg_replace("\{\{.*\}\}", "", $summary);
			}
			$url = $titleObj->getFullURL();

			$from_name = "";
			$validEmail = "";
			if ($wgUser->getID() > 0) {
				$from_name = $wgUser->getName();
				$real_name = $wgUser->getRealName();
				if ($real_name != "") {
					$from_name = $real_name;
				}
				$email = $wgUser->getEmail();
				if ($email != "") {
					$validEmail = $email;
					$from_name .= "<$email>";
				} else {
					$from_name .= "<do_not_reply@wikihow.com>";
				}
			} else {
				$email = $wgRequest->getVal("email");
				$name = $wgRequest->getVal("name");
				if ($email == "") {
					$email = "do_not_reply@wikihow.com";
				} else {
					$validEmail = $email;
				}

				$from_name = "$name <$email>";
			}

			if (strpos($email, "\n") !== false
				|| strpos($recipient1, "\n") !== false
				|| strpos($recipient2, "\n") !== false
				|| strpos($recipient3, "\n") !== false
				|| strpos($title, "\n") !== false) {
				echo "reciep\n";
				exit;
				$this->reject();
				return;
			}
			$r_array = array();
			$num_recipients = 0;
			if ($recipient1 != "") {
				$num_recipients++;
				$x = split(";", $recipient1);
				$r_array[] = $x[0];
			}
			if ($recipient2 != "") {
				$num_recipients++;
				$x = split(";", $recipient2);
				$r_array[] = $x[0];
			}
			if ($recipient3 != "") {
				$num_recipients++;
				$x = split(";", $recipient3);
				$r_array[] = $x[0];
			}

			if ($titleObj->getNamespace() == NS_PROJECT) {
				$r_array[] = 'elizabethwikihowtest@gmail.com';
			}

			if ($validEmail != "" && !in_array($validEmail, $r_array)) {
				$num_recipients++;
				$r_array[] = $validEmail;
			}

			if ($titleObj->getNamespace() == NS_ARTICLE_REQUEST) {
				$body = "$message

----------------

	".wfMsg('article-request-email',
			$how_to,
			"http://www.wikihow.com/index.php?title2=$dbkey&action=easy&requested=$dbkey",
			"http://www.wikihow.com/Request:$dbkey",
			"http://www.wikihow.com/".wfMsg('writers-guide-url'),
			"http://www.wikihow.com/".wfMsg('about-wikihow-url')."") ;
			} else if ($titleObj->getText() == wfMsg('mainpage')) {
				$body = "$message

----------------

	".wfMsg('friend-sends-article-email-main-page')."

	";
			}else if ($titleObj->getNamespace() == NS_PROJECT) {
				$body = "$message";
			} else {
				$body = "$message

----------------

".wfMsg('friend-sends-article-email',$how_to, $summary, $url)."

	";
			}

			$from = new MailAddress($email);
			foreach ($r_array as $address) {
				$address = preg_replace("@,.*@", "", $address);
				$to = new MailAddress($address);
				$sbody = $body;
				if ($address == $validEmail) {
					$sbody = wfMsg('copy-email-from-yourself') . "\n\n" . $sbody;
				}
                if (!UserMailer::send($to, $from, $subject, $sbody, false)) {
                        //echo "got an en error\n";
                };

			}
			EmailLink::addLinksEmailed($num_recipients);
			$this->thanks();
		}
	}

	
	//used to be in SiteStats.php but pulled out here
	//because it's the only place we use it and fewer tweaks
	//to core code is better
    function addLinksEmailed($num) {
    	$dbw =& wfGetDB( DB_MASTER );
        $sql = "UPDATE site_stats SET ss_links_emailed = ss_links_emailed + $num";
       	$dbw->query( $sql );
 	}
}

