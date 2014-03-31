<?

	require_once('commandLine.inc');

	$lines = split("\n", file_get_contents($argv[0]));

	function outputLine($s, $intro) {
		global $wgOut;
		$s = $wgOut->parse(preg_replace("@^#[ ]+@", "", $s));
		if ($intro)
			echo "\t\t\t<introduction>\n";
		else
			echo "\t\t\t<step>\n";
		preg_match("@<img[^>]*>@", $s, $matches);
		if (sizeof ($matches) > 0) {
			echo "\t\t\t\t<images>\n";
			foreach ($matches as $m) {
				$m = preg_replace('@.*src="([^"]*)".*@', '$1', $m);
				if (strpos($m, "LinkFA-star.jpg") !== false)
					continue;
				echo "\t\t\t\t\t<image>$m</image>\n";
			}
			echo "\t\t\t\t</images>\n";
		}
		$s = trim(strip_tags($s));
		echo "\t\t\t\t<text><![CDATA[$s]]></text>\n";
		# check for images
		if ($intro)
			echo "\t\t\t</introduction>\n";
		else
			echo "\t\t\t</step>\n";
		
	}
	echo "<wikihowmedia>\n";
	foreach ($lines as $l) {
		$tokens = split("\t", $l);
		$t = Title::newFromURL($tokens[0]);
		if (!$t) continue;
		$r = Revision::newFromTitle($t);
#echo $r->getText() . "\n"; 
#print_r($matches); exit;	
		echo "\t<article>\n";	
		echo "\t\t<title>How to {$t->getText()}</title>\n";	
		echo "\t\t<tags>" . trim($tokens[3]) . ", {$tokens[4]}</tags>\n";
		echo "\t\t<categories>\n";
		echo "\t\t\t<category type=\"mainmenu\">" . htmlspecialchars(trim($tokens[1])) . "</category>\n";
		$sub = trim($tokens[2]);
		if ($sub != "None") 
			echo "\t\t\t<category type=\"featured\">{$sub}</category>\n";
		echo "\t\t</categories>\n";
		echo "\t\t<content>\n";
		$intro = preg_replace("@== Steps(.|\n)*@im", "", $r->getText());
		outputLine($intro, true);
		echo "\t\t<steps>\n";
		preg_match("@== Steps ==(.|\n)*^==@imU", $r->getText(), $matches);
		$steps = split("\n", $matches[0]);
		foreach ($steps as $s) {
			if (preg_match("@^#@", $s)) {
				outputLine($s, false);
			}
		}
		echo "\t\t</steps>\n";
		echo "\t\t</content>\n";
		echo "\t</article>\n";	
	
	}
	echo "</wikihowmedia>\n";
