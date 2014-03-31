<?

class EditFinder extends UnlistedSpecialPage {
	var $topicMode = false;

	function __construct() {
		global $wgHooks;
		parent::__construct( 'EditFinder');
		$wgHooks['getToolStatus'][] = array('Misc::defineAsTool');
	}
	
	/**
	 * Set html template path for EditFinder actions
	 */
	public static function setTemplatePath() {
		EasyTemplate::set_path( dirname(__FILE__).'/' );
	}

	public static function getUnfinishedCount(&$dbr, $type) {
		switch ($type) {
		case 'Stub':
			$count = $dbr->selectField(
				array('page', 'templatelinks'),
				'count(*) as count',
				array('tl_title' => 'Stub', 'tl_from=page_id', 'page_namespace' => '0'),
				__METHOD__);
			return $count;
				
		case 'Format':
			$count = $dbr->selectField(
				array('page', 'templatelinks'),
				'count(*) as count',
				array('tl_title' => 'Format',
					'tl_from=page_id',
					'page_namespace' => '0'),
				__METHOD__);
			return $count;
		case 'Copyedit':
			$count = $dbr->selectField(
				array('page', 'templatelinks'),
				'count(*) as count',
				array('tl_title' => 'Copyedit',
					'tl_from=page_id',
					'page_namespace' => '0'),
				__METHOD__);
			return $count;
		case 'Cleanup':
			$count = $dbr->selectField(
				array('page', 'templatelinks'),
				'count(*) as count',
				array('tl_title' => 'Cleanup',
					'tl_from=page_id',
					'page_namespace' => '0'),
				__METHOD__);
			return $count;
		case 'Topic':
			// No real unfinished count for Greenhouse by Topic
			return 0;
		}

		return 0;
	}
	
	function getNextArticle() {
		global $wgRequest;
		
		//skipping something?
		$skip_article = $wgRequest->getVal('skip');
		
		//flip through a few times in case we run into problem articles
		for ($i = 0; $i < 30; $i++) {
			$pageid = $this->topicMode ? $this->getNextByInterest($skip_article) : $this->getNext($skip_article);
			if (!$this->hasProblems($pageid)) {
				return $this->returnNext($pageid);
			} else {
				// If there's a problem, come back to it later
				$skip_article = intVal($pageid);
			}
		}
		return $this->returnNext('');
	}


	function getNextByInterest($skip_article) {
		global $wgRequest, $wgUser;

		wfProfileIn(__METHOD__); 

		$dbw = wfGetDB(DB_MASTER);

		// mark skipped 
		if (!empty($skip_article)) {
			$t = is_int($skip_article) ? 
				Title::newFromID($skip_article) : Title::newFromText($skip_article);
				
			if (!$t) exit;
			$id = $t->getArticleID();
			
			//mark the db for this user
			if (!empty($id)) {
				$dbw->insert(
					'editfinder_skip',
					array('efs_page' => $id,
						'efs_user' => $wgUser->getID(),
						'efs_timestamp' => wfTimestampNow()),
					__METHOD__);
			}
		}
	
		$aid = $wgRequest->getInt('id');
		
		if ($aid) {
			//get a specific article
			$sql = "SELECT page_id from page WHERE page_id = $aid LIMIT 1";
		} 
		else {
			$timediff = date("YmdHis", strtotime("-1 hour"));
			$sql = "SELECT page_id from page p INNER JOIN categorylinks c ON c.cl_from = page_id WHERE page_namespace = 0 ";
			$sql .= $this->getSkippedArticles('page_id');
			$sql .= $this->getUserInterests();
			
			$sql .= " ORDER BY p.page_random LIMIT 1;";
		}
		
		$res = $dbw->query($sql, __METHOD__); 

		while ($row = $res->fetchObject()) {
			$pageid = $row->page_id;
		}
		
		if ($pageid) {
			//not a specified an article, right?
			if (empty($aid)) {
				//is the article {{in use}}?
				if ($this->articleInUse($pageid)) {
					//mark it as viewed
					$pageid = '';
				}
			}
		}
		wfProfileOut(__METHOD__);
		return $pageid;
	}

