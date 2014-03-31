<?
function format_data($mysql_timestamp){    preg_match('/(\d{4})(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})/', $mysql_timestamp,$pieces);    $unix_timestamp = mktime($pieces[4], $pieces[5], $pieces[6], $pieces[2], $pieces[3], $pieces[1]);
    return($unix_timestamp);
}

require_once( "commandLine.inc" );

$dbw =& wfGetDB( DB_SLAVE );

$domains = array();
$domains['wikihow.com'] = 1;

function throttle() {
		$x =  rand(0, 50);
		if ($x == 10) {
			$s = rand(1,10);
			echo "sleeping for $s seconds\n";
			sleep($s);
		}
}

function getSuggestions() {
	global $argv;
	$f = file_get_contents($argv[0]);
	$results = split("\n", $f);
	return $results;
}

function checkGoogle($query, $page_id, $domains, $dbw) {
		$url = "http://www.google.com/search?q=" . urlencode($query) . "&num=100";
		$contents = file_get_contents($url);
		$matches = array();
		$preg = "/href=\"http:\/\/[^\"]*\"*/";
		$preg = "/href=\"http:\/\/[^\"]*\" class=l */ ";
		preg_match_all($preg, $contents, $matches);

//print_r($matches); exit;	
#echo "checking $query\n";
		$count = 0;
		$results = array();
		foreach ($matches[0] as $url) {
			$url = substr($url, 6, strlen($url) - 7);
			$url = str_replace('" class=', '', $url);

				// check for cache article
			if (strpos($url, "/search?q=cache") !== false || strpos($url, "google.com/") !== false) 
				continue;
			$count++;		
			$domain = str_replace("http://", "", $url); 
			$domain = substr($domain, 0, strpos($domain, '/'));
			foreach($domains as $d=>$index) {
				if (strpos($domain, $d) !== false && !isset($results[$d])) {
					$r = array();
					$r['domain'] = $d;
					$r['position'] = $count;
					$r['url'] = $url;
					$results[$d] = $r;
				}
			}
		}
		if (sizeof($matches) == 0) {
			echo "size of matches is 0:-------------\n\n " . $contents	. "\n-------------\n\n ";
			continue;
		}		

		foreach ($results as $r) {	
			$sql = "INSERT INTO serps.chris_serps(gs_page, gs_query, gs_position, gs_domain, gs_url) 
				VALUES ($page_id,
						{$dbw->addQuotes($query)},
						{$r['position']},
						'{$r['domain']}',
						{$dbw->addQuotes($r['url'])}
					);" ;
			$dbw->query($sql);
		}
		#echo "adding " . sizeof($results) . " for $query " . print_r($results, true) . "\n";

}
$suggestions = getSuggestions();
foreach ($suggestions as $s) {
	checkGoogle($s, 0, $domains, $dbw);
}

// get dates
$dbr = wfGetDB(DB_SLAVE);
$res = $dbr->query('select distinct(substr(gs_timestamp, 1,10)) as date from serps.chris_serps');
$dates = array();
while ($row = $dbr->fetchObject($res)) {
	$dates[]= $row->date;
}

echo "<table><tr><td>Query</td>";
foreach ($dates as $d) {
	echo "<td>{$d}</td>";
}
echo "</tr>";

$results = array();
foreach ($dates as $d) {
	$res = $dbr->query("select gs_query, min(gs_position)as m from serps.chris_serps where datediff('{$d}', gs_timestamp) = 0 group by gs_query");
	while ($row = $dbr->fetchObject($res)) {
		if (!isset($results[$row->gs_query]))
			$results[$row->gs_query] = array();
		$results[$row->gs_query][$d] = $row->m;
	}
}

foreach ($suggestions as $s) {
	if (trim($s) == "") continue;
	echo "<tr><td>$s</td>";
	foreach ($dates as $d) {
		echo "<td>";
		if (!isset($results[$s][$d]))
			echo "-";
		else
			echo $results[$s][$d];
		echo "</td>";
	}
	echo "</tr>\n";
}	
echo "</table>\n";
?>

