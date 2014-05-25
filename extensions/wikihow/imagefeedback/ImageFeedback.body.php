<?
/*
*   Collects feedback on article images from users of wikihow
*/
class ImageFeedback extends UnlistedSpecialPage {
	const WIKIPHOTO_USER_NAME = 'wikiphoto';
	public static $allowAnonFeedback = null;

	function __construct() {
		parent::__construct('ImageFeedback');
	}

	function execute($par) {
		global $wgRequest, $wgOut, $wgUser, $wgServer;

		if ($wgRequest->wasPosted()) {
			$action = $wgRequest->getVal('a');
			if (in_array('staff', $wgUser->getGroups()) && $action == 'reset_urls') {
				$this->resetUrls();
			} else {
				$this->handleImageFeedback();
			}
		} else {
			if ((strpos($wgServer, "wikiknowhow.com") !== false || strpos($wgServer, "wikidiy.com") !== false) && 
				in_array('staff', $wgUser->getGroups())) {
				$this->showAdminForm();
			}
		}
	}

	private function showAdminForm() {
		global $wgOut;
		EasyTemplate::set_path(dirname(__FILE__));
		$vars['ts'] = wfTimestampNow();
		$wgOut->addHtml(EasyTemplate::html('imagefeedback_admin'));
	}
	
	private function resetUrls() {
		global $wgRequest, $wgOut;
		$urls = preg_split("@\n@", trim($wgRequest->getVal('if_urls')));
		foreach ($urls as $url) {
			if (!empty($url)) {
				$t = WikiPhoto::getArticleTitle($url);
				if ($t && $t->exists()) {
					$aids[] = $t->getArticleId();
				} else {
					$invalid[] = $url;
				}
			}
		}
		$numUrls = sizeof($aids);
		if ($numUrls) {
			$dbw = wfGetDB(DB_MASTER);
			$aidsList = "(" . implode(",", $aids) . ")";
			$dbw->delete('image_feedback', array("ii_img_page_id IN $aidsList"), __METHOD__);
		}
	
		if (sizeof($invalid)) {
			$invalid = "These input urls are invalid:<br><br>" . implode("<br>", $invalid);
		}
		$wgOut->setArticleBodyOnly(true);
		$wgOut->addHtml("$numUrls reset.$invalid");
	}

	private function handleImageFeedback() {
		global $wgRequest, $wgOut, $wgName, $wgUser;

		$dbw = wfGetDB(DB_MASTER);

		$reason = $wgRequest->getVal('reason');
		// Remove / chars from reason since this will be our delimeter in the ii_reason field
		$reason = $dbw->strencode(trim(str_replace("/", "", $reason)));
		// Add user who reported
		$reason = $wgUser->getName() . " says: $reason";

		$voteTypePrefix = $wgRequest->getVal('voteType') == 'good' ? 'ii_good' : 'ii_bad';
		
		$aid = $dbw->addQuotes($wgRequest->getVal('aid'));
		$imgUrl = substr(trim($wgRequest->getVal('imgUrl')), 1);
		$isWikiPhotoImg = 0;

		// Check if image is a wikiphoto image
		$t = Title::newFromUrl($imgUrl);
		if ($t && $t->exists()) {
			$r = Revision::newFromTitle($t);
			$userText = $r->getUserText();
			if (strtolower($r->getUserText()) == self::WIKIPHOTO_USER_NAME) {
				$isWikiPhotoImg = 1;
			}

			$url = substr($t->getLocalUrl(), 1);
			$voteField = $voteTypePrefix . "_votes";
			$reasonField = $voteTypePrefix . "_reasons";
			$sql = "INSERT INTO image_feedback 
				(ii_img_page_id, ii_wikiphoto_img, ii_page_id, ii_img_url, $voteField, $reasonField) VALUES 
				({$t->getArticleId()}, $isWikiPhotoImg, $aid, '$url', 1, '$reason') 
				ON DUPLICATE KEY UPDATE
				$voteField = $voteField + 1, $reasonField = CONCAT($reasonField, '/$reason')";
			$dbw->query($sql, __METHOD__);
			$wgOut->setArticleBodyOnly(true);
		}
	}

	public static function getImageFeedbackLink() {
		global $wgUser;

		if (self::isValidPage()) {
			$rptLink = "<a class='rpt_img' href='#'><span class='rpt_img_ico'></span>Helpful?</a>";
		} else {
			$rptLink = "";
		}
		return $rptLink;
	}

	public static function isValidPage() {
		global $wgUser, $wgTitle, $wgRequest;

		if (is_null(self::$allowAnonFeedback)) {
			// Allow anon feedback on ~5% of articles
			self::$allowAnonFeedback = mt_rand(1, 100) <= 5;
		}

		$allowAnonFeedback = self::$allowAnonFeedback;

		return $wgUser &&
			(!$wgUser->isAnon() || $allowAnonFeedback) && 
			!MobileWikihow::isMobileDomain() &&
			$wgTitle &&
			$wgTitle->exists() &&
			$wgTitle->getNamespace() == NS_MAIN &&
			$wgRequest &&
			$wgRequest->getVal('create-new-article') == '' &&
			!self::isMainPage();
	}

	public static function isMainPage() {
		global $wgTitle;
		return $wgTitle && $wgTitle->getNamespace() == NS_MAIN && 
			$wgTitle->getText() == wfMessage('mainpage')->text();
	}
}
