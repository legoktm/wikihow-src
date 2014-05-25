<?

if (!defined('MEDIAWIKI')) die();

class MobileUciHtmlBuilder {
	private $includes; // Only show image uploader for these articles.
	private $blacklist;

	public function __construct() {
		$this->setIncludes();
		$this->setBlacklist();
	}

	public function createByHtml(&$t) {
		global $wgDebugToolbar, $wgLanguageCode;

		if ((!$t || !$t->exists())) {
			return '';
		}

		$artIdInt = intval($t->getArticleId());
		if (isset($this->blacklist[$artIdInt]) || !isset($this->includes[$artIdInt]) || $wgLanguageCode != "en") {
			// if we are debugging always show this
			if (!$wgDebugToolbar) {
				return '';
			}
		}

		$this->addJSLibs();

		$this->t = $t;
		$html = $this->generateHtml();
		return $html;
	}

	protected function addJSLibs() {
		MobileBasicArticleBuilder::addJS('maim', true); // AIM.js
		MobileBasicArticleBuilder::addJS('muci', true); // User Completed Images script
	}

	private function generateHtml() {
		global $wgOut;

		$me = Title::makeTitle(NS_SPECIAL, 'ImageUploadHandler');

		$vars = array();

		$vars['submitUrl'] = $me->getFullUrl() . '?viapage=' . $this->t->getPartialURL();
		$vars['loadingWheel'] = wfGetPad('/extensions/wikihow/rotate.gif');

		return EasyTemplate::html('mobile-image-upload.tmpl.php', $vars);
	}

	private function setIncludes() {
		$filename = __DIR__.'/uci_whitelist.txt';
		$this->includes = array();
		$list = file($filename);
		foreach($list as $line) {
			$this->includes[intval($line)] = true;
		}
	}

	private function setBlacklist() {
		// These articles are *NOT* to have an image upload button, overriding
		// the includes on conflict.
		$filename = __DIR__.'/uci_blacklist.txt';
		$list = file($filename);
		foreach($list as $line) {
			$this->blacklist[intval($line)] = true;
		}
	}
}
