<?php
//
// Refresh the cache of the Special:Wantedpages page.  To be called hourly.
//

define('WH_USE_BACKUP_DB', true);
require_once('commandLine.inc');

$MAX_RESULTS = 1000000;

$included = false;
$nlinks = true;

$wpp = new WantedPagesPage( $included, $links );
$wpp->recache($MAX_RESULTS);

$wpp = new DeadendPagesPage( $included, $links );
$wpp->recache($MAX_RESULTS);
