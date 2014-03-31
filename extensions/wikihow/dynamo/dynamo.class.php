<?php

/***
 * 
 * This is a class that is used to interface with 
 * Amazon's DynamoDB. Current it is only used to 
 * take the data from Titus and add it into
 * DynamoDB.
 * 
 ***/

require_once("$IP/extensions/wikihow/common/sdk/sdk.class.php");

class dynamo {
	var $dbr;
	var $maxWrites; //Write throughput as set in the DynamoDB management console
	var $maxReads;  //Read throughput as set in the DynamoDB management console
	var $itemSize;  //Size of 1 rows worth of data
	var $dynamodb;
	var $batchArray;
	var $batchCount;
	var $maxErrors; //maximum number of errors allowed from dynamo before we quit the run
	var $maxTime;
	var $table_name; //which table on the dynamo server to use
	
	function __construct() {
		global $IP;
		
		if(IS_PROD_EN_SITE)
			$this->table_name	= 'live_titus_intl';
		else
			$this->table_name	= 'dev_titus_intl';
		
		echo "Using " . $this->table_name . " in DynamoDB\n";
		
		$this->dbr = wfGetDB(DB_SLAVE);
		$this->maxWrites = 7.5;
		$this->maxReads = 10;
		$this->itemSize = 800;
		$this->maxErrors = 10;
		$this->maxTime = 60*60*12; //12 hours in seconds
		
		$this->dynamodb = new AmazonDynamoDB();
		$this->dynamodb->disable_ssl();
	}
	
	public function insertDaysData($datestamp = null) {
		$articles = array();
		$this->batchArray = array();
		$this->batchCount = 0;
		
		echo "Starting " . __METHOD__ . " at " . wfTimestamp(TS_MW) . "\n";
		
		$beginTime = microtime(true);
		$titus = new TitusDB();
		if($datestamp == null)
			$rows = $titus->getRecords();
		else
			$rows = $titus->getOldRecords ($datestamp);
		
		foreach($rows as $row) {
			$newRow = (array)$row;
			$newRow['page_id_language'] = $newRow['ti_language_code'] . $newRow['ti_page_id']; //need this to actually be an integer for DynamoDB
			$articles[] = $newRow;
		}
		
		echo "Putting " . count($articles) . " rows into dynamodb\n";
		
		$this->batchArray[] = array();
		$this->batchCount++;
		foreach($articles as $articleData) {
			$this->addDataToBatch($articleData, "PutRequest");
		}
		
		echo "Getting ready to process " . $this->batchCount . " batches\n\n";
		$errorCount = 0;
		for($i = 0; $i < $this->batchCount; $i++) {
			$startTime = microtime(true);
			
			$this->processBatch($this->batchArray[$i], "PutRequest", $errorCount);
			$finishTime = microtime(true);
			
			echo "batch #{$i} of {$this->batchCount} " . ($finishTime - $startTime) . "s, ";
			if($errorCount >= $this->maxErrors) {
				echo "Too many errors found. Stopping this run.\n\n";
				mail('gershon@wikihow.com', 'Dynamo Quitting', 'Quitting Dynamo run...too many errors.');
				break;
			}
			while($finishTime - $startTime < 1) {
				//need to wait till a second is up.
				$finishTime = microtime(true);
			}
			$currentTime = microtime(true);
			if($currentTime - $beginTime > $this->maxTime) {
				echo "Taking too long. Stopping this run.\n\n";
				mail('gershon@wikihow.com', 'Dynamo Quitting', 'Quitting Dynamo run...taking too long.');
				break;
			}
		}
		
		echo "\nFinished " . __METHOD__ . " at " . wfTimestamp(TS_MW) . " for a total time of " . ($currentTime - $beginTime) . " seconds.\n";
	}
	
	private function addDataToBatch(&$data, $requestType) {
		
		$attributes = $this->dynamodb->attributes($data);

		$this->addAttributesToBatch($attributes, $requestType);
	}
	
	private function addAttributesToBatch(&$attibuteArray, $requestType) {
		if(count($this->batchArray[$this->batchCount - 1]) >= $this->maxWrites) {
			$this->batchArray[] = array();
			$this->batchCount++;
		}
		
		$this->batchArray[$this->batchCount - 1][] = array(
			$requestType => array(
				'Item' => $attibuteArray
			)
		);
	}
	
	private function processBatch(&$batch, $requestType, &$errorCount) {
		
		try{
			$response = $this->dynamodb->batch_write_item(
				array('RequestItems' => array(
					$this->table_name => $batch
				) )
			);
		} catch(Exception $e) {
			mail('alerts@wikihow.com', 'dynamo error', print_r($e->getMessage(), true));
			exit;
		}
		
		$unprocessedItems = $response->body->UnprocessedItems->to_array();
		
		if($unprocessedItems->count() > 0) {
			echo "There are unprocessed items in this batch. Adding them back into a queue\n\n";
			$this->handleUnprocessedItems($unprocessedItems, $requestType);
			$errorCount++;
		}
		
		if($response->body->message == "The security token included in the request is expired") {
			echo "Resetting credentials, ";
			$this->dynamodb->refresh_sts_credentials();
			$errorCount++;
		}

		// Check for success...
		if ($response->isOK())
		{
			//echo "The data has been added to the table.";
		}
			else
		{
			print_r($response->body);
			print_r($repsonse->body->message);
		}
	}
	
	private function handleUnprocessedItems($unprocessedItems, $requestType) {
		$this->batchArray[] = array();
			$this->batchCount++;
		foreach($unprocessedItems['page_titus']['page_titus'] as $index => $value) {
			$this->addAttributesToBatch($value[$requestType]['Item'], $requestType);
		}
		
	}	
}
