<?

if (!defined('MEDIAWIKI')) die();

global $IP;
require_once("$IP/includes/SkinTemplate.php");

abstract class MobileHtmlBuilder {
	protected $deviceOpts = null;
	protected $nonMobileHtml = '';
	protected $t = null;
	private static $jsScripts = array();
	private static $jsScriptsCombine = array();
	private $cssScriptsCombine = array();

	const SHOW_GOSQUARED = false;
	const SHOW_CLICK_IGNITER = true;
	const SHOW_RUM = true;

	public function createByRevision(&$t, &$r) {
		global $wgParser, $wgOut;

		$html = '';
		if(!$t) {
			return $html;
		}

		if ($r) {
			$popts = $wgOut->parserOptions();
			$popts->setTidy(true);
			$html = $wgParser->parse($r->getText(), $t, $popts, true, true, $r->getId());
			$html = $html->mText;
			$popts->setTidy(false);
			$html = $this->createByHtml($t, $html);
		}
		return $html;
	}

	public function createByHtml(&$t, &$nonMobileHtml) {
		if ((!$t || !$t->exists()) 
			&& !($this instanceof Mobile404Builder) 
			&& !($this instanceof MobileSampleBuilder)) {
			return '';
		}

		$this->deviceOpts = MobileWikihow::getDevice();
		$this->t = $t;
		$this->nonMobileHtml = $nonMobileHtml;
		$this->setTemplatePath();
		$this->addCSSLibs();
		$this->addJSLibs();
		return $this->generateHtml();
	}

	private function generateHtml() {
		$html = '';
		$html .= $this->generateHeader();
		$html .= $this->generateBody();
		$html .= $this->generateFooter();
		return $html;
	}

	abstract protected function generateHeader();
	abstract protected function generateBody();
	abstract protected function generateFooter();
	
	protected function mobileParserBeforeHtmlSave(&$xpath) {}

	protected function getDefaultHeaderVars() {
		global $wgRequest, $wgLanguageCode, $wgSSLsite, $wgUser, $wgOut;

		$t = $this->t;
		$articleName = $t->getText();
		$action = $wgRequest->getVal('action', 'view');
		$deviceOpts = $this->getDevice();
		$pageExists = $t->exists();
		$randomUrl = '/' . wfMsg('special-randomizer');
		$isMainPage = $articleName == wfMsg('mainpage');
		$defaultHtmlTitle = $isMainPage ? wfMsg('mobile-mainpage-title') : wfMsg('pagetitle', $articleName);

		$startTime = strtotime('February 9, 2014');
		$fourWeeks = 4 * 7 * 24 * 60 * 60;
		$rolloutArticle = Misc::percentileRollout($startTime, $fourWeeks);
		if ($rolloutArticle) {
			$htmlTitle = SkinWikihowSkin::getHTMLTitle($wgOut->getHTMLTitle(), $defaultHtmlTitle, $isMainPage);
		} else {
			$htmlTitle = $defaultHtmlTitle;
		}
		$canonicalUrl = MobileWikihow::getNonMobileSite() . '/' . $t->getPartialURL();
		$headLinks = $wgOut->getHeadLinks();

		if ($wgUser->getID() > 0) {
			$login_link = '/Special:Mypage';
			$login_text = wfMsg('me');
		}
		else {
			$login_link = '/Special:Userlogin';
			$login_text = wfMsg('log_in');
		}
		if (SSL_LOGIN_DOMAIN && !$wgSSLsite) $login_link = 'https://'.SSL_LOGIN_DOMAIN.$login_link;

		$headerVars = array(
			'isMainPage' => $isMainPage,
			'htmlTitle' => $htmlTitle,
			'headLinks' => $headLinks,
			'css' => $this->cssScriptsCombine,
			'randomUrl' => $randomUrl,
			'deviceOpts' => $deviceOpts,
			'canonicalUrl' => $canonicalUrl,
			'pageExists' => $pageExists,
			'lang' => $wgLanguageCode,
			'loginlink' => $login_link,
			'logintext'	=> $login_text,
		);
		return $headerVars;
	}

	protected function getDefaultFooterVars() {
		global $wgRequest, $wgLanguageCode, $wgTitle;

		$t = $this->t;
		$redirMainBase = '/' . wfMsg('special') . ':' . wfMsg('MobileWikihow') . '?redirect-non-mobile=';
		$footerVars = array(
			'showSharing' => !$wgRequest->getVal('share', 0),
			'isMainPage' => $t->getText() == wfMsg('mainpage'),
			'pageUrl' => $t->getFullUrl(),
			'showAds' => false,  //temporarily taking ads out of the footer
			'deviceOpts' => $this->getDevice(),
			'redirMainUrl' => $redirMainBase,
		);

		$footerVars['androidAppUrl'] = 'https://market.android.com/details?id=com.wikihow.wikihowapp';
		$footerVars['androidAppLabel'] = wfMsg('try-android-app');

		$footerVars['iPhoneAppUrl'] = 'http://itunes.apple.com/us/app/wikihow-how-to-diy-survival-kit/id309209200?mt=8';
		$footerVars['iPhoneAppLabel'] = wfMsg('try-iphone-app');

		$footerVars['showGoSquared'] = MobileHtmlBuilder::showGoSquared();
		$footerVars['showClickIgniter'] = MobileHtmlBuilder::showClickIgniter();
		$footerVars['showRUM'] = MobileHtmlBuilder::showRUM();
		$footerVars['showOptimizely'] = class_exists('OptimizelyPageSelector') && OptimizelyPageSelector::isArticleEnabled($wgTitle);
		$footerVars['isEnglish'] = ($wgLanguageCode == "en");

		return $footerVars;
	}

	static function showGoSquared() {
		return self::SHOW_GOSQUARED && mt_rand(1, 100) <= 30; // about 30% odds
	}

	static function showClickIgniter() {
		return self::SHOW_CLICK_IGNITER;
	}

	static function showRUM() {
		return false; //self::SHOW_RUM && mt_rand(1, 100) <= 25; // 25% odds
	}

