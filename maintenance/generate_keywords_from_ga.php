<?php

require_once('commandLine.inc');
require 'analytics.class.php';
require_once('extensions/wikihow/GoogleSearch.php');  
 
  // session_start for caching
  session_start();

	function scrapeGoogle($q) {
		$q = urlencode($q);
		$url = "http://www.google.com/uds/GwebSearch?callback=google.search.WebSearch.RawCompletion&context=0&lstkp=0&rsz=large&hl=en&source=gsc&gss=.com&sig=c1cdabe026cbcfa0e7dc257594d6a01c&q={$q}%20site%3Awikihow.com&gl=www.google.com&qid=124c49541548cd45a&key=ABQIAAAAYdkMNf23adqRw4vVq1itihTad9mjNgCjlcUxzpdXoz7fpK-S6xTT265HnEaWJA-rzhdFvhMJanUKMA&v=1.0";
		$contents = file_get_contents($url);
		#echo $q . "\n";
		$result = preg_match('@unescapedUrl":"([^"]*)"@u',$contents, $matches);
		return $matches[1];
	}  
  try {
      
      // construct the class
      $oAnalytics = new analytics('tderouin', 'rem700sps');
      
      // set it up to use caching
      $oAnalytics->useCache();
      
      #$oAnalytics->setProfileByName('[Google analytics accountname]');
      $oAnalytics->setProfileById('ga:16643416');
      
      // set the date range
      $oAnalytics->setMonth(date('n'), date('Y'));
      // or $oAnalytics->setDateRange('YYYY-MM-DD', 'YYYY-MM-DD');
      
      // print out visitors for given period
      #print_r($oAnalytics->getVisitors());
      
      // print out pageviews for given period
      #print_r($oAnalytics->getPageviews());
      
      // use dimensions and metrics for output
      // see: http://code.google.com/intl/nl/apis/analytics/docs/gdata/gdataReferenceDimensionsMetrics.html
      $results = $oAnalytics->getData(array(   'dimensions' => 'ga:keyword',
                                            'metrics'    => 'ga:visits',
                                            'sort'       => '-ga:visits'));
	$skip = array("(other)", "(not set)", "wikihow", "wiki how");
	array_shift($results);
	foreach ($results as $r=>$c) {
		$r = trim(preg_replace("@^how to@im", "", $r));
		if (in_array($r, $skip)) continue;
    	$l = new LSearch();
    	#$results = $l->googleSearchResultTitles($r);
		#$results = gSearch::query($r);
		$result = scrapeGoogle($r);
		if (empty($result)) {
			echo "{$r}\t(no matches)\n";
			continue;
		}
		$top = Title::newFromURL(str_replace("http://www.wikihow.com/", "", $result));
		if (!$top) {
			echo "Can't build title out of {$result} - query: {$r}\n";
			continue;
		}
		echo "$r\t{$top->getText()}\n";
	}
      
  } catch (Exception $e) { 
      echo 'Caught exception: ' . $e->getMessage(); 
  }
?>
