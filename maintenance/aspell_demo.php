<?

require_once('commandLine.inc');

$bad = 0;
function spellCheckWord($word) {
    global $pspell, $bad;
    $autocorrect = TRUE;
	$ignore_words = array("wikihows", "blog", "online", "ipod", "nano");

    // Take the string match from preg_replace_callback's array
    $word = $word[0];
   
    // Ignore ALL CAPS, and numbers
    if (preg_match('/^[A-Z]*$/',$word)) return;
    if (preg_match('/^[0-9]*$/',$word)) return;
	if (in_array(strtolower($word), $ignore_words)) return;

    // Return dictionary words
    if (pspell_check($pspell,$word)) {
		// this word is OK
        return;
	}

	echo "Bad word $word - ";
	$bad++;
	$suggestions = pspell_suggest($pspell,$word);
	if (sizeof($suggestions) > 0) {
		if (sizeof($suggestions) > 5) {
			echo implode(",", array_splice($suggestions, 0, 5)) . "\n";
		} else {
			echo implode(",", $suggestions) . "\n";
		}
	} else {
		echo "no suggestions\n";	
	}
   
	
}

function spellCheck($string) {
    return preg_replace_callback('/\b(\w|\')+\b/','spellCheckWord',$string);
}
		

$t = null;
if (isset($argv[0])) {
	$t = Title::newFromURL(urldecode($argv[0]));
} else {
	$rp = new RandomPage();
	$t = $rp->getRandomTitle();
}

echo "Doing {$t->getFullURL()}\n";
$r = Revision::newFromTitle($t);
if (!$r) {
	echo "can't get revision for this bad boy\n";
}

$text = $r->getText();

$newtext = WikihowArticleEditor::textify($text, array('remove_ext_links'=>1));
echo "text ...$newtext\n\n";

$pspell = pspell_new('en','american','','utf-8',PSPELL_FAST);
spellCheck($newtext);
if ($bad == 0) {
	echo "No misspellings\n";
}

