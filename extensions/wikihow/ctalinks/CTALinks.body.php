<?

class CTALinks {

	function __construct() {
	}

        function getCTA() {
                global $wgTitle, $wgUser, $wgRequest;

		// Check various conditions to verify we should be displaying CTA links
		if (!CTALinks::isArticlePageTarget()) return "";

		// ctaLinks structure is array [ctaId, link, ctaId, link, ...]. Whenever a ctaLink is added to the cta_links message, it must be preceded with a unique id number
                $ctaLinks  = explode(",", trim(wfMsgForContent('cta_links')));
		if (sizeof($ctaLinks) == 0) return "";

                $result = "";
		// Debug option to pass in specific ctaId to display
		$debugId = $wgRequest->getVal('ctaid');
		if (isset($debugId)) {
			$result = CTALinks::getCTAById($debugId, $ctaLinks);
		} else {
			if (isset($_COOKIE["ctaId"])) {
				$result = CTALinks::getCTAById($_COOKIE["ctaId"], $ctaLinks);
			} else {
				$ctaNum = time() % (sizeof($ctaLinks) / 2);
				$result = CTALinks::getCTAById($ctaLinks[$ctaNum * 2], $ctaLinks);
				setcookie("ctaId", intval($ctaLinks[$ctaNum * 2]));
			}
		}
                return $result;
        }
	
	function getCTAById($ctaId, &$ctaLinks) {
		$ctaId = intval($ctaId);
		$ctaIdx = array_search($ctaId, $ctaLinks);
		if ($ctaIdx === FALSE) {
			wfDebug("cta not found with id $ctaId");
			return "";
		}
		return '<script>utmx_section("ctaLink")</script><div class="ctaLink" id="' . $ctaId . '"></noscript><a href="' . trim($ctaLinks[$ctaIdx + 1]) . '?ctaconv=true">' .
			'<img src="' . wfGetPad('extensions/wikihow/ctalinks/cta_img_' . $ctaId . '.png') . '" /></a></div><!--end cta_link-->';
	}

	function getBlankCTA() {
		// Check various conditions to verify we should be displaying CTA links
		if (!self::isArticlePageTarget()) return "";
		return '<script>utmx_section("ctaLink")</script><div class="ctaLink"></div></noscript><!--end cta_link-->';
	}

	function getGoogleConversionScript() {
		$script = "";
		if (self::isConversionPageTarget()) {
			$script = wfMsgForContent('cta_conversion');
		}
		return $script;
	}
	
	function getGoogleControlScript() {
		$script = "";
		if (self::isArticlePageTarget()) {
			$script = wfMsgForContent('cta_control');
		}
		return $script;
	}

	function getGoogleControlTrackingScript() {
		$script = "";
		if (self::isArticlePageTarget()) {
			$script = wfMsgForContent('cta_control_tracking');
		}
		return $script;
	}
	
	
	function isArticlePageTarget() {
                global $wgTitle, $wgRequest, $wgUser;
                // Only display for article pages that aren't new articles
                $createNewArticle = $wgRequest->getVal('create-new-article', '') == 'true';
                return !($createNewArticle || $wgTitle->getNamespace() != NS_MAIN || $wgTitle->getFullText() == wfMsg('mainpage') || $wgRequest->getVal('action') != '');
	}

	function isLoggedIn() {
		global $wgUser;
		return $wgUser->getId() > 0;
	}

	function isConversionPageTarget() {
                global $wgRequest;
		//return $wgRequest->getVal('ctaconv') == 'true';
		return self::isArticlePageTarget();
	}
}
