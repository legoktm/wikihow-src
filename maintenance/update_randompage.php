<?

require_once('commandLine.inc');

global $IP;
require_once("$IP/extensions/wikihow/Randomizer.body.php");

Randomizer::processAllArticles();