	public static function showDeferredJS($deviceOpts) {
		static $displayed = false;
		if (!$displayed) {
			$displayed = true;

			global $wgOut;
			$mwResources = $wgOut->getHeadScripts();

			//$combine1 = array('mjq');
			$vars = array(
				'mwResources' => $mwResources,
				'scriptsCombine1' => $combine1,
				'scripts' => self::$jsScripts,
				'scriptsCombine2' => self::$jsScriptsCombine,
				'deviceOpts' => $deviceOpts,
			);
			return EasyTemplate::html('include-js.tmpl.php', $vars);
		} else {
			return '';
		}
	}

	public static function showBootStrapScript() {
		static $displayed = false;
		if (!$displayed) {
			$displayed = true;
			return '<script>
			WH.lang = WH.lang || {};
			WH.mergeLang = function(A){for(i in A){v=A[i];if(typeof v==="string"){WH.lang[i]=v;}}};
			' .  (self::stuEnabled() ? 'var debug = false; WH.ExitTimer.start(debug);' : '') . '
			WH.mobile.startup();
			</script>';
		} else {
			return '';
		}
	}
	
	protected function setTemplatePath() {
		EasyTemplate::set_path( dirname(__FILE__).'/' );
	}

	protected function getDevice() {
		return $this->deviceOpts;
	}

	protected function addJSLibs() {
		// We separate the lib from JS from the other stuff so that it can
		// be cached.  iPhone caches objects under 25k.
		self::addJS('mwh', true); // wikiHow's mobile JS
		if (self::stuEnabled()) self::addJS('stu', true);
	}

	protected function addCSSLibs() {
		$this->addCSS('mwhc');
	}

	protected function addCSS($script) {
		$this->cssScriptsCombine[] = $script;
	}

	public static function addJS($script, $combine) {
		if ($combine) {
			self::$jsScriptsCombine[] = $script;
		} else {
			self::$jsScripts[] = $script;
		}
	}

	/*
	 * make the array for a related article special box
	 */
	protected function makeRelatedBox($title, $forceProcessing = false) {
		global $wgUser;		
		$sk = $wgUser->getSkin();
		
		$box = array();
		
		if (!$title || !$title->exists()) return $box;
		
		if (!$forceProcessing) {
			//exit if there's a word that will be too long
			$word_array = explode(' ', $title->getText());
			foreach ($word_array as $word) {
				if (strlen($word) >= 12) return $box;
			}
		}

		//the image...
		$file = Wikitext::getTitleImage($title);
		
		if ($file && isset($file)) {
			$thumb = $file->getThumbnail(222, 222, true, true);
			if ($thumb->getUrl() == '') return $box;
			$box['bgimg'] = 'background-image:url('.wfGetPad($thumb->getUrl()).')';
		}
		else {
			return $box;
		}
		
		if (strlen($title->getText()) > 35) {
			//too damn long
			$the_title = substr($title->getText(),0,32) . '...';
		}
		else {
			//we're good
			$the_title = $title->getText();
		}
		
		$box['url'] = $title->getLocalURL();
		$box['name'] = $the_title;
		$box['fullname'] = $title->getText();
		return $box;
	}
	
	protected function getArticleInfo($title) {
		global $wgUser;
		$skin = $wgUser->getSkin();
		$html = '';
		
		//cats
		$catlinks = $skin->getCategoryLinks(false);
		if ($catlinks) $html .= '<p><span class="ai_hdr">'.wfMsg('categories').':</span><br />'.$catlinks.'</p>';
		
		//authors
		ArticleAuthors::loadAuthorsCache();
		$users = array_slice(ArticleAuthors::$authorsCache, 0, min(sizeof(ArticleAuthors::$authorsCache), 4));
		if (!empty($users)) {
			$otherUserCount = sizeof(ArticleAuthors::$authorsCache) - 4;
			$authorSpan = ArticleAuthors::formatAuthorList($users, false, false);
			if ($otherUserCount > 1) {
				$authors = wfMsg('originated_by_and_others_anon', $authorSpan, $otherUserCount);
			} elseif ($otherUserCount == 1) {
				$authors = wfMsg('originated_by_and_1_other_anon', $authorSpan);
			} else {
				$authors = wfMsg('originated_by_anon', $authorSpan);
			}
		}
		if ($authors) $html .= '<p>'.$authors.'</p>';
		
		$html = '<div id="article_info">'.$html.'</div>';
		return $html;
	}

	public static function stuEnabled() {
		global $wgLanguageCode;
		return $wgLanguageCode == 'en' && class_exists('BounceTimeLogger');
	}

}

class MobileArticleBuilder extends MobileBasicArticleBuilder {

	protected function mobileParserBeforeHtmlSave(&$xpath) {
		$this->addThumbRatingsHtml($xpath);
		$this->addCTAs($xpath);
	}

	private function addThumbRatingsHtml(&$xpath) {
		global $wgLanguageCode;
		if ($wgLanguageCode == "en" && $this->deviceOpts['show-thumbratings']) {
			ThumbRatings::injectMobileThumbRatingsHtml($xpath, $this->t);
		}
	}
	
	private function addCTAs(&$xpath) {
		global $wgLanguageCode;
		if ($wgLanguageCode == "en" && $this->deviceOpts['show-cta']) {
			TipsAndWarnings::injectRedesignCTAs($xpath, $this->t);
		}
	}

	private function addCheckMarkFeatureHtml(&$vars) {
		global $IP;
		if ($this->deviceOpts['show-checkmarks']) {
			require_once("$IP/extensions/wikihow/checkmarks/CheckMarks.class.php");

			CheckMarks::injectCheckMarksIntoSteps_redesign($vars['sections']);
			$vars['checkmarks'] = CheckMarks::getCheckMarksHtml();
		}
	}
	
