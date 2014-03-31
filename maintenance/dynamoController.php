<?
require_once('commandLine.inc');
require_once("$IP/extensions/wikihow/dynamo/dynamo.class.php");
require_once("$IP/extensions/wikihow/titus/Titus.class.php");

$dynamo = new dynamo();

if($argv[0] == null)
	$dynamo->insertDaysData();
else {
	$datestamp = $argv[0];
	if(preg_match('@[^0-9]@', $datestamp)) {
		echo "Expected argument should be a datestamp (yyyymmdd)\n";
		exit;
	}
	elseif(strlen($datestamp) != 8) {
		echo "Expected argument should be a datestamp (yyyymmdd)\n";
		exit;
	}
	else{
		echo "Looks like you're trying to grab all the titus data for the given date (yyyymmdd) {$datestamp}.\n";
		echo "Is this correct? (y/n)\n";
		$response = trim(fgets(STDIN));
		if($response == "y") {
			$dynamo->insertDaysData($datestamp);
		}
		else {
			echo "Ok, getting outta here!\n";
			exit;
		}
	}
}


