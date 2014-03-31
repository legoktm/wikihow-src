<?
require_once('commandLine.inc');
require_once("$IP/extensions/wikihow/titus/Titus.class.php");

$statsToCalc = TitusConfig::getDailyEditStats();
$titus = new TitusDB(true);
/*
$titus->calcStatsForAllPages($statsToCalc);*/
$ids = array(163806);
$titus->calcStatsForPageIds($statsToCalc, $ids);

/*$dailyEditStats = TitusConfig::getDailyEditStats();
$titus->calcLatestEdits($dailyEditStats);*/
