<?

class StarterTool extends UnlistedSpecialPage {

	const COOKIE_NAME = "wiki_starterTool";
	
	function __construct() {
		parent::__construct( 'StarterTool' );
	}

	/**
	 * Set html template path for StarterTool actions
	 */
	public static function setTemplatePath() {
		EasyTemplate::set_path( dirname(__FILE__).'/' );
	}

	/**
	 * A Mediawiki callback set in contructor of this class to stop the display
	 * of breadcrumbs at the top of the page.
	 */
	public static function removeBreadCrumbsCallback(&$showBreadCrum) {
		$showBreadCrum = false;
		return true;
	}

	/**
	 * Define a Mediawiki callback to make it so that the body doesn't
	 * get wrapped with <div class="article_inner"></div> ...
	 */
	/*public static function wrapBodyWithArticleInner() {
		return false;
	}*/

	public static function getSentence($sentenceNum = null) {
		
		$sentences = self::getSentences();
		
		if($sentenceNum != null) {
			return $sentences[$sentenceNum];
		}
		else {

			//get random sentence from our array
			$numb = rand(0,(count($sentences)-1));
			$sentence = $sentences[$numb];

			$sent = '<span id="starter_sentence">'.$sentence.'</span>';
			$sent .= '<input type="hidden" value="'.$numb.'" id="starter_title" />';

			return $sent;
		
		}
	}
	
	function getSentences() {
		//global $wgMemc;
		
		$key = "startertool_sentences";
		//$sentences = $wgMemc->get($key);
		
		if(!$sentences) {
			$msg = ConfigStorage::dbGetConfig('startertool_sentences'); //startup companies
			$sentences = split("\n", $msg);
			
			//$wgMemc->set($key, $sentences);
		}
		
		return $sentences;
	}
	
	function getSentenceCode() {
		$sentenceInfo = getSentence();
		
		$number = $sentenceInfo['number'];
	}

	function getFirstArticleRevision($pageId) {
		$fname = 'StarterTool::getFirstArticleRevision';
		wfProfileIn( $fname );

		$dbr = wfGetDB(DB_SLAVE);
		$minRev = $dbr->selectField('revision', array('min(rev_id)'), array("rev_page" => $pageId), __METHOD__);

		wfProfileOut( $fname );

		return $minRev;
	}

	/**
	 * EXECUTE
	 **/
	function execute($par) {
		global $wgRequest, $wgOut, $wgUser, $wgLang, $wgParser, $wgHooks, $wgTitle, $wgStarterPages;

		$wgHooks['ShowBreadCrumbs'][] = array($this, 'removeBreadCrumbsCallback');
		//$wgHooks['WrapBodyWithArticleInner'][] = array($this, 'wrapBodyWithArticleInner');

		wfLoadExtensionMessages('StarterTool');

		self::setTemplatePath();

		if ($wgUser->isBlocked()) {
			$wgOut->blockedPage();
			return;
		}
		
		$referral = $wgRequest->getVal('ref');

		//get contents
		if ($wgRequest->getVal('edit')) {
			$wgOut->setArticleBodyOnly(true);
			$sentenceNum = $wgRequest->getVal('starter_title');
			$sentence = $this->getSentence($sentenceNum);
			
			$sentence = preg_replace('@<[/]?(span|[ovwxp]:\w+)[^>]*?>@', '', $sentence);
			
			$vars = array('sentence' => $sentence);
			$html = EasyTemplate::html('edit',$vars);
			$wgOut->addHTML($html);
			
			return;
		} elseif ($wgRequest->getVal('finish') ) {
			$finishNum = "Finish-" . $wgRequest->getVal('finish');
			self::logInfo($finishNum);
		} elseif ($wgRequest->getVal('editNum')) {
			$editNum = "Edit-" . $wgRequest->getVal('editNum');
			self::logInfo($editNum);
			if($wgRequest->getVal('getsome')) {
				$wgOut->setArticleBodyOnly(true);
				$sentence = self::getSentence();

				$wgOut->addHTML($sentence);
				return;
			}
		
		}elseif ($wgRequest->getVal('getsome')) {
			$wgOut->setArticleBodyOnly(true);
			$sentence = self::getSentence();
			
			$wgOut->addHTML($sentence);
			return;

		} elseif ($wgRequest->getVal( 'action' ) == 'submit') {
			$wgOut->setArticleBodyOnly(true);

			$t = Title::newFromText($wgRequest->getVal('starter-title'));
			$a = new Article($t);
			
			//internal log
			if($referral != "")
				self::logInfo("submit");

			//log it
			$params = array();
			$log = new LogPage( 'Starter_Tool', false ); // false - dont show in recentchanges
			$log->addEntry('', $t, 'Fixed a sentence with the Starter Tool.', $params);

			$text = $wgRequest->getVal('wpTextbox1');
			$sum = $wgRequest->getVal('wpSummary');

			//save the edit
		 	if ($a->doEdit($text,$sum,EDIT_SUPPRESS_RC)) {

				//revert the edit for the next user
				$minRev = self::getFirstArticleRevision($t->getArticleId());

				//don't log rollback for the user
				$oldglobal = $wgUser;
				$wgUser = User::newFromName("MasterSockPuppet421");

				$dbr = wfGetDB(DB_SLAVE);
				$r = Revision::loadFromId($dbr,$minRev);
				$a->doEdit($r->getText(),'Auto-rollback from Starter Tool.',EDIT_SUPPRESS_RC);

				// reset the wguser var
				$wgUser = $oldglobal;

				wfRunHooks("StarterToolSaveComplete", array($a, $text, $sum, $wgUser, $efType));
			}

 			return;
		} else {
			//default; get a sentence
			if($referral != "") {
				//log that they came in from the specific ad
				self::logInfo("Ad-" . $referral);
				
				setcookie(StarterTool::COOKIE_NAME, 1, 0);
			}

			$wgOut->addScript(HtmlSnips::makeUrlTags('css', array('starter.css'), 'extensions/wikihow/starter', false));
			$wgOut->addScript(HtmlSnips::makeUrlTags('js', array('starter.js'), 'extensions/wikihow/starter', false));
			$wgOut->addScript(HtmlSnips::makeUrlTags('js', array('clientscript.js'), 'skins/common/', false));
			$wgOut->addScript(HtmlSnips::makeUrlTags('js', array('jquery.cookie.js'), 'extensions/wikihow/common', false));
			
			$sk = $wgUser->getSkin();
			$wgOut->setArticleBodyOnly(false);

			$vars = array('pagetitle' => wfMsg('app-name'),'question' => wfMsg('fix-this'),'yep' => wfMsg('yep'), 'nope' => wfMsg('nope'));
			$html = EasyTemplate::html('starter',$vars);
			$wgOut->addHTML($html);
		}

		$wgOut->setHTMLTitle( wfMsg('pagetitle', wfMsg('app-name')) );

	}
	
