<?
	require_once('commandLine.inc');
	require_once('EditPageWrapper.php');
	require_once('Newarticleboost.body.php');
	$name = "Wendy Weaver";
	$lines = split("\n", file_get_contents($argv[0]));
	$titles = array(); 
	$wgUser = User::newFromName($name);
	foreach ($lines as $line) {
		$line = trim($line);
		if ($line == "") continue;
		$tokens= split("\t", $line);
		$t = Title::newFromURL(preg_replace("@^/@", "", $tokens[0]));
		if (!$t) {
			echo "Can't make title out of {$tokens[0]}\n";
			continue;
		}
		$text = EditPageWrapper::formatTitle(trim(preg_replace("@how to@i", "", $tokens[1])));
		$titles[$text] = $t;
	}
	foreach ($titles as $text=>$t) {
		if ($t->getArticleID() ==0) {
			echo "{$t->getFullText()} doesnt exist on this server\n";
			continue;
		}	
		AddRelatedLinks::addLinkToRandomArticleInSameCategory($t, "sprinkling links", $text);
		echo "Doing {$t->getFullText()} ...\n";
	}
	$dbw = wfGetDB(DB_MASTER);
	$dbw->update('recentchanges', array('rc_patrolled'=>1), array('rc_user_text'=>$name));
