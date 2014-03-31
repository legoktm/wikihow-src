<?php

if (!defined('MEDIAWIKI')) die();

class MobileWikihow extends UnlistedSpecialPage {

	const NON_MOBILE_COOKIE_NAME = 'wiki_nombl';

	private $executeAsSpecialPage;
	private static $language;
	
	public function __construct() {
		parent::__construct('MobileWikihow');
		$this->executeAsSpecialPage = false;
	}

	public function execute() {
		$this->executeAsSpecialPage = true;
		$this->controller();
	}

	/**
	 * Process params that either display html for the mobile site, redirect
	 * to the mobile site, set cookies, etc.
	 *
	 * @return true if skin should continue processing, false otherwise
	 */
	public function controller() {
		global $wgTitle, $wgRequest, $wgOut;

		self::setTemplatePath();
		
		$action = $wgRequest->getVal('action', 'view');
		$isArticlePage = $wgTitle
			&& $wgTitle->getNamespace() == NS_MAIN
			&& $action == 'view';

		if (self::isMobileDomain()) {
			$redir = $wgRequest->getVal('redirect-non-mobile');
			if (!empty($redir) || $redir === '') {
				// this code is called if someone is on the mobile site and
				// asks to be directed to the full site. We set a cookie
				// so that the automatic varnish redirect based on
				// their mobile browser doesn't happen
				self::setNonMobileCookie(true);
				$newUrl = self::getNonMobileSite() . '/' . $redir;
				$wgOut->redirect($newUrl);
				$wgOut->output();
				return false;
			} elseif (self::hasNonMobileCookie()) {
				// if they've chosen to come back to the mobile site from
				// the full site, we unset that mobile redirect cookie
				self::setNonMobileCookie(false);
			}

			if (self::isMobileViewable()) {
				// If the URL is Special:MobileWikihow, this
				// page has already been processed through the special
				// page code path
				if ($wgTitle->getText() != wfMsg('MobileWikihow') || $this->executeAsSpecialPage) {
					$this->displayHtml();
				}
				return false;
			}
		} elseif (!IS_PROD_EN_SITE
			&& !IS_PROD_INTL_SITE
			&& !self::hasNonMobileCookie()
			&& self::isUserAgentMobile()
			&& self::isMobileViewable())
		{
			// if we're not in production, and we want to test redirections
			// in mobile browsers, we need this clause to duplicate the
			// functionality of the varnish redirect
			$newUrl = self::getMobileSite() . '/' . $wgTitle->getPrefixedUrl();
			$wgOut->redirect($newUrl);
			$wgOut->output();
			return false;
		}

		return true;
	}

	private function displayHtml() {
		global $IP, $wgTitle, $wgOut, $wgRequest;
		
		$isMainPage = $wgTitle->getText() == wfMsg('mainpage');
		$isSample = class_exists('DocViewer') && $wgTitle->getText() == 'DocViewer';
		$action = $wgRequest->getVal('action', 'view');
		$pageExists = $wgTitle->exists();

		$article = "";
		if (!$isMainPage) {
			// get the html for the article since it's already been generated
			$article = $wgOut->getHTML();
		}
		$wgOut->clearHTML();
		
		// handle search and i10l pages here
		require_once("$IP/extensions/wikihow/mobile/MobileHtmlBuilder.class.php");
		if ($isMainPage) {
			// The main page doesn't need any content
			$article = 'Main-Page';
			$m = new MobileMainPageBuilder();
		} elseif ($isSample) {
			$sample_title = DocViewer::getPageTitle();
			$title = Title::newFromText($sample_title);
			$article = 'Sample';
			$m = new MobileSampleBuilder();
			echo $m->createByHtml($title,$article);
			return;
		} elseif ($action == 'view-languages') {
			$m = new MobileViewLanguagesBuilder();
		} elseif (!$pageExists) {
			$article = '404 page';
			$m = new Mobile404Builder();
		} else { // article page
			$m = new MobileArticleBuilder();
		}
		$wgOut->setArticleBodyOnly(true);

		wfRunHooks( 'JustBeforeOutputHTML', array( &$this ) );

		echo $m->createByHtml($wgTitle, $article);
	}

