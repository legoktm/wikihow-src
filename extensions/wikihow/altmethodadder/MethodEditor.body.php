<?php

class MethodEditor extends SpecialPage {
	
	const METHOD_EXPIRED = 7200; // 2 hours
	const METHOD_ACTION_DELETE = 1;
	const METHOD_ACTION_KEEP = 2;
	const TABLE_NAME = "altmethodadder";
	const LOGGING_TABLE_NAME = "methodeditorlog";
	const EDIT_COMMENT = "added method from [[Special:MethodEditor|Method Editor]]";
	
	var $skipTool;
	
	function __construct() {
		global $wgHooks;
		parent::__construct("MethodEditor");
		$wgHooks['getToolStatus'][] = array('Misc::defineAsTool');
	}
	
	public static function userAllowed($user) {
		$userGroups = $user->getGroups();

		if ($user->isSysop() || in_array('staff', $userGroups) ||
				in_array('staff_widget', $userGroups) ||
				in_array('newarticlepatrol', $user->getRights()) ) {
			return true;
		}

		return false;
	}

	function execute($par) {
		global $wgOut, $wgRequest, $wgParser;
		
		wfLoadExtensionMessages("MethodEditor");
		
		$user = $this->getContext()->getUser();
		if (!$user || $user->isBlocked()) {
			$wgOut->blockedPage();
			return;
		}

		if (!$this->userAllowed($user)) {
			$wgOut->setRobotpolicy( 'noindex,nofollow' );
			$wgOut->showErrorPage( 'nosuchspecialpage', 'nospecialpagetext' );
			return;
		}

		$this->skipTool = new ToolSkip("methodeditor", MethodEditor::TABLE_NAME, "ama_checkout", "ama_checkout_user", "ama_page");
		
		if ( $wgRequest->getVal('getNext') ) {
			$wgOut->disable();
			
			$result = $this->getNextMethod();
			echo json_encode($result);
			return;
		} else if ( $wgRequest->getVal('skipMethod') ) {
			$wgOut->disable();
			$articleId = $wgRequest->getVal('articleId');
			$this->skipTool->skipItem($articleId);
			$this->skipTool->unUseItem($articleId);
			$result = $this->getNextMethod();
			echo json_encode($result);
			return;
		}
		elseif ($wgRequest->getVal('deleteMethod')) {
			$wgOut->disable();
			
			$methodId = $wgRequest->getVal('methodId');
			$articleId = $wgRequest->getVal('articleId');
			$method = $wgRequest->getVal('method');
			$this->deleteMethod($methodId, $articleId, $method);
			
			$result = $this->getNextMethod();
			echo json_encode($result);
			return;
		}
		elseif ($wgRequest->getVal('keepMethod')) {
			$wgOut->disable();
			
			$methodId = $wgRequest->getVal('methodId');
			$articleId = $wgRequest->getVal('articleId');
			$altMethod = $wgRequest->getVal('method');
			$altSteps = $wgRequest->getVal('steps');
			$this->keepMethod($methodId, $articleId, $altMethod, $altSteps);
			
			$result = $this->getNextMethod();
			echo json_encode($result);
			return;
		}
		elseif( $wgRequest->getVal('quickEdit') ) {
			$wgOut->disable();

			$methodId = $wgRequest->getVal('methodId');
			$articleId = $wgRequest->getVal('articleId');
			$this->quickEditRecord($methodId, $articleId);

			$result = $this->getNextMethod();
			echo json_encode($result);
			return;
		}
		elseif( $wgRequest->getVal('clearSkip') ) {
			$wgOut->disable();
			$this->skipTool->clearSkipCache();
			echo "Skip cache has been cleared";
			return;
		}
		
		$wgOut->setPageTitle(wfMessage('methodeditor')->text());
		
		$wgOut->addJScode('csjs');
		$wgOut->addCSScode('methc');
		$wgOut->addJScode('methj');
        $wgOut->addJScode('jcookj');
		$wgOut->addHTML(PopBox::getPopBoxJSAdvanced());
		
		$tmpl = new EasyTemplate( dirname(__FILE__) );

		$wgOut->addHTML($tmpl->execute('MethodEditor.tmpl.php'));
		$this->displayLeaderboards();

		$wgOut->addHTML(QuickNoteEdit::displayQuickEdit());
	}
	
