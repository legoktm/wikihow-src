<?php
global $IP;
require_once("$IP/extensions/wikihow/titus/GoogleSpreadsheet.class.php");
define('WH_KEYWORD_MASTER_GOOGLE_DOC','0Aoa6vV7YDqEhdDZXQ3RCaXJYWUdxN3RYelQzYVBfNnc/od6'); 
class DedupQueryInput {
	public static function addSpreadsheet() {
		$gs = new GoogleSpreadsheet();	
		$gs->login(WH_TITUS_GOOGLE_LOGIN, WH_TITUS_GOOGLE_PW);
		$cols = $gs->getCols(WH_KEYWORD_MASTER_GOOGLE_DOC,1,1,2);
		foreach($cols as $col) {
			DedupQuery::addQuery($col[0]);
		}
	}
}