	private function addAdsToSteps(&$vars) {
		global $wgLanguageCode;

		if ($this->deviceOpts['show-ads']) {
			$stepsCount = substr_count($vars['sections']['steps']['html'], "<li>");
			if( $stepsCount > 0) {
				$needle = '</div></li>';
				$needleLen = strlen($needle);

				//not using this code right now, but leaving it in for a bit
				/*if($stepsCount > 1) {
					//there's more than one step, so put an ad
					//at the end of the the first step
					$replacement = '<div class="clearall"></div>' . wfMsg('adunitmobile4') . '</div></li>';
					$pos = strpos($vars['sections']['steps']['html'], $needle);
					$vars['sections']['steps']['html'] = substr_replace($vars['sections']['steps']['html'], $replacement, $pos, $needleLen);
				}*/

				//now put an ad after the last step
				$pos = strrpos($vars['sections']['steps']['html'], $needle);
				if ($pos !== false) {
					$adLabel = wfMessage('ad_label')->text();
					$replacement = wfMsg('adunitmobile3', $adLabel) . '</div></li>';
					$vars['sections']['steps']['html'] = substr_replace($vars['sections']['steps']['html'], $replacement, $pos, $needleLen);
				} else {
					$adLabel = wfMessage('ad_label')->text();
					$vars['sections']['steps']['html'] .= wfMsg('adunitmobile3', $adLabel);
				}
			}
		}
	}

	private function addArticleRatingsFeatureHtml(&$vars) {
		return; // turn off test for now
		$ar = new ArticleRatingMobileView();
		$vars['articleRating'] = $ar->getHtml();
	}

	/**
	 * Move samples to bottom of the page
	 */
	private function moveSamples(&$vars) {
		$secs = explode("<h3>",$vars['sections']['steps']['html']);
		$sampleSecs = array();
		$n =0 ;
		foreach($secs as $key => $sec) {
			// Match samples, and image upload
			if(preg_match("/id=\"(sd_container|image-upload-file)\"/",$sec, $matches)) {
				$sampleSecs[] = $sec;  
				unset($secs[$key]);
			}
		}
		foreach($sampleSecs as $sample) {
			$secs[] = $sample;	
		}
		$vars['sections']['steps']['html'] = implode($secs,"<h3>");                                                                                                                                 
	}


	protected function addExtendedArticleVars(&$vars) {
		global $wgLanguageCode;
		
		$this->moveSamples($vars);
		if ($wgLanguageCode == 'en') {
			$this->addCheckMarkFeatureHtml($vars);
		}
		$this->addAdsToSteps($vars);

		$this->addArticleRatingsFeatureHtml($vars);
		
		// Add last question
		$rateItem = new RateItem();
		$vars['page_rating'] = $rateItem->showMobileForm('article');
		
		// Add bottom sharing buttons
		if (class_exists('WikihowShare') && class_exists('RobotPolicy')) {
			$isIndexed = RobotPolicy::isIndexable($wgTitle);
			$vars['final_share'] = WikihowShare::getBottomShareButtons_redesign($isIndexed);
		}
		
		$vars['isTestArticle'] = $this->isTestArticle();
	}

	protected function isTestArticle() {
		$testArticles = array();
		return in_array($this->t->getDBKey(), $testArticles) !== false ? true : false;
	}

	protected function addCSSLibs() {
		global $wgLanguageCode;

		parent::addCSSLibs();
		if ($wgLanguageCode == 'en' && $this->deviceOpts['show-checkmarks']) {
			$this->addCSS('mcmc'); // Checkmark css
		}

		if ($wgLanguageCode == 'en' && $this->deviceOpts['show-thumbratings']) {
			$this->addCSS('mthr'); // thumbs up/down ratings css
		}
		
		if ($wgLanguageCode == 'en' && $this->deviceOpts['show-cta']) {
			$this->addCSS('mtptc'); // top ten tips feature
		}

		if ($wgLanguageCode == 'en' && class_exists('Hillary')) {
			$this->addCSS('stbc'); // Stubs / Hillary tool css
		}

		if (class_exists('WHVid') && strpos($this->nonMobileHtml, 'whvid_cont') !== false) {
			self::addCSS('whvc', true); // Wikivideo
		}

		if (class_exists('TextScroller') && strpos($this->nonMobileHtml, 'textscroller_outer') !== false) {
			self::addCSS('tsc', true); // TextScroller
		}
	}

	protected function addJSLibs() {
		global $wgLanguageCode;

		parent::addJSLibs();
		if ($wgLanguageCode == 'en' && $this->deviceOpts['show-checkmarks']) {
			self::addJS('cm', true); // checkmark js
		}

		if ($wgLanguageCode == 'en' && $this->deviceOpts['show-thumbratings']) {
			self::addJS('thr', true); // thumbs up/down ratings js
		}
		
		if ($wgLanguageCode == 'en' && $this->deviceOpts['show-cta']) {
			self::addJS('mtip', true); // tips features
			self::addJS('mtpt', true); // top ten tips feature
		}

		if ($wgLanguageCode == 'en' && class_exists('Hillary')) {
			self::addJS('stb', true); // Stubs / Hillary tool js
		}

		if (class_exists('WHVid') && strpos($this->nonMobileHtml, 'whvid_cont') !== false) {
			self::addJs('whv', true); // Wikivideo
		}

		if (class_exists('TextScroller') && strpos($this->nonMobileHtml, 'textscroller_outer') !== false) {
			self::addJS('ts', true); // TextScroller
		}
	}
}

class MobileBasicArticleBuilder extends MobileHtmlBuilder {

	protected function generateHeader() {
		$headerVars = $this->getDefaultHeaderVars();
		$headerVars['showRUM'] = MobileHtmlBuilder::showRUM();
		return EasyTemplate::html('header.tmpl.php', $headerVars);
	}

	protected function getArticleParts() {
		return $this->parseNonMobileArticle($this->nonMobileHtml);
	}

