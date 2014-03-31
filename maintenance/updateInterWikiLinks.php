<?php
/** 
 * Add interwiki language links to pages for articles translated in the past day by translators in the 'translator' group, or
 * add interwiki language links for translation links added in the TranslationLinkOverride tool. When interwiki links are added
 * for translated articles, they will only be added to the other side (I.E. the English article for an English article translated to Spanish)
 */
require_once('commandLine.inc');
require_once('TranslationLink.php');

//The maximum number of links we add
$MAX_CHANGES = 100000;
//In test mode, we just display, but don't actually save 
$debug = @$argv[0] != "live";

//Number of links added or modified
$changes = 0;

$dbr = wfGetDB(DB_MASTER);
global $wgLanguageCode;
if(@$_SERVER['HOSTNAME']=="doh.wikidiy.com") {
	$allLangs = array("en","es","pt");
}
else {
	$allLangs = $wgActiveLanguages;
	$allLangs[] = "en";
}
//We add interwiki links as the interwiki user
global $wgUser;
$wgUser = User::newFromName("InterwikiBot");

$ourPageTable = Misc::getLangDB($wgLanguageCode) . ".page";
$lowDate = wfTimestamp(TS_MW, strtotime("-30 day", strtotime(date('Ymd', time()))));

$links = TranslationLink::batchGetRemovedLinks($wgLanguageCode, true);
TranslationLink::batchPopulateURLs($links);
foreach($links as $link) {
	if(	$link->removeLink(true, $debug)) {
		print "Removing link between " . $link->fromURL . "(" . $link->fromAID . ") and " . $link->toURL . "(" . $link->toAID . ")\n";
	}
	else {
		print "Failed to remove link between " . $link->fromURL . "(" . $link->fromAID . ") and " . $link->toURL . "(" . $link->toAID . ")\n";

	}

}
$links = TranslationLink::batchGetRemovedLinks($wgLanguageCode, false);
TranslationLink::batchPopulateURLs($links);
foreach($links as $link) {
	if($link->removeLink(false, $debug)) {
		print "Removing link between " . $link->toURL . "(" . $link->toAID . ") and " . $link->fromURL . "(" . $link->fromAID . ")\n";
	}
	else {
		print "Failed to remove link between " . $link->toURL . "(" . $link->toAID . ") and " . $link->fromURL . "(" . $link->fromAID . ")\n";

	}
}

foreach($allLangs as $lang) {
	// We don't add interwiki links from a language to itself	
	if($lang == $wgLanguageCode) {
		continue;
	}
	$anotherLink = array();
	$invalidLink = array();
	$links = TranslationLink::getLinks($wgLanguageCode,$lang, array("tl_timestamp > '" . $lowDate . "'" ));
	foreach($links as $link) {
		if($changes >= $MAX_CHANGES) {
			break;	
		}
		print "Checking for proper interwiki links between " . $link->fromURL . "(" . $link->fromAID . ") and " . $link->toURL . "(" . $link->toAID . ")\n";
		$ret = $link->addLink(true,$debug);
		if($ret['status'] == 0) {
			print "One or more URL(s) invalid\n";	
			$invalidLink[] = array('urla'=>$link->fromURL,'urlb'=>$link->toURL);
		}
		elseif($ret['status'] == 1) {
			print "Already added\n";	
		}
		elseif($ret['status'] == 2) {
			print "Overwriting existing link(s):" . implode(",",$ret['dup']) . "\n";
			$anotherLink[] = array('urla'=>$link->fromURL,'urlb'=>$link->toURL, 'dup'=>$ret['dup']);
			$changes++;
		}
		else {
			print "Link added\n";	
			$changes++;
		}
	}
	$links = TranslationLink::getLinks($lang, $wgLanguageCode, array("tl_timestamp > '" . $lowDate . "'"));

	foreach($links as $link) {
		if($changes >= $MAX_CHANGES) {
			break;	
		}
		print "Checking for proper interwiki links between " . $link->toURL . "(" . $link->toAID . ") and " . $link->fromURL . "(" . $link->fromAID . ")\n";
		$ret = $link->addLink(false,$debug);
		if($ret['status'] == 0) {
			print "One or more URL(s) invalid\n";	
			$invalidLink[] = array('urla'=>$link->toURL,'urlb'=>$link->fromURL);
		}
		else if($ret['status'] == 1) {
			print "Already added\n";
		}
		else if($ret['status'] == 2) {
			print "Overwriting existing link(s):" . implode(",",$ret['dup']) .  "\n";
			$anotherLink[] = array('urla'=>$link->toURL,'urlb'=>$link->fromURL, 'dup'=>$ret['dup']);
			$changes++;
		}
		else {
			print "Link added\n";	
			$changes++;
		}
	}
}

if(count($invalidLink)>0 || count($anotherLink) > 0) {
	$msg = "";

	if(count($invalidLink) > 0) {
		$msg .= "The following URL connections are invalid:\n";
		foreach($invalidLink as $link) {
			$msg .= $link['urla'] . " " . $link['urlb']	. "\n";
		}
		$msg .= "\n\n";
	}
	if(count($anotherLink) > 0) {
		$msg .= "The following new links overwrote existing links :\n";
		foreach($anotherLink as $link) {
			$msg .= $link['urla'] . " " . $link['urlb'] . " overwrote: " . implode(",",$link['dup']) . "\n";
		}
	}
	$to = new MailAddress("gershon@wikihow.com, bridget@wikihow.com");
	$from = new MailAddress("gershon@wikihow.com");
	$subject = "Language Links: Errors adding interwiki links";
	UserMailer::send($to, $from, $subject, $msg);
}