	private static function isMobileViewable() {
		global $wgTitle, $wgRequest;
		$validMobileActions = array('view', 'view-languages');

		$action = $wgRequest->getVal('action', 'view');
		$isArticlePage = $wgTitle
			&& ($wgTitle->getNamespace() == NS_MAIN
				|| $wgTitle->getText() == wfMsg('MobileWikihow'))
			&& in_array($action, $validMobileActions);
		$isMQG = $wgTitle && $wgTitle->getText() == 'MQG';
		$isSample = $wgTitle && class_exists('DocViewer') && $wgTitle->getText() == 'DocViewer';

		return $isArticlePage || $isMQG || $isSample;
	}

	public static function isUserAgentMobile() {
		global $wgRequest;

		$header = @$_SERVER['HTTP_X_BROWSER'];
		if ($header) {
			return $header == 'mb';
		} else {
			// Backup if varnish isn't present to send X-Browser header; this
			// code is executed on dev/doh etc
			$uagent = @$_SERVER['HTTP_USER_AGENT'];

			// NOTE: this regexp MUST match the same things as the corresponding
			// one in /usr/local/wikihow/config/varnish/wikihow-fastly.vcl, else an
			// evil redirect loop could be created
//			return preg_match('@iphone|ipod|blackberry|palm|android|windows ce|webos|symbian|motorola|opera.mini|nokian9@i', $uagent) > 0;
			// updated 01/14/2014 [sc]
			return preg_match('@android|blackberry|ipad|iphone|ipod|ios|kindle|motorola|nokian9|opera.mini|palm|silk|symbian|touch|webos|windows.ce|windows.phone|WP[7-9]@i', $uagent) > 0; 
		}
	}

	public static function isMobileDomain() {
		global $wgServer;
		return preg_match('@\bm\.@', $wgServer) > 0;
	}

	public static function getMobileSite() {
		global $wgServer;
		if ($wgServer == 'http://www.wikihow.com') {
			return 'http://m.wikihow.com';
		} else {
			if (!preg_match('@\bm\.@', $wgServer)) {
				return preg_replace('@^(https?://[^\.]*)\.(.+\.com)$@', '$1.m.$2', $wgServer);
			} else {
				return $wgServer;
			}
		}
	}
	
	public function getSiteLanguage() {
		global $wgServer;
		if (!empty(self::$language)) {
			return self::$language;
		} else {
			if (preg_match('@^https?://([a-z]{2})\.m\.wikihow\.com$@i', $wgServer, $m)) {
				self::$language = strtolower($m[1]);
			} else {
				self::$language = 'en';
			}
		}
	}

	public static function getNonMobileSite() {
		global $wgServer;
		if ($wgServer == 'http://m.wikihow.com') {
			return 'http://www.wikihow.com';
		} else {
			return str_replace('.m.', '.', $wgServer);
		}
	}

	private static function hasNonMobileCookie() {
		$cookie = @$_COOKIE[ self::NON_MOBILE_COOKIE_NAME ];
		return !empty($cookie) && $cookie == '1';
	}

	private static function setNonMobileCookie($useNonMobileSite) {
		global $wgCookieDomain;
		$cookieValue = ($useNonMobileSite ? '1' : false);
		$expires = time() + 2*60*60; // 2 hours from now -- specified by Jack
		setcookie(self::NON_MOBILE_COOKIE_NAME, $cookieValue, $expires, '/', $wgCookieDomain);
	}

	const DEFAULT_DEVICE = 'iphone'; // works fine for android phones too