	protected function generateBody() {
		global $wgLanguageCode;

		$device = $this->getDevice();

		list($sections, $intro, $firstImage) = $this->getArticleParts();
		if ($firstImage) {
			$title = Title::newFromText($firstImage->getTitle()->getText(), NS_IMAGE);
			if ($title) {
				$introImage = RepoGroup::singleton()->findFile($title);
			}
			if ($introImage) {
				list($thumb, $width, $height) =
					self::makeThumbDPI($introImage, 290, 194, $device['enlarge-thumb-high-dpi']);
				
				//make a srcset value
				$bigWidth = 600;
				$bigHeight = 800;
				list($thumb_big, $newWidth, $newHeight) =
					self::makeThumbDPI($introImage, $bigWidth, $bigHeight, $device['enlarge-thumb-high-dpi']);
				$url = wfGetPad($thumb_big->getUrl());
				
				$thumb_ss = $url.' '.$bigWidth.'w';
				$thumb_id = md5($thumb->getUrl());
				$swap_script = '<script type="text/javascript">if (isBig) WH.mobile.swapEm("'.$thumb_id.'");</script>';
			} else {
				$firstImage = '';
			}
		} 

		//articles that we don't want to have a top (above tabs)
		//image displayed
		$titleUrl = "";
		if($this->t != null)
			$titleUrl = $this->t->getFullURL();
		$exceptions = ConfigStorage::dbGetConfig('mobile-topimage-exception');
		$exceptionArray = explode("\n", $exceptions);
		if(in_array($titleUrl, $exceptionArray)) {
			$firstImage = false;
		}

		if (!$firstImage) {
			$thumb = null;
			$width = 0; $height = 0;
		}

		$redirMainBase = '/' . wfMsg('special') . ':' . wfMsg('MobileWikihow') . '?redirect-non-mobile=';

		$articleVars = array(
			'title' => $this->t->getText(),
			'title_class' => self::getTitleClass($this->t->getText()),
			'sections' => $sections,
			'intro' => $intro,
			'thumb' => &$thumb,
			'thumb_id' => &$thumb_id,
			'thumb_ss' => &$thumb_ss,
			'swap_script' => &$swap_script,
			'width' => $width,
			'height' => $height,
			'deviceOpts' => $device,
			'nonEng' => $wgLanguageCode != 'en',
			'isGerman' => $wgLanguageCode == 'de',
			'redirMainUrl' => $redirMainBase,
		);
		$this->addExtendedArticleVars(&$articleVars);

		$this->setTemplatePath();
		return EasyTemplate::html('article.tmpl.php', $articleVars);
	}
	
	/*
	 * sets the size of the title based on number of characters
	 * returns a CSS class name
	 */
	private function getTitleClass($text) {
		$count = strlen($text);
		if ($count > 40) {
			$className = 'title_sm';
		}
		else if ($count > 20) {
			$className = 'title_md';
		}
		else {
			$className = 'title_lg';
		}
		
		return $className;
	}

	protected function addExtendedArticleVars(&$vars) {
		// Nothing to add here. Used for subclasses to inject variables to be passed to article.tmpl.php html
	}

	protected function generateFooter() {
		global $wgUser;
		if ($wgUser->getID() > 0) {
			$tipsUrl = '/Special:TipsPatrol';
		}
		else {
			$tipsUrl = '/Special:Userlogin?returnto=Special:TipsPatrol';
		}
		
		$footerVars = $this->getDefaultFooterVars();	
		$t = $this->t;
		$partialUrl = $t->getPartialURL();
		$footerVars['redirMainBase'] = $footerVars['redirMainUrl'];
		$footerVars['redirMainUrl'] = $footerVars['redirMainUrl'] . urlencode($partialUrl);
		$baseMainUrl = MobileWikihow::getNonMobileSite() . '/';
		$footerVars['editUrl'] = $baseMainUrl . 'index.php?action=edit&title=' . $partialUrl;
		$footerVars['tipsUrl'] = $tipsUrl;
		return EasyTemplate::html('footer.tmpl.php', $footerVars);
	}

	protected function addCSSLibs() {
		$this->addCSS('mwhc');
		$this->addCSS('mwha');
	}

	// Make a thumb either regular res or high res (2x pixel density such
	// as retina display)
	private static function makeThumbDPI($image, $newWidth, $newHeight, $makeHighDPI) {
		if ($makeHighDPI) {
			$thumb = $image->getThumbnail(2 * $newWidth, 2 * $newHeight);
			$actualWidth = $thumb->getWidth();
			$actualHeight = $thumb->getHeight();
			if ($actualWidth > $newWidth) {
				$nh = round( $actualHeight * $newWidth / $actualWidth );
				// if $nh is still too high, balance $newWidth
				if ($nh > $newHeight) { 
					$newWidth = round( $newWidth * $newHeight / $nh );
				} else {
					$newHeight = $nh;
				}
			} elseif ($actualHeight > $newHeight) {
				$newWidth = round( $actualWidth * $newHeight / $actualHeight );
			} else {
				$newWidth = $actualWidth;
				$newHeight = $actualHeight;
			}
		} else {
			if ($image->getWidth() < $newWidth) {
				$thumb = $image;
			}
			else {
				$thumb = $image->getThumbnail($newWidth, $newHeight);
			}
			$newWidth = $thumb->getWidth();
			$newHeight = $thumb->getHeight();
		}
		return array($thumb, $newWidth, $newHeight);
	}

