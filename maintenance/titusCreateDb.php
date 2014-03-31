<?php
require_once('commandLine.inc');
require_once("$IP/extensions/wikihow/titus/Titus.class.php");
$titus = new TitusDB(true);
$allStats = array('Title' => 1, 'PageId' => 1, 'LanguageCode' => 1, 'Photos' => 1);
$titus->calcStatsForAllPages($allStats);
