<?
require_once('commandLine.inc');

// Microsecond -> second conversion
$wgMS = 1000000;
// How long to wait until the next request in nanoseconds.  This is set if we detect a too_many_requests error from youtube
$wgWait = 0;
// Frequency of requests in nanoseconds
$wgFrequency = 1 * $wgMS;

$wgUser = User::newFromName('vidbot');
$wgUser->load();

$dbr = wfGetDB(DB_SLAVE);
$startingChars = "Ma";
$where = array(
	"page_namespace" => NS_VIDEO,
	"page_is_redirect" => 0);
// Add the following to the where class to work on a subset of the videos - esp if you need to restart it because the db timed out
//$where[] = "page_title > '{$startingChars}%'";
$dbw = wfGetDB(DB_MASTER);$res = $dbr->select('page', array('page_id'), $where, "removeUnsupportedVideos.php");
//$res = $dbr->select('page', array('page_id'), $where, "removeUnsupportedVideos.php", array("LIMIT 20"));

while ($row = $dbr->fetchObject($res)) {
	$pages[] = $row->page_id;
}
$dbr->freeResult( $res );

foreach ($pages as $page) {
	if (!$dbr->ping()) {
		$dbr->close();
		$dbr = wfGetDB(DB_SLAVE);
	}

	if (!$dbw->ping()) {
		$dbw->close();
		$dbw = wfGetDB(DB_MASTER);
	}

	$r = Revision::loadFromPageId($dbr, $page);
	if ($r) {
		$body = $r->getText();
		$video = array(
			"vid_service" => null, 
			"vid_id" => null,
			"vid_page_id" => $page, 
			"vid_page_txt" => $r->getTitle()->getText(), 
			"vid_page_db_key" => $r->getTitle()->getDBKey(), 
			"vid_page_body" => $body);	

		if(preg_match("@{{Video:@", $body)) {
			// Just pointing to another video
			continue;
		} else if(preg_match("@{{Curatevideo\|(wonderhowto|videojug|youtube|howcast|5min)\|([^\|]+)\|@", $body, $matches)) {
			// Check to see if the supported video providers videos are valid (ie video still exists). If not, add them to the unsupported list
			if(!isSupportedVideo($matches[1], $matches[2])) {
				$video['vid_id'] = $matches[2];
				$video['vid_service'] = $matches[1];
				$unsupported[] = $video;	
				printVideo($video);
				removeVideo($video);
			}
		} else {
			$video['vid_service'] = "UNKNOWN";
			$unsupported[] = $video;	
			printVideo($video);
			removeVideo($video);
		}
	} else {
		error("oops. couldn't load page_id $page\n");
	}
}

#var_dump($unsupported);
echo "\n\n\nTotal videos to remove: " . sizeof($unsupported) . "\n";

function removeVideo(&$video) {
	if (!removeVideosFromLinkedArticles($video)) {
		return;	
	}
	
	if (!removeVideoPage($video)) {
		return;
	}
}

function removeVideosFromLinkedArticles(&$video) {
	$articles = getLinkedArticles($video);
	foreach($articles as $article) {
		if (!removeVideoSection($article)) {
			return false;
		}
	}
	return true;
}

function removeVideoSection(&$article) {
	$ret = false;	
	$t = Title::newFromID($article['page_id']);
	if ($t) {
		$dbw = wfGetDB(DB_MASTER);
		$wikitext =	Wikitext::getWikitext($dbw, $t);
		try {
			$wikitext = Wikitext::removeVideoSection($wikitext);
			$result = Wikitext::saveWikitext($t, $wikitext, "vidbot - removing video section");
			printArticleRemoval($t);	
			$ret = true;
		} catch (Exception $e) {
			error($e->getMessage() . ", Title: " . $t->getText() . "\n");
		}
	}
	return $ret;
}

function removeVideoPage(&$video) {	
	$ret = false;
	$dbw = wfGetDB(DB_MASTER);
	$t = Title::newFromId($video['vid_page_id']);
	if ($t && $t->exists()) {
		$a = new Article($t);
		if ($a && $a->exists()) {
			$dbw->begin();
			$reason = "vidbot - Deleting Video Page - text was: '" . $a->getContent() . "'";
			if($a->doDeleteArticle($reason)) {
				$ret = true;
			} else {
				error("Couldn't delete video page with page id" . $video['vid_page_id']);
			}
			$dbw->commit();
		} else {
			error("Couldn't create article for page id" . $video['vid_page_id']);	
		}
	} else {
		error("Couldn't create title for page id" . $video['vid_page_id']);	
	}
	return $ret;
}

function printVideo(&$video) {
	echo "service: " . $video['vid_service'] . ", http://www.wikihow.com/Video:" . $video['vid_page_db_key'] . "\n";
}

function printArticleRemoval(&$t) {
	echo "--vid section removed on: " . $t->getFullUrl() . "\n";
}

function isSupportedVideo($service, $id) {
	$ret = true;

	switch ($service) {
		case "videojug":
			$ret = isSupportedVideoJug($id);			
			break;
		case "youtube":
			$ret = isSupportedYouTube($id);			
			break;
		case "wonderhowto":
			$ret = isSupportedWonderHowto($id);			
			break;
		/*  Turning off howcast b/c api requests are currently broken
		case "howcast":
			$ret = isSupportedHowcast($id);			
			break;
		*/
		case "5min":
			$ret = isSupported5min($id);			
			break;
	}
	
	return $ret;
}

