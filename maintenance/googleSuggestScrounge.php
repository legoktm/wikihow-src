<?
require_once( "commandLine.inc" );


$base_url = "http://google.com/complete/search?hl=en&js=true&q=";

//queue of terms
$terms = array();

//results
$search_results = array();

$base_terms = array();

//////////////////////////
function throttle() {
        $x =  rand(0, 100);
        if ($x == 15) {
            $s = rand(1,30);
            //error_log("sleeping for $s seconds\n");
            sleep($s);
        }
}

function cleanup($str) {
	$str = str_replace("\"", "", $str);
	$str = str_replace("(", "", $str);
	$str = str_replace(")", "", $str);
	$str = str_replace(", ", ",", $str);
	return $str;
}

function findresults($term) {
	global $terms, $search_results, $base_terms, $base_url;
	$url = $base_url . urlencode("how to " . $term);
	$url = $base_url . urlencode($term);
	$results = file_get_contents($url);
	if ($results == "") {
		error_log("error getting results, throttling\n");
		array_push($terms, $term);
		throttle();
		return;
	}

	$x = strpos($results, "new Array");
	if ($x === false) {
		error_log("got $results for $url");
		return;
	}
	$x += strlen("new Array");
	$y = strpos($results, "new Array", $x+1);
	$urls = substr($results, $x, $y-$x-2);
	$urls = cleanup($urls);;

	if ($urls == "")
		return;
	// trim the results
	$url_array = explode(",", $urls);
	
	$x = $y + strlen("new Array");
	$y = strpos($results, "new Array", $x+1);
	$results = substr($results, $x, $y-$x-2);
	$results  = cleanup($results);
	$results = preg_replace('/([0-9]),([0-9])/','$1$2',$results); 
	$results = str_replace(" results", "", $results);
	$count_array = explode(",", $results);
	if (sizeof($url_array) == 10) {
		//print("Adding terms for $term\n");
		foreach ($base_terms as $b) {
			if (strlen($term . $b) < 6) 
				array_push($terms, $term . $b);
		}
		// add a space only if it isn't already there
		if (substr($term, strlen($term)-1, 1) != ' ') {
			array_push($terms, $term . " ");
			//printf("Adding space to +$term+ -" . substr($term, strlen($term)-1, 1) . "-\n");
		}
		//print("Terms are now " . sizeof($terms) . " long\n");
	}
//	print_r($array);
	for($i = 0; $i < sizeof($url_array); $i++) {
		$a = $url_array[$i];
		$x = array();
		$x[0] = $term;
		$x[1] = $a;
		$x[2] = $count_array[$i];
		$search_results[] = $x;
	}
	throttle();
}
//////////////////////////

function getBaseTerms() {
	$results = array();
	for ($i = 97; $i <= 122; $i++) {
	
		$results[] = chr($i);
	}
	return $results;
}

//THE DRIVER
if (isset($args[0])) {
	$terms[] = $args[0];
} else {
	// push the first letter of the alphabet
	$terms = getBaseTerms();
}

$base_terms = getBaseTerms();

while ($term = array_pop($terms)) {
	findresults($term);
}

//print_r(array_unique($search_results));
//output tab- delimited text
foreach ($search_results as $x) {
	print("{$x[0]}\t{$x[1]}\t{$x[2]}\n");
}


?>
