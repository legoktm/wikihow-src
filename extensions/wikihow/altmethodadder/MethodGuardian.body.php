<?php

class MethodGuardian extends SpecialPage {
	
	const METHOD_EXPIRED = 1800; // 30 minutes
	const METHOD_ACTION_DELETE = 1;
	const METHOD_ACTION_KEEP = 2;
	const TABLE_NAME = "altmethodadder";
	
	var $skipTool;
	
	function __construct() {
		global $wgHooks;
		parent::__construct("MethodGuardian");
		$wgHooks['getToolStatus'][] = array('Misc::defineAsTool');
	}
	
	function execute($par) {
		global $wgOut, $wgRequest, $wgUser, $wgParser;
		
		wfLoadExtensionMessages("MethodGuardian");
		
		if ($wgUser->isBlocked()) {
			$wgOut->blockedPage();
			return;
		}
		
		if ($wgUser->isAnon()) {
			$wgOut->setRobotpolicy( 'noindex,nofollow' );
			$wgOut->showErrorPage( 'nosuchspecialpage', 'nospecialpagetext' );
			return;
		}
		
		$this->skipTool = new ToolSkip("methodguardian", MethodGuardian::TABLE_NAME, "ama_checkout", "ama_checkout_user", "ama_id");
		
		if ( $wgRequest->getVal('getNext') ) {
			$wgOut->disable();
			
			$result = $this->getNextMethod();
			echo json_encode($result);
			return;
		} else if ( $wgRequest->getVal('skipMethod') ) {
			$wgOut->disable();
			$methodId = $wgRequest->getVal('methodId');
			$this->skipTool->skipItem($methodId);
			$this->skipTool->unUseItem($methodId);
			$result = $this->getNextMethod();
			echo json_encode($result);
			return;
		}
		elseif ($wgRequest->getVal('deleteMethod')) {
			$wgOut->disable();
			
			$methodId = $wgRequest->getVal('methodId');
			$articleId = $wgRequest->getVal('articleId');
			$this->deleteMethod($methodId, $articleId);
			
			$result = $this->getNextMethod();
			echo json_encode($result);
			return;
		}
		elseif ($wgRequest->getVal('keepMethod')) {
			$wgOut->disable();
			
			$methodId = $wgRequest->getVal('methodId');
			$articleId = $wgRequest->getVal('articleId');
			$this->keepMethod($methodId, $articleId);
			
			$result = $this->getNextMethod();
			echo json_encode($result);
			return;
		}
		
		$wgOut->setPageTitle(wfMsg('methodguardian'));
		$wgOut->addCSScode('meguc');
		$wgOut->addJScode('meguj');
		
		$tmpl = new EasyTemplate( dirname(__FILE__) );
		
		$wgOut->addHTML($tmpl->execute('MethodGuardian.tmpl.php'));
		$this->displayLeaderboards();
	}
	
	private function getNextMethod() {
		global $wgUser, $wgOut, $wgParser;
		
		$dbw = wfGetDB(DB_MASTER);
		$expired = wfTimestamp(TS_MW, time() - MethodGuardian::METHOD_EXPIRED);
		$i = 0;
		$content['error'] = true;
		$goodRevision = false;
		do {
			$skippedIds = $this->skipTool->getSkipped();
			$where = array();
			$where[] = "ama_checkout < '$expired'";
			$where[] = "ama_patrolled = 0";
			if($skippedIds) {
				$where[] = "ama_id NOT IN ('" . implode("','", $skippedIds) . "')";
			}
			$row = $dbw->selectRow(MethodGuardian::TABLE_NAME, array('*'), $where, __METHOD__, array("LIMIT" => 1));
			$content['sql' . $i] = $dbw->lastQuery();
			$content['row'] = $row;

			if($row !== false) {
				$title = Title::newFromID($row->ama_page);
				$isRedirect = false;
				if ($title) {
					$dbr = wfGetDB(DB_SLAVE);
					$isRedirect = intval($dbr->selectField('page', 'page_is_redirect', 
						array('page_id' => $row->ama_page), __METHOD__, array("LIMIT" => 1)));
				}
				if($title && !$isRedirect) {
						$this->skipTool->useItem($row->ama_id);
						$revision = Revision::newFromTitle($title);
						$popts = $wgOut->parserOptions();
						$popts->setTidy(true);
						$parserOutput = $wgOut->parse($revision->getText(), $title, $popts);
						$magic = WikihowArticleHTML::grabTheMagic($revision->getText());
						$content['article'] = WikihowArticleHTML::processArticleHTML($parserOutput, array('no-ads' => true, 'ns' => NS_MAIN, 'magic-word' => $magic));
						$content['method'] = $row->ama_method;
						$content['methodId'] = $row->ama_id;
						$content['articleId'] = $row->ama_page;
						
						$newMethod = $wgParser->parse("=== " . $row->ama_method . " ===\n" . $row->ama_steps, $title, new ParserOptions())->getText();
						$newMethod = preg_replace('@<span class="editsection">.*?<\/span>@','',$newMethod); //remove that edit link
						$content['steps'] = WikihowArticleHTML::processArticleHTML("<div class='steps'><div class='section_text'>" . $newMethod . "</div></div>", array('no-ads' => true, 'ns' => NS_MAIN));
						
						$content['articleTitle'] = "<a href='{$title->getLocalURL()}'>{$title->getText()}</a>";
						$content['error'] = false;
				} else {
					//article must no longer exist or be a redirect, so delete the tips associated with that article
					$dbw = wfGetDB(DB_MASTER);
					$dbw->delete(MethodGuardian::TABLE_NAME, array('ama_page' => $row->ama_page));
				}
			}
			$i++;
		// Check up to 5 titles.
		// If no good title then return an error message
		} while ($i <= 5 && !$title && $row !== false);

		$content['i'] = $i;
		$content['methodCount'] = self::getCount();
		return $content;
		
	}
	
