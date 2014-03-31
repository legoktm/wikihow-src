<?
/*
* Mobile QG tool used on mobile wikiHow
*/
class MQG extends UnlistedSpecialPage {
	// The qg item to display
	private $qgItem = null;

	// The revision to display
	private $r = null;

	// The picture to be displayed with the prompt
	private $picture = null;

	function __construct() {
		parent::__construct('MQG');
	}

	function execute($par) {
		global $wgOut, $wgRequest, $IP, $wgArticle, $wgUser, $isDevServer;

		wfProfileIn(__METHOD__);
		$wgOut->disable(); 
		header('Vary: Cookie');

		$oldWgUser = $wgUser;
		// Run this as the mqg user if anonymous user
		if (!$wgUser->mId || $wgUser->isAnon()) {
			if ($isDevServer) {
				$wgUser = User::newFromName('Mqg');
			} else {
				// MQG User id. Use this vs. name so we can load from memcache
				$wgUser = User::newFromId(1738044);
			}
			$wgUser->load();
		}


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
		} else if ($wgRequest->wasPosted()) {
			if ($wgRequest->getVal('qc_skip', 1) == 1) {
				QCRule::skip($wgRequest->getVal('qc_id'));				
			} else {
				QCRule::vote($wgRequest->getVal('qc_id'), $wgRequest->getVal('qc_vote'));				
			}
			echo json_encode($this->getInnards());
			wfProfileOut(__METHOD__);
			return;
		} else {
			// Only for initial load
			echo $this->getShell();
		}

		// Restore $wgUser
		$wgUser = $oldWgUser;
		wfProfileOut(__METHOD__);
	}

	private function getInnards() {
		wfProfileIn(__METHOD__);
		$retryNum = 7;
		$i = 0;
		do {
			$qgItem = $this->getNext();
			$picture = $this->getPicture($qgItem);
			$i++;
		} while ($i < $retryNum && $qgItem && !$picture);  // We don't want to show a QG if there isn't a picture.  This can happen for multiple reasons.
		$this->picture = $picture;
		$res = $this->getData($qgItem);
		
		// If we haven't found a valid qg item yet, just pass in null to body html to show the finished page
		if ($i == $retryNum) {
			/*
			// We predict that the anon (or mqg users) will go through the queue much more quickly
			// If it's this user, set this flag so we can prevent the cta from showing up
			if(strtolower($wgUser->getName()) == 'mqg') {
				$this->setMoreQG('off');
			}
			*/
			$qgItem = null;
		}
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

	private function getNext() {
		wfProfileIn(__METHOD__);
		$qgItem = QCRule::getNextToPatrol('changedintroimage', null);
		if ($qgItem) {
			$this->qgItem = $qgItem;
			$revId = $qgItem->mResult->qc_rev_id;
			$this->r = Revision::newFromId($revId);
		}
		wfProfileOut(__METHOD__);
		return $qgItem;
	}

	private function getBodyHtml(&$qgItem) {
		wfProfileIn(__METHOD__);
		$vars = $this->getBodyVars($qgItem);
		wfLoadExtensionMessages('MQG');
		$html = null;
		if ($qgItem) {
			$this->setTemplatePath();
			$html = EasyTemplate::html('mqg_body.tmpl.php', $vars);
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
		$vars['mqg_css'] = HtmlSnips::makeUrlTags('css', array('mqg.css'), 'extensions/wikihow/mqg', false);	
		$vars['mqg_js'] = HtmlSnips::makeUrlTags('js', array('mqg.js'), 'extensions/wikihow/mqg', false);	
		wfProfileOut(__METHOD__);
		return $vars;
	}

	private function getBodyVars(&$qgItem) {
		wfProfileIn(__METHOD__);
		$vars['mqg_article'] = $this->getArticleHtml($qgItem);
		$vars['mqg_pic'] = $this->picture;
		$vars['mqg_device'] = MobileWikihow::getDevice();	
		wfProfileOut(__METHOD__);
		return $vars;
	}

	private function getData(&$qgItem) {
		wfProfileIn(__METHOD__);
		$data['qc_id'] = $qgItem->mResult->qc_id;
		$data['rev_id'] = $qgItem->mResult->qc_rev_id;
		//$data['sql'] = $qgItem->sql;
		wfProfileOut(__METHOD__);
		return $data;
	}

	private function getPicture(&$qgItem) {
		wfProfileIn(__METHOD__);
		$pic = null;
		$r = $this->r;

		if ($r) {
			$intro = Article::getSection($r->getText(), 0);	
			$pic = $qgItem->getPicture($intro);
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
		$t = $qgItem->mTitle;
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
}
