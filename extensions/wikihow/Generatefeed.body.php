<?php

class Generatefeed extends UnlistedSpecialPage {

	public function __construct() {
		parent::__construct('Generatefeed');
	}

	private static function addTargetBlank($source) {
		$preg = '/<a href=/';
		$source = preg_replace($preg, '<a target="_blank" href=', $source);
		return $source;
	}

	// I re-used this code without copying it in the mobile MobileWikihow
	// class. -Reuben
	public static function getArticleSummary(&$article, &$title) {
		global $wgParser;
		$summary = Article::getSection($article->getContent(true), 0);
		// remove templates from intro
		$summary = preg_replace('@\{\{[^}]*\}\}@', '', $summary);
		$summary = preg_replace('@\[\[Image:[^\]]*\]\]@', '', $summary);
		$summary = preg_replace('@<ref[^>]*>.*</ref>@iU','', $summary);
		// parse summary from wiki text to html
		$output = $wgParser->parse($summary, $title, new ParserOptions() );
		// strip html tags from summary
		$summary = trim(strip_tags($output->getText()));
		return $summary;
	}

	function getImages(&$article, &$title) {
		global $wgParser;
		$content = $article->getContent(true);

		$images = array();

		$count = 0;
		preg_match_all("@\[\[Image[^\]]*\]\]@im", $content, $matches);
		foreach($matches[0] as $i) {
			$i = preg_replace("@\|.*@", "", $i);
			$i = preg_replace("@^\[\[@", "", $i);
			$i = preg_replace("@\]\]$@", "", $i);
			$i = urldecode($i);
			$image = Title::newFromText($i);
			if ($image && $image->getArticleID() > 0) {
				$file = wfFindFile($image);
				if (isset($file)) {
					/* UNCOMMENT TO USE REAL IMAGES RATHER THAN THUMBNAILS IN MRSS - GOOGLE ISSUE
					$images[$count]['src'] = $file->getUrl();
					$images[$count]['width'] = $file->getWidth();
					$images[$count]['height'] = $file->getHeight();
					*/
					$thumb = $file->getThumbnail(200);
					$images[$count]['src'] = $thumb->getUrl();
					$images[$count]['width'] = $thumb->getWidth();
					$images[$count]['height'] = $thumb->getHeight();
					$images[$count]['size'] = $file->getSize();
					$images[$count]['mime'] = $file->getMimeType();
					$count++;
				} else {
					wfDebug("VOOO SKIN gallery can't find image $i \n");
				}
			} else {
				wfDebug("VOOO SKIN gallery can't find image title $i \n");

			}
		}

		return $images;
	}

