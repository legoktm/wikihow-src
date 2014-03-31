<?php
//
// Check the functioning of our CDN using the Pingdom API
//

require_once('commandLine.inc');

define('PINGDOM_API_STATUS_OK', 0);

define('BAD_CHECK_LOCAL', 300);
define('BAD_CHECK_REMOTE', 500); 
define('BAD_CHECK_DEFAULT', 300);

$badCheckTimes = array( 
		"Stockholm, Sweden" 		=> BAD_CHECK_REMOTE,  
		"Montreal, Canada" 			=> BAD_CHECK_LOCAL, 
		"London, UK" 				=> BAD_CHECK_REMOTE, 
		"Dallas 4, TX" 				=> BAD_CHECK_LOCAL, 
		"Herndon, VA" 				=> BAD_CHECK_LOCAL, 
		"Houston 3, TX" 			=> BAD_CHECK_LOCAL, 
		"Amsterdam 2, Netherlands" 	=> BAD_CHECK_REMOTE, 
		"London 2, UK" 				=> BAD_CHECK_REMOTE, 
		"Dallas 5, TX" 				=> BAD_CHECK_LOCAL, 
		"Dallas 6, TX" 				=> BAD_CHECK_LOCAL, 
		"Los Angeles, CA" 			=> BAD_CHECK_LOCAL, 
		"Frankfurt, Germany" 		=> BAD_CHECK_REMOTE, 
		"Atlanta, Georgia" 			=> BAD_CHECK_LOCAL, 
		"New York, NY" 				=> BAD_CHECK_LOCAL, 
		"Chicago, IL" 				=> BAD_CHECK_LOCAL, 
		"Copenhagen, Denmark" 		=> BAD_CHECK_REMOTE, 
		"Tampa, Florida" 			=> BAD_CHECK_LOCAL, 
		"Seattle, WA" 				=> BAD_CHECK_LOCAL, 
		"Washington, DC" 			=> BAD_CHECK_LOCAL, 
		"Madrid, Spain" 			=> BAD_CHECK_REMOTE, 
		"Las Vegas, NV" 			=> BAD_CHECK_LOCAL, 
		"Denver, CO" 				=> BAD_CHECK_LOCAL, 
		"San Francisco, CA" 		=> BAD_CHECK_LOCAL, 
		"Paris, France" 			=> BAD_CHECK_REMOTE, 
		"Manchester, UK" 			=> BAD_CHECK_REMOTE,
); 

$maxBadChecks = 2;

// From https://www.pingdom.com/services/api-documentation-rest/
function pingdomAPIcall($what) {
	$curl = curl_init();
	  // Set target URL -- Get Probe Server List
	curl_setopt($curl, CURLOPT_URL, "https://api.pingdom.com/api/2.0/$what");
	  // Set the desired HTTP method (GET is default, see the documentation for each request)
	curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "GET");
	  // Set user (email) and password
	curl_setopt($curl, CURLOPT_USERPWD, WH_PINGDOM_USERNAME . ":" . WH_PINGDOM_PASSWORD);
	  // Add a http header containing the application key (see the Authentication section of this document)
	curl_setopt($curl, CURLOPT_HTTPHEADER, array("App-Key: " . WH_PINGDOM_API_KEY));
	  // Ask cURL to return the result as a string
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
 
	  // Execute the request and decode the json result into an associative array
	$response = json_decode(curl_exec($curl),true);
 
	  // Check for errors returned by the API
	if (isset($response['error'])) {
		print "Error: " . $response['error']['errormessage'] . "\n";
		exit;
	}
 
 	return $response;
}

//$probes = pingdomAPIcall('probes');
$checks = pingdomAPIcall('checks');
print_r($checks);





// NOTE THE SOAP API IS DEPRECATED AND NOW GONE. THIS CODE BELOW IS BAD.

$body = "";
/*
$rawRequest->from 			= date("c", time() - 3600);
$rawRequest->to 			= date("c");
$rawRequest->resultsPerPage = 50;
$rawLoc						= array();
foreach ($list_of_checks as $check_name) {
	$rawRequest->checkName = $check_name;
	$rawdata = array();
	for ($i = 0; $i < 3; $i++) {
		$rawRequest->pageNumber = $i + 1;
		$rawdata = array_merge($rawdata, $client->Report_getRawData(WH_PINGDOM_API_KEY, $sessionId, $rawRequest)->rawDataArray); 
	}
	foreach($rawdata as $r) {
		if (!isset($rawLoc[$r->location]))
			$rawLoc[$r->location] = array();
		$rawLoc[$r->location][] = $r;
	}
	foreach ($rawLoc as $location=>$vals) {
		$bad = 0;
		$badCheckTime = isset($badCheckTimes[$location]) ? $badCheckTimes[$location] : BAD_CHECK_DEFAULT; 
		foreach ($vals as $r ) {
			if ($r->responseTime > $badCheckTime)
				$bad++;
		}
		if ($bad > $maxBadChecks) {
			$body .= "<h3>$check_name / $location has $bad responses over $badCheckTime ms </h3> ";// . print_r($vals, true);
			$body .= "<table width='80%' align='center'>";
			$i = 0;
			foreach ($vals as $r) {
				$c = "";
				if ($i % 2 == 1)
					$c = "style='background-color: #eee;'" ;
				if ($r->responseTime > $badCheckTime) 
					$body .= "<tr><td $c>{$r->checkTime}</td><td $c>{$r->checkState}</td><td $c><b>{$r->responseTime}</b></td></tr>\n";
				else
					$body .= "<tr><td $c>{$r->checkTime}</td><td $c>{$r->checkState}</td><td $c>{$r->responseTime}</td></tr>\n";
				$i++;
			}
			$body .= "</table>";
		}
	}
}
*/

if ($body != "")  {
	print "sending mail...\n";
	$reportsEmail = WH_ALERTS_EMAIL;
	mail($reportsEmail, "Pingdom checks for " . date("r"), $body, "Content-type: text/html;\nFrom: $reportsEmail;");
}
