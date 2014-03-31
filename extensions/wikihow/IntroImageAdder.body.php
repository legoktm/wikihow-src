<?

class IntroImageAdder extends UnlistedSpecialPage {

	function __construct() {
		parent::__construct('IntroImageAdder');
	}

	/**	
	 * hasProblems
	 * (returns TRUE if there's a problem)
	 * - Checks an article to see if it contains an image in the intro section
	 * - Checks to see if there's a {{nointroimg}} template
	 * - Checks to see if there's an {{nfd}} template
	 * - Checks to see if there's an {{copyvio}} template
	 * - Checks to see if there's an {{copyviobot}} template
	 * - Makes sure an article has been NABbed
	 * - Makes sure last edit has been patrolled
	 **/
	function hasProblems($t,$dbr) {
		$r = Revision::newFromTitle($t);
		$intro = Article::getSection($r->getText(), 0);

		//check for intro image
		if (preg_match('/\[\[Image:(.*?)\]\]/', $intro)) return true;
		
		//check for {{nointroimg}} template
		if (preg_match('/{{nointroimg/', $intro)) return true;
		
		//check for {{nfd}} template
		if (preg_match('/{{nfd/', $intro)) return true;
		
		//check for {{copyvio}} or {{copyviobot}} template
		if (preg_match('/{{copyvio/', $intro)) return true;
		
		//is it NABbed?
		$is_nabbed = Newarticleboost::isNABbed($dbr,$t->getArticleId());
		if (!$is_nabbed) return true;
		
		//last edit patrolled?
		if (!GoodRevision::patrolledGood($t)) return true;
		
		//all clear?
		return false;
	}

	/**	
	 * addIntroImage
	 * Called from EasyImageUploader and adds image to intro section and updates article
	 **/
	function addIntroImage($v) {
		global $wgOut, $wgRequest, $wgUser;

		$fname = "IntroImageAdder::addIntroImage";
		wfProfileIn($fname); 

		$title = $wgRequest->getVal('iiatitle');
		$imgtag = "[[Image:".$v['imageFilename']."|thumb|right|251px]]";
		$json = '';

		$t = Title::newFromText($title);
		$r = Revision::newFromTitle($t);
		$intro = Article::getSection($r->getText(), 0);

		if (!preg_match('/\[\[Image:(.*?)\]\]/', $intro)) {
			$a = new Article($t);

			//gotta insert the image AFTER any templates
			//split 'em
			$parts = preg_split("@(\{\{[^}]*\}\})@im", $intro, 0, PREG_SPLIT_DELIM_CAPTURE);
			
			//iterate through until we hit the first non-template
			$newintro = "";
			$found = false;
			while (sizeof($parts) > 0) {
				$x = array_shift($parts);
				if (trim($x) != '') { 
					if (!preg_match('@^\{\{@', $x)) {
						// we have found a non template
						$newintro .= $imgtag . $x;
						$found = true;
						break;
					} 
				}
				// otherwise keep pasting the templates into the new intro
				$newintro .= $x;
			}
			// we may have stuff left over from the parts
			$newintro .= implode($parts); 
			
			if (!$found) {
				// the intro had no template(s)
				$newintro .= $imgtag; 
			}
	
	
			//$text = $a->replaceSection(0, $newintro);
			global $wgParser;
			$oldtext = $r->getText();
			$text = $wgParser->replaceSection($oldtext, 0, $newintro);
			
			$a->doEdit($text, wfMsg('iia-editsummary'), EDIT_MINOR);	
		
			wfRunHooks("IntroImageAdderUploadComplete", array($t, $imgtag,$wgUser));
		} else {
			wfDebug("IntroImageAdder - image already exists for article $title \n");
		}
		
		$json['status'] = "SUCCESS";
		$json['title'] = urlencode( $t->getText() );
		$json['img'] = urlencode( $v['imageFilename'] );

		wfProfileOut($fname); 
		return json_encode( $json );
		
	}

	function confirmationModal($iiatitle, $img) {
		global $wgOut, $wgParser, $Title, $wgServer;

		$fname = "IntroImageAdder::confirmationModal";
		wfProfileIn($fname); 

		$t = Title::newFromText($iiatitle);
		$imgtag = "[[Image:".$img."|251px]]";
		$titletag = "[[$iiatitle|How to $iiatitle]]";
		$content = wfMsg('iia_confirmation_dialog', $titletag, $imgtag );
		$output = $wgParser->parse($content, $Title, new ParserOptions() );
		$content = $output->getText();
		$content = "
<div class='iia_modal'>
$content
<div style='clear:both'></div>
<span style='float:right'>
<input class='button blue_button_100 submit_button' onmouseover='button_swap(this);' onmouseout='button_unswap(this);' type='button' value='".wfMsg('iia_confirmation_button')."' onclick='introImageAdder.closeConfirmation();return false;' >
</span>
<input type='checkbox' id='confirmModalFlag' >   ".wfMsg('iia_confirmation_dialog_flagmsg')."
</div>";

		$wgOut->addHTML( $content );
		wfProfileOut($fname); 
	}