	/**
	 * Parse and transform the document from the old HTML for NS_MAIN articles to the new mobile
	 * style. This should probably be pulled out and added to a subclass that can then be extended for
	 * builders that focus on building NS_MAIN articles
	 */
	protected function parseNonMobileArticle(&$article) {
		global $IP, $wgContLang, $wgLanguageCode;

		$sectionMap = array(
			wfMsg('Intro') => 'intro',
			wfMsg('Ingredients') => 'ingredients',
			wfMsg('Steps') => 'steps',
			wfMsg('Video') => 'video',
			wfMsg('Tips') => 'tips',
			wfMsg('Warnings') => 'warnings',
			wfMsg('relatedwikihows') => 'relatedwikihows',
			wfMsg('sourcescitations') => 'sources',
			wfMsg('thingsyoullneed') => 'thingsyoullneed',
			wfMsg('article_info') => 'article_info',
		);

		$lang = MobileWikihow::getSiteLanguage();
		$imageNsText = $wgContLang->getNsText(NS_IMAGE);
		$device = $this->getDevice();

		// munge steps first
		$opts = array('no-ads' => true);
		$article = WikihowArticleHTML::postProcess($article, $opts);

		// Make doc correctly formed
$articleText = <<<DONE
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="$lang" lang="$lang">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset='utf-8'" />
</head>
<body>
$article
</body>
</html>
DONE;
		require_once("$IP/extensions/wikihow/mobile/JSLikeHTMLElement.php");
		$doc = new DOMDocument('1.0', 'utf-8');
		$doc->registerNodeClass('DOMElement', 'JSLikeHTMLElement');
		$doc->strictErrorChecking = false;
		$doc->recover = true;
		//$doc->preserveWhiteSpace = false;
		//$wgOut->setarticlebodyonly(true);
		@$doc->loadHTML($articleText);
		$doc->normalizeDocument();
		//echo $doc->saveHtml();exit;
		$xpath = new DOMXPath($doc);
		$pqDoc = PHPQuery::newDocument($doc);


		// Insert alternate images (or fork, as eliz calls it) that may exist.
		// Do this before other image processing later in this function so 
		// these images will be dealt with as any other article image would.
		if (class_exists('WHVid')) {
			WHVid::handleAlternateMobileImages();
		}

		// Delete #featurestar node
		$node = $doc->getElementById('featurestar');
		if (!empty($node)) {
			$node->parentNode->removeChild($node);
		}
		
		$node = $doc->getElementById('newaltmethod');
		if( !empty($node)) {
			   $node->parentNode->removeChild($node);
		}
		
		// Remove all "Edit" links
		$nodes = $xpath->query('//a[@id = "gatEditSection"]');
		foreach ($nodes as $node) {
			$node->parentNode->removeChild($node);
		}

		// Resize youtube video
		$nodes = $xpath->query('//embed');
		foreach ($nodes as $node) {
			$url = '';
			$src = $node->attributes->getNamedItem('src')->nodeValue;
			if (!$device['show-youtube'] || stripos($src, 'youtube.com') === false) {
				$parent = $node->parentNode;
				$grandParent = $parent->parentNode;
				if ($grandParent && $parent) {
					$grandParent->removeChild($parent);
				}
			} else {
				foreach (array(&$node, &$node->parentNode) as $node) {
					$widthAttr = $node->attributes->getNamedItem('width');
					$oldWidth = (int)$widthAttr->nodeValue;
					$newWidth = $device['max-video-width'];
					if ($newWidth < $oldWidth) {
						$widthAttr->nodeValue = (string)$newWidth;

						$heightAttr = $node->attributes->getNamedItem('height');
						$oldHeight = (int)$heightAttr->nodeValue;
						$newHeight = (int)round($newWidth * $oldHeight / $oldWidth);
						$heightAttr->nodeValue = (string)$newHeight;
					}
				}
			}
		}

		// Remove templates from intro so that they don't muck up
		// the text and images we extract
		$nodes = $xpath->query('//div[@class = "template_top"]');
		foreach ($nodes as $node) {
			$node->parentNode->removeChild($node);
		}

		$introResult = ArticleHTMLParser::processMobileIntro($imageNsText);
		$intro = $introResult['html'];
		$firstImage = $introResult['image'];

		// Get rid of the <span> element to standardize the html for the
		// next dom query
		$nodes = $xpath->query('//div/span/a[@class = "image"]');
		foreach ($nodes as $a) {
			$parent = $a->parentNode;
			$grandParent = $parent->parentNode;
			$grandParent->replaceChild($a, $parent);
		}

		// Resize all resize-able images
		$nodes = $xpath->query('//div/a[@class = "image"]/img');
		$imgNum = 1;
		foreach ($nodes as $img) {
			$srcNode = $img->attributes->getNamedItem('src');
			$widthNode = $img->attributes->getNamedItem('width');
			$width = (int)$widthNode->nodeValue;
			$heightNode = $img->attributes->getNamedItem('height');
			$height = (int)$heightNode->nodeValue;

			$imageClasses = $img->parentNode->parentNode->attributes->getNamedItem('class')->nodeValue;
			/*
			if (!stristr($imageClasses, "tcenter")) {
				$img->parentNode->parentNode->parentNode->attributes->getNamedItem('class')->nodeValue = '';
				$img->parentNode->parentNode->parentNode->attributes->getNamedItem('style')->nodeValue = '';
			}
			*/
//			if( stristr($imageClasses, "tcenter") !== false) {
			if( stristr($imageClasses, "floatcenter") !== false) {
				$newWidth = $device['full-image-width'];
				$newHeight = (int)round($device['full-image-width'] * $height / $width);
			}
			else {
				$newWidth = $device['max-image-width'];
				$newHeight = (int)round($device['max-image-width'] * $height / $width);
			}
			
			$a = $img->parentNode;
			$href = $a->attributes->getNamedItem('href')->nodeValue;
			if (!$href) {
				$onclick = $a->attributes->getNamedItem('onclick')->nodeValue;
				$onclick = preg_replace('@.*",[ ]*"@', '', $onclick);
				$onclick = preg_replace('@".*@', '', $onclick);
				$imgName = preg_replace('@.*(Image|' . $imageNsText . '|' . urlencode($imageNsText) . '):@', '', $onclick);
			} else {
				$imgName = preg_replace('@^/(Image|' . $imageNsText . '|' . urlencode($imageNsText) . '):@', '', $href);
			}
			
			$title = Title::newFromText($imgName, NS_IMAGE);
			if (!$title) {
				$imgName = urldecode($imgName);
				$title = Title::newFromText($imgName, NS_IMAGE);
			}
			
			if ($title) {
				$image = wfFindFile($title);
			
				if ($image) {
					list($thumb, $newWidth, $newHeight) =
						self::makeThumbDPI($image, $newWidth, $newHeight,
							$device['enlarge-thumb-high-dpi']);

					$url = wfGetPad($thumb->getUrl());

					$srcNode->nodeValue = $url;
					$widthNode->nodeValue = $newWidth;
					$heightNode->nodeValue = $newHeight;
					
					// change surrounding div width and height
					$div = $a->parentNode;
					$styleNode = $div->attributes->getNamedItem('style');
					//removing the set width/height
					$styleNode->nodeValue = '';
					//$div->attributes->getNamedItem('class')->nodeValue = '';
/*					if (preg_match('@^(.*width:)[0-9]+(px;\s*height:)[0-9]+(.*)$@', $styleNode->nodeValue, $m)) {
						$styleNode->nodeValue = $m[1] . $newWidth . $m[2] . $newHeight . $m[3];
					}
*/
					//add in our old class so all our logic still works
					$imgclass = $img->getAttribute('class');
					$img->setAttribute('class',$imgclass.'mwimage101');
					
					//default width/height for the srcset
					$bigWidth = 600;
					$bigHeight = 800;
					
					// change grandparent div width too
					$grandparent = $div;
					if ($grandparent && $grandparent->nodeName == 'div') {
						$class = $grandparent->attributes->getNamedItem('class');
                        if($class) {
                            $isThumb = stristr($class->nodeValue, 'mthumb') !== false;
                            $isRight = stristr($class->nodeValue, 'tright') !== false;
                            $isLeft = stristr($class->nodeValue, 'tleft') !== false;
                            $isCenter = stristr($class->nodeValue, 'tcenter') !== false;

                            if($isThumb) {
                                if($isRight) {
                                    $style = $grandparent->attributes->getNamedItem('style');
                                    $style->nodeValue = 'width:' . $newWidth . 'px;height:'.$newHeight.'px;';
                                    $bigWidth = 300;
                                    $bigHeight = 500;
                                }
                                elseif ($isCenter) {
                                    $style = $grandparent->attributes->getNamedItem('style');
                                    $style->nodeValue = 'width:' . $newWidth . 'px;height:'.$newHeight.'px;';
                                    $bigWidth = 600;
                                    $bigHeight = 800;
                                }
                                elseif ($isLeft) {
                                    //if its centered or on the left, give it double the width if too big

                                    $style = $grandparent->attributes->getNamedItem('style');
                                    $oldStyle = $style->nodeValue;
                                    $matches = array();
                                    preg_match('@(width:\s*)[0-9]+@', $oldStyle, $matches);

                                    if($matches[0]){
                                        $curSize = intval(substr($matches[0], 6)); //width: = 6
                                        if($newWidth*2 < $curSize){
                                            $existingCSS = preg_replace('@(width:\s*)[0-9]+@', 'width:'.$newWidth*2, $oldStyle);
                                            $style->nodeValue = $existingCSS;
                                        }
                                    }
                                    $bigWidth = 300;
                                    $bigHeight = 500;
                                }
                            }
                        }
					}
                    
					list($thumb, $newWidth, $newHeight) =
						self::makeThumbDPI($image, $bigWidth, $bigHeight, $device['enlarge-thumb-high-dpi']);
					$url = wfGetPad($thumb->getUrl());
					$img->setAttribute('srcset',$url.' '.$newWidth.'w');
					
					//if we couldn't make it big enough, let's add a class
					if ($newWidth < $bigWidth) {
						$imgclass = $img->getAttribute('class');
						$img->setAttribute('class',$imgclass.' not_huge');
					}
					
					//add the hidden info
					/*
					$newDiv = new DOMElement( 'div', htmlentities('test') );
					$a->appendChild($newDiv);
					$newDiv->setAttribute('style', 'display:none;');
					*/
					$a->setAttribute('id', 'image-zoom-' . $imgNum);
					$a->setAttribute('class', 'image-zoom');
					$a->setAttribute('href', '#');

					global $wgServer;
					$href = $wgServer . $href;
					$href = preg_replace('@\bm\.@', '', $href);
					$href = preg_replace('@^http://wikihow\.com@', 'http://www.wikihow.com', $href);

					$details = array(
						'url' => $url,
						'width' => $newWidth,
						'height' => $newHeight,
						'credits_page' => $href
					);
					$newDiv = new DOMElement( 'div', htmlentities(json_encode($details)) );
					$a->appendChild($newDiv);
					$newDiv->setAttribute('style', 'display:none;');
					$newDiv->setAttribute('id', 'image-details-' . $imgNum);
					$imgNum++;
				}
				else {
					//huh? can't find it? well, then let's not display it
					$img->parentNode->parentNode->parentNode->parentNode->setAttribute('style','display:none;');
				}
			}
			else {
				//huh? can't find it? well, then let's not display it
				$img->parentNode->parentNode->parentNode->parentNode->setAttribute('style','display:none;');
			}
		}

		// Remove template from images, add new zoom one
		$nodes = $xpath->query('//img');
		foreach ($nodes as $node) {
			$src = ($node->attributes ? $node->attributes->getNamedItem('src') : null);
			$src = ($src ? $src->nodeValue : '');
			if (stripos($src, 'magnify-clip.png') !== false) {
				$parent = $node->parentNode;
				$parent->parentNode->removeChild($parent);
			}
		}


		// //get rid of the corners and watermarks
		// $nodes = $xpath->query('//div[@class = "corner top_left" 
								// or @class = "corner bottom_left"
								// or @class = "corner top_right"
								// or @class = "corner bottom_right"
								// or @class = "wikihow_watermark"]');
		// foreach ($nodes as $node) {
			// $parent = $node->parentNode;
			// $parent->removeChild($node);
		// }
		
		//gotta swap in larger images if the client's width is big enough
		//(i.e. tablet et al)
		$nodes = $xpath->query('//img[@class = "mwimage101" 
								or @class = "mwimage101 not_huge"]');
		foreach ($nodes as $node) {
			//make a quick unique id for this
			$id = md5($node->attributes->getNamedItem('src')->nodeValue).rand();
			$node->setAttribute('id',$id);
			
			//pass it to our custom function for swapping in larger images
			$swap_it = 'if (isBig) WH.mobile.swapEm("'.$id.'");';
			$scripttag = new DOMElement( 'script', htmlentities($swap_it) );
			$node->appendChild($scripttag);
		}

		// Change the width attribute from any tables with a width set.
		// This often happen around video elements.
		$nodes = $xpath->query('//table/@width');
		foreach ($nodes as $node) {
			$width = preg_replace('@px\s*$@', '', $node->nodeValue);
			if ($width > $device['screen-width'] - 20) {
				$node->nodeValue = $device['screen-width'] - 20;
			}
		}

		// Surround step content in its own div. We do this to support other features like checkmarks
		$nodes = $xpath->query('//div[@id="steps"]/ol/li');
		foreach ($nodes as $node) {
			$node->innerHTML = '<div class="step_content">' . $node->innerHTML . '</div>';
		}

		//remove quiz
		$nodes = $xpath->query('//div[@class = "quiz_cta"]');
		foreach ($nodes as $node) {
			$node->parentNode->removeChild($node);
		}
		
		//remove quiz header
		$nodes = $xpath->query('//h3/span[text()="Quiz"]');
		foreach ($nodes as $node) {
			$parentNode = $node->parentNode;
			$parentNode->parentNode->removeChild($parentNode);
		}
		
		//remove edit link in h3 headers
		$nodes = $xpath->query('//h3/a[@class="editsection"]');
		foreach ($nodes as $node) {
			$node->parentNode->removeChild($node);
		}

		//pull out the first 6 related wikihows and format them
		$nodes = $xpath->query('//div[@id="relatedwikihows"]/ul/li');
		$count = 0;
		$related_boxes = array();
		foreach ($nodes as $node) {
			if ($count > 6) break;

			//grab the title
			preg_match('@href=\"\/(.*?)?\"@',$node->innerHTML,$m);			
			$title = Title::newFromText($m[1]);
			if (!$title) continue;
			
			$temp_box = $this->makeRelatedBox($title);
			
			if ($temp_box) {
				$related_boxes[] = $temp_box;
				$last_node = $node;
				$parent = $node->parentNode;
				$last_parent = $parent;
				$parent->removeChild($node);
				$count++;
			}
		}
		//only 1? not enough. throw it back
		if ($count == 1) {
			$related_boxes = array();
			$last_parent->appendChild($last_node);
		}
		
		// Inject html into the DOM tree for specific features (ie thumb ratings, ads, etc)
		$this->mobileParserBeforeHtmlSave($xpath);

		//self::walkTree($doc->documentElement, 1);
		$html = $doc->saveXML();

		$sections = array();
		$sectionsHtml = explode('<h2>', $html);
		unset($sectionsHtml[0]); // remove leftovers from intro section
		foreach ($sectionsHtml as $i => &$section) {
			$section = '<h2>' . $section;
			$count = 0;
			$heading = '';
			$replFunc = function($matches) use (&$heading) {
				$heading = trim($matches[1]);
				return '';
			};
			$output = preg_replace_callback('@^<h2>[^\n]*<span class="mw-headline"[^>]*>[ \t]*([^<]+)</span></h2>@', 
				$replFunc, $section, 1, $count);
			if ($count > 0) {
				$section = $output;
				if (isset($sectionMap[$heading])) {
					$key = $sectionMap[$heading];
					$sections[$key] = array(
						'name' => $heading,
						'html' => $section,
					);
				}
			}
		}
		
		// Remove Video section if there is no longer a youtube video
		if (isset($sections['video'])) {
			if ( !preg_match('@<object@i', $sections['video']['html']) ) {
				unset( $sections['video'] );
			}
		}
		
		// Add the related boxes
		if (isset($sections['relatedwikihows']) && !empty($related_boxes)) {
			$sections['relatedwikihows']['boxes'] = $related_boxes;
		}
		
		// Add article info
		$sections['article_info']['name'] = wfMsg('article_info');
		$sections['article_info']['html'] = $this->getArticleInfo($title);
		
		// Remove </body></html> from html
		if (count($sections) > 0) {
			$keys = array_keys($sections);
			$last =& $sections[ $keys[count($sections) - 2] ]['html'];
			$last = preg_replace('@</body>(\s|\n)*</html>(\s|\n)*$@', '', $last);
		}

		// Add a simple form for uploading images of completed items to the article
		if ($wgLanguageCode == 'en' && isset($sections['steps'])
				&& isset($device['show-upload-images']) && $device['show-upload-images']) {
			require_once("$IP/extensions/wikihow/mobile/MobileUciHtmlBuilder.class.php");
			$userCompletedImages = new MobileUciHtmlBuilder();
			$sections['steps']['html'] .= $userCompletedImages->createByHtml($this->t);
		}

		return array($sections, $intro, $firstImage);
	}
}

