<?
/*
* Mobile QG tool used on mobile wikiHow
*/
class MQG extends UnlistedSpecialPage {

	private $testType = null;

	private $pictureTest = array(7810970, 7751394, 7590823, 7820277, 7784697, 7810190, 7628461, 6676800, 7797642, 7772818, 7816548, 7664662, 7813861, 7790215, 7232728);
	private $yesNoTest = array(7234323, 7720285, 7799955, 7777813, 7517083, 7775291, 7815346, 7678496, 7733113, 7681301, 7750290, 7720622, 7766181, 7807566, 7230660, 7460459);
	private $ratingTest = array(7754418, 7701276, 7218513, 6697889, 7815740, 7773283, 7643530, 7472861, 7345259, 7767528, 7710468, 7807526, 6195798, 7724174, 7726215);
	private $recommendTest = array(7234323, 7720285, 7799955, 7777813, 7517083, 7775291, 7815346, 7678496, 7733113, 7681301, 7750290, 7720622, 7766181, 7807566, 7230660, 7460459);
	private $tipTest = array(7234323, 7720285, 7799955, 7777813, 7517083, 7775291, 7815346, 7678496, 7733113, 7681301, 7750290, 7720622, 7766181, 7807566, 7230660, 7460459);
	private $videoTest = array(7751094, 7854496, 7974271, 7942108, 7971756, 7850201, 7934085, 7901777, 7976640, 7782617, 7890799 );

	// The qg item to display
	private $qgItem = null;

	// The revision to display
	private $r = null;

	function __construct() {
		parent::__construct('MQG');
	}

	function execute($par) {
		global $wgOut, $wgRequest, $IP, $wgArticle, $wgUser, $isDevServer;

		wfProfileIn(__METHOD__);
		$wgOut->disable(); 

		require_once("$IP/extensions/wikihow/mobile/MobileHtmlBuilder.class.php");
		if ($wgRequest->getVal('fetchInnards')) {
			echo json_encode($this->getInnards());
			wfProfileOut(__METHOD__);
			return;
		} else if ($email = strtolower(trim($wgRequest->getVal('email')))) {
			if (preg_match("/^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,3})$/", $email)) {
				$this->saveEmail($email);
			}
			return;
		} else if ($wgRequest->getVal('log')) {
			$this->logEvent();
			return;
		} else if ($wgRequest->wasPosted()) {
			echo json_encode($this->getInnards());
			wfProfileOut(__METHOD__);
			return;
		} else {
			// Only for initial load
			echo $this->getShell();
		}

		wfProfileOut(__METHOD__);
	}

	private function getInnards() {
		wfProfileIn(__METHOD__);
		$qgItem = $this->getNext();
		$res = $this->getData($qgItem);
		
		$res['html'] = $this->getBodyHtml($qgItem);

		wfProfileOut(__METHOD__);
		return $res;
	}

	private function printInnards(&$innards) {
		wfProfileIn(__METHOD__);
		$wgOut->disable(); 
		header('Vary: Cookie' );
		$result = $this->getInnards();
		echo json_encode($result);
		wfProfileOut(__METHOD__);
		return;
	}

	private function getShell() {
		wfProfileIn(__METHOD__);
		$qgItem = $this->getNext();
		$res = $this->getData($qgItem);
		wfProfileOut(__METHOD__);
		return $this->getShellHtml($qgItem);
	}

	private function getTestRevIds($qgTestType) {
		// default test type
		$testType = $this->pictureTest;

		switch ($qgTestType) {
			case 'pic': 
				$testType = $this->pictureTest;
				break;
			case 'yesno':
				$testType = $this->yesNoTest;
				break;
			case 'video':
				$testType = $this->videoTest;
				break;
			case 'tip':
				$testType = $this->tipTest;
				break;
			case 'rating':
				$testType = $this->ratingTest;
				break;
			case 'recommend':
				$testType = $this->recommendTest;
				break;
		}

		return $testType;
	}

	private function getNextRevId() {
		global $wgRequest;


		// Special case for tip type tests
		if ($this->getQGTestType() == 'tip') {
			if ($wgRequest->getVal('qc_last')) {
				return null;
			} else {
				return $wgRequest->getVal('qc_rev_id');
			}
		}

		$revId = $wgRequest->getVal('qc_rev_id', 'start');
		$qgTestRevs = $this->getTestRevIds($this->getQGTestType());

		if ($revId == "null" || $revId == "" || $revId == "start") {
			return $qgTestRevs[0];
		}

		$pos = array_search($revId, $qgTestRevs);
		// Oops. Something must be wrong. We couldn't find the revId
		if ($pos === false) {
			return null;
		}
		// last one in the queue.  Return null to indicate there aren't any more revs to look at
		if ($pos == sizeof($qgTestRevs) - 1) {
			return null;
		}

		return $qgTestRevs[++$pos];
	}

