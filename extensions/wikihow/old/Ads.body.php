<?

class Ads extends UnlistedSpecialPage {

    function __construct($source = null) {
        parent::__construct( 'Ads' );
    }

    function execute ($par) {
		global $wgRequest,$wgOut, $wgSquidMaxage, $wgMimeType;

		$params = split("/", $par);
		$ads = array_shift($params);

		$wgOut->setSquidMaxAge($wgSquidMaxage);
		$wgOut->setArticleBodyOnly(true);
		$wgMimeType = "application/x-javascript";
		$wgOut->addHTML(wfMsg($ads, $params[0])); 
		return;

	}

}
