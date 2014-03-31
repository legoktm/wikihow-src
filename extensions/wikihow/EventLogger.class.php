<?
class EventLogger {
	public static function logEvent($key, $type, $value) {
		wfProfileIn(__METHOD__);
		global $wgRequest;

		if(!preg_match('@^http://([^\.]+\.|[^\.]*\.m\.doh\.|[^\.]*\.m\.)(wikidiy|wikihow)\.com\/@', $_SERVER['HTTP_REFERER'])) {
			die();
		}

		$dbw = wfGetDB(DB_MASTER);
		$key = trim($dbw->strencode($key));
		$type = trim($dbw->strencode($type));
		$value = trim($dbw->strencode($value));

		if (!empty($key) && !empty($type)) {
			$result = $dbw->insert('event_logger', array('el_key' => $key, 'el_type' => $type, 'el_value' => $value, 'el_timestamp' => wfTimestamp(TS_MW)), __METHOD__);
		}
		wfProfileOut(__METHOD__);
	}
}