// http://gdata.youtube.com/feeds/api/videos/[id]  # 400 bad request if video doesn't exist
function isSupportedYouTube($id) {
	global $wgWait, $wgFrequency, $wgMS;

	$id = urlencode($id);
	$url = "http://gdata.youtube.com/feeds/api/videos/$id";
	$res = curl($url, true);

	// If there's a quota violation wait 10 minutes, throttle down the requests by .25 seconds  and check this video again
	if (preg_match("@too_many_recent_calls@", $res['body'])) {
		debug("BUMMER - YouTube Throttled us!==========================\n");
		$wgWait = 10 * 60 * $wgMS;
		$wgFrequency += .25 * $wgMS;
		return isSupportedYouTube($id);
	}

	debug("isSupportedYouTube, id: $id, status: " . $res['code'] . "\n");
	return $res['code'] == 200 ? true : false;
}

// http://api.howcast.com/videos/[id].xml  # returns 404 error code if video doesn't exist
function isSupportedHowcast($id) {
	$id = urlencode($id);
	$url = "http://api.howcast.com/videos/$id.xml";
	$res = curl($url);
	debug("isSupportedHowcast, id: $id, status: " . $res['code'] . "\n");
	return $res['code'] == 200 ? true : false;
}

// http://api.5min.com/video/[id]/info.xml # "The video request you sent is incorrect." is sent if a bad url
function isSupported5min($id) {
	$id = urlencode($id);
	$url = "http://api.5min.com/video/$id/info.xml";
	$res = curl($url);
	$notFound = "@(The video request you sent is incorrect|This video is not avaliable)@";
	debug("isSupported5min id: $id, body: " . $res['body'] . "\n");
	return preg_match($notFound, $res['body']) ? false : true;
}

// http://www.videojug.com/film/player?id=[id]&username=wikihow #returns a 302 not found if video doesn't exist 
function isSupportedVideoJug($id) { 
	$id = urlencode($id);
    $url = "http://www.videojug.com/film/player?id=$id&username=wikihow";
	$res = curl($url); 
	debug("isSupportedVideoJug id: $id, status: " . $res['code'] . "\n");
	return $res['code'] == 302 ? true : false; 
}

function isSupportedWonderHowto($id) {
	$ret = true;

	// Any videos not from these services are unsupported
	if(!preg_match("@videojug|youtube|howcast|5min@", $id)) {
		$ret = false;
	} else {
		// If it's a supported service extract which service and the video id, then check if it exists
		$url_re = '@value&61;"(http://[^"]+)"@';
		if (preg_match($url_re, $id, $url)) {
			$url = $url[1];
			$url = htmlspecialchars_decode($url);
		} else {
			// No match. Ignore.
			error("oops, couldn't find a url for $id\n");
			return $ret;
		}

		$service_re = '@http://www.(videojug|youtube|howcast|5min).com/@';
		if (preg_match($service_re, $url, $service)) {
			$service = $service[1];
		} else {
			// No match. Ignore.
			
			error("oops, couldn't find a service for url: $url\n");
			return $ret;
		}

		$id = extractId($url, $service);
		// If we can extract an id, see if it's a valid video, otherwise return true
		$ret = strlen($id) ? isSupportedVideo($service, $id) : $ret;
	}
	return $ret;
}

function extractId($url, $service) {
	$id = "";	
	switch ($service) {
		case "youtube":
			if (preg_match('@/v/([^&]+)&@', $url, $matches)) {
				$id = $matches[1];
			}
			break;
		case "howcast":
			if (preg_match('@/videos/([^\.]+)\.xml$@', $url, $matches)) {
				$id = $matches[1];
			}
			break;
		case "5min":
			if (preg_match('@/video/([^/]+)/@', $url, $matches)) {
				$id = $matches[1];
			}
			break;
		case "videojug":
			if (preg_match('@id=([^&]+)&@', $url, $matches)) {
				$id = $matches[1];
			}
			break;
	}
	return $id;
}

function curl($url, $isYouTube = false) {
	global $wgWait, $wgFrequency;
	// Throttling mechanism to deal with youtube request quotas
	if ($isYouTube) {
		$sleep = intVal($wgWait + $wgFrequency);
		usleep($sleep);
		#echo "YouTube request at " . date('h:i:s') . "\n";
		// reset the wait
		$wgWait = 0;
	}

	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	//curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_TIMEOUT, 5);
	curl_setopt($ch, CURLOPT_REFERER, 'http://www.wikihow.com/');
	$contents = curl_exec($ch);
	$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	if (curl_errno($ch)) {
		return curl($url);
	}
	curl_close($ch);
	return array('code' => $httpCode, 'body' => $contents);
}

function getLinkedArticles(&$video) {
	$dbr = wfGetDB(DB_SLAVE);
	$page = $dbr->tableName('page');
	$templatelinks = $dbr->tableName( 'templatelinks' );
	$sql = "SELECT page_id,page_title FROM $templatelinks, $page WHERE tl_title=" .
	$dbr->addQuotes($video['vid_page_db_key']) . " AND tl_namespace = " . NS_VIDEO . " AND tl_from=page_id";
	$sql = $dbr->limitResult($sql, 500, 0);
	$res = $dbr->query($sql, "removeUnsupportedVideos");

	$articles = array();
	while ($row = $dbr->fetchObject($res)) {
		$article = get_object_vars($row);
		$articles[] = $article;
	}
	return $articles;
}

function error($string) {
	echo "ERROR: $string";
}

function debug($string) {
	if (false) {
		echo $string;
	}
}
