<?
//
// Loops through all pages in the Video: namespace and checks whether they
// match these criteria: (1) Is a youtube video, (2) has a <yt:noembed>
// tag in the xml data for the video, (3) there are links from a page
// with namespace Video:.
//
// This is run nightly on the spare server as part of the nightly.sh script
//

require_once('commandLine.inc');

function loginAsUser($user) {
    global $wgUser;
	// next 2 lines taken from maintenance/deleteDefaultMessages.php
	$wgUser = User::newFromName($user);
	$wgUser->addGroup('bot');
}

loginAsUser('Vidbot');

$db = wfGetDB(DB_SLAVE);
$res = $db->select('page',
	array('page_namespace', 'page_title'),
	array('page_namespace=' . NS_VIDEO)
);

// Grab a list of all youtube vids
$list = "";
$vids = array();
while ($row = $db->fetchObject($res)) {
	$t = Title::makeTitle($row->page_namespace, $row->page_title);
	$r = Revision::newFromTitle($t);
	if (!$r) continue;
	$text = $r->getText();
	preg_match("@\|youtube\|[^\|]+\|@U", $text, $matches);
	if (sizeof($matches) > 0) {
		$id = $matches[0];
		$id = str_replace("|", "", $id);
		$id = str_replace("youtube", "", $id);
		$vids[] = array(
			'id' => $id,
			'text' => $t->getText(),
			'full-text' => $t->getFullText(),
		);
	}
}

// Pull from the youtube API to figure out whether the video is "noembed"
foreach ($vids as &$vid) {
	$url = "http://gdata.youtube.com/feeds/api/videos/" . $vid['id'];
	$results = Importvideo::getResults($url);
	$vid['noembed'] = intval( strpos($results, "<yt:noembed/>") !== false );
}

// Remove all embedable videos from the list
$vids = array_filter($vids, function ($item) {
	return $item['noembed'];
});

// Sometimes DB connection goes away at this point. To reconnect using
// Mediawiki's abstraction of the mysql database, the only way I found 
// is to "close" the non-existent connection then request another.
$db->close();
$db = wfGetDB(DB_SLAVE);
$db->ping();

// Check if any of these noembed vids have links on the site
$start = microtime(true);
foreach ($vids as $i => $vid) {
	if ($vid['noembed']) {
		$sql = "SELECT count(*) AS C
				FROM pagelinks LEFT JOIN page ON pl_from = page_id
				WHERE pl_namespace=" . NS_VIDEO . "
					AND pl_title=" . $db->addQuotes($vid['text']) . "
					AND page_namespace=0";
		$res = $db->query($sql);
		if ($row = $res->fetchObject()) {
			if ($row->C == 0) {
				echo "{$vid['full-text']} has no links...skipping\n";
				continue;
			}
		}

		$list .= "# [[{$vid['full-text']}]]\n";
	}
}

if ($list == "") $list = "There are no videos at this time.";
$t = Title::makeTitle(NS_PROJECT, "Videos that can no longer be embedded");
$a = new Article($t);
$date = date("Y-m-d");
$text = wfMsg('no_more_embed_video') . "\n\n{$list}\nThis page was last updated {$date}\n";
if ($t->getArticleID() == 0) {
	$a->insertNewArticle($text, "list of videos that cannot be embedded", false, false);
} else {
	$a->updateArticle($text, "list of videos that cannot be embedded", false, false);
}

