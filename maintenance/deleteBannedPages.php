<?php
/*
 * Delete pages, that have been flagged as spam
 * Written By Gershon Bialer
 */
 /*
 CREATE TABLE `page_ban` (
   `pb_page` varchar(255) NOT NULL,
	 `pb_namespace` int(11) NOT NULL default '2'
	 `pb_timestamp` timestamp 
	 )
 */
require_once("commandLine.inc");
//Sanity check. We only delete this many articles, and if we want to delete more, we send an error email with lots of capital letters
$max_deletes = 100;

$dbr = wfGetDB(DB_SLAVE);

$res = $dbr->select("page_ban",array("pb_namespace","pb_page"));
$deleted = array();
$wgUser = User::newFromName("EmilyPostBot");
$n = 0;
print "Start :" . wfTimestampNow(TS_MW) . "\n";

$pages = array();
foreach($res as $row) {
	$pages[] = array('name' => $row->pb_page, 
									 'ns' => $row->pb_namespace);
}
foreach($pages as $page) {
	$title = Title::newFromText($page['name'], $page['ns']);
	$deletion = false;
	
	if($title && $title->exists() && $title->getNamespace() != NS_MAIN) {
		print( 'Deleting article ' . $wgContLang->getNSText($row->pb_namespace) . ':' . $title->getText() . "\n");
		$article = new Article($title);
		$article->doDelete('Bad page');
		$deletion = true;
	}

	if($page['ns'] == NS_USER) {
		$user = User::newFromName($page['name']);
		if($user && $user->getID() > 0) {
			if(ProfileBox::removeUserData($user)) {
				print("Removed profilebox for " . $user->getName() . "\n");
				$deletion = true;
			}

			$ra = Avatar::getAvatarRaw($user->getName());
			if($ra['url'] != '') {
				if(preg_match("@SUCCESS@",Avatar::removePicture($user->getID()))) {
					print("Remove avatar picture for " . $user->getName() . "\n");
					$deletion = true;	
				}
			}
		}
	}
	if($deletion) {
		$deleted[] = array($page['name'], $page['ns']);
		$n++;	
	}	

	//Safety check to only delete a maximum of 100 articles
	if($n > $max_deletes) {
		break;
	}
}
$msg = "Pages deleted: \n\n";
foreach($deleted as $d) {
	$msg .= $wgContLang->getNSText($d[1]) . ":" . $d[0] . "\n";
}
$to = new MailAddress("gershon@wikihow.com, reuben@wikihow.com");
$from = new MailAddress("gershon@wikihow.com");
if($n > $max_deletes) {
	$msg .= "\nATTEMPTING TO DELETE OVER " . $max_deletes . " ARTICLES";
	$subject = "MAJOR ERROR PLEASE CHECK: Auto-deleted pages"; 
}
else {
	$subject = "Auto-deleted pages";
}
if($n > 0) { 
	UserMailer::send($to, $from, $subject, $msg);
	print $msg;
}