	/**	
	 * getSearchTerms
	 * Passed in a title an removes stop words and unneccessary punctuation
	 **/
	function getSearchTerms($t) {
		$fname = "IntroImageAdder::getSearchTerms";
		wfProfileIn($fname);
		$stopwords = explode(',',wfMsg('iia_stopwords'));
		$exclude = array();
		foreach ($stopwords as $word) {
			array_push($exclude, strtoupper(trim($word)));
		}

		$t = str_replace("-"," ",$t);
		$t_arr = explode(" ",$t);
		$s_arr = array();
		foreach ($t_arr as $word) {
			if ((strlen($word) > 2) &&
				(!in_array(strtoupper($word),$exclude)) ){
				array_push($s_arr, $word);
			}
		}
		$s = implode(" ",$s_arr);

		//Characters to replace from string
		//$s = preg_replace('/\W/', ' ', $s);
		$s = preg_replace('/[,()"]/', '', $s);
		wfProfileOut($fname);
		return $s;
	}

	/**
	 *
	 * Returns the total number of articles waiting to
	 * have images added to the Intro
	 */
	function getArticleCount(&$dbr){
		$timediff = date("YmdHis", strtotime("-1 day")); //24 hours ago
		$res = $dbr->select('imageadder', array('count(*) as C'), array("imageadder_last_viewed < '$timediff'","imageadder_page != 5791", "imageadder_skip < 4", "imageadder_hasimage = '0'"), 'IntroImageAdder::getArticleCount');
		$row = $dbr->fetchObject($res);

		return $row->C;
	}

	/**	
	 * getNext
	 * Get the next article to show
	 **/
	function getNext() {
		global $wgRequest, $wgUser;

		$fname = "IntroImageAdder::getNext";
		wfProfileIn($fname);

		$dbm = wfGetDB(DB_MASTER);
		$dbr = wfGetDB(DB_SLAVE);

		// mark skipped 
		if ($wgRequest->getVal('skip', null)) {
			$t = Title::newFromText($wgRequest->getVal('skip'));
			$id = $t->getArticleID();
			$dbm->update('imageadder', array('imageadder_skip=imageadder_skip+1', 'imageadder_skip_ts'=>wfTimestampNow()), 
				array('imageadder_page'=>$id));
		}
		$a = array();
		
		for ($i = 0; $i < 30; $i++) {
		
			$timediff = date("YmdHis", strtotime("-1 day")); //24 hours ago
			
			//NOTE SQL Queries are excluding pageid 5791 cause it's a Categories page, don't know why it's not in wikihow NS.
			$opts = array("imageadder_last_viewed < '$timediff'",'imageadder_page != 5791', 
				'imageadder_skip < 4', 'imageadder_hasimage' => 0
			);

			$tables = array('imageadder'); 

			if (mt_rand(0,9) < 7) {
				//ORDER BY PAGE_COUNTER
				$pageid = $dbr->selectField($tables, 'imageadder_page',  
					$opts,			
					"IntroImageAdder::getNext", 
					array("ORDER BY" => "imageadder_page_counter DESC", "LIMIT" => 1));
			} else {
				//ORDER BY PAGE_TOUCHED
				$pageid = $dbr->selectField($tables, array('imageadder_page'), 
					$opts,
					"IntroImageAdder::getNext", 
					array("ORDER BY" => "imageadder_page_touched DESC", "LIMIT" => 1));
			}
			
			//No articles need images?
			if (empty($pageid)) continue;			
			
			/*
			 * XXNOTE: One day when we can prefetch search terms we will do this instead of call the function
			 * $sql = "SELECT imageadder_page,imageadder_terms from imageadder where imageadder_inuse != 1";
			 * $res = $dbr->query($sql);
			 */
			$dbm->update('imageadder', array('imageadder_last_viewed'=>wfTimestampNow()),array('imageadder_page'=>$pageid));			

			$t = Title::newFromID($pageid);
			if (!$t) continue;

			//prove false
			$b_good = true;
	
			//valid article?
			if ($t->getArticleId() == 0) $b_good = false;
			
			//protected article?
			if ($t->isProtected()) $b_good = false;
			
			//check the wikitext for problems
			if ($this->hasProblems( $t, $dbr ) ) {
	 			$b_good = false;
				$dbm->update('imageadder', array('imageadder_hasimage'=>1), array("imageadder_page"=>$pageid));
			}
			
			//is this a redirect?
			$article = new Article($t);
			if ($article->isRedirect()) $b_good = false;
			
			if ($b_good) {
				$a['aid'] = $t->getArticleId();
				$a['title'] = $t->getText();
				$a['url'] = $t->getLocalURL();
				$a['terms'] = $this->getSearchTerms($t->getText());
				wfProfileOut($fname);
				return( $a );
			}
			else {
				//not be good; mark it skipped
				$dbm->update('imageadder', array('imageadder_skip=imageadder_skip+1', 'imageadder_skip_ts'=>wfTimestampNow()), 
					array('imageadder_page'=>$t->getArticleId()));
			}
		}
	
		//send error msg
		$a['aid'] = '0';
		$a['title'] = 'No articles need images';
		$a['url'] = '';
		$a['terms'] = 'fail whale';
		wfProfileOut($fname);
		return $a;
	}