	private function getNextMethod() {
		global $wgUser, $wgOut;
		
		$dbw = wfGetDB(DB_MASTER);
		$expired = wfTimestamp(TS_MW, time() - MethodEditor::METHOD_EXPIRED);
		$i = 0;
		$content['error'] = true;
		$goodRevision = false;
		do {
			$skippedIds = $this->skipTool->getSkipped();
			$content['debug']["skippedIds"] = $skippedIds;
			$where = array();
			$where[] = "ama_checkout < '$expired'";
            $where[] = "ama_patrolled = 1";
			$where[] = "NOT EXISTS (SELECT rc_id from recentchanges where rc_cur_id = ama_page and rc_patrolled = 0 LIMIT 1)";
			if($skippedIds) {
				$where[] = "ama_page NOT IN ('" . implode("','", $skippedIds) . "')";
			}
			$row = $dbw->selectRow(MethodEditor::TABLE_NAME, array('*'), $where, __METHOD__, array("LIMIT" => 1));
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
						$this->skipTool->useItem($row->ama_page);
						$revision = Revision::newFromTitle($title);
						$popts = $wgOut->parserOptions();
						$popts->setTidy(true);
						$parserOutput = $wgOut->parse($revision->getText(), $title, $popts);
						$magic = WikihowArticleHTML::grabTheMagic($revision->getText());
						$content['article'] = WikihowArticleHTML::processArticleHTML($parserOutput, array('no-ads' => true, 'ns' => NS_MAIN, 'magic-word' => $magic));
						$content['method'] = $row->ama_method;
						$content['methodId'] = $row->ama_id;
						$content['articleId'] = $row->ama_page;
						$content['steps'] = $row->ama_steps;
						$content['articleTitle'] = "<a href='{$title->getLocalURL()}'>{$title->getText()}</a>";

						$editURL = Title::makeTitle(NS_SPECIAL, "QuickEdit")->getFullURL() . '?type=editform&target=' . urlencode($title->getFullText());
						$class = "class='button secondary buttonleft'";
						$link =  "<a id='qe_button' title='" . wfMessage("rcpatrol_quick_edit_title")->text() . "' accesskey='e' href='' $class onclick=\"return loadQuickEdit('".$editURL."') ;\">" . htmlspecialchars( 'Quick edit' ) . "</a> ";

						$content['quickEditUrl'] = $link;
						$content['error'] = false;
				} else {
					//article must no longer exist or be a redirect, so delete the tips associated with that article
					$dbw = wfGetDB(DB_MASTER);
					$dbw->delete(MethodEditor::TABLE_NAME, array('ama_page' => $row->ama_page));
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
		return $dbr->selectField(MethodEditor::TABLE_NAME, 'count(*) as count', array('ama_patrolled' => 1));
	}
	
	private function deleteMethod($methodId = null, $articleId, $method) {
        global $wgUser;
		if($methodId != null) {
			$dbw = wfGetDB(DB_MASTER);
			$dbw->delete(MethodEditor::TABLE_NAME, array('ama_id' => $methodId));
			
			$title = Title::newFromID($articleId);
			if($title) {
				$logPage = new LogPage('methedit', false);
				$logS = $logPage->addEntry("Deleted", $title, wfMessage('editor-rejected-logentry', $title->getFullText(), $method)->text());
			}

            wfRunHooks("MethodEdited", array($wgUser, $title, '0'));
		}
	}
	
	private function keepMethod($methodId, $articleId, $altMethod, $altSteps) {
		global $wgUser, $wgParser;
		
		$title = Title::newFromID($articleId);
		
		if($title) {
			$revision = Revision::newFromTitle($title);
			$article = new Article($title);
			if($revision && $article) {
				$wikitext = $revision->getText();
				$section = Wikitext::getStepsSection($wikitext, true);
				$newSection = $section[0] . "\n\n=== {$altMethod} ===\n{$altSteps}";
				
				$newText = $wgParser->replaceSection($wikitext, $section[1], $newSection);
				
				$success = $article->doEdit($newText, MethodEditor::EDIT_COMMENT);
				
				if($success) {
					$logPage = new LogPage('methedit', false);

                    $altMethodTransform = str_replace(" ", "_", $altMethod);
					
					$logS = $logPage->addEntry("Added", $title,
					wfMessage('editor-approved-logentry', $title->getFullText(), $altMethod, $altMethodTransform)->text());

					$dbw = wfGetDB(DB_MASTER);

					//now we need to log the save in the new table
					$dbw->insert(MethodEditor::LOGGING_TABLE_NAME, array('mel_timestamp' => wfTimestampNow(), 'mel_user' => $wgUser->getID()));

					$dbw->delete(MethodEditor::TABLE_NAME, array('ama_id' => $methodId));
				}
                wfRunHooks("MethodEdited", array($wgUser, $title, '0'));
				return $success;
			}
		}
	}

	private function quickEditRecord($methodId, $articleId) {
		global $wgUser;

		$title = Title::newFromID($articleId);

		if($title) {
			$logPage = new LogPage('methedit', false);

			$logPage->addEntry("Added", $title, wfMessage('editor-quickedit-logentry', $title->getFullText())->text());

			$dbw = wfGetDB(DB_MASTER);
			$dbw->delete(MethodEditor::TABLE_NAME, array('ama_id' => $methodId));

			wfRunHooks("MethodEdited", array($wgUser, $title, '0'));
		}

	}

	function displayLeaderboards() {
		$stats = new MethodEditorStandingsIndividual();
		$stats->addStatsWidget();
		$standings = new MethodEditorStandingsGroup();
		$standings->addStandingsWidget();
	}
}
