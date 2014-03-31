<?

class WikihowCSSDisplay {
	static $specialBackground = false;

	public static function isSpecialBackground() {
		return self::$specialBackground;
	}

	public static function setSpecialBackground($isSpecial) {
		self::$specialBackground = $isSpecial;
	}

}

