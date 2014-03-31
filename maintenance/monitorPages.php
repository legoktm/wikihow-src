<?
function format_data($mysql_timestamp){    preg_match('/(\d{4})(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})/', $mysql_timestamp,$pieces);    $unix_timestamp = mktime($pieces[4], $pieces[5], $pieces[6], $pieces[2], $pieces[3], $pieces[1]);
    return($unix_timestamp);
}

require_once( "commandLine.inc" );

$dbw =& wfGetDB( DB_SLAVE );

function throttle() {
		$x =  rand(0, 15);
		if ($x == 10) {
			$s = rand(1,30);
			echo "sleeping for $s seconds\n";
			sleep($s);
		}
}

function getTitles($num = 1000) {
	$sql = "select page_namespace, page_title from google_monitor, page where page_id=gm_page and gm_active=1;";
	
	$dbr =& wfGetDB( DB_SLAVE );
	$titles = array();
	$res = $dbr->query($sql);
     while( $row = $dbr->fetchObject( $res ) ) {
            $titles[] = Title::makeTitle($row->page_namespace, $row->page_title);
     }
	$dbr->freeResult( $res );
	return $titles;
}

function checkGoogle($query, $page_id, $dbw) {
		$url = "http://www.google.com/search?q=" . urlencode($query) . "&num=100";
		$contents = file_get_contents($url);
		$matches = array();
        $preg = "/href=\"http:\/\/[^\"]*\" class=l */ ";
		preg_match_all($preg, $contents, $matches);
	
echo "checking $query\n";
		$count = 0;
		$results = array();
		$found = false;
		foreach ($matches[0] as $url) {
			$url = substr($url, 6, strlen($url) - 7);
				// check for cache article
			if (strpos($url, "/search?q=cache") !== false || strpos($url, "google.com/") !== false) 
				continue;
			$count++;		
			$domain = str_replace("http://", "", $url); 
			$domain = substr($domain, 0, strpos($domain, '/'));
			if (strpos($domain, "wikihow.com") !== false) {
				$sql = "INSERT INTO google_monitor_results (gmr_page, gmr_position) VALUES ($page_id, $count);";
				$dbw->query($sql);
				$found = true;
				break;
			}
		}		
		if (!$found) {
			$sql = "INSERT INTO google_monitor_results (gmr_page, gmr_position) VALUES ($page_id, 0);";
			$dbw->query($sql);
		}
	}
	// load queries from the database
	$titles = getTitles();	
	foreach($titles as $title) {
		checkGoogle ($title->getText(), $title->getArticleID(), $dbw);
		throttle();
	}

?>

