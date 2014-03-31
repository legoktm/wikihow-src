<?

global $wgHooks;
$wgHooks['PostMemcacheInit'][] = array('CachePrefetch::fetchMemcacheGlobals');
$wgHooks['PreMemcacheGet'][] = array('CachePrefetch::recordMemcacheGet');
$wgHooks['ArticleFromTitle'][] = array('CachePrefetch::startPrefetchRecording');
$wgHooks['PostOutput'][] = array('CachePrefetch::storePrefetchHints');

/**
 * An amalgamation of methods to optimize use of memcache by leveraging
 * the get_multi call in novel ways.
 */
class CachePrefetch {

	// This function is an optimization to fetch some variables from memcache
	// that are retrieved each page view. This is an optimization because
	// doing a $memcache->get_multi is faster than a bunch of individual
	// $memcache->get calls and the get_multi results are stored in
	// the memcache class.
	public static function fetchMemcacheGlobals($memcache) {
		$globals = array(
			'lag_times', 'localisation:en', 'messages', 'featuredbox:4:4',
			'botids', 'sitenotice', 'interwiki:error');

		foreach ($globals as &$global) {
			$global = wfMemcKey($global);
		}

		// note that we don't use the result -- it's just to warm the
		// $memcache->_localCache variable
		$keys = $memcache->get_multi($globals);

		return true;
	}

	private static $articleID = 0,
		$origKeys = array(),
		$newKeys = array();

	private static function genPrefetchArticleCachekey($articleID) {
		return wfMemcKey('memcprefetch', $articleID);
	}

	public static function startPrefetchRecording() {
		global $wgTitle, $wgMemc;

		if ($wgTitle) {
			$articleID = $wgTitle->getArticleID();

			// prefetch the cachekeys
			$cachekey = self::genPrefetchArticleCachekey($articleID);
			self::$origKeys = $wgMemc->get($cachekey);
			// do actual prefetch
			if (self::$origKeys) {
				// don't store the result here -- it's in 
				// the $wgMemc->_localCache variable
				$wgMemc->get_multi(self::$origKeys);
			} else {
				self::$origKeys = array();
			}

			// we do this last so that $cachekey isn't added to prefetch list
			self::$articleID = $articleID;
		}
		return true;
	}

	// we record all the memcache keys that match :articleID so
	// that we can use them to warm the cache next time this article
	// is loaded
	public static function recordMemcacheGet($memcache, $key) {
		// we don't start recording keys until we know the articleID
		if (!self::$articleID) return true;

		// store new entries if they match
		if (preg_match('@:' . self::$articleID . '\b@', $key)
			&& !isset( self::$origKeys[$key] ))
		{
			self::$newKeys[$key] = 1;
		}

		return true;
	}

	// This method is called once the last of the keys have been fetched.
	// It determines whether or not to refresh this list
	public static function storePrefetchHints() {
		global $wgMemc;

		// if there are 5 or more new cachekeys to prefetch, we update the
		// prefetch info. this is a heuristic so that we don't constantly
		// update this value.
		if (count(self::$newKeys) > 3) {
			$allKeys = array_merge(self::$origKeys, self::$newKeys);
			$cachekey = self::genPrefetchArticleCachekey(self::$articleID);
			$wgMemc->set($cachekey, $allKeys);
		}

		return true;
	}

}
