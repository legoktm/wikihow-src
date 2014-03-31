<?
function format_data($mysql_timestamp){    preg_match('/(\d{4})(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})/', $mysql_timestamp,$pieces);    $unix_timestamp = mktime($pieces[4], $pieces[5], $pieces[6], $pieces[2], $pieces[3], $pieces[1]);
    return($unix_timestamp);
}

require_once( "commandLine.inc" );

$cond = " AND page_id not In (SELECT gi_page FROM google_indexed WHERE datediff(now(), gi_timestamp) < 7)";
//check pages at most once a week

if (isset($args[0]) )
	$sql = "select page_id from page where page_is_redirect=0 and page_namespace=0 $cond order by rand() limit {$args[0]};";
else
	$sql = "select page_id from page where page_is_redirect=0 and page_namespace=0 $cond order by rand() limit 1000;"; // default 500

$wgUser = new User();
$wgUser->setID(1236204);

function throttle() {
        $x =  rand(0, 15);
        if ($x == 10) {
            $s = rand(1,30);
            echo "sleeping for $s seconds\n";
            sleep($s);
        }
}

$gTotalAPIRequests = 0; 

function checkGoogle($query, $a_url) {
	global $gTotalAPIRequests;
	$a_url = urldecode($a_url);
	$start = 0;
	$position = 0;
echo "checking $query\n";
	$found = false;
	while ($start < 90 && !$found) {
//		echo "searching at $start\n";
		$results = SearchEngineAPI::serp($query, $start);
		$gTotalAPIRequests++;
		$i = $start + 1;
		if ($results == null) {
			"Error: null API results or $query ($gTotalAPIRequests)\n";
			continue;
		}
		foreach ($results[0] as $r) {
			//echo "{$r['URL']} $a_url\n";
			if ($a_url == $r['URL'] ) {
//				echo "found position at $i\n";
				$position = $i;
				$found = true;
				break;	
			}
			$i++;
		}
		$start += 10;
	}
	return $position;	
}

//$t = Title::newFromText("Tell a Guy You Know He's Lying to You");
//checkGoogle(wfMsg('howto', $t->getText()), "http://www.wikihow.com/" . $t->getPartialURL());
//exit;

$dbr =& wfGetDB( DB_SLAVE );

	$titles = array();
	$res = $dbr->query($sql);
     while( $row = $dbr->fetchObject( $res ) ) {
            $titles[] = Title::newFromID($row->page_id); 
     }
	$dbr->freeResult( $res );

$dbw =& wfGetDB( DB_SLAVE );
	
	foreach ($titles as $title) {	
		if ($title == null) {
			echo "error title is null " . print_r($title, true);
			continue;
		}

		$url = "http://www.wikihow.com/" . $title->getPartialURL() ;

		// get age 

		$findAge = true;
		$age = 0;
		if ($findAge) {
			$min = $dbr->selectField('revision', 
					'min(rev_timestamp)',
					 array('rev_page=' .$title->getArticleID())
					);
			$d = format_data($min);
			$diff = time() - $d;
			$age = ceil($diff/60/60/24);
			//echo "$url " . $title->getArticleID() . "  is $age days old..\n";
			$age = " age $age days";
		}
		
		$position = checkGoogle(wfMsg('howto', $title->getFullText()), "http://www.wikihow.com/" . $title->getPrefixedURL());
		$sql = "INSERT INTO google_indexed (gi_page, gi_is_indexed, gi_position) VALUES 
				({$title->getArticleID()}, " . ($position > 0 ?"1" : "0" ) . 
				", $position );" ;
		$dbw->query($sql);

		if ($position > 0) 
			print "indexed: $url $age position $position \n";
		else
			print "not indexed: $url $age\n";
		//throttle();
	}
	
	echo "total api requests $gTotalAPIRequests\n";

?>

