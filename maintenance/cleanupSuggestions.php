<?
/*
* maintenance/bad_words.txt
* maintenance/acronyms.txt
*
* php cleanupSuggestions.php bad_words.txt acronyms.txt [optional limit] 
*/

	require_once('commandLine.inc');
	require_once('EditPageWrapper.php');
	$dbw = wfGetDB(DB_MASTER);
    $bad_re = "";

#echo "getting bad words from file {$argv[0]}\n";
    $f = file_get_contents($argv[0]);
    $lines = split("\n", trim($f));
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line == "") continue;
        if ($bad_re != "") $bad_re .= "|";  
        $bad_re .= "\b$line\b";
    }

#echo "getting acronyms\n";
    $f = file_get_contents($argv[1]);
    $lines = split("\n", trim($f));
	$ac_re = array();
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line == "") continue;
		$tokens = split(",", $line);
		$ac_re[] = "/\b" . $tokens[0] . "\b/i";
		if (sizeof($tokens) > 1) {
			$ac_re2[] = trim($tokens[1]);	
		} else
			$ac_re2[] = strtoupper($tokens[0]);
    }

	
#echo "going over suggestions\n";
	$dbr = wfGetDB(DB_SLAVE);
	$options = array("LIMIT"=>500000);
	$clauses = array("last_processed IS NULL or last_processed = '0000-00-00 00:00:00'");
	if (isset($argv[2])) 
		$options = array("LIMIT" => $argv[2]); // 'ORDER BY'=>'rand()');
	//$res = $dbr->select('goog', array('id', 'raw_title'), $clauses, "maintenance:cleanupSuggestions", $options);
	$res = $dbr->select('test_table', array('id', 'keyword'), $clauses, "maintenance:cleanupSuggestions", $options);
	$updates = array();
	$count = 0;
#echo "got res\n";
	while ($row = $dbr->fetchObject($res)) {
        //$title = substr($row->raw_title, 1, strlen($row->raw_title) - 2);
	
		//echo $row->raw_title . "\n";
        $title = preg_replace('/^how to[ ]?/', '', $row->keyword);
		$title = preg_replace('/^\"/', '', $title);
		$title = preg_replace('/\"$/', '', $title);
		#echo $title; exit;
		$excluded = 0;
		if (preg_replace('/\W/', '', $title) == '') {
			$updates[] = array($row->id, "", 1, "");
			continue;
		}
#echo "checking bad_re for {$title}, {$row->id}\n";
        if (preg_match("/{$bad_re}/i", $title, $matches)) {
            #echo "excluding $title because of {$matches[0]}\n";
			$excluded = 1;
        }
		if (trim($title) == "") continue;
#echo "formatting title bad_re for {$title}, {$row->id}\n";
		$title = EditPageWrapper::formatTitle($title);
/*		try {
			$title = 'How to ' . preg_replace($ac_re, $ac_re2,  $title);	
		} catch (Exception $e) {
			echo "caught e $e\n";
			print_r($ac_re);
			print_r($ac_re2);
			exit;	
		}*/
#echo "generating key for for {$title}, {$row->id}\n";
		$key = generateSearchKey($title);
		$updates[] = array($row->id, $title, $excluded, $key);
		if ($count > 0 && $count % 100 == 0) {
			#echo "current at $count\n";
		}
		$count++;
		//echo "updating '{$row->raw_title}' to $title\n";
	}
	$dbr->freeResult($res);
#echo "updating results\n";
	$count = 0;
	$sql = "";
	foreach ($updates as $u) {
		#echo "updating {$u[0]}, {$u[1]}, {$u[2]}, {$u[3]}\n";
		//$updateEntry($u[0], $u[1], $u[2], $u[3]);
		$sql .= "UPDATE test_table set clean_title=" . $dbw->addQuotes($u[1]) . ", exclude={$u[2]}, tskey = " . $dbw->addQuotes($u[3]) . ", last_processed=now() where id={$u[0]};\n";
		#if ($count > 0 && $count % 100 == 0) {
			#echo "current at $count\n";
			echo $sql;
			if ($dbw->doQuery($sql)) echo "query ok\n"; else echo "Query NOT OK\n";
			$sql = "";
		#}
		#$count++;
	}	
	#echo "current at $count\n";
	#echo $sql;
	#if ($dbw->doQuery($sql)) echo "query ok\n"; else echo "Query NOT OK\n";
?>
