<?
	require_once('commandLine.inc');

	function sendComeBackMail($user, $batch) {
		$body = wfMsg('Come-on-back-email' . $batch, $wgServer, $user->getName(), $wgServer .'/'. preg_replace('/ /','-',$user->getTalkPage()), $user->getName()  );
		$from = new MailAddress("wiki@wikihow.com"); 
		$to = new MailAddress($user->getEmail());
		$content_type = "text/html; charset={$wgOutputEncoding}";
		$subject = "Can you help out with a few things on wikiHow?";
		if ($batch == 3) {
			$subject = "Can you try out wikiHow’s new and improved Quality Guardian tool?";
		}
		UserMailer::send($to, $from, $subject, $body, false, $content_type);
	}

	function sendComeBackTalkPageMessage($user, $batch) {
		global $wgLang;
		$dateStr = $wgLang->timeanddate(wfTimestampNow());
		$talkpage = $user->getTalkPage();
		$r = Revision::newFromTitle($talkpage);
		$wgUser = User::newFromName("Krystle");
		$text = "";
		if ($r) {
			$text = $r->getText(); 
		}
		$text .= '<div class="de">
		<div class="de_header">
		<p class="de_date">On ' . $dateStr . '</p>
		<p class="de_user">[[User:Krystle|Krstyle]] said:</p>
		</div>
		<div class="de_comment">' . wfMsg('Come-on-back-message' . $batch) . '
		</div>
		<div class="de_reply">[[User_talk:Krystle#post|Reply to Krystle]] </div></div>';
		$a = new Article($talkpage);
		$a->doEdit($text, "Reaching out to user");
	}

	// oops
	$lines = split("\n", file_get_contents("again.txt"));
	foreach ($lines as $line) {
		if ($line == "") {
			continue;
		}
		$t = split("\t", $line);
		$u = User::newFromName($t[1]);
		echo "sending {$t[0]} to {$u->getName()}\n";
		sendComeBackMail($u, $t[0]);
	}
	exit;

	$cutoff1 = wfTimestamp(TS_MW, time() - $argv[0] * 24 * 3600 * 30);
	$cutoff2 = wfTimestamp(TS_MW, time() - $argv[1] * 24 * 3600 * 30);

	$users = array(); 
	$dbr = wfGetDB(DB_SLAVE);
	$res = $dbr->select('user', array('user_id', 'user_email', 'user_name', 'user_editcount'),  
		array("user_registration > '{$cutoff1}'", "user_registration < '{$cutoff2}'", 'user_editcount > 5')
		);
	while ($row = $dbr->fetchObject($res)) {

		// did this user make an edit since the cutoff?
		$edits = $dbr->selectField(array('page', 'revision'), array('count(*)'), 
				array('page_id=rev_page', 
				'rev_user'=>$row->user_id, 'page_namespace'=>NS_MAIN, "rev_timestamp>'{$cutoff2}'" ));
		
		if ($edits > 0 && $row->user_email) {
			$users[$row->user_id] = $row->user_email;
		}
	}

	echo "got " . sizeof($users) . "\n";
	$newusers = array();
	foreach ($users as $id=>$email) {
		$u = User::newFromID($id);
		if (!$u) {
			echo "can'tmake user from id $id\n";
			continue;
		}
		if ($u->getOption( 'disablemarketingemail', 0) != 1) {
			$newusers [] = $u;
		}
	}
	echo "got " . sizeof($newusers) . "\n";

	shuffle($newusers); 

	$size = sizeof($newusers); 

	$control = array_splice($newusers, 0, $size / 2);


	$batch1 = array_splice($newusers, 0, $size / 12);	
	$batch2 = array_splice($newusers, 0, $size / 12);	
	$batch3 = array_splice($newusers, 0, $size / 12);	
	$batch4 = array_splice($newusers, 0, $size / 12);	
	$batch5 = array_splice($newusers, 0, $size / 12);	
	$batch6 = $newusers;
	
	foreach ($control as $user) {
		echo "sending nothing to {$user->getName()}\n";
	}
	foreach ($batch1 as $user) {
		sendComeBackMail($user, 1);
		echo "sending email #1 to {$user->getName()}\n";
	}
	foreach ($batch2 as $user) {
		sendComeBackMail($user, 2);
		echo "sending email #2 to {$user->getName()}\n";
	}
	foreach ($batch3 as $user) {
		sendComeBackMail($user, 3);
		echo "sending email #3 to {$user->getName()}\n";
	}
	foreach ($batch4 as $user) {
		sendComeBackTalkPageMessage($user, 1);
		echo "sending talk page message #1 to {$user->getName()}\n";
	}
	foreach ($batch5 as $user) {
		sendComeBackTalkPageMessage($user, 2);
		echo "sending talk page message #2 to {$user->getName()}\n";
	}
	foreach ($batch6 as $user) {
		sendComeBackTalkPageMessage($user, 3);
		echo "sending talk page message #3 to {$user->getName()}\n";
	}

