<?php
// Copyright 2009, Google Inc. All Rights Reserved.
//
// Licensed under the Apache License, Version 2.0 (the "License");
// you may not use this file except in compliance with the License.
// You may obtain a copy of the License at
//
//     http://www.apache.org/licenses/LICENSE-2.0
//
// Unless required by applicable law or agreed to in writing, software
// distributed under the License is distributed on an "AS IS" BASIS,
// WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
// See the License for the specific language governing permissions and
// limitations under the License.

/** This code sample checks a keyword to see whether it will get any traffic.*/
require_once('commandLine.inc');
require_once('soapclientfactory.php');

function show_xml($service) {
  echo $service->request;
  echo $service->response;
  echo "\n";
}

function show_fault($service) {
  echo "\n";
  echo 'Fault: ' . $service->fault . "\n";
  echo 'Code: ' . $service->faultcode . "\n";
  echo 'String: ' . $service->faultstring . "\n";
  echo 'Detail: ' . $service->faultdetail . "\n";
}
# Define SOAP headers.
  //'<clientEmail>' . WH_AW_CLIENT_EMAIL . '</clientEmail>' .
$headers =
  '<email>' . WH_AW_EMAIL . '</email>'.
  '<password>' . WH_AW_PASS . '</password>' .
  '<useragent>' . WH_AW_USER_AGENT. '</useragent>' .
  '<developerToken>' . WH_AW_DEV_TOKEN . '</developerToken>' .
  '<applicationToken>' . WH_AW_APP_TOKEN . '</applicationToken>';

# Set up service connection. To view XML request/response, change value of
# $debug to 1. To send requests to production environment, replace
# "sandbox.google.com" with "adwords.google.com".
$namespace = 'https://adwords.google.com/api/adwords/v13';
$estimator_service = SoapClientFactory::GetClient(
  $namespace . '/TrafficEstimatorService?wsdl', 'wsdl');
$estimator_service->setHeaders($headers);
$debug = 0;

# Create keyword structure.

$dbr = wfGetDB(DB_SLAVE);

$cats = array("Cars & Other Vehicles", "Computers and Electronics", "Education and Communications", "Family Life", "Arts and Entertainment", "Finance and Business", "Food and Entertaining", "Health", "Hobbies and Crafts", "Holidays and Traditions", "Home and Garden", "Other", "Personal Care and Style", "Pets", "Pets and Animals", "Philosophy and Religion", "Relationships", "Sports and Fitness", "Travel", "Work World", "Youth");


foreach ($cats as $c) {
	$vol = 0;
	$res = $dbr->query("select st_title, st_id from suggested_titles where st_used=0 and st_category = " . $dbr->addQuotes($c) . " and st_traffic_volume < 0 limit 100");
	$map = array();
	echo "Doing $c\n";
	while ($row = $dbr->fetchObject($res)) {
	
		$t = Title::makeTitle(NS_MAIN, $row->st_title);
		echo "\tChecking {$t->getText()}\n";		
		# Create keyword structure.
			$keyword =
		  	'<keywordText>' . htmlspecialchars($t->getFullText()) . '</keywordText>' .
		  		'<keywordType>Exact</keywordType>' .
		  		'<language>en</language>';
		
			# Check keyword traffic.
				$request_xml =
		  			'<checkKeywordTraffic>' .
		  			'<requests>' . $keyword . '</requests>' .
		  			'</checkKeywordTraffic>';
		
			try {
				$estimates = $estimator_service->call('checkKeywordTraffic', $request_xml);
				$estimates = $estimates['checkKeywordTrafficReturn'];
				if ($debug) show_xml($estimator_service);
				if ($estimator_service->fault) show_fault($estimator_service);
			} catch (Exception $e) {
			}
		
			# Display estimate.
			switch($estimates) {
				case 'HasTraffic':
					$map[$row->st_id] = 2;
					$val = 2;
					$vol++;
					break;
				case 'VeryLowTraffic': 
					$map[$row->st_id] = 1;
					$val = 1;
					break;
				default: 
					$map[$row->st_id] = 0;
					$val = 0;
					break;
			}
			$id = $row->st_id;
			$sql = "update suggested_titles set st_traffic_volume=$val where st_id=$id;\n";
			$dbw = wfGetDB(DB_MASTER);
			$dbw->query($sql);
			
	}
	echo "Got $vol with volume 2\n";
}