	function getNext($skip_article) {
		global $wgRequest, $wgUser;

		$dbw = wfGetDB(DB_MASTER);

		// mark skipped 
		if (!empty($skip_article)) {
			$t = is_int($skip_article) ? 
				Title::newFromID($skip_article) : Title::newFromText($skip_article);
				
			$id = $t->getArticleID();
			
			//mark the db for this user
			if (!empty($id))
				$dbw->insert('editfinder_skip', array('efs_page'=>$id,'efs_user'=>$wgUser->getID(),'efs_timestamp'=>wfTimestampNow() ));
		}
	
		$aid = $wgRequest->getInt('id');
		
		if ($aid) {
			//get a specific article
			$sql = "SELECT ef_edittype, ef_page from editfinder WHERE 
				ef_page = $aid LIMIT 1";
		} else {
			$edittype = strtolower($wgRequest->getVal( 'edittype' ));
			
			$timediff = date("YmdHis", strtotime("-1 hour"));
			$sql = "SELECT ef_edittype, ef_page from editfinder 
					INNER JOIN page p ON p.page_id = ef_page
					WHERE ef_last_viewed < ". $dbw->addQuotes($timediff) ."
					AND lower(ef_edittype) = ".$dbw->addQuotes($edittype)
					.$this->getSkippedArticles();

			$sql .= $this->getUserCats() . " ";
			
			$sql .= " LIMIT 1";
		}
		
		$res = $dbw->query($sql, __METHOD__); 
		while ($row = $res->fetchObject()) {
			$pageid = $row->ef_page;
		}
		
		if ($pageid) {
			//not a specified an article, right?
			if (empty($aid)) {
				//is the article {{in use}}?
				if ($this->articleInUse($pageid)) {
					//mark it as viewed
					$dbw->update(
						'editfinder',
						array('ef_last_viewed' => wfTimestampNow()),
						array('ef_page' => $pageid),
						__METHOD__);
					$pageid = '';
				}
			}
		}
		return $pageid;
	}
	
	function returnNext($pageid) {
		global $wgOut, $Title;
		
		if (empty($pageid)) {
			//nothing? Ugh.
			$a['aid'] = '';
		}
		else {
			if (!$this->topicMode) {
				//touch db
				$dbw = wfGetDB(DB_MASTER);
				$dbw->update(
					'editfinder',
					array('ef_last_viewed' => wfTimestampNow()),
					array('ef_page' => $pageid),
					__METHOD__);
			}

			$a = array();
			
			$t = Title::newFromID($pageid);
			
			$a['aid'] = $pageid;
			$a['title'] = $t->getText();
			$a['url'] = $t->getLocalURL();
		}
			
		//return array
		return( $a );	 
	}	
	
	function confirmationModal($type,$id) {
		global $wgOut, $Title;

		wfProfileIn(__METHOD__); 

		$t = Title::newFromID($id);
		$titletag = "[[".$t->getText()."|".wfMsg('howto', $t->getText())."]]";
		$content = 	"
			<div class='editfinder_modal'>
			<p>Thanks for your edits to <a href='".$t->getLocalURL()."'>".wfMsg('howto', $t->getText())."</a>.</p>
			<p>Would it be appropriate to remove the <span class='template_type'>".strtoupper($type)."</span> from this article?</p>
			<div style='clear:both'></div>
			<span style='float:right'>
			<input class='button primary submit_button' type='button' value='".wfMsg('editfinder_confirmation_yes')."' onclick='editFinder.closeConfirmation(true);return false;' >
			<input class='button secondary submit_button' type='button' value='".wfMsg('editfinder_confirmation_no')."' onclick='editFinder.closeConfirmation(false);return false;' >
			</span>
			</div>";
		$wgOut->addHTML($content);
		wfProfileOut(__METHOD__); 
	}
	
	function cancelConfirmationModal($id) {
		global $wgOut, $Title;

		wfProfileIn(__METHOD__); 

		$t = Title::newFromID($id);
		$titletag = "[[".$t->getText()."|".wfMsg('howto', $t->getText())."]]";
		$content = 	"
			<div class='editfinder_modal'>
			<p>Are you sure you want to stop editing <a href='".$t->getLocalURL()."'>".wfMsg('howto', $t->getText())."</a>?</p>
			<div style='clear:both'></div>
			<p id='efcc_choices'>
			<a href='#' id='efcc_yes'>".wfMsg('editfinder_cancel_yes')."</a>
			<input class='button blue_button_100 submit_button' onmouseover='button_swap(this);' onmouseout='button_unswap(this);' type='button' value='".wfMsg('editfinder_confirmation_no')."' id='efcc_no'>
			</p>
			</div>";
		$wgOut->addHTML($content);
		wfProfileOut(__METHOD__); 
	}

	
	/**	
	 * articleInUse
	 * check to see if {{inuse}} or {{in use}} is in the article
	 * returns boolean
	 **/
	function articleInUse($aid) {
		$dbr = wfGetDB(DB_SLAVE);
		$r = Revision::loadFromPageId( $dbr, $aid );
		
		if (strpos($r->getText(),'{{inuse') === false)
			$result = false;
		else
			$result = true;	
		return $result;
	}
		