/*
* Builds the body of the article with appropriate javascript and google analytics tracking.  
* This is used primarily for the Mobile QG (MQG) tool.
*/
class MobileQGArticleBuilder extends MobileBasicArticleBuilder {

	protected function generateHeader() {
		return "";
	}

	protected function generateFooter() {
		return "";
	}


	// never run test for mobileqg articles
	protected function isStaticTestArticle() {
		return false;
	}

	// Override device options so we can turn off ads
	protected function getDevice() {
		$device = $this->deviceOpts;
		$device['show-ads'] = false;
		return $device;
	}

	protected function addJSLibs() {
		// Don't include the jquery JS here.  This will be added in the MQG special page
	}
}

class MobileMainPageBuilder extends MobileHtmlBuilder {
	
	protected function generateHeader() {
		$headerVars = $this->getDefaultHeaderVars();
		$headerVars['showTagline'] = true;
		return EasyTemplate::html('header.tmpl.php', $headerVars);
	}

	protected function generateBody() {
		global $wgLanguageCode;

		$featured = $this->getFeaturedArticles(7);
		$randomUrl = '/' . wfMsg('special-randomizer');
		$spotlight = $this->selectSpotlightFeatured($featured);
		$langUrl = '/' . wfMsg('mobile-languages-url');
		$vars = array(
			'randomUrl' => $randomUrl,
			'spotlight' => $spotlight,
			'featured' => $featured,
			'languagesUrl' => $langUrl,
			'imageOverlay' => $wgLanguageCode == 'en',
			'nonEng' => $wgLanguageCode != 'en',
		);
		return EasyTemplate::html('main-page.tmpl.php', $vars);
	}

