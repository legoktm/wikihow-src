<?
class ConciergeArticle extends WAPArticle {
	protected function __construct($row, $dbType) {
		$this->init($row, $dbType);
	}

	public static function newFromId($aid, $langCode, $dbType) {
		$row = self::getDBRow($aid, $langCode, $dbType);
		return new ConciergeArticle($row, $dbType);
	}

	public static function newFromDBRow(&$row, $dbType) {
		return new ConciergeArticle($row, $dbType);
	}

	public static function newFromUrl($url, $langCode, $dbType) {
		$url = str_replace('http://www.wikihow.com/', '', $url);
		$pageTitle = Misc::getUrlDecodedData($url);
		$pageTitle = preg_replace("@\ @", "+", trim($url));
		$row = self::getDBRowFromPageTitle($pageTitle, $langCode, $dbType);
		return is_null($row) ? null : ConciergeArticle::newFromDBRow($row, $dbType);
	}
}
