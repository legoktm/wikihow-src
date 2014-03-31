<?php

require_once('commandLine.inc');
require_once("$IP/extensions/wikihow/DatabaseHelper.class.php");
require_once("$IP/extensions/wikihow/authors/AuthorEmailNotification.php");

$day = intval(date("j"));

if($day < 1 || $day > 6) {
	echo "We don't send emails on the $day day of the month. Exiting.\n";
	exit;
}

$startTime = microtime(true);

$users = DatabaseHelper::batchSelect('user', array('user_id'), array(), __FILE__);

$dbr = wfGetDB(DB_SLAVE);

//TESTING CODE
/*$res = $dbr->select('user', array('user_id'), array('user_name' => 'Bsteudel'), __FILE__, array("LIMIT" => 100));
$users = array();
foreach($res as $user)
	$users[] = $user;
*/


$todayUnix = wfTimestamp(TS_UNIX);
$minUnix = strtotime("-1 month", $todayUnix);
$minDate = wfTimestamp(TS_MW, $minUnix);
//$testDate = wfTimestamp(TS_MW, strtotime("-2 months", $todayUnix));

echo "looking for data from {$minDate} and forward\n";

$emailCount = 0;
$userNames = "";
foreach($users as $userInfo) {
	$user = User::newFromId($userInfo->user_id);
	
	$name = $user->getName();
	
	$firstLetter = strtolower(substr($name, 0, 1));
	$omit = false;
	switch($day){
		case 1:
			if($firstLetter < "a" || $firstLetter > "c")
				$omit = true;
			break;
		case 2:
			if($firstLetter < "d" || $firstLetter > "i")
				$omit = true;
			break;
		case 3:
			if($firstLetter < "j" || $firstLetter > "l")
				$omit = true;
			break;
		case 4:
			if($firstLetter < "m" || $firstLetter > "r")
				$omit = true;
			break;
		case 5:
			if($firstLetter < "s" || $firstLetter > "z")
				$omit = true;
			break;
		case 6:
			if(ctype_alpha($firstLetter))
				$omit = true;
			break;
	}

	if($omit)
		continue;
	
	$email = $user->getEmail();
			
	if($email == "") {
		//echo "They don't have an email.\n";
		continue;
	}
	
	if($user->getOption('disablemarketingemail') == '1') {
		//echo "They don't want notifications\n";
		continue;
	}
	
	$views = 0;
	$articles = 0;
	//CHECK DATE (dont' want testing date left in there)
	$res = $dbr->select(
		array('revision','pageview'),
		array('pv_30day'),
		array('rev_page=pv_page', 'rev_user' => $user->getID(), "rev_timestamp > {$minDate}"),
		__METHOD__,
		array('GROUP BY' => 'rev_page')
		);

	foreach ($res as $object) {
		$views += intval($object->pv_30day);
		$articles++;
	}

	if($views < 50) {
		//echo "No email sent to " . $user->getName() . ". Not enough views ({$views})\n";
		continue;
	}

	$from_name = "Krystle <krystle@wikihow.com>";
	$subject = wfMsg("viewership_subject");

	$cta = AuthorEmailNotification::getCTA('monthly_views', 'email');
	
	if($articles == 1)
		$article = "article";
	else
		$article = "articles";

	$contribsPage = SpecialPage::getTitleFor( 'Contributions', $user->getName() );
	$contribsLink = $contribsPage->getFullURL();

	$body = wfMsg("viewership_body", $user->getName(), number_format($articles), number_format($views), $cta, $article, $contribsLink);

	wfDebug($email . " " . $subject . " " . $body . "\n");
	$emailCount++;

	AuthorEmailNotification::notify($user, $from_name, $subject, $body, "", true);
	
	$userNames .= $user->getName() . " ";
	
	if($emailCount > 500)
		break;
	
}

echo "\n\nEmail sent to:\t{$userNames}\n\n";

$endTime = microtime(true);
$timeDiff = $endTime - $startTime;

echo $emailCount . " viewership emails were sent, Finished in {$timeDiff} sec.\n";