	function logInfo($action) {
		global $wgUser;
		
		$dbw = wfGetDB(DB_MASTER);
		
		$dbw->insert('startertool', array('st_user' => $wgUser->getID(), 'st_username' => $wgUser->getName(), 'st_date' => wfTimestamp(TS_MW), 'st_action' => $action) );
		
	}
}

class StarterToolAdmin extends UnlistedSpecialPage {

	function __construct() {
		parent::__construct( 'StarterToolAdmin' );
	}
	
	function execute($par) {
		global $wgOut, $wgUser;
		
		if ($wgUser->getID() == 0 || !in_array('staff', $wgUser->getGroups())) {
			$wgOut->setRobotpolicy( 'noindex,nofollow' );
			$wgOut->showErrorPage( 'nosuchspecialpage', 'nospecialpagetext' );
			return;
		}
		
		$dbr = wfGetDB(DB_SLAVE);
		
		$html = self::getUserData($dbr);
		
		$wgOut->addHTML($html);
		
	}
	
	static function getUserData(&$dbr, $startDate = "", $endDate = "") {
		$conds = array();
		if($startDate != "")
			$conds[] = "st_date >= {$startDate}";
		if($endDate != "")
			$conds[] = "st_date < {$endDate}";
		$res = $dbr->select("startertool", array('st_action', 'count(*) as total'), $conds, __METHOD__, array("GROUP BY" => "st_action"));
		
		$html = "";
		$html .= "<table>";
		foreach($res as $row) {
			if(stripos($row->st_action, "ad-") !== false) {
				$adNum = substr($row->st_action, 3);
				$html .= "<tr><td>Number of people who clicked on ad #{$adNum}</td><td style='padding-left:10px;'>{$row->total}</td></tr>";
			}
			if(stripos($row->st_action, "edit-") !== false) {
				$editNum = substr($row->st_action, 5);
				$html .= "<tr><td>Number of people who made {$editNum} edit" . ($editNum=="1"?"":"s") . "</td><td style='padding-left:10px;'>{$row->total}</td></tr>";
			}
			if(stripos($row->st_action,"finish-" ) !== false) {
				$finish = substr($row->st_action, 7);
				$html .= "<tr><td>Number of people who clicked \"{$finish}\" on final screen</td><td style='padding-left:10px;'>{$row->total}</td></tr>";
			}
			else if(stripos($row->st_action, "signup_top") !== false) {
				$html .= "<tr><td>Number of people who finished the signup process (clicked top signup)</td><td style='padding-left:10px;'>{$row->total}</td></tr>";
			}
			elseif(stripos($row->st_action, "signup") !== false) {
				$html .= "<tr><td>Number of people who finished the signup process</td><td style='padding-left:10px;'>{$row->total}</td></tr>";
			}
		}
		$html .= "</table>";
		
		return $html;
	}
		
}
