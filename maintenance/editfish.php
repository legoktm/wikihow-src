<?
define('WH_USE_BACKUP_DB', true);
require_once('commandLine.inc');

$maintenance = WAPMaintenance::getInstance(WAPDB::DB_EDITFISH);
$maintenance->nightly();
