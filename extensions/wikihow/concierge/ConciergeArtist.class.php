<?
class ConciergeArtist extends WAPUser {
	protected function __construct(&$u, $dbType) {
		$config = new WAPConciergeConfig();
		$this->init($u, $dbType);
	}

	public static function newFromId($uid, $dbType) {
		$u = User::newFromId($uid);
		$u->load();
		return new ConciergeArtist($u, $dbType);
	}

	public static function newFromName($userName, $dbType) {
		$u = User::newFromName($userName);
		$u->load();
		return new ConciergeArtist($u, $dbType);
	}

	public static function newFromUserObject(&$u, $dbType) {
		if ($u instanceof StubUser) {
			$u->load();
		}
		return new ConciergeArtist($u, $dbType);
	}

	// Concierge only supports one  (en) language
	public function getLanguageTag() {
		$languages = WAPDB::getInstance($this->dbType)->getWAPConfig()->getSupportedLanguages();
		return $languages[0];
	}
}
