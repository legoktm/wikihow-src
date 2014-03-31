<?
require_once("commandLine.inc");

$t1 = time();
echo "Starting AuthorEmailNotifications - Viewership Processing: ".date('m/d/Y H:i:s', time())."\n";
$debug = 0;

AuthorEmailNotification::processViewership() ;

$t2 = time() - $t1;
echo "Took " . number_format($t2, 0, ".", ",") . " seconds...\n";
echo "Completed AuthorEmailNotifications - Viewership Processing: ".date('m/d/Y H:i:s', time())."\n";

