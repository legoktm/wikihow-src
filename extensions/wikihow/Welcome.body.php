<?php

class Welcome extends UnlistedSpecialPage {

	function __construct() {
		parent::__construct('Welcome');
	}

	function sendWelcome() {
		global $wgUser;
		return self::sendWelcomeUser($wgUser);
	}

	function sendWelcomeUser($user) {
		global $wgServer, $wgOutputEncoding;

		if ($user->getID() == 0) {
			wfDebug("Welcome email:User must be logged in.\n");
			return true;
		}

		if ($user->getOption('disablemarketingemail') == '1' ) {
			wfDebug("Welcome email: Marketing preference not selected.\n");
			return true;
		}

		if ($user->getEmail() == "") {
			wfDebug("Welcome email: No email address found.\n");
			return true;
		}

		$subject = wfMsg('welcome-email-subject');

		$from_name = "";
		$validEmail = "";
		$from_name = wfMsg('welcome-email-fromname');

		$to_name = $user->getName();
		$to_real_name = $user->getRealName();
		if ($to_real_name != "") {
			$to_name = $real_name;
		}
		$username = $to_name;
		$email = $user->getEmail();

		$validEmail = $email;
		$to_name .= " <$email>";

		//server,username,talkpage,username
		$body = wfMsg('welcome-email-body',
			$wgServer, $username,
			$wgServer .'/'. preg_replace('/ /','-',$user->getTalkPage()),
			$user->getName() );

		$from = new MailAddress($from_name);
		$to = new MailAddress($to_name);
		$content_type = "text/html; charset={$wgOutputEncoding}";
		if (!UserMailer::send($to, $from, $subject, $body, false, $content_type)) {
			wfDebug( "Welcome email: got an en error while sending.\n");
		};

		return true;

	}

	function execute($par) {
		global $wgUser, $wgRequest, $wgOut, $wgServer;
		wfLoadExtensionMessages('Welcome');
		$fname = 'Welcome';

		$wgOut->setArticleBodyOnly(true);

		$username = $wgRequest->getVal('u', null);

		if ($username != '') {
			$u = new User();
			$u->setName($username);
		} else {
			echo 'Sorry invalid request.<br />';
			return;
		}

		//server,username,talkpage,username
		$body = wfMsg('welcome-email-body',
			$wgServer, $username,
			$wgServer .'/'. preg_replace('/ /','-',$u->getTalkPage()),
			$username );

		echo $body;
	}
}

