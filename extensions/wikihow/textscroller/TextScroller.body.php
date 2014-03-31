<?
class TextScroller extends UnlistedSpecialPage {

	public static function setParserFunction () { 
		# Setup parser hook
		global $wgParser;
		$wgParser->setFunctionHook( 'txtscrl', 'TextScroller::parserFunction' );
		return true;    
	}

	public static function parserFunction($parser, $arrowText, $grayText, $scrollText) {
		global $wgTitle, $wgContLang;
		$scrollText = self::prepareText($scrollText);
		$grayText = self::prepareText($grayText);
		$arrowText = self::prepareText($arrowText);
		$vars = array(
			'arrowText' => $arrowText, 
			'grayText' => $grayText, 
			'scrollText'=> $scrollText, 
			'id' => 'scrl-' . hash("md5", $scrollText . mt_rand(1, 1000))
		);

		EasyTemplate::set_path(dirname(__FILE__).'/');
		$html = EasyTemplate::html('textscroller', $vars);
		$html = preg_replace("@\n@","", $html);
		return $parser->insertStripItem($html);
	}

	private static function prepareText($text) {
		$text = trim($text);
		// Replace line breaks with a token to later be replaced in the scroller javascript
		$text = preg_replace("@[\r\n]@", "@br@", $text);
		return $text;
	}

    public static function languageGetMagic( &$magicWords ) {
		$magicWords['txtscrl'] = array( 0, 'txtscrl' );
        return true;
    }

}