	protected function generateFooter() {
		$footerVars = $this->getDefaultFooterVars();	
		return EasyTemplate::html('footer.tmpl.php', $footerVars);
	}

	private function selectSpotlightFeatured(&$featured) {
		$spotlight = array();
		if ($featured) {
			// grab a random article from the list without replacement
			$r = mt_rand(0, count($featured) - 1);
			$spotlight = $featured[$r];
			unset($featured[$r]);
			$featured = array_values($featured); // re-key array

			$title = Title::newFromText($spotlight['fullname']);
			if ($title && $title->getArticleID() > 0) {
				$spotlight['img'] = $this->getFeatureArticleImage($title, 600, 400);
				$spotlight['intro'] = $this->getFeaturedArticleIntro($title);
			}
			else {
				$spotlight['img'] = 'nope';
				$spotlight['intro'] = 'nothin';
			}
		}
		return $spotlight;
	}

	private function getFeatureArticleImage(&$title, $width, $height) {
		global $wgUser;
		$skin = $wgUser->getSkin();

		// The next line was taken from:
		//   SkinWikihowskin::featuredArticlesLineWide()
		$img = SkinWikihowskin::getGalleryImage($title, $width, $height);
		return wfGetPad($img);
	}

	private function getFeaturedArticles($num) {
		global $IP;
		$NUM_DAYS = 15; // enough days to make sure we get $num articles

		$featured = FeaturedArticles::getFeaturedArticles($NUM_DAYS);

		$fas = '';
		$n = 1;
		foreach($featured as $f) {
			$partUrl = preg_replace('@^http://(\w|\.)+\.wikihow\.com/@', '', $f[0]);
			$title = Title::newFromURL(urldecode($partUrl));

			$box_array = $this->makeRelatedBox($title, true);
			if (!empty($box_array)) {
				$fas[] = $box_array;
				if (++$n > $num) break;
			}
		}

		return $fas;
	}