	private function getCount() {
		$dbr = wfGetDB(DB_SLAVE);
		return $dbr->selectField(MethodGuardian::TABLE_NAME, 'count(*) as count', array('ama_patrolled' => 0));
	}
	
	private function deleteMethod($methodId = null, $articleId) {
        global $wgUser;

		if($methodId != null) {
            $dbr = wfGetDB(DB_SLAVE);
            $altMethod = $dbr->selectField(MethodGuardian::TABLE_NAME, 'ama_method', array('ama_id' => $methodId));
			
			$title = Title::newFromID($articleId);
			if($title) {
				$logPage = new LogPage('methgua', false);
				$logS = $logPage->addEntry("Rejected", $title, wfMsg('guardian-rejected-logentry', $title->getFullText(), $altMethod));

                wfRunHooks("MethodGuarded", array($wgUser, $title, '0'));
			}

            $dbw = wfGetDB(DB_MASTER);
            $dbw->delete(MethodGuardian::TABLE_NAME, array('ama_id' => $methodId));
		}
	}
	
	private function keepMethod($methodId, $articleId) {
		global $wgUser, $wgParser;
		
		$title = Title::newFromID($articleId);
		
		if($title) {
			$revision = Revision::newFromTitle($title);
			$article = new Article($title);
			if($revision && $article) {
                $dbr = wfGetDB(DB_SLAVE);
                $altMethod = $dbr->selectField(MethodGuardian::TABLE_NAME, 'ama_method', array('ama_id' => $methodId));
				
				$logPage = new LogPage('methgua', false);

				$logS = $logPage->addEntry("Saved", $title, wfMsg('guardian-approved-logentry', $title->getFullText(), $altMethod));

				$dbw = wfGetDB(DB_MASTER);
				$dbw->update(MethodGuardian::TABLE_NAME, array('ama_patrolled' => '1') , array('ama_id' => $methodId));

                $this->skipTool->unUseItem($methodId);

                wfRunHooks("MethodGuarded", array($wgUser, $title, '0'));

				return true;
			}
		}
	}

	function displayLeaderboards() {
		$stats = new MethodGuardianStandingsIndividual();
		$stats->addStatsWidget();
		$standings = new MethodGuardianStandingsGroup();
		$standings->addStandingsWidget();
	}

	public static function checkContent(&$title, &$methodName, &$methodSteps, $debug = false) {
		global $IP;

		$stepsArray = explode("#", $methodSteps);
		$wordsArray = array();
		foreach ($stepsArray as $step) {
			preg_match_all('/\b(\w|\')+\b/u', $step, $matches); //u modified allows for international characters
			foreach ($matches[0] as $match) {
				$wordsArray[] = $matches[0];
			}
		}

		//check length of each step
		$hasLongEnough = false;
		$minLength = 8;
		foreach($wordsArray as $stepsArray) {
			if(count($stepsArray) >= $minLength) {
				$hasLongEnough = true;
			}
		}

		if( !$hasLongEnough ) {
			if($debug)
				echo "****** Steps are not long enough\n";
			return false;
		}

		//contains no letters
		if(preg_match('@[a-z]@i', $methodSteps) == 0) {
			if($debug)
				echo "****** Doesn't have any letters in the steps\n";
			return false;
		}

		//check and see if everything is uppercase
		if(strtoupper($methodSteps) == $methodSteps) {
			if($debug)
				echo "****** Steps are all uppercase\n";
			return false;
		}

		//check for gibberish and bad words
		$filename = $IP . "/maintenance/bad_words.txt";
		$fi = fopen($filename, "r");
		$badWordsArray = array();
		while ( !feof( $fi ) ) {
			$fcontent = fgets($fi);
			$fcontent = strtolower(trim($fcontent));
			if($fcontent != "")
				$badWordsArray[] = $fcontent;
		}

		$totalWords = 0;
		$gibberishWords = 0;
		//can't use pspell on www, so not doing this test for now
		//$pspell = wikiHowDictionary::getLibrary();

		foreach($wordsArray as $stepArray) {
			foreach($stepArray as $word) {
				if (in_array(strtolower($word), $badWordsArray)) {
					if($debug)
						echo "****** Contains the bad word: $word\n";
					return false;
				}
				/*if (!pspell_check($pspell,$word)) {
					$gibberishWords++;
				}
				$totalWords++;*/
			}
		}
		/*if($totalWords == 0 || $gibberishWords/$totalWords > .5) {
			if($debug)
				echo "****** Too many gibberish words\n";
			return false;
		}*/

		//check for default values
		if(str_replace(array("\r"), "", $methodSteps) == trim(AltMethodAdder::DEFAULT_STEPS)) {
			if($debug)
				echo "****** Same as default\n";
			return false;
		}


		return true;
	}
}
