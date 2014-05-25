<?
class BabelfishArticle extends WAPArticle {
	private $score = null;
	private $price = null;
	private $rank = null;
	private static $pricing = null;
	const MSG_INVALID_PRICING_DATA = "Invalid pricing data";

	protected function __construct($row, $dbType) {
		$config = new WAPBabelfishConfig();
		$this->init($row, $dbType);;
	}

	protected function init(&$row, $dbType) {
		parent::init($row, $dbType);
		$this->score = $row->ct_score;
		$this->price = $row->ct_price;
		$this->rank = $row->ct_rank;
	}

	public static function newFromId($aid, $langCode, $dbType) {
		$row = self::getDBRow($aid, $langCode, $dbType);
		return new BabelfishArticle($row, $dbType);
	}

	public static function newFromDBRow(&$row, $dbType) {
		return new BabelfishArticle($row, $dbType);
	}

	public static function newFromUrl($url, $langCode, $dbType) {
		$url = str_replace('http://www.wikihow.com/', '', $url);
		$pageTitle = Misc::getUrlDecodedData($url);
		$row = self::getDBRowFromPageTitle($pageTitle, $langCode, $dbType);
		return is_null($row) ? null : BabelfishArticle::newFromDBRow($row, $dbType);
	}

	public function getPrice() {
		$pricing = self::getPricing();	
		$pricing = $pricing[$this->getLangCode()];

		// Check for valid pricing data, return N/A otherwise
		if (is_null($pricing)) {
			return self::MSG_INVALID_PRICING_DATA;
		}

		$price = bcmul($this->getScore(), $pricing["multiplier"], 5);
		if (bccomp($price, $pricing['min'], 3) == -1) {
			// If lower than min, $price is min val
			$price = $pricing['min'];
		} elseif (bccomp($price, $pricing['max'], 3) === 1) {
			// If higher than max, $price is max val
			$price = $pricing['max'];
			
		} else {
			// Round to nearest 0.25
			$price = bcdiv(round(bcmul($price, 4, 2)), 4, 2);

		}
		return $price;
	}

	protected function getScore() {
		return $this->score;
	}

	protected static function getPricing() {
		if (is_null(self::$pricing)) {
			self::$pricing = array();
			//$pricing = explode("\n", trim(wfMsg('babelfish_mult')));
			$pricing = "
				fr,1,0,10000
				pt,.0085,2.50,20
				ru,.0085,3.50,24
				zh,.0075,3,20
				it,1,0,10000
				de,.015,8,32
				nl,.015,8,32
				es,.0055,2,15";
			$pricing = explode("\n", trim($pricing));

			foreach ($pricing as $price) {
				$price = explode(",", trim($price));
				if (sizeof($price) != 4) {
					throw new Exception(self::MSG_INVALID_PRICING_DATA);
				}
				self::$pricing[$price[0]] = array("multiplier" => $price[1], "min" => $price[2], "max" => $price[3]);
			}
		}
		return self::$pricing;
	}

	public function getRank() {
		return $this->rank;
	}
}