	function getUserInterests() {
		$interests = CategoryInterests::getCategoryInterests();	
		$interests = array_merge($interests, CategoryInterests::getSubCategoryInterests($interests));
		$interests = array_values(array_unique($interests));

		$fn = function(&$value) {
			$dbr = wfGetDB(DB_SLAVE);
			$value = $dbr->strencode($value);
		};
		array_walk($interests, $fn);
		$sql = " AND c.cl_to IN ('" . implode("','", $interests) . "') ";
		return $sql;
	}

	/**	
	 * getUserCats
	 * grab categories specified by the user
	 * returns sql string
	 **/
	function getUserCats() {
		global $wgUser, $wgCategoryNames;
		$cats = array();
		$catsql = '';
		$bitcat = 0;

		$dbr = wfGetDB(DB_SLAVE);

		$row = $dbr->selectRow(
			'suggest_cats',
			array('*'),
			array('sc_user' => $wgUser->getID()),
			__METHOD__);

		if ($row) {
			$field = $row->sc_cats;
			$cats = preg_split("@,@", $field, 0, PREG_SPLIT_NO_EMPTY);			
		}
		
		$topcats = array_flip($wgCategoryNames);
		
		foreach ($cats as $key => $cat) {
			foreach ($topcats as $keytop => $cattop) {
				$cat = str_replace('-',' ',$cat);
				if (strtolower($keytop) == $cat) {
					$bitcat |= $cattop;
					break;
				}
			}
		}
		if ($bitcat > 0) {
			$catsql = ' AND p.page_catinfo & '.$bitcat.' <> 0';
		}
		return $catsql;
	}
	
	/**	
	 * getSkippedArticles
	 * grab articles that were already "skipped" by the user
	 * returns sql string
	 **/
	function getSkippedArticles($column = 'ef_page') {
		global $wgUser;
		$skipped = '';
		$dbw = wfGetDB(DB_MASTER);
		$res = $dbw->select(
			'editfinder_skip',
			array('efs_page'),
			array('efs_user' => $wgUser->getID()),
			__METHOD__);

		while ($row = $res->fetchObject()) {
			$skipped_ary[] = $row->efs_page;
		}
		if (count($skipped_ary) > 0)
			$skipped = ' AND ' . $column . ' NOT IN ('. implode(',',$skipped_ary) .') ';

		return $skipped;
	}
	
	
	/**	
	 * hasProblems
	 * (returns TRUE if there's a problem)
	 * - Makes sure last edit has been patrolled
	 **/
	function hasProblems($pageid) {
		if (empty($pageid)) return true;
		
		$t = Title::newFromId($pageid);
		if (!$t) return true;
		
		//last edit patrolled?
		if (!GoodRevision::patrolledGood($t)) return true;
		
		//all clear?
		return false;
	}
	
