<?
/*
*  This script imports urls into the babelfish system given a filename on the commandline
*/

require_once('commandLine.inc');

$wapDB = WAPDB::getInstance(WAPDB::DB_BABELFISH);

$file = $argv[0];
$simulate = false;
$wapDB->importArticles($file, $simulate);
