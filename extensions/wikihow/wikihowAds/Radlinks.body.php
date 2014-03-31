<?

class Radlinks extends UnlistedSpecialPage {

	function __construct() {
		parent::__construct( 'Radlinks' );
	}

	function execute($par) {
		global $wgRequest, $wgOut;

		$google_kw = $wgRequest->getVal('google_kw', $par);
		$google_kw = strip_tags($google_kw);

		$wgOut->setPageTitle("Ads for $google_kw");

		$wgOut->addHTML("<script>function google_ad_request_done(ads) {}</script>");

		$wgOut->addHTML("<style type=\"text/css\" media=\"all\">/*<![CDATA[*/ @import \"/extensions/min/f/extensions/wikihow/wikihowAds/radlinks.css\"; /*]]>*/</style>");
		
		$wgOut->addHTML("<h2>Ads for '$google_kw'</h2>");

		$chans = "5047600031";
		$radpos = $wgRequest->getVal('radPos1');
		if ($radpos == 'true') {
			$chans .= "+8354837063";
		} elseif ($radpos == 'false') {
			$chans .= "+3168052762";
		}
		$inner = wfMsg('custom_rad_links_landing',
			$chans, $google_kw, 
			$wgRequest->getVal('google_rt'),
			$wgRequest->getVal('google_page_url'));
		$inner = preg_replace('/\<[\/]?pre[^>]*>/', '', $inner);
		$wgOut->addHTML($inner);

		$exp = wfMsg('rad_links_explanation');
		$exp = preg_replace('/\<[\/]?pre\>/', '', $exp);
		$wgOut->addHTML($exp);
	
	}
}

