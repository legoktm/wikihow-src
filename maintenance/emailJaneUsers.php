<?php

/******
 * 
 * This script checks the Jane db each night
 * and grabs a list of users who complete the process
 * and sign up for a new account. These users
 * are emailed to 
 * 
 *****/


require_once('commandLine.inc');

$yesterdayStartUnix = strtotime('midnight yesterday');
$yesterdayEndUnix = strtotime('midnight today');

$yesterdayStart = wfTimestamp(TS_MW, $yesterdayStartUnix);
$yesterdayEnd = wfTimestamp(TS_MW, $yesterdayEndUnix);

$yesterday = date('n/j/y', $yesterdayStartUnix);

$dbr = wfGetDB(DB_SLAVE);

$users = $dbr->select('startertool', array('st_user', 'st_action'), array("st_action = 'signup' OR st_action = 'signup_top'", "st_date >= {$yesterdayStart}", "st_date <= {$yesterdayEnd}"), __FUNCTION__);

$totalNewUsers = $dbr->numRows($users);

$emailBody .= "<h3>New Jane Users " . $yesterday . ":</h3>\n";

if($totalNewUsers == 0) {
	$emailBody .= "No new users yesterday. :(<br /><br />";
}
else{

	
	$emailBody .= "<ol>";
	foreach($users as $userObject) {
		$user = User::newFromId($userObject->st_user);
		$emailBody .= "<li><a href='" . $user->getUserPage()->getFullURL() . "'>" . $user->getName() . "</a></li>\n" ;
	}
	$emailBody .= "</ol><br /><br />";
}

$emailBody .= StarterToolAdmin::getUserData($dbr, $yesterdayStart, $yesterdayEnd);

$to = new MailAddress("bebeth@wikihow.com, krystle@wikihow.com, elizabeth@wikihow.com, jack@wikihow.com");
$from = new MailAddress("bebeth@wikihow.com");
$subject = "New Jane Users " . $yesterday;
$content_type = "text/html; charset={$wgOutputEncoding}";

UserMailer::send($to, $from, $subject, $emailBody, null, $content_type);