	/**	
	 * show
	 * Display the main window.  Right now it's skinless
	 **/
	function show() {
		global $wgOut, $wgUser;

		$fname = "Introimageadder::show";
		wfProfileIn($fname);
	
		$sk = $wgUser->getSkin();
		//$wgOut->setArticleBodyOnly(true);
		$wgOut->addHTML( Easyimageupload::getUploadBoxJS() );

		$wgOut->addHTML( "
	<style type='text/css' media='all'>/*<![CDATA[*/ @import '" . wfGetPad('/extensions/min/f/extensions/wikihow/introimageadder.css?rev=') . WH_SITEREV . "'; /*]]>*/</style>
	<script type='text/javascript' src='" . wfGetPad('/extensions/min/f/extensions/wikihow/introimageadder.js?rev=') . WH_SITEREV . "'></script>

	<div id='IntroImageAdder'>
		<div id='introimageheader'>
			<h1>". wfMsg('iia_title')."</h1>
			" . wfMsg('iia_msg_instructions') . "
			<div id='iia_msg'>
			</div>
		</div><!--end introimageheader-->
		<div style='clear:both;'></div>
		<div id='iia_main'><img src='" . wfGetPad('/extensions/wikihow/rotate.gif') . "' alt='' class='eiu-wheel' id='eiu-wheel-details' /></div>
	</div>
<script type='text/javascript'>
var replacelinks = false;
var pastmessages = [];

jQuery(window).load(introImageAdder.init);
</script>

		");
		wfProfileOut($fname);
		return;
	}

	/**	
	 * fetchArticle
	 * Gets the next article (getNext) and prepares to return it in a json object with stats
	 * and corresponding message
	 **/
	function fetchArticle() {
        $fname = "Introimageadder::fetchArticle";
        wfProfileIn($fname);
		$a = $this->getNext();
        wfProfileOut($fname);
		return $a;
	}

	/**
	 * EXECUTE
	 **/
	function execute ($par) {
		global $wgRequest, $wgOut, $wgUser, $wgLang;
        $fname = "Introimageadder::execute";
        wfProfileIn($fname);
		$target = isset( $par ) ? $par : $wgRequest->getVal( 'target' );

		$wgOut->addHTML('<center><tt><b>
Dear intro image adder,<br>
<br>
We had some good days together, we had some bad days together. At this point, I think<br>
we\'ve outgrown each other. It\'s time for us to break up.<br>
<br>
Love, <a href="http://forums.wikihow.com/discussion/6321/retiring-the-intro-image-adder">wikiHow</a><br>
		</b></tt></center>');
		return;

		if ($wgUser->isBlocked()) {
			$wgOut->blockedPage();
        	wfProfileOut($fname);
			return;
		}

		if ($wgUser->getID() == 0) {
			$wgOut->setRobotpolicy( 'noindex,nofollow' );
			$wgOut->showErrorPage( 'nosuchspecialpage', 'nospecialpagetext' );
        	wfProfileOut($fname);
			return;
		}

		//XXNOTE Temporary push to prod code.  When released, remove admin requirement
		//
		//if ( !in_array( 'newarticlepatrol', $wgUser->getRights() ) ) {
		//	$wgOut->showErrorPage( 'nosuchspecialpage', 'nospecialpagetext' );
		//	rreturn;
		//}

		if ($wgRequest->getVal( 'fetchArticle' )) {
			$wgOut->setArticleBodyOnly(true);
			echo json_encode( $this->fetchArticle() );
        	wfProfileOut($fname);
			return;
		} else if ($wgRequest->getVal( 'confirmation' )) {
			$wgOut->setArticleBodyOnly(true);
			echo $this->confirmationModal($wgRequest->getVal('iiatitle'), $wgRequest->getVal('imgtag') ) ;
        	wfProfileOut($fname);
			return;
		} else if ($wgRequest->getVal( 'fetchMessage' )) {
			$wgOut->setArticleBodyOnly(true);
			echo json_encode( $this->fetchMessage() );
        	wfProfileOut($fname);
			return;
		}

		$wgOut->setHTMLTitle('Intro Image Adder - wikiHow');

		$indi = new IntroImageStandingsIndividual();
		$indi->addStatsWidget();

		$standings = new IntroImageStandingsGroup();
		$standings->addStandingsWidget();
		$this->show();
        wfProfileOut($fname);
		return;
	}
}