	private function getFeaturedArticleIntro(&$title) {
		// use public methods from the RSS feed that do the same thing
		$article = GoodRevision::newArticleFromLatest($title);
		$summary = Generatefeed::getArticleSummary($article, $title);
		return $summary;
	}

	protected function addCSSLibs() {
		#$this->addCSS('mwhf');
		#$this->addCSS('mwhh');
		$this->addCSS('mwhc');
		$this->addCSS('mwha');
	}

}

class MobileViewLanguagesBuilder extends MobileHtmlBuilder {
	
	protected function generateHeader() {
		$headerVars = $this->getDefaultHeaderVars();
		$headerVars['css'][] = 'mwhr';
		return EasyTemplate::html('header.tmpl.php', $headerVars);
	}

	protected function generateBody() {
		$vars = array('languages' => self::getLanguages());
		return EasyTemplate::html('language-select.tmpl.php', $vars);
	}

	protected function generateFooter() {
		$footerVars = $this->getDefaultFooterVars();	
		return EasyTemplate::html('footer.tmpl.php', $footerVars);
	}

	private static function getLanguages() {
		$ccedil = htmlspecialchars_decode('&ccedil;');
		$ntilde = htmlspecialchars_decode('&ntilde;');
		$ecirc = htmlspecialchars_decode('&ecirc;');
		$langs = array(
			array(
				'code' => 'en', 
				'name' => 'English',
				'url'  => 'http://m.wikihow.com/',
				'img'  => '/extensions/wikihow/mobile/images/flag_england.gif',
			),
			array(
				'code' => 'es', 
				'name' => "Espa{$ntilde}ol",
				'url'  => 'http://es.m.wikihow.com/',
				'img'  => '/extensions/wikihow/mobile/images/flag_spain.gif',
			),
			array(
				'code' => 'de', 
				'name' => 'Deutsch',
				'url'  => 'http://de.m.wikihow.com/',
				'img'  => '/extensions/wikihow/mobile/images/flag_germany.gif',
			),
			array(
				'code' => 'pt', 
				'name' => "Portugu{$ecirc}s",
				'url'  => 'http://pt.m.wikihow.com/',
				'img'  => '/extensions/wikihow/mobile/images/flag_portugal.gif',
			),
			array(
				'code' => 'fr', 
				'name' => "Fran${ccedil}ais",
				'url'  => 'http://fr.m.wikihow.com/',
				'img'  => '/extensions/wikihow/mobile/images/flag_france.gif',
			),
			array(
				'code' => 'it', 
				'name' => 'Italiano',
				'url'  => 'http://it.m.wikihow.com/',
				'img'  => '/extensions/wikihow/mobile/images/flag_italy.gif',
			),
			array(
				'code' => 'nl', 
				'name' => 'Nederlands',
				'url'  => 'http://nl.m.wikihow.com/',
				'img'  => '/extensions/wikihow/mobile/images/flag_netherlands.gif',
			),
		);
		return $langs;
	}
}

class Mobile404Builder extends MobileHtmlBuilder {
	
	protected function generateHeader() {
		$headerVars = $this->getDefaultHeaderVars();
		return EasyTemplate::html('header.tmpl.php', $headerVars);
	}

	protected function generateBody() {
		$vars = array('mainPage' => wfMsg('mainpage'));
		return  EasyTemplate::html('not-found.tmpl.php', $vars);
	}

	protected function generateFooter() {
		$footerVars = $this->getDefaultFooterVars();	
		return EasyTemplate::html('footer.tmpl.php', $footerVars);
	}
}

class MobileSampleBuilder extends MobileBasicArticleBuilder {

	protected function generateHeader() {
		$headerVars = $this->getDefaultHeaderVars();
		return EasyTemplate::html('header.tmpl.php', $headerVars);
	}
	
	protected function generateBody() {
		return DocViewer::displayContainer('',true);
	}

	protected function generateFooter() {
		$footerVars = $this->getDefaultFooterVars();	
		return EasyTemplate::html('footer.tmpl.php', $footerVars);
	}

	// Override device options so we can turn off ads
	protected function getDevice() {
		$device = $this->deviceOpts;
		$device['show-ads'] = false;
		return $device;
	}

	protected function addCSSLibs() {
		//parent::addCSSLibs();
		$this->addCSS('msd');
		$this->addCSS('mwhc');
		$this->addCSS('mwha');
	}
}