	private function getQGTestType() {
		global $wgRequest;
		$qgType = $wgRequest->getVal('qc_type', 'pic');
		if ($qgType == "null" || $qgType == "") {
			$qgType = "pic";
		}

		return $qgType;
	}
	private function getNext() {
		global $wgRequest;

		wfProfileIn(__METHOD__);

		$qgItem = null;
		if ($revId = $this->getNextRevId()) {
			$qgItem = array('qg_rev_id' => $revId, 'qg_type' => $this->getQGTestType());
			$this->qgItem = $qgItem;
			$this->r = Revision::newFromId($revId);
			$this->t = $this->r->getTitle(); 
		}
		wfProfileOut(__METHOD__);
		if (is_null($qgItem)) {
			//throw new Exception("qgItem is null");
		}
		return $qgItem;
	}

	private function getBodyHtml(&$qgItem) {
		global $wgMemc;

		wfProfileIn(__METHOD__);
		wfLoadExtensionMessages('MQG');
		$html = null;
		if ($qgItem) {
			$key = wfMemcKey($this->getQGTestType() . "-" . $this->getNextRevId());
			$html = $wgMemc->get($key);
			if (!$html) {
				$this->setTemplatePath();
				$vars = $this->getBodyVars($qgItem);
				$html = EasyTemplate::html('mqgtest_body.tmpl.php', $vars);
				$wgMemc->set($key, $html);
			}
		} else {
			$this->setTemplatePath();
			$html = EasyTemplate::html('mqg_finished.tmpl.php');
		}
		wfProfileOut(__METHOD__);
		return $html;
	}
	private function getShellHtml(&$qgItem) {
		wfProfileIn(__METHOD__);
		$vars = $this->getShellVars($qgItem);
		wfLoadExtensionMessages('MQG');
		$this->setTemplatePath();
		wfProfileOut(__METHOD__);
		return EasyTemplate::html('mqg.tmpl.php', $vars);
	}

	private function getShellVars(&$qgItem) {
		wfProfileIn(__METHOD__);
		$vars['randomUrl'] = '/' . wfMsg('special-randomizer');
		$vars['mqg_title'] = 'Mobile QG';
		$vars['mqg_css'] = HtmlSnips::makeUrlTags('css', array('mqgtest.css'), 'extensions/wikihow/mqg', false);	
		$vars['mqg_css'] .= HtmlSnips::makeUrlTags('css', array('jquery.rating.css'), 'extensions/wikihow/mqg/rating', false);	
		$vars['mqg_js'] = HtmlSnips::makeUrlTags('js', array('mqgtest.js'), 'extensions/wikihow/mqg', false);	
		$vars['mqg_js'] .= HtmlSnips::makeUrlTags('js', array('jquery.rating.pack.js', 'jquery.MetaData.js'), 'extensions/wikihow/mqg/rating', false);	
		wfProfileOut(__METHOD__);
		return $vars;
	}

	private function getPromptHtml(&$qgItem) {
		global $wgRequest;
		$html = "";
		$testType = null;
		switch ($qgItem['qg_type']) {
			case "pic":
				$testType = new MQGPhotoTest($qgItem, $this->r);
				break;
			case "yesno":
				$testType = new MQGYesNoTest($qgItem, $this->r);
				break;
			case "rating":
				$testType = new MQGRatingTest($qgItem, $this->r);
				break;
			case "tip":
				$testType = new MQGTipTest($qgItem, $this->r);
				break;
			case "video":
				$testType = new MQGVideoTest($qgItem, $this->r);
				break;
			case "recommend":
				$testType = new MQGRecommendTest($qgItem, $this->r);
				break;
		}

		if (!is_null($testType)) {
			$html = $testType->getPromptHtml();
		}

		return $html;
	}

	private function getBodyVars(&$qgItem) {
		wfProfileIn(__METHOD__);
		$vars['mqg_article'] = $this->getArticleHtml($qgItem);
		$vars['mqg_prompt_html'] = $this->getPromptHtml($qgItem);
		$vars['mqg_qgItem'] = $this->qgItem;
		wfProfileOut(__METHOD__);
		return $vars;
	}

	private function getData(&$qgItem) {
		wfProfileIn(__METHOD__);
		$data = $this->qgItem;
		wfProfileOut(__METHOD__);
		return $data;
	}

	private function getPicture(&$qgItem) {
		wfProfileIn(__METHOD__);
		$pic = null;
		$r = $this->r;

		if ($r) {
			$intro = Article::getSection($r->getText(), 0);	
			$pic = QCRuleIntroImage::getPicture($intro);
			if ($pic) {
				$pic = $pic->getThumbnail(290, 194);
				$pic->width = floor($pic->getWidth() * .75);
				$pic->height = floor($pic->getHeight() * .75);
			}
		}
		wfProfileOut(__METHOD__);
		return $pic;
	}

