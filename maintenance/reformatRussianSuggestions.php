<?
	require_once('commandLine.inc');
	$f = file_get_contents($argv[0]);
	$lines = split("\n", $f);
	foreach($lines as $line) {
		$parts = split(";", $line);
		$title = array_shift($parts);
		echo "{$title}\t" . implode($parts, ";") . "\n";
	}
