<?php
define('WH_USE_BACKUP_DB', true);
require_once( 'commandLine.inc' );

global $wgSharedDB;

$EXPERIMENT_NAME = 'after_rc_notify';
$dbr = wfGetDB(DB_SLAVE);
$sql = "select hcu_user, u.user_email, rc_title, pu.user_name, pu.user_id, rev.rev_id from recentchanges join hydra_cohort_user on hcu_user=rc_user join ".$wgSharedDB.".user u on u.user_id=hcu_user join logging on log_timestamp > date_sub(now(), interval 3 day) AND log_type='patrol' AND log_params=rc_this_oldid join ".$wgSharedDB.".user pu on pu.user_id=log_user join revision rev on rev_id=rc_this_oldid left join revision rev2 on rev.rev_page=rev2.rev_page AND rev2.rev_id > rev.rev_id AND (rev2.rev_comment like '%Reverted edits by [[Special:Contributions/%' or rev2.rev_comment like '%Copyviocheckbot has found a potential copyright violation%') left join nfd on nfd_page=rev.rev_page and nfd_status=0  where hcu_experiment=" . $dbr->addQuotes($EXPERIMENT_NAME) . " AND u.user_email is NOT NULL AND u.user_email <> '' AND u.user_email_authenticated is NOT NULL AND hcu_run=0 AND rc_patrolled=1 AND rc_namespace=0 AND nfd_page is NULL AND rev2.rev_page is NULL GROUP BY hcu_user";

$res = $dbr->query($sql, __METHOD__);
$emails = array();
foreach($res as $row) {
	$email = array('from' => 'Krystle <krystle@wikihow.com>', 'to' => $row->user_email, 'article' => $row->rc_title,'username' => $row->user_name, 'user_id' => $row->hcu_user, 'revision' => $row->rev_id);
	print_r($email);
	$emails[] = $email;	
}
global $wgOutputEncoding;

$dbw = wfGetDB(DB_MASTER);
foreach($emails as $email) {
	$from = new MailAddress($email['from']);
	$to = new MailAddress($email['to']);
	$subject = 'Nice contribution on wikiHow!';
	EasyTemplate::set_path(dirname(__FILE__).'/../extensions/wikihow/hydra/experiments');
	$body = EasyTemplate::html('RCPatrolEmail.tmpl.php', array('articleURL' => 'http://www.wikihow.com/' . $email['article'], 'articleName' => str_replace("-"," ",$email['article']), 'userURL' => 'http://www.wikihow.com/User:' . $email['username'], 'username' => $email['username'] ));
	$content_type = "text/html; charset={$wgOutputEncoding}";
	UserMailer::send($to,$from,$subject, $body, null, $content_type);
	$sql = "update hydra_cohort_user set hcu_run=1 WHERE hcu_user=" . $dbw->addQuotes($email['user_id']) . " AND hcu_experiment=" . $dbw->addQuotes($EXPERIMENT_NAME) . " LIMIT 1";
	$res = $dbw->query($sql, __METHOD__);
	$logTo = new MailAddress("elizabethwikihowtest@gmail.com, gershon@wikihow.com");
	UserMailer::send($logTo,$from,$to . ":" .$subject, $body, null, $content_type);
}