	public static function getPlatformConfigs() {
		$platforms = array(
			'iphone' => array(
				'name' => 'iphone',
				'screen-width' => 320,
				'screen-height' => 480,
				'image-zoom-width' => 270,
				'image-zoom-height' => 430,
				'max-image-width' => 100,
				'full-image-width' => 250,
				'enlarge-thumb-high-dpi' => false,
				'max-video-width' => 300,
				'intro-image-format' => 'conditional',
				'show-only-steps-tab' => true,
				'show-header-footer' => true,
				'show-youtube' => true,
				'show-ads' => true,
				'show-css' => true,
				'show-analytics' => true,
				'show-checkmarks' => true,
				'show-thumbratings' => true,
				'show-cta' => true,
				'show-upload-images' => true,
			),
			'iphoneapp' => array(
				'name' => 'iphoneapp',
				'screen-width' => 320,
				'screen-height' => 480,
				'image-zoom-width' => 270,
				'image-zoom-height' => 430,
				'max-image-width' => 100,
				'full-image-width' => 250,
				'enlarge-thumb-high-dpi' => false,
				'max-video-width' => 300,
				'intro-image-format' => 'conditional',
				'show-only-steps-tab' => true,
				'show-header-footer' => false,
				'show-youtube' => true,
				'show-ads' => false,
				'show-css' => true,
				'show-analytics' => false,
				'show-checkmarks' => true,
				'show-thumbratings' => false,
				'show-cta' => false,
				'show-upload-images' => false,
			),
			'symbianapp' => array(
				'name' => 'symbianapp',
				'screen-width' => 320,
				'screen-height' => 480,
				'image-zoom-width' => 270,
				'image-zoom-height' => 430,
				'max-image-width' => 100,
				'full-image-width' => 100,
				'enlarge-thumb-high-dpi' => false,
				'max-video-width' => 300,
				'intro-image-format' => 'conditional',
				'show-only-steps-tab' => true,
				'show-header-footer' => false,
				'show-youtube' => false,
				'show-ads' => false,
				'show-css' => false,
				'show-analytics' => true,
				'show-checkmarks' => true,
				'show-thumbratings' => true,
				'show-cta' => true,
				'show-upload-images' => false,
			),
			'ipad' => array(
				'name' => 'ipad',
				'screen-width' => 768,
				'screen-height' => 1024,
				'image-zoom-width' => 500,
				'image-zoom-height' => 600,
				'max-image-width' => 250,
				'full-image-width' => 250,
				'enlarge-thumb-high-dpi' => true,
				'max-video-width' => 600,
				'intro-image-format' => 'right',
				'show-only-steps-tab' => true,
				'show-header-footer' => false,
				'show-youtube' => true,
				'show-ads' => false,
				'show-css' => true,
				'show-analytics' => false,
				'show-checkmarks' => false,
				'show-thumbratings' => false,
				'show-cta' => false,
				'show-upload-images' => false,
			),
			'chromestore' => array(
				'name' => 'chromestore',
				'screen-width' => 768,
				'screen-height' => 1024,
				'image-zoom-width' => 500,
				'image-zoom-height' => 600,
				'max-image-width' => 250,
				'full-image-width' => 250,
				'enlarge-thumb-high-dpi' => false,
				'max-video-width' => 600,
				'intro-image-format' => 'right',
				'show-only-steps-tab' => false,
				'show-header-footer' => true,
				'show-youtube' => true,
				'show-ads' => false,
				'show-css' => true,
				'show-analytics' => true,
				'show-checkmarks' => true,
				'show-thumbratings' => true,
				'show-cta' => true,
				'show-upload-images' => false,
			),
		);
		return $platforms;
	}

	/**
	 * Returns properties of device that we need to abstract in certain causes.
	 * Sort of functions like a poor man's WURFL database.
	 */
	public static function getDevice() {
		global $wgRequest, $wgUser, $wgTitle;

		$platforms = self::getPlatformConfigs();
		$platform = $wgRequest->getVal('platform', self::DEFAULT_DEVICE);
		if (!isset($platforms[ $platform ])) {
			$platform = self::DEFAULT_DEVICE;
		}
		
		$device = $platforms[ $platform ];
		
		if ($wgUser->getID() > 0) { 
			$device['show-ads'] = false;
		}
        elseif (wikihowAds::adExclusions($wgTitle)) {
            $device['show-ads'] = false;
        }
		
		return $device;
	}

	/**
	 * Set html template path for Easyimageupload actions
	 */
	public static function setTemplatePath() {
		EasyTemplate::set_path( dirname(__FILE__).'/' );
	}

}
