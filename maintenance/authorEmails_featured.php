<?
require_once("commandLine.inc");

$t1 = time();
echo "Starting AuthorEmailNotifications - Featured Processing: ".date('m/d/Y H:i:s', time())."\n";
$debug = 0;

AuthorEmailNotification::processFeatured() ;

$t2 = time() - $t1;
echo "Took " . number_format($t2, 0, ".", ",") . " seconds...\n";
echo "Completed AuthorEmailNotifications - Featured Processing: ".date('m/d/Y H:i:s', time())."\n";

