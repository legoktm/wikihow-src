<?
	require_once('commandLine.inc');
	$h5e = new Html5editor();
	$html = file_get_contents($argv[0]); 

	$newtext = $h5e->convertHTML2Wikitext($html, "");
	echo $newtext; 

