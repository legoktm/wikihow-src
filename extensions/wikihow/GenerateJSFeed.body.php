<?

class GenerateJSFeed extends UnlistedSpecialPage {

	function __construct() {
		parent::__construct( 'GenerateJSFeed' );
	}

	function execute($par) {
		global $wgUser, $wgOut, $wgLang, $wgContLang, $wgTitle, $wgMemc, $wgDBname;
		global $wgRequest, $wgSitename, $wgLanguageCode, $wgContLanguageCode;
		global $wgFeedClasses, $wgUseRCPatrol;
		global $wgScriptPath, $wgServer;
		global $wgSitename, $wgFeedClasses, $wgContLanguageCode;

		$fname = 'wfSpecialGeneratefeed';

		$pretty = isset($_GET['pretty']) && $_GET['pretty'] != null;

		global $messageMemc, $wgDBname, $wgFeedCacheTimeout;
		global $wgFeedClasses, $wgTitle, $wgSitename, $wgContLanguageCode;
		header('Content-type: application/x-javascript');

		if ($pretty) {
			echo 'document.writeln("<div style=\"border: 1px solid #ccc; padding: 15px; width:275px; font-size: small; font-family: Arial;\"><center><a href=\"http://www.wikihow.com\"><img src=\"http://www.wikihow.com/skins/WikiHow/wikiHow.gif\" border=0></a></center><br/>");';
			echo 'document.writeln("<b>How-to of the Day:</b><br />");';
		} else {
			echo 'document.writeln("<b>wikiHow: How-to of the Day:</b><br />");';
		}

		$feeds = FeaturedArticles::getFeaturedArticles(6);
		$now = time();
		foreach( $feeds as $f ) {
			$url = $f[0];
			$d = $f[1];
			if ($d > $now) continue;

			$url = str_replace("http://www.wikihow.com/", "", $url);
			$title = Title::newFromURL(urldecode($url));
			echo 'document.writeln("<a href=\"' . $title->getFullURL() . '\">How to ' .
			$title->getText() . '</a><br/>");';
		}
		if ($pretty) {
			echo 'document.writeln("</div>");';
		}
		exit;
	}

}

