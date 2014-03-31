<?
class EditfishArtist extends WAPUser {
	protected function __construct(&$u, $dbType) {
		$config = new WAPEditfishConfig();
		$this->init($u, $dbType);
	}

	public static function newFromId($uid, $dbType) {
		$u = User::newFromId($uid);
		$u->load();
		return new EditfishArtist($u, $dbType);
	}

	public static function newFromName($userName, $dbType) {
		$u = User::newFromName($userName);
		$u->load();
		return new EditfishArtist($u, $dbType);
	}

	public static function newFromUserObject(&$u, $dbType) {
		if ($u instanceof StubUser) {
			$u->load();
		}
		return new EditfishArtist($u, $dbType);
	}

	// Editfish only supports one  (en) language
	public function getLanguageTag() {
		$languages = WAPDB::getInstance($this->dbType)->getWAPConfig()->getSupportedLanguages();
		return $languages[0];
	}
}