	/**
	 * cuteCUTE
	 **/
	function execute($par) {
		global $wgRequest, $wgOut, $wgUser, $wgLang, $wgParser, $efType, $wgTitle;
		$target = isset( $par ) ? $par : $wgRequest->getVal( 'target' );
		wfLoadExtensionMessages('EditFinder');
		
		self::setTemplatePath();

		if ($wgUser->isBlocked()) {
			$wgOut->blockedPage();
			return;
		}

		if ($wgUser->getID() == 0) {
			$wgOut->setRobotpolicy( 'noindex,nofollow' );
			$wgOut->showErrorPage( 'nosuchspecialpage', 'nospecialpagetext' );
			return;
		}

		$this->topicMode = strtolower($par) == 'topic' || strtolower($wgRequest->getVal('edittype')) == 'topic';

		if ($wgRequest->getVal( 'fetchArticle' )) {
			$wgOut->setArticleBodyOnly(true);
			echo json_encode($this->getNextArticle());
			return;
			
		} elseif ($wgRequest->getVal( 'show-article' )) {
			$wgOut->setArticleBodyOnly(true);
			
			if ($wgRequest->getInt('aid') == '') {
				$catsJs = $this->topicMode ? "editFinder.getThoseInterests();" : "editFinder.getThoseCats();";
				$catsTxt = $this->topicMode ? "interests" : "categories";
				$wgOut->addHTML('<div class="article_inner">No articles found.  <a href="#" onclick="' . $catsJs . '">Select more ' . $catsTxt . '</a> and try again.</div>');
				return;
			}
			
			$t = Title::newFromID($wgRequest->getInt('aid'));
			
			$articleTitleLink = $t->getLocalURL();
			$articleTitle = $t->getText();
			//$edittype = $a['edittype'];
						
			//get article
			$a = new Article($t);
			
            $r = Revision::newFromTitle($t);
            $popts = $wgOut->parserOptions();
            $popts->setTidy(true);
            $popts->enableLimitReport();
            $parserOutput = $wgParser->parse( $r->getText(), $t, $popts, true, true, $a->getRevIdFetched() );
            $popts->setTidy(false);
            $popts->enableLimitReport( false );
			$magic = WikihowArticleHTML::grabTheMagic($r->getText());
            $html = WikihowArticleHTML::processArticleHTML($parserOutput->getText(), array('no-ads' => true, 'ns' => NS_MAIN, 'magic-word' => $magic));
			$wgOut->addHTML($html);
			return;
			
		} elseif ($wgRequest->getVal( 'edit-article' )) {
			// SHOW THE EDIT FORM
			$wgOut->setArticleBodyOnly(true);
			$t = Title::newFromID($wgRequest->getInt('aid'));
			$a = new Article($t);
			$editor = new EditPage( $a );
			$editor->edit();
			return;
			
		} elseif ($wgRequest->getVal( 'action' ) == 'submit') {
			$wgOut->setArticleBodyOnly(true);
			
			$efType = strtolower($wgRequest->getVal('type'));
			
			$t = Title::newFromID($wgRequest->getInt('aid'));
			$a = new Article($t);
			
			//log it
			$params = array($efType);            
			$log = new LogPage( 'EF_'. substr($efType, 0, 7), false ); // false - dont show in recentchanges

			$log->addEntry('', $t, 'Repaired an article -- '.strtoupper($efType).'.', $params);
			
			$text = $wgRequest->getVal('wpTextbox1');
			$sum = $wgRequest->getVal('wpSummary');

			//save the edit
			$a->doEdit($text,$sum,EDIT_UPDATE);
			wfRunHooks("EditFinderArticleSaveComplete", array($a, $text, $sum, $wgUser, $efType));
			return;
			
		} elseif ($wgRequest->getVal( 'confirmation' )) {
			$wgOut->setArticleBodyOnly(true);
			echo $this->confirmationModal($wgRequest->getVal('type'),$wgRequest->getInt('aid')) ;
        	wfProfileOut(__METHOD__);
			return;
			
		} elseif ($wgRequest->getVal( 'cancel-confirmation' )) {
			$wgOut->setArticleBodyOnly(true);
			echo $this->cancelConfirmationModal($wgRequest->getInt('aid')) ;
        	wfProfileOut(__METHOD__);
			return;
			
		} else { //default view (same as most of the views)
			$sk = $wgUser->getSkin();
			$wgOut->setArticleBodyOnly(false);
		
			$efType = strtolower($target);
			if (strpos($efType,'/') !== false) {
				$efType = substr($efType,0,strpos($efType,'/'));
			}
			if ($efType == '') {
				//no type specified?  send 'em to format...
				$wgOut->redirect('/Special:EditFinder/Format');
			}

			// Add min group for Article Greenhouses
			$wgOut->addJSCode('ag');
		
			
			//add main article info
			$vars = array('pagetitle' => wfMsg('app-name').': '.wfMsg($efType),'question' => wfMsg('editfinder-question'),
				'yep' => wfMsg('editfinder_yes'),'nope' => wfMsg('editfinder_no'),'helparticle' => wfMsg('help_'.$efType));
			$vars['uc_categories'] = $this->topicMode ? 'Interests' : 'Categories';
			$vars['lc_categories'] = $this->topicMode ? 'interests' : 'categories';
			$vars['editfinder_edit_title'] = wfMsg('editfinder_edit_title');
			$vars['editfinder_skip_title'] = wfMsg('editfinder_skip_title');
			$vars['css'] = HtmlSnips::makeUrlTags('css', array('editfinder.css'), 'extensions/wikihow/editfinder', false);
			$vars['css'] .= HtmlSnips::makeUrlTags('css', array('suggestedtopics.css'), 'extensions/wikihow', false);

			$html = EasyTemplate::html('editfinder_main',$vars);
			$wgOut->addHTML($html);
			
			$wgOut->setHTMLTitle(wfMsg('app-name').': '.wfMsg($efType).' - wikiHow');
			$wgOut->setPageTitle(wfMsg('app-name').': '.wfMsg($efType).' - wikiHow');
		}
		
		$stats = new EditFinderStandingsIndividual($efType);
        $stats->addStatsWidget();
		$standings = new EditFinderStandingsGroup($efType);
		$standings->addStandingsWidget();
	}

}