	private function getArticleHtml(&$qgItem) {
		wfProfileIn(__METHOD__);
		$t = $this->t;
		$r = $this->r;
		//echo "<a target=_blank href='http://jordan.wikidiy.com/" . $t->getPartialURL() . "?oldid=" . $r->getId() ."'>link</a>";
		$html = '';
		if ($t && $t->exists()) {
			$m = new MobileQGArticleBuilder();
			$html = $m->createByRevision($t, $r);
		}
		wfProfileOut(__METHOD__);
		return $html;
	}

	private function setTemplatePath() {
		EasyTemplate::set_path(dirname(__FILE__).'/');
	}

	private function saveEmail($email) {
		wfProfileIn(__METHOD__);
		$dbw = wfGetDB(DB_MASTER);
		if ($email) {
			$email = $dbw->strencode($email);
			$dbw->insert('mqg_emails', array('mqg_email' => $email, 'mqg_timestamp' => wfTimestamp(TS_MW)), 'MQG::saveEmail', array('IGNORE'));
		}
		wfProfileOut(__METHOD__);
	}

	private function logEvent() {
		wfProfileIn(__METHOD__);
		global $wgRequest;

		$event = explode(",", trim(urldecode($wgRequest->getVal('log'))));
		if (sizeof($event)) {
			$key = 'mqg';
			$type = $event[0];
			$val = str_replace("-", "\t", $event[1]);
			EventLogger::logEvent($key, $type, $val);
		}
		wfProfileOut(__METHOD__);
	}
}

abstract class MQGTestType {
	var $qgItem = null;
	var $r = null;

	public function __construct(&$qgItem, &$r) {
		$this->r = $r;
		$this->qgItem = $qgItem;
	}
	
	public abstract function getPromptHtml();

	protected function setTemplatePath() {
		EasyTemplate::set_path(dirname(__FILE__).'/');
	}
}


class MQGPhotoTest extends MQGTestType {
	var $pic = null;

	public function getPromptHtml() {
		$this->setTemplatePath();
		$vars['mqg_pic'] = $this->getPicture();
		$vars['mqg_device'] = MobileWikihow::getDevice();	
		return EasyTemplate::html('mqg_photo_prompt.tmpl.php', $vars);
	}

	public function getPicture() {
		wfProfileIn(__METHOD__);
		$pic = null;
		$r = $this->r;

		if ($r) {
			$intro = Article::getSection($r->getText(), 0);	
			$pic = QCRuleIntroImage::getPicture($intro);
			if ($pic) {
				$pic = $pic->getThumbnail(290, 194);
				$pic->width = floor($pic->getWidth() * .75);
				$pic->height = floor($pic->getHeight() * .75);
			}
		}
		wfProfileOut(__METHOD__);
		return $pic;
	}

}

class MQGRatingTest extends MQGTestType {

	public function getPromptHtml() {
		$this->setTemplatePath();
		$vars['prompt'] = "Rate this article 1 to 5 stars";
		return EasyTemplate::html('mqg_rating_prompt.tmpl.php', $vars);
	}
}

class MQGRecommendTest extends MQGTestType {

	public function getPromptHtml() {
		$this->setTemplatePath();
		$vars['prompt'] = "Would you recommend this article to a friend?";
		return EasyTemplate::html('mqg_yesno_prompt.tmpl.php', $vars);
	}
}

class MQGYesNoTest extends MQGTestType {

	public function getPromptHtml() {
		$this->setTemplatePath();
		$vars['prompt'] = "Is this article helpful?";
		return EasyTemplate::html('mqg_yesno_prompt.tmpl.php', $vars);
	}
}

class MQGTipTest extends MQGTestType {
	var $pic = null;

	public function getPromptHtml() {
		$this->setTemplatePath();
		$vars['prompt'] = "Is this tip helpful?";
		return EasyTemplate::html('mqg_yesno_prompt.tmpl.php', $vars);
	}
}

class MQGVideoTest extends MQGTestType {
	var $pic = null;

	public function getPromptHtml() {
		$this->setTemplatePath();
		$vars['prompt'] = "Does this video go in this article?";
		return EasyTemplate::html('mqg_yesno_prompt.tmpl.php', $vars);
	}

	public function getVideo() {
		wfProfileIn(__METHOD__);
		$pic = null;
		$r = $this->r;

		if ($r) {
			$intro = Article::getSection($r->getText(), 0);	
			$pic = QCRuleIntroImage::getPicture($intro);
			if ($pic) {
				$pic = $pic->getThumbnail(290, 194);
				$pic->width = floor($pic->getWidth() * .75);
				$pic->height = floor($pic->getHeight() * .75);
			}
		}
		wfProfileOut(__METHOD__);
		return $pic;
	}

}
