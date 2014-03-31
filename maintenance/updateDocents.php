<?
require_once('commandLine.inc');

$start = 1236367281;

$wgUser = new User();
$wgUser->setID(1236204);

$debug = 0;

# 60 days
$offset = 60 * 60 * 24 * 60;
$cutoff = wfTimestamp( TS_MW, time() - $offset );
$minedits = 5;

$dbw = wfGetDB(DB_MASTER); 
$dbr = wfGetDB(DB_MASTER); 

$from = new MailAddress("wiki@wikihow.com"); 

# get all of the docents
$users = array(); 
$res = $dbr->query ("select distinct(dc_user) as dc_user from docentcategories;");
while ($row = $dbr->fetchObject($res)) {
	$users[] = $row->dc_user;
}
$project_page = Title::makeTitle(NS_PROJECT, "Docents");

function dropDocent($user) {
	global $dbw, $debug, $project_page;
	if (!$debug) $dbw->query("delete from docentcategories where dc_user={$user->getId()}");
    $params = array($user->getID());
    $log = new LogPage( 'doc', false );
    $log->addEntry( 'doc', $project_page, wfMsg('doc_logsummary', $user->getName()), $params );
}

function getQuietDocents($cutoff) {
	global $dbr, $minedits, $debug, $users;

	$removing = array();
	foreach ($users as $u) {
		$count = $dbr->selectField(
					array('revision', 'page'), 
					array('count(*)'), 
					array('page_id=rev_page', 'rev_user' => $u, "rev_timestamp > '{$cutoff}'")
			);
		$newuser = $dbr->selectField('user',
				array('count(*)'),
				array('user_id' => $u, "user_registration > '{$cutoff}'")
			);
		if ($newuser == 1) {
			if ($debug) echo "$u ....is a new user, skipping\n";
			continue;
		}	
		if ($count < $minedits) {
			// remove them
			if ($debug) echo "$u .... has .... $count edits... - adding to list\n";
			$removing[]= $u;
		} else {
			if ($debug) echo "$u .... has .... $count edits... - ignoring \n";
		}
	}	
	return $removing;
}

$offset = 60 * 60 * 24 * 30;
$cutoff = wfTimestamp( TS_MW, time() - $offset );
$lastfifteen = wfTimestamp( TS_MW, time() - 60 * 60 * 24 * 15 );
$warnings = getQuietDocents($cutoff); 
$warn = 0;
$noemail = 0;
$subject = wfMsg('docent_email_about_to_be_dropped_subject');
foreach ($warnings as $id) {

	
	$user = User::newFromId($id);
	if ($user->mRegistration > $cutoff) {
		if ($debug) {
			echo "User {$user->getName()} registered on {$user->mRegistration} after cutoff $cutoff so ignored...\n";
		}
		continue;
	}

	$count = $dbr->selectField('docentwarnings', array('count(*)'), array('dw_user' => $id, "dw_timestamp > '$lastfifteen'"));
	if ($count > 0) {
		if ($debug) echo "skipping $id - received warning in last 15 days....\n";
		continue;
	}

	// has an e-mail address?
	$email = $user->getEmail();
	if ($email == '') {
		$noemail++;
		continue;
	}
	$to = new MailAddress($email);
	$cats = "";
	$res = $dbr->select( 'docentcategories',
				array('dc_to'),
				array('dc_user' => $id)
			);
	while ($row = $dbr->fetchObject($res)) {
		$t = Title::makeTitle(NS_CATEGORY, $row->dc_to);
		$cats .= "* " . $t->getText() . "\n";
	}
	$name = $user->getRealName() != "" ? $user->getRealName() : $user->getName();
	$body = wfMsg('docent_email_about_to_be_dropped', $name, $cats);
	if (!$debug) UserMailer::send($to, $from, $subject, $body);
	$dbw->insert('docentwarnings', array('dw_user' => $user->getId(), 'dw_timestamp' =>  wfTimestamp( TS_MW, time())));
	if ($debug) echo "Warning {$name} ($email) \n";
	$warn++;
}
			
/*if (time() <  ($start + 60 * 60 * 24 * 15) ) {
	if ($debug) echo "returning early\n";
	echo wfTimestamp( TS_MW, time()) . " - warned {$warn} users ($noemail no e-mailed), 0 dropped\n";
	return;
}*/

$noemail = 0;
$subject = wfMsg('docent_email_dropped_subject');
$dropped = 0;
$offset = 60 * 60 * 24 * 45;
$cutoff = wfTimestamp( TS_MW, time() - $offset );
$removing = getQuietDocents($cutoff); 

foreach ($removing as $id) {
	$user = User::newFromId($id);
	if ($user->mRegistration > $cutoff) {
		if ($debug) {
			echo "User {$user->getName()} registered on {$user->mRegistration} after cutoff $cutoff so ignored...\n";
		}
		continue;
	}
	// has an e-mail address?
	$email = $user->getEmail();
	if ($email == '') {
		dropDocent($user);
		#echo "$id no email address removed automagically $email\n";
		$noemail++;
		$dropped++;
	}
	$to = new MailAddress($email);
	$cats = "";
	$res = $dbr->select( 'docentcategories',
				array('dc_to'),
				array('dc_user' => $id)
			);
	while ($row = $dbr->fetchObject($res)) {
		$t = Title::makeTitle(NS_CATEGORY, $row->dc_to);
		$cats .= "* " . $t->getText() . "\n";
	}
	$name = $user->getRealName() != "" ? $user->getRealName() : "";
	$body = wfMsg('docent_email_dropped', $name, $cats);
	if (!$debug) UserMailer::send($to, $from, $subject, $body);
	dropDocent($user);
	$dropped++;
	# TODO log in special log somewhere
}
			
echo wfTimestamp( TS_MW, time()) . " - warned {$warn} users ($noemail have no email), $dropped dropped ($noemail had no email)\n";

