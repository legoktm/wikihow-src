<?

/**
 * ConfigStorage class (and associated Special:AdminConfigEditor page) exist
 * to edit and store large configuration blobs (such as lists of 1000+ URLs)
 * because we've found that Mediawiki messages are not optimal for this task.
 * But it's important that they're non-engineer editable, so we provide an
 * admin interface to edit them.
 */

/*
 *db schema:
 *
CREATE TABLE config_storage (
	cs_key VARCHAR(64) NOT NULL PRIMARY KEY,
	cs_config LONGTEXT NOT NULL
);
INSERT INTO config_storage SET cs_key='wikiphoto-article-exclude-list', cs_config='';
 */

class ConfigStorage {

	const MAX_KEY_LENGTH = 64;

	/**
	 * List all current config keys.
	 */
	public static function dbListConfigKeys() {
		$dbr = wfGetDB(DB_SLAVE);
		$res = $dbr->select('config_storage', 'cs_key', '', __METHOD__);
		$keys = array();
		while ($row = $res->fetchRow()) {
			$keys[] = $row['cs_key'];
		}
		$res->free();
		return $keys;
	}

	/**
	 * Pulls the config for a given key from either memcache (if it's there)
	 * or the database.
	 */
	public static function dbGetConfig($key) {
		global $wgMemc;

		$cachekey = self::getMemcKey($key);
		$res = $wgMemc->get($cachekey);
		if (!$res) {
			$dbr = wfGetDB(DB_SLAVE);
			$res = $dbr->selectField('config_storage', 'cs_config', array('cs_key' => $key), __METHOD__);
			
			if ($res) {
				$wgMemc->set($cachekey, $res);
			}
		}
		return $res;
	}

	/**
	 * Set the new config key in the database (along with the config value).
	 * Clear the memcache key too.
	 */
	public static function dbStoreConfig($key, $config) {
		global $wgMemc;

		$cachekey = self::getMemcKey($key);
		$wgMemc->delete($cachekey);

		$dbw = wfGetDB(DB_MASTER);
		$dbw->replace('config_storage', 'cs_key', 
			array(
				array('cs_key' => $key, 'cs_config' => $config)
			),
			__METHOD__);
	}

	// consistently generate a memcache key
	private static function getMemcKey($key) {
		return wfMemcKey('cfg', $key);
	}
}

