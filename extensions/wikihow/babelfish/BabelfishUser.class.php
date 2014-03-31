<?
class BabelfishUser extends WAPUser {
	protected function __construct(&$u, $dbType) {
		$this->init($u, $dbType);
	}

	public static function newFromId($uid, $dbType) {
		$u = User::newFromId($uid);
		$u->load();
		return new BabelfishUser($u, $dbType);
	}

	public static function newFromName($userName, $dbType) {
		$u = User::newFromName($userName);
		$u->load();
		return new BabelfishUser($u, $dbType);
	}

	public static function newFromUserObject(&$u, $dbType) {
		if ($u instanceof StubUser) {
			$u->load();
		}
		return new BabelfishUser($u, $dbType);
	}

	public function getLanguageTag() {
		$languages = WAPDB::getInstance($this->dbType)->getWAPConfig()->getSupportedLanguages();
		$tag = null;
		foreach ($languages as $langTag) {
			if ($this->hasTag($langTag)) {
				$tag = $langTag;
				break;
			}
		}
		if (is_null($tag)) {
			throw new Exception(self::EX_NO_LANG_TAG);
		}
		return $tag;
	}
}
