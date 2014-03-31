<?php

if ( !defined( 'MEDIAWIKI' ) ) {
    exit(1);
}

$wgExtensionCredits['specialpage'][] = array(
    'name' => 'NewHowtoArticles',
    'author' => 'Bebeth Steudel',
    'description' => 'Tool to show new articles',
);

$wgSpecialPages['NewHowtoArticles'] = 'NewHowtoArticles';
$wgAutoloadClasses['NewHowtoArticles'] = dirname( __FILE__ ) . '/NewHowtoArticles.body.php';

$wgHooks['NABArticleFinished'][] = array("wfMarkAvailableForNewArticles");
$wgHooks['WRMArticlePublished'][] = array("wfMarkAvailableForNewArticlesWRM");
$wgHooks['ArticleSaveComplete'][] = array("wfCheckForWRM");
$wgHooks['wgQueryPageLine'][] = array("wfMarkLiveLine");

/**
 *
 * WRM is now not only used via importXML, so we need to make sure
 * those articles get grabbed too.
 */
function wfCheckForWRM($article, $user, $text, $summary) {
	$t = $article->getTitle();
	if (!$t || $t->getNamespace() != NS_MAIN)  {
		return true;
	}

	if($user->getName() == "WRM"){
		$dbw = wfGetDB(DB_MASTER);

		$sqlInsert = "INSERT IGNORE INTO newarticles VALUES (" . $article->getID() . ", " . wfTimestampNow() . ", 1, '')";
		$dbw->query($sqlInsert);

	}

	return true;
}

function wfMarkLiveLine($line_num, &$html){
	global $wgUser;

	$userGroups = $wgUser->getGroups();
	if (!in_array('staff', $userGroups))
		return true;

	if($line_num + 1 == wfMsg('newarticles_listnum'))
		$html[] = "<hr />";

	return true;
}

//this hook takes a recently published WRM article and automatically makes it available for the list of new articles.
function wfMarkAvailableForNewArticlesWRM($article_id) {
	$dbw = wfGetDB(DB_MASTER);

	$sqlInsert = "INSERT IGNORE INTO newarticles VALUES (" . $article_id . ", " . wfTimestampNow() . ", 1, '')";
	$dbw->query($sqlInsert);

	return true;
}

//this hook takes a recently NAB'd article and checks to see if it is valid for the list of new articles
function wfMarkAvailableForNewArticles($article_id) {
	$dbw = wfGetDB(DB_SLAVE);

	$sql = "SELECT fe_user_text, fe_user, pl_list, tl_from, page_namespace as namespace, nap_patrolled, page_counter as counter, page_title AS title, page_id as cur_id, pl_list, tl_from FROM `newarticlepatrol` LEFT JOIN `page` ON page_id = nap_page LEFT JOIN `firstedit` ON fe_page = nap_page LEFT JOIN `templatelinks` ON tl_from = page_id LEFT JOIN pagelist ON pl_page = page_id WHERE page_id = " . $article_id;
	$res = $dbw->query($sql, __FILE__);

	$valid = true;
	$i = 0;
	$firstedituser = "";
	while($row = $dbw->fetchObject($res)){
		if(isset($row->tl_from)) //no templates
			$valid = false;
		if(isset($row->pl_list)) //not a rising star
			$valid = false;
		if($row->nap_patrolled != 1) //has been new article patrolled
			$valid = false;
		if(isset($row->fe_user_text))
			$firstedituser = $row->fe_user_text;
		$i++;

	}

	if($i == 0){ //nothing was returned
		$valid = false;
	}

	$levels = array('articles'=>5, 'edits'=>10);
	$msg = wfMsg('Newarticles_threshold');
	$lines = split("\n", $msg);
	foreach ($lines as $l) {
		$l = trim($l);
		$parts = split("=", $l);
		$k = $parts[0];
		$v = $parts[1];
		$levels[$k] = $v;
	}

	if ($valid && $levels['edits'] > 0) {
		$count = $dbw->selectField(array('revision', 'page'), 'count(*)', array('rev_page=page_id', 'page_namespace'=>NS_MAIN, 'rev_user_text'=>$firstedituser));
		if ($count < $levels['edits'])
			$valid = false;
	}
	if ($valid && $levels['articles'] > 0) {
		// how many articles created?
		$count = $dbw->selectField(array('firstedit'), 'count(*)', array('fe_user_text'=>$firstedituser));
		if ($count < $levels['articles'])
			$valid = false;
	}
	
	$sqlInsert = "INSERT IGNORE INTO newarticles VALUES (" . $article_id . ", " . wfTimestampNow() . ", ";
	if($valid)
		$sqlInsert .= "1, ";
	else
		$sqlInsert .= "0, ";
	$sqlInsert .= "'')";

	$dbr = wfGetDB(DB_MASTER);
	$dbr->query($sqlInsert);

	return true;
}


/*************************
 * Db Structure
 *
 *  na_page - article Id
 *  na_timstamp - timestamp when article was added/removed from new article feed
 *  na_user_text - username of person who removed article from feed.
 *  na_valid - 1 if not allowed in feed
 */