	public function execute($par) {
		global $wgUser, $wgOut, $wgLang, $wgContLang, $wgTitle, $wgMemc;
		global $IP, $wgDBname, $wgParser;
		global $wgRequest, $wgSitename, $wgLanguageCode, $wgContLanguageCode;
		global $wgFeedClasses, $wgUseRCPatrol;
		global $wgScriptPath, $wgServer;
		global $wgSitename, $wgFeedClasses, $wgContLanguageCode;
		global $messageMemc, $wgDBname, $wgFeedCacheTimeout;
		global $wgFeedClasses, $wgTitle, $wgSitename, $wgContLanguageCode, $wgLanguageCode;

		$fname = 'wfSpecialGeneratefeed';

		$fullfeed = 0;
		$mrss = 0;
		if ($par == 'fullfeed') $fullfeed = 1;
		else if ($par == 'mrss') $mrss = 1;

		require_once("$IP/extensions/wikihow/FeaturedRSSFeed.php");

		header('Content-Type: text/xml');
		$wgOut->setSquidMaxage(60);
		$feedFormat = 'rss';
		$timekey = "$wgDBname:rcfeed:$feedFormat:timestamp";
		$key = "$wgDBname:rcfeed:$feedFormat:limit:$limit:minor:$hideminor";

		$feedTitle = wfMsg('Rss-feedtitle');
		$feedBlurb = wfMsg('Rss-feedblurb');
		$feed = new FeaturedRSSFeed(
			$feedTitle,
			$feedBlurb,
			"$wgServer$wgScriptPath/Main-Page"
		);

		if ($mrss) {
			$feed->outHeaderMRSS();
		} else {
			// Replace to get back to raw feed (not full and without mrss)
			//$feed->outHeader();
			$feed->outHeaderFullFeed();
		}
 
		// extract the number of days below -- this is default
		$days = 6;

		date_default_timezone_set('UTC');
		if ($wgRequest->getVal('micro', null) == 1) {
			$days = FeaturedArticles::getNumberOfDays($days, 'RSS-Microblog-Feed');
			$feeds = FeaturedArticles::getFeaturedArticles($days, 'RSS-Microblog-Feed');
		} else {
			$days = FeaturedArticles::getNumberOfDays($days);
			$feeds = FeaturedArticles::getFeaturedArticles($days);
		}

		$now = time();
		$itemcount = 0;
		$itemcountmax = 6;
		foreach ($feeds as $f) {
			$url = trim($f[0]);
			$d = $f[1];
			if ($d > $now) continue;
			if (!$url) continue;

			$url = str_replace('http://wiki.ehow.com/', '', $url);
			$url = str_replace('http://www.wikihow.com/', '', $url);
			$url = str_replace($wgServer . $wgScriptPath . '/', '', $url);
			$title = Title::newFromURL(urldecode($url));
			$summary = '';
			$content = '';
			if ($title == null) {
				echo "title is null for $url";
				exit;
			}
			
			//from the Featured Articles
			if ($title->getArticleID() > 0) {
				$article = GoodRevision::newArticleFromLatest($title);
				$summary = self::getArticleSummary($article, $title);
				$images = self::getImages($article, $title);

				//XXFULL FEED
				if (!$mrss) {
					$content = $article->getContent(true);
					$content = preg_replace('/\{\{[^}]*\}\}/', '', $content);
					$output = $wgParser->parse($content, $title, new ParserOptions() );
					$content = self::addTargetBlank($output->getText());
					$content = preg_replace('/href="\//', 'href="'.$wgServer.'/', $content);
					$content = preg_replace('/src="\//', 'src="'.$wgServer.'/', $content);
					$content = preg_replace('/<span id="gatEditSection" class="editsection1">(.*?)<\/span>/', '', $content);
					$content = preg_replace('/<h2> <a target="_blank" href="(.*?)>edit<\/a>/', '<h2>', $content);
					$content = preg_replace('/<img src="(.*?)\/skins\/common\/images\/magnify-clip.png"(.*?)\/>/', '', $content);

					$linkEmail = $wgServer .'/index.php?title=Special:EmailLink&target='. $title->getPrefixedURL() ;

					$backlinks = "\n<div id='articletools'><div class='SecL'></div><div class='SecR'></div><a name='articletools'></a><h2> <span>".wfMsg('RSS-fullfeed-articletools')."</span></h2> </div>";
					$backlinks .= "<ul>\n";
					$backlinks .= "<li type='square'><a target='_blank' href='".$wgServer."/".$title->getPrefixedURL()."'>".wfMsg('RSS-fullfeed-articletools-read')."</a></li>\n";
					$backlinks .= "<li type='square'><a target='_blank' href='".$linkEmail."'>".wfMsg('RSS-fullfeed-articletools-email')."</a></li>\n";
					$backlinks .= "<li type='square'><a target='_blank' href='".$wgServer.$title->getEditURL()."'>".wfMsg('RSS-fullfeed-articletools-edit')."</a></li>\n";
					$backlinks .= "<li type='square'><a target='_blank' href='".$wgServer."/".$title->getTalkPage()."'>".wfMsg('RSS-fullfeed-articletools-discuss')."</a></li>\n";
					$backlinks .= "<ul>\n";

					$content .= $backlinks;
				}
			} else {
				continue;
			}

			$talkpage = $title->getTalkPage();

			$title_text = $title->getPrefixedText();
			if (isset($f[2])
				&& $f[2] != null
				&& trim($f[2]) != '')
			{
				$title_text = $f[2];
			} else {
				$title_text = wfMsg('howto', $title_text);
				}

			$item = new FeedItem(
				$title_text,
				$summary,
				$title->getFullURL(),
				$d,
				null,
				$talkpage->getFullURL()
			);

			if ($mrss) {
				$feed->outItemMRSS($item, $images);
			} else {
				// Replace to get back to raw feed (not full and without mrss)
				$feed->outItemFullFeed($item, $content, $images);
			}
			$itemcount++;

		}
		$feed->outFooter();
	}
}

