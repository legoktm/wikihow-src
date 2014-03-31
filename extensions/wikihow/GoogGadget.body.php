<?php

if (!defined('MEDIAWIKI')) die();

class GoogGadget extends UnlistedSpecialPage {

	function __construct() {
		parent::__construct( 'GoogGadget' );
	}

	function addTargetBlank($source) {
		$preg = '/<a href=/';
		$source = preg_replace($preg, '<a target="_blank" href=', $source);
		return $source;
	}

	private function getRelatedWikihowsFromSource($title, $num) {
		global $wgParser;
		$whow = WikihowArticleEditor::newFromTitle($title);
		if (!$whow) return '';

		$related = $whow->getSection('related wikihows');
		$preg = "/\\|[^\\]]*/";
		$related = preg_replace($preg, "", $related);
		//splice and dice
		$rarray = split("\n", $related);
		$related = implode("\n", array_splice($rarray, 0, $num));
		$options = new ParserOptions();
		$output = $wgParser->parse($related, $title, $options);
		$ra = $this->addTargetBlank($output->getText());
		return $ra;
	}

	function execute($par) {
		global $wgUser, $wgOut, $wgLang, $wgContLang, $wgTitle, $wgMemc, $wgDBname;
		global $wgServer, $wgRequest, $wgSitename, $wgLanguageCode, $wgContLanguageCode;
		global $wgFeedClasses, $wgUseRCPatrol;
		global $wgScriptPath, $wgServer;
		global $wgSitename, $wgFeedClasses, $wgContLanguageCode;

		$wgOut->setArticleBodyOnly(true);

		$type = $wgRequest->getVal("type");
		$ndisplay = $wgRequest->getVal("ndisplay");
		$DEBUG = 1;

		require_once('IGGadgetTMPL.php');
		global $messageMemc, $wgDBname, $wgFeedCacheTimeout;
		global $wgFeedClasses, $wgTitle, $wgSitename, $wgContLanguageCode;

		$wgOut->setSquidMaxage( 3600 );

		// extract the number of days
		$days = 6;
		if (($ndisplay != "") && (is_numeric($ndisplay)) && ($ndisplay < 10)) {
			$numitems = $ndisplay;
		} else {
			$numitems = 3;
		}
		date_default_timezone_set("UTC");
		$days = FeaturedArticles::getNumberOfDays($days);
		$feeds = FeaturedArticles::getFeaturedArticles($days);

		if ($type == "home") {

			$now = time();
			$count = 0;
			$mainTitle = "";
			$relatedArticles = "";
			$featuredArticles = "";

			$ggtmpl = new GoogleGadgetHome2();
			$ggtmpl->outHeader();
			$itemnum = 1;
			$itemmax = 3;

			foreach( $feeds as $f ) {

				$url = $f[0];
				$d = $f[1];
				if ($d > $now) continue;

				$url = str_replace("http://wiki.ehow.com/", "", $url);
				$url = str_replace("http://www.wikihow.com/", "", $url);
				$url = str_replace($wgServer . $wgScriptPath . "/", "", $url);
				$title = Title::newFromURL(urldecode($url));
				$title_text = $title->getPrefixedText();
				if (isset($f[2]) && $f[2] != null && trim($f[2]) != '') {
					$title_text = $f[2];
				} else {
					$title_text = wfMsg('howto', $title_text);
				}

				$mainTitle = $title;
				$summary = "";
				$a = "";
				if ($title == null) {
					echo "title is null for $url";
					exit;
				}
				if ($title->getArticleID() > 0) {
					$a = GoodRevision::newArticleFromLatest($title);
					$summary = $a->getContent(true);
					$summary = preg_replace('/\{\{fa\}\}/', '', $summary);

					global $wgParser;
					$output = $wgParser->parse($summary, $title, new ParserOptions() );
					$relatedArticles = $this->getRelatedWikihowsFromSource($title, 4);
					$summary = $this->addTargetBlank($output->getText());
					$summary = preg_replace('/href="\//', 'href="'.$wgServer.'/', $summary);
					$summary = preg_replace('/src="\//', 'src="'.$wgServer.'/', $summary);
					$summary = preg_replace('/<span id="gatEditSection" class="editsection1">(.*?)<\/span>/', '', $summary);


					$linkEmail = $wgServer .'/index.php?title=Special:EmailLink&target='. $title->getPrefixedURL() ;

					$backlinks = "\n<div id='articletools'><div class='SecL'></div><div class='SecR'></div><a name='articletools'></a><h2> <span>Article Tools</span></h2> </div>";
					$backlinks .= "<ul style='list-style: none;margin-left:0;padding-left:4em;text-indent:-1em;'>";
					$backlinks .= "<li style='list-style-type: none;'><img src='".$wgServer."/skins/WikiHow/images/logo_small.png' height=12 width=15>&nbsp;&nbsp;<a target='_blank' href='".$wgServer."/".$title->getPrefixedURL()."'>Read on wikiHow</a></li>";
					$backlinks .= "<li style='list-style-type: none;'><img src='".$wgServer."/skins/WikiHow/sharetab/ShareTab_Email.gif' height=15 width=15>&nbsp;&nbsp;<a target='_blank' href='".$linkEmail."'>Email this Article</a></li>";
					$backlinks .= "<li style='list-style-type: none;'><img src='".$wgServer."/skins/WikiHow/images/pencil_benji_park_01.png' height=15 width=15>&nbsp;&nbsp;<a target='_blank' href='".$wgServer.$title->getEditURL()."'>Edit</a></li>";
					$backlinks .= "<li style='list-style-type: none;'><img src='".$wgServer."/skins/WikiHow/images/speech_ballon.png' height=15 width=15>&nbsp;&nbsp;<a target='_blank' href='".$wgServer."/Discussion:".$title->getPrefixedURL()."'>Discuss</a></li>";
					$backlinks .= "<ul>\n";

					$summary .= $backlinks;


					$ggtmpl->outMain($title_text, $summary, $url, $itemnum);
					$itemnum++;
				} else {
					echo '<!-- no article found for articleid:'.$title->getArticleID().' title:'.$title_text.' -->';
				}

				if ($itemnum > $itemmax) { break; }
			}

			$relatedArticles = preg_replace('/href="\//', 'href="'.$wgServer.'/', $relatedArticles);

			$ggtmpl->outFooter();

		} else if ($type == "canvas") {

			$now = time();
			$count = 0;
			$mainTitle = "";
			$relatedArticles = "";
			$featuredArticles = "";
			$maxShow = 7;

			$ggtmpl = new GoogleGadgetCanvas();
			$ggtmpl->outHeader();

			foreach( $feeds as $f ) {

				$url = $f[0];
				$d = $f[1];
				if ($d > $now) continue;

				$url = str_replace("http://wiki.ehow.com/", "", $url);
				$url = str_replace("http://www.wikihow.com/", "", $url);
				$url = str_replace($wgServer . $wgScriptPath . "/", "", $url);
				$title = Title::newFromURL(urldecode($url));
				$title_text = $title->getPrefixedText();
				if (isset($f[2]) && $f[2] != null && trim($f[2]) != '') {
					$title_text = $f[2];
				} else {
					$title_text = wfMsg('howto', $title_text);
				}

				if ($count <  $maxShow ) {
					$mainTitle = $title;
					$summary = "";
					$a = "";
					if ($title == null) {
						echo "title is null for $url";
						exit;
					}
					if ($title->getArticleID() > 0) {
						$a = GoodRevision::newArticleFromLatest($title);
						$summary = $a->getContent(true);
						$summary = preg_replace('/\{\{.*\}\}/', '', $summary);

						global $wgParser;
						$output = $wgParser->parse($summary, $title, new ParserOptions() );
						$relatedArticles = $this->getRelatedWikihowsFromSource($title, 4);
						$summary = $this->addTargetBlank($output->getText());
						$summary = preg_replace('/href="\//', 'href="'.$wgServer.'/', $summary);
						$summary = preg_replace('/src="\//', 'src="'.$wgServer.'/', $summary);
						$summary = preg_replace('/<span id="gatEditSection" class="editsection1">(.*?)<\/span>/', '', $summary);

						$linkEmail = $wgServer .'/index.php?title=Special:EmailLink&target='. $title->getPrefixedURL() ;

						$backlinks = "\n<div id='articletools'><div class='SecL'></div><div class='SecR'></div><a name='articletools'></a><h2> <span>Article Tools</span></h2> </div>";
						$backlinks .= "<ul style='list-style: none;margin-left:0;padding-left:4em;text-indent:-1em;'>";
						$backlinks .= "<li style='list-style-type: none;'><img src='".$wgServer."/skins/WikiHow/images/logo_small.png' height=12 width=15>&nbsp;&nbsp;<a target='_blank' href='".$wgServer."/".$title->getPrefixedURL()."'>Read on wikiHow</a></li>";
						$backlinks .= "<li style='list-style-type: none;'><img src='".$wgServer."/skins/WikiHow/sharetab/ShareTab_Email.gif' height=15 width=15>&nbsp;&nbsp;<a target='_blank' href='".$linkEmail."'>Email this Article</a></li>";
						$backlinks .= "<li style='list-style-type: none;'><img src='".$wgServer."/skins/WikiHow/images/pencil_benji_park_01.png' height=15 width=15>&nbsp;&nbsp;<a target='_blank' href='".$wgServer.$title->getEditURL()."'>Edit</a></li>";
						$backlinks .= "<li style='list-style-type: none;'><img src='".$wgServer."/skins/WikiHow/images/speech_ballon.png' height=15 width=15>&nbsp;&nbsp;<a target='_blank' href='".$wgServer."/Discussion:".$title->getPrefixedURL()."'>Discuss</a></li>";
						$backlinks .= "<ul>\n";

						$summary .= $backlinks;

						$ggtmpl->outMain($title_text, $summary, $url);
					} else {
						echo '<!-- no article found for articleid:'.$title->getArticleID().' title:'.$title_text.' -->';
					}
				}

				$featuredArticles .= '<li><a href="'.$wgServer.'/'.$url.'" target="_blank">'.$title_text.'</a></li>'. "\n";
				$count++;
			}

			$relatedArticles = preg_replace('/href="\//', 'href="'.$wgServer.'/', $relatedArticles);
			$ggtmpl->outFeaturedArticles($featuredArticles);
			$ggtmpl->outRelatedArticles($relatedArticles);

			$ggtmpl->outFooter();
		} else {
			$ggtmpl = new GoogleGadgetModule ( $wgServer );
			$ggtmpl->outModulePrefs();
			exit;
		}
	}

}

