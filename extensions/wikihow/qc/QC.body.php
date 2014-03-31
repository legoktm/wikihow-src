<?

if (!defined('MEDIAWIKI')) die();

global $IP;
require_once("$IP/skins/WikiHowSkin.php");

abstract class QCRule {

	// flexibility if we want to track different namespaces
	var $mValidNamespaces = array(NS_MAIN);
	var	$mArticle	= null;
	var $mAction	= '';
	var $mKey		= '';
	var $mResult	= null; // action item to patrol, a row from the qc table
	var $mTitle		= null;

	function __construct($article) {
		global $wgHooks;
		$this->mArticle 	= $article;
		$wgHooks['getToolStatus'][] = array('Misc::defineAsTool');
	}	

	function textRemoved($part, $oldtext, $newtext) {
		if (preg_match("@{$part}@i", $oldtext) && !preg_match("@{$part}@", $newtext)) {
			return true;
		}
		return false;
	}

	function textAdded($part, $oldtext, $newtext) { 
		if (!preg_match("@{$part}@i", $oldtext) && preg_match("@{$part}@", $newtext)) {
			return true;
		}
		return false;
	}

	function hasText($part, $text) {
		return preg_match("@{$part}@i", $text);
	}

	function hasEntry($articleID) {
		$dbr = wfGetDB(DB_SLAVE);
		$hasEntry = $dbr->selectField('qc', 'count(*)', 
				array('qc_page'=> $articleID, 'qc_patrolled'=>0, 'qc_key'=>$this->mKey)) > 0;
		return $hasEntry;
	}

	function textAddedOrRemoved($part, $oldtext, $newtext) {
		return self::textAdded($part, $oldtext, $newtext) || self::textRemoved($part, $oldtext, $newtext);	
	}

	function textChanged($part, $oldtext, $newtext) {
		preg_match_all("@" . $part . "@iU", $oldtext, $matches1);
		preg_match_all("@" . $part . "@iU", $newtext, $matches2); 
		return !($matches1 == $matches2); 
	}

	function process() {
		if ($this->flagAction()) {
			return $this->logQCEntry();
		}
	}

	function getEntryOptions() {
		return array();
	}
	
	function getKey() {
		return $this->mKey;
	}
	
	function getAction() {
		return $this->mAction;
	}
	
	abstract public function getYesVotesRequired();
	abstract public function getNoVotesRequired();

	static function deleteIfNotPatrolled($qc_id, $qc_user) {
		if (!$qc_id) {
			return false;
		}

		$dbw = wfGetDB(DB_MASTER);

		return $dbw->delete('qc', array('qc_id'=>$qc_id, 'qc_patrolled'=>0, 'qc_user'=>$qc_user));
	}

	function deleteBad($qc_page) {
		// is there something we can delete ? 
		$dbw = wfGetDB(DB_MASTER);
		$page_title = $dbw->selectField('page', 'page_title', array('page_id'=>$qc_page));
		if (!$page_title) {
			$dbw->delete('qc', array('qc_page'=>$qc_page));
		}
	}

	function getTitleFromQCID($qcid) {

		$dbr = wfGetDB(DB_SLAVE);
		$page_id = $dbr->selectField('qc', array('qc_page'), array('qc_id'=>$qcid)); 

		// construct the HTML to reply
		// load the page
		$t = Title::newFromID($page_id); 
		return $t;
	}

	function getRevFromQCID($qcid) {

		$dbr = wfGetDB(DB_SLAVE);
		$rev_id = $dbr->selectField('qc', array('qc_rev_id'), array('qc_id'=>$qcid)); 

		// construct the HTML to reply
		// load the page
		$r = Revision::newFromID($rev_id); 
		if (!$r) return null;
		return $r;
	}

	function markPreviousAsPatrolled() {
		$dbw = wfGetDB(DB_MASTER);
		//mark any existing entries as patrolled for this entry
		$dbw->update("qc", array("qc_patrolled"=>1), array("qc_page"=> $this->mArticle->getID(), "qc_key"=>$this->getKey()));
	}

	public static function markAllAsPatrolled($title) {
		$dbw = wfGetDB(DB_MASTER);
		//mark any existing entries as patrolled for this entry
		$dbw->update("qc", array("qc_patrolled"=>1), array("qc_page"=> $title->getArticleID()));
	}

	function logQCEntry () {
		global $wgUser;
		$opts = array(	"qc_key" => $this->getKey(), 
						"qc_action" => $this->getAction(),
						"qc_timestamp" => wfTimestampNow(),	
						"qc_user" => $wgUser->getID(),
						"qc_user_text" => $wgUser->getName(),
						"qc_yes_votes_req" 	=> $this->getYesVotesRequired(),
						"qc_no_votes_req" 	=> $this->getNoVotesRequired(),
						"qc_page" => $this->mArticle->getID(),
				);
		$opts = array_merge($this->getEntryOptions(), $opts);

		$this->markPreviousAsPatrolled(); 

		$dbw = wfGetDB(DB_MASTER);
		$dbw->insert('qc', $opts);

		return $dbw->insertId();
		#print_r($dbw); exit;
	}

	function getPreviouslyViewedExp() {
		global $wgUser;
		$exp = 0;
		if (strtolower($wgUser->getName()) == 'mqg') {
			// expire every 30 min for MQG 
			$exp = 60 * 30;
		}
		return $exp;
	}

	/***** 
	 * Functions for displaying the QC entry to the patrolling user and accepting votes
	 *
	 ****/

	function getCacheKey($userid) {
		return wfMemcKey('qcuserlog', $userid);
	}

	function markQCAsViewed($qcid) {
		global $wgMemc, $wgUser; 
		$userid = $wgUser->getID();
		$key = self::getCacheKey($userid);
		$log = $wgMemc->get($key); 
		if (!is_array($log)) {
			$log = array(); 
		}
		$log[] = $qcid;
		$wgMemc->set($key, $log, self::getPreviouslyViewedExp()); 
	}
	
	function getPreviouslyViewed() {
		global $wgMemc, $wgUser; 
		$userid = $wgUser->getID();
		
		$key = self::getCacheKey($userid);

		$log = $wgMemc->get($key); 
		if (!is_array($log)) {
			return "";
		}

		$good = array();
		foreach ($log as $u) {
			if (!preg_match("@[^0-9]@", $u) && $u != "") {
				$good[] = $u;
			}
		}
		$str = preg_replace("@,$@", "", implode(",", array_unique($good))); 
		
		return $str;
	}

	public static function getNextToPatrol($type,$by_username) {
		global $wgUser; 

		// grab the next one
		$dbw = wfGetDB(DB_MASTER);
		$expired = wfTimestamp(TS_MW, time() - 3600); 
		
		$sql = "SELECT * from qc left join qc_vote ON qc_id=qcv_qcid AND qcv_user = {$wgUser->getID()} "
			. " WHERE ( qc_checkout_time < '{$expired}' OR qc_checkout_time = '')
				AND qc_patrolled = 0
				AND qc_user != {$wgUser->getID()} 
				AND qcv_qcid is NULL 
				AND qc_page > 0 
				AND qc_key != 'changedintroimage'";
				
		if (!empty($type)) {
			//fix up types string
			$key = strtolower(preg_replace("@qcrule_@", "", $type));
			$key = preg_replace("@/@", "_", $key);
			$key = preg_replace("@,@", "','", $key);
			
			$sql .= " AND qc_key IN ('$key') "; // $opts["qc_key"] = $key;
		}
		else {
			//get all (just video right now)
			$sql .= " AND qc_key IN ('changedvideo') ";
		}

		$previous = self::getPreviouslyViewed();
		if ($previous) {
			$sql .= " AND qc_id NOT IN ({$previous})"; 
		}
		
		if ($by_username) {
			$u = User::newFromName($by_username);
			if ($u) {
				$sql .= " AND qc_user = {$u->getID()} ";
			}
		}

		$sql .= self::getOrderBy($type) . " LIMIT 1";

		$res = $dbw->query($sql);	
		$result = $dbw->fetchObject($res);	
		
		// if we have one, check it out of the queue so multiple people don't get the same item to review
		if ($result) {
			// mark this as checked out
			$dbw->update('qc', array('qc_checkout_time'=>wfTimestampNow(), 'qc_checkout_user'=>$wgUser->getID()), array('qc_id' => $result->qc_id));
		} 
		else {
			return null;
		}
		
		$res = $dbw->query('SELECT COUNT(*) AS c FROM revision WHERE rev_page = '.$result->qc_page.' AND rev_id < '.$result->qc_rev_id.' AND rev_id > '.$result->qc_old_rev_id);
		$user = $dbw->fetchObject($res);
		
		$c = null;
		$key = $result->qc_key;
		$c = self::newRuleFromKey($key);
		$c->mResult = $result;
		$c->mTitle = Title::newFromID($c->mResult->qc_page);		
		$c->mUsers = $user->c;
		$c->sql = $sql;
		return $c; 
	}

	public static function newRuleFromKey($key) {
		$c = null;
		if (preg_match("@changedtemplate_@", $key)) {
			$template = preg_replace("@changedtemplate_@", "", $key); 
			$c = new QCRuleTemplateChange($template);
		} elseif ($key == "changedvideo") {
			$c = new QCRuleVideoChange();
		} elseif ($key == "changedintroimage") {
			$c = new QCRuleIntroImage();
		} elseif ($key == "rcpatrol") {
			$c = new QCRCPatrol();
		} elseif ($key == "rollback") {
			$c = new QCRuleRollback();
		} elseif ($key == "newtip") {
			$c = new QCRuleTip();
		}
		return $c;
	}

	function getOrderBy($type) {
		if (preg_match('@qcrule_rcpatrol@',$type) > 0) {
			//RC Patrol in there
			//gotta do this by most recent
			$ob = " ORDER BY qc_id DESC";
		}	
		else {
			//randomize the ordering
			$rdm = mt_rand(0,1);
			if ($rdm) {
				$ob = " ORDER BY qc_id DESC";
			}
			else {
				$ob = " ORDER BY qc_page ASC";

			}
		}
			
		return $ob;
	}
	
	function releaseQC($qcid) {
		$dbw = wfGetDB(DB_MASTER);
		$dbw->update('qc', array('qc_checkout_time'=> "", 'qc_checkout_user'=> 0), array('qc_id' => $qcid));
		return true;
	}

	function markQCPatrolled($qcid) {
		$dbw = wfGetDB(DB_MASTER);
		$dbw->update('qc', array('qc_patrolled' => 1), array('qc_id'=>$qcid));
		return true; 
	}


	public static function vote($qcid, $vote) {
		global $wgUser;
		$dbw = wfGetDB(DB_MASTER); 

		// have they already voted on this?  if so, forget about it, release the current one back to the queue and get out of here
		$count = $dbw->selectField('qc_vote', array('count(*)'), array('qcv_user'=>$wgUser->getID(), 'qcv_qcid'=>$qcid));
		if ($count > 0) {
			self::releaseQC($qcid);
			return;
		}

		$opts = array(); 
		if ($vote == 1) {
			$opts[] = "qc_yes_votes = qc_yes_votes + 1";
			$voteint = 1;
		} else {
			$opts[] = "qc_no_votes = qc_no_votes + 1";
			$voteint = 0;
		}

		$dbw->update('qc', $opts, array('qc_id'=>$qcid));
		$dbw->insert('qc_vote', array('qcv_user'=>$wgUser->getID(), 'qcv_vote'=>$voteint, 'qcv_qcid'=>$qcid, 'qc_timestamp' => wfTimestampNow()));

		// check, do we have to mark it as patrolled, or roll the change back? 
		$row = $dbw->selectRow('qc', array('qc_page', 'qc_rev_id', 'qc_yes_votes', 'qc_no_votes', 'qc_yes_votes_req', 'qc_no_votes_req'),array('qc_id'=>$qcid));
		$key = $dbw->selectField('qc', 'qc_key', array('qc_id'=>$qcid));
		
		if ($vote) {
			if ($row->qc_yes_votes >= $row->qc_yes_votes_req) {
				self::markQCPatrolled($qcid);
				$c = self::newRuleFromKey($key);
				$c->applyChange($qcid);
			}
		} else {
			if ($row->qc_no_votes >= $row->qc_no_votes_req) {
				// what kind of rule are we ? figure it out so we can roll it back
				$c = self::newRuleFromKey($key);
				$c->rollbackChange($qcid);
				self::markQCPatrolled($qcid);
			}
		}
		self::markQCAsViewed($qcid);	
		self::releaseQC($qcid);

		// log page entry
		$title = Title::newFromID($row->qc_page);
		$log = new LogPage( 'qc', false );

		$vote_param = $vote > 0 ? "yesvote" : "novote"; 
		
		# Generate a diff link
		$skin = $wgUser->getSkin();
		$bits[] = 'oldid=' . urlencode( $row->qc_rev_id );
		$bits[] = 'diff=prev';
		$bits = implode( '&', $bits );
		$diff = "[[{$title->getText()}]]"; // $skin->makeKnownLinkObj( $title, $title->getText(), $bits );
 
		$msg = wfMsgHtml("qcrule_log_{$key}_{$vote_param}", $diff);	
		$log->addEntry('qc', $title, $msg, array($vote, $row->qc_rev_id, $key)); 
		wfRunHooks("QCVoted", array($wgUser, $title, $vote)); 
	}

	// user skips it, so add this to the stuff they have viewed
	function skip($qcid) {
		self::markQCAsViewed($qcid);	
	}

	// these are specific to the rule that is being used
	abstract public function getPrompt(); 
	abstract public function rollbackChange($qcid);

	// since this is specific to only 1 class, template changes, make it non-abstract and just return true
	function applyChange($qcid) {
		return true;
	}

	function getHeader($t) {
		$html = "<div class='qc_title'>".wfMsg('qc_title_prefix').": <a href='{$t->getFullURL()}' target='new'>" . wfMsg('howto', $t->getText()) . "</a></div>";
		return $html;	
	}
	
	function getChangedBy($action_str, $div_id = 'qc_changedby',$u = null) {
		if ($u == null) {
			//normal use
			$userText = $this->mResult->qc_user_text;
			$u = User::newFromName($userText);
		}
		
		$html = "<div id='{$div_id}' class='qc_by'>{$action_str}";	

		if ($u) {
			$display = $u->getRealName() == "" ? $u->getName() : $u->getRealName();
			$img = "<a target='new' href='{$u->getUserPage()->getFullURL()}' class='tooltip'><img src='".Avatar::getAvatarURL($u->getName())."' /></a>";
			$html .= "{$img} <a target='new' href='{$u->getUserPage()->getFullURL()}'>{$display}</a>";
			$html .= "<span class='tooltip_span'>Hi, I'm {$display}</span>";
			
			//add a Quick Note button for patrols
			if (preg_match('@patrol@',$action_str)) {				
				$t = Title::newFromID($this->mResult->qc_page);
				
				//make and format the Quick Note button
				$qn = QuickNoteEdit::getQuickNoteDiffButton($t, $u, $this->mResult->qc_rev_id, $this->mResult->qc_old_rev_id);
				$class = "";
				
				$html .= preg_replace("@href@",$class." href",$qn);
			}
			
		} else {
			$html .= "<a target='new' href='{$userText}'>{$userText}</a>";
		}	
		$html .= '</div>';
		return $html;	
	}

}

/***********************
 *
 *  An abstract class that groups together some functions that are relevant only to text chagnes
 *  Some rules may not involve text changes (patrolling an edit for example)
 *
***********************/
abstract class QCRuleTextChange extends QCRule {
	var	$mTemplate 	= null;
	var $mRevision	= null;
	var $mLastRevid	= null;

	function __construct($template, $revision, $article) {
		$this->mTemplate	= $template; 
		$this->mRevision	= $revision;
		$this->mArticle		= $article;
	}

	function getLastRevID() {
		if (!$this->mLastRevid) {
			$dbr = wfGetDB(DB_SLAVE); 
			$revid = $this->mRevision->getID();
			$pageid = $this->mRevision->getPage(); 
			$lastrev = $dbr->selectField('revision', 'max(rev_id)', array('rev_page'=>$pageid, 'rev_id<' . $revid));
			if (!$lastrev) return null;
			$this->mLastRevid = $lastrev;
		}
		return $this->mLastRevid;
	}

	function getLastRevisionText() {
		$lastrev = $this->getLastRevID();
		$r = Revision::newFromID($lastrev); 
		if (!$r) return null;
		return $r->getText();	
	}
	
	function getEntryOptions() {
		$opts = array("qc_rev_id" => $this->mRevision->getID());
		$old_rev = $this->getLastRevID();
		if ($old_rev) {
			$opts['qc_old_rev_id'] = $old_rev;
		}
		return $opts;
	}
	
}

/***********************
 *
 *  The rule for when an intro image gets added
 *
***********************/
class QCRuleIntroImage extends QCRuleTextChange {

	function __construct($revision = null, $article = null) {
		$this->mAction = "added";
		$this->mKey			= "changedintroimage";
		parent::__construct($template, $revision, $article);
	}

	function getPart() {
		return "\[\[Image:.*[\|\]]";
	}
	
	function getYesVotesRequired() {
		global $wgQCIntroImageVotesRequired;
		return $wgQCIntroImageVotesRequired["yes"];
	}

	function getNoVotesRequired() {
		global $wgQCIntroImageVotesRequired;
		return $wgQCIntroImageVotesRequired["no"];
	}

	function flagAction() {

		// check for a revision
		if (!$this->mRevision) {
			return false;
		}

		// check the title
		$title = $this->mArticle->getTitle();
		if (!$title || !in_array($title->getNamespace(), $this->mValidNamespaces)) {
			return false;
		}

		$part	  = $this->getPart();
		$oldtext = Article::getSection($this->getLastRevisionText(), 0);
		$newtext = Article::getSection($this->mRevision->getText(), 0);

		//make sure it doesn't have a nointroimg template in it
		if (preg_match('@{{nointroimg}}@im',$newtext)) return false;
		
		$ret = false;
		if ($oldtext == null && $this->hasText($part, $newtext)) {
			$ret = true;
			$this->mAction = "added";
		} elseif ($this->textRemoved($part, $oldtext, $newtext)) {	
			$this->markPreviousAsPatrolled();
		} elseif ($this->textAdded($part, $oldtext, $newtext) || $this->textChanged($part, $oldtext, $newtext)) {
			$ret = true;
			$this->mAction = "added";
		}
	
		wfDebug("QC: intro image added " . print_r($ret, true) . "\n");
		return $ret;
	}

	function getPrompt() {
		return wfMsg('qcprompt_introimage');
	}	

	function rollbackChange($qcid) {
		// remove the intro image from this article
		$t = self::getTitleFromQCID($qcid);
		$r = Revision::newFromTitle($t); 
		if (!$r) {
			return false;
		}
	
		$text = $r->getText(); 
		$intro = Article::getSection($text, 0); 
		
		//make sure the image is still in there
		preg_match("@\[\[Image:[^\]]*\]\]@im", $intro, $matches);
		
		if (sizeof($matches) > 0) {
			$old_rev = self::getRevFromQCID($qcid);
			$old_intro = Article::getSection($old_rev->getText(), 0); 
			
			//make sure the it's not a different image
			if (stripos($old_intro,$matches[0]) === false) {
				return false;
			}
			
			$newintro = preg_replace("@\[\[Image:[^\]]*\]\]@", "", $intro);
		
			$a = new Article($t); 
			$newtext = $a->replaceSection($intro, $newintro);
			if ($a->doEdit($newtext, wfMsg('qc_editsummary_introimage'))) {
				return true;
			}
		}
		return false;
	}

	function getPicture($text) {
		preg_match("@\[\[Image:[^\]]*\]\]@im", $text, $matches);
		$img = "";
		if (sizeof($matches) > 0) {
			$img = preg_replace("@\[\[Image:@", "", $matches[0]);
			$img = preg_replace("@\|.*@", "", $img);
			$img = preg_replace("@\]\]@", "", $img);
			$imgtitle = Title::makeTitle(NS_IMAGE, $img);
			$x = wfFindFile($imgtitle);
			return $x;
		}
		return null;
	}


	function getNextToPatrolHTML() {
		global $wgOut;
		
		if (!$this->mResult) {
			// nothing to patrol
			return null;
		}
		
		// construct the HTML to reply
		// load the page
		$t = Title::newFromID($this->mResult->qc_page);
		if (!$t) {
			$this->deleteBad($this->mResult->qc_page);
			return "<!--{$this->mResult->qc_page}-->error creating title, oops, please <a href='#' onclick='window.location.reload()'>refresh</a>";
		}

		// get current revsion
		$r = Revision::newFromId($this->mResult->qc_rev_id);
		if (!$r) {
			return "Error creating revision";
		}

		// grab the intro image
		$text = $r->getText(); 
		$intro = Article::getSection($text, 0); 

		//ignore if we have a {{nointroimg}} template in there
		$a = new Article($t);
		$templates = $a->getUsedTemplates();
		if (in_array('Template:Nointroimg',$templates)) {
			$this->deleteBad($this->mResult->qc_page);
			return "<!--{$this->mResult->qc_page}--><p></p><p>Intro images have been disabled for this article. Please <a href='#' onclick='window.location.reload()'>refresh</a> for the next article.</p>";
		}
		
		$html = "";
		$changedby = self::getChangedBy("Image added by: ");
		$pic = self::getPicture($intro); 
		if ($pic) {
			//make sure it's not too big
			if ($pic->width > 600) $pic = $pic->getThumbnail(600);
			
			if ($r->getPrevious()) {
				$old = $r->getPrevious()->getID();
			}
			else {
				$old = -1;
			}
			
			$thumbresult['new'] = $r->getID();
			$thumbresult['old'] = $old;
			$thumbresult['title'] = $t;
			
			$pic_width = ((632-$pic->width) /2) + $pic->width - 32; //31px = thumbbutton width

			$thumbs = ThumbsUp::getThumbsUpButton($thumbresult);
			$style = " style='margin-left:".$pic_width."px;'";
			$thumbs = "<div class='qc_changedby_inset'{$style}>{$thumbs}</div>";
			
			$html .= 	"<div id='qc_bigpic'>
						".$thumbs."
						<img class='qc_bigpic_img' src='" . $pic->getURL() . "' width='".$pic->width."' height='".$pic->height."' />
						</div>";
		} else {
			$html .= "<br />" . wfMsg('qc_nothing_found');
		}

		$html = "<div id='quickeditlink'></div>";
		$html .= "<div id='qc_box'>".$changedby.$html."</div>";
		$popts = $wgOut->parserOptions();
		$popts->setTidy(true);
		$magic = WikihowArticleHTML::grabTheMagic($text);
		$html .= WikihowArticleHTML::processArticleHTML($wgOut->parse($text, $t, $popts), array('ns' => $t->getNamespace(), 'magic-word' => $magic));
		$html .= "<input type='hidden' name='qc_id' value='{$this->mResult->qc_id}'/>";
		$html .= "<div id='numqcusers'>{$this->mUsers}</div>";
		return $html;
	}

}

/***********************
 *
 *  The rule for when a video is added, chagned or removed
 *
***********************/
class QCRuleVideoChange extends QCRuleTextChange {
	
	function __construct($revision = null, $article = null) {
		$this->mKey		= "changedvideo";
		$this->mValidNamespaces = array(NS_MAIN, NS_VIDEO);
		parent::__construct($template, $revision, $article);
	}

	function getPart() {
		return "\{\{Video:.*[\|\}]";
	}
	
	function getYesVotesRequired() {
		global $wgQCVideoChangeVotesRequired;
		return $wgQCVideoChangeVotesRequired["yes"];
	}

	function getNoVotesRequired() {
		global $wgQCVideoChangeVotesRequired;
		return $wgQCVideoChangeVotesRequired["no"];
	}

	function flagAction() {

		// check for a revision
		if (!$this->mRevision) {
			return false;
		}

		// check the title
		$title = $this->mArticle->getTitle();
		if (!$title || !in_array($title->getNamespace(), $this->mValidNamespaces)) {
			return false;
		}

		// deal with the situation where the Video: page has been changed
		// TODO: can we narrow it down to just the video changing? probably not. if 
		// a video namespace page has changed, we can assume the video has changed
		if ($title->getNamespace() == NS_VIDEO) {
			if ($this->getLastRevisionText() == null) {
				$this->mAction = "added";
			} 
			// do we already have an entry in the QC log for the main namespace article 
			// for this type of rule? do we need to check? I guess we do.
			$mainTitle = Title::newFromText($title->getText());
			$hasEntry = $this->hasEntry($mainTitle->getArticleID());
			if ($hasEntry) {
				return false;
			}
			$this->mArticle = new Article($mainTitle);
			return true;
		}

		// we may have already put this in for a video namespace edit
		$hasEntry = $this->hasEntry($title->getArticleID());
		if ($hasEntry)  {
			return false; 
		}

		// deal with the situation where the main namespace video has been changed
		$part	  = $this->getPart();
		$oldtext = $this->getLastRevisionText();
		$newtext = $this->mRevision->getText();

#$test = $this->hasText($part, $newtext); echo var_dump($test); exit;
		$ret = false;
		if ($newtext == null && $this->hasText($part, $newtext)) {
			$ret = true;
			$this->mAction = "added";
		} elseif ($this->textRemoved($part, $oldtext, $newtext)) {	
			$this->markPreviousAsPatrolled();
		} elseif ($this->textAdded($part, $oldtext, $newtext) || $this->textChanged($part, $oldtext, $newtext)) {
			$ret = true;
			$this->mAction = "added";
		}
	
		wfDebug("QC: video change " . print_r($ret, true) . "\n");
		return $ret;
	}

	function getPrompt() {
		return wfMsg('qcprompt_video');
	} 

	//returns array with title text and video wikitext
	function getVideoSection($text) {
		$index = 0;
		$vidsection = null;
		while ($section = Article::getSection($text, $index)) {
			if (preg_match("@^==\s*" . wfMsg('video') . "@", $section)) {
				$vidsection = $section;
				$vidname = preg_replace("@^==\s".wfMsg('video')."\s==\s{{([^}]*)\}}@", "$1", $section);
				break;
			}
			$index++;
		}
		
		//format the video name
		if (!empty($vidname)) {
			$parts = split('\|',$vidname);
			$vidname = $parts[0];
		}
		
		if (!empty($vidsection)) {
			$vidresult = array();
			$vidresult['vidtitle'] = self::getVideoTitle($vidname);
			$vidresult['vidsection'] = trim($vidsection);
		}		
		return $vidresult;
	}
	
	//get the title of the video
	function getVideoTitle($text) {
		$videotitletext = '';
		
		$t = Title::newFromText($text);		
		if ($t) {
			$vidrev = Revision::newFromTitle($t);
			
			if ($vidrev) {
				$vidtext = $vidrev->getText(); 
				$parts = split('\|', $vidtext);
				
				if (!empty($parts[3])) {
					$videotitletext = $parts[3];
				}
			}
		}
		return trim($videotitletext);
	}

	function rollbackChange($qcid) {
		// remove the video from this article
		// remove the intro image from this article
		$t = self::getTitleFromQCID($qcid);
		$r = Revision::newFromTitle($t);
		if (!$r) {
			return false;
		}
   
		$text = $r->getText();
		$vidsection = $this->getVideoSection($text);
		if (!$vidsection) {
			return true;
		}

		$a = new Article($t);

		# replace section doesn't work for some reason for the Video section
		$newtext = str_replace($vidsection['vidsection'], "", $text);
		
		if ($a->doEdit($newtext, wfMsg('qc_editsummary_video'))) {
			return true;
		}

		return false;
	}

	function getNextToPatrolHTML() {
		global $wgOut;

		if (!$this->mResult) {
			// nothing to patrol
			return null;
		}

		// construct the HTML to reply
		// load the page
		$t = Title::newFromID($this->mResult->qc_page);
		if (!$t) {
			// is there something we can delete ? 
			$this->deleteBad($this->mResult->qc_page);
			return "<!--{$this->mResult->qc_page}-->error creating title, oops, please <a href='#' onclick='window.location.reload()'>refresh</a>";
		}

		// get current revsion
		$r = Revision::newFromTitle($t);
		if (!$r) {
			return "Error creating revision";
		}

		$vidsection = $this->getVideoSection($r->getText());
		
		$html = "";
		$changedby = self::getChangedBy("Video added by: ");
		
		if (!empty($vidsection)) {
			$html .= "<div id='qc_bigvid'><div class='section_text'>";
			if (!empty($vidsection['vidtitle'])) $html .= "<h3 id='qc_vidtitle'>\"".$vidsection['vidtitle']."\"</h3>";
			$html .= $wgOut->parse($vidsection['vidsection']) . "</div>";
			$html .= "</div>";
		} else {
			$html .= "<br />" . wfMsg('qc_nothing_found');
		} 	
		
		$html = "<div id='qc_box'>".$changedby.$html."</div>";
		$html .= "<div id='quickeditlink'></div>";
		$popts = $wgOut->parserOptions();
		$popts->setTidy(true);
		$magic = WikihowArticleHTML::grabTheMagic($r->getText());
		$html .= WikihowArticleHTML::processArticleHTML($wgOut->parse($r->getText(), $t, $popts), array('no-ads'=>1, 'ns' => $t->getNamespace(), 'magic-word' => $magic));
		$html .= "<input type='hidden' name='qc_id' value='{$this->mResult->qc_id}'/>";
		$html .= "<div id='numqcusers'>{$this->mUsers}</div>";
		return $html;
	}
}

class QCRuleRollback extends QCRule {

	var $mRevision	= null;
	function __construct($revision = null, $article = null) {
		$this->mArticle = $article;
		$this->mRevision = $revision;
		$this->mKey = "rollback";
		$this->mAction = "rollback_edit";
	}

	function flagAction() {
		if ($this->mArticle->getTitle()->getNamespace() == NS_MAIN && 
			preg_match("@Reverted edits@", $this->mRevision->mComment)) {
			return true;
		}
		return false;
	}
	
	function getPrompt() {
		return wfMsg('qcprompt_rollback'); 
	}	

	function getYesVotesRequired() {
		global $wgQCRollbackVotesRequired;
		return $wgQCRollbackVotesRequired["yes"];
	}

	function getNoVotesRequired() {
		global $wgQCRollbackVotesRequired;
		return $wgQCRollbackVotesRequired["no"];
	}

	function getEntryOptions() {
		$dbr = wfGetDB(DB_SLAVE); 
		$opts = array(); 
		$min_rev = $dbr->selectField('revision', array('rev_id'), 
			array('rev_page'=>$this->mArticle->getID(), "rev_id < " . $this->mRevision->mId), 
			"QCRollback::getEntryOptions",
			array("ORDER BY"=>"rev_id desc", "LIMIT"=> 1)
		);
		$opts['qc_old_rev_id'] = $min_rev;
		$opts['qc_rev_id'] = $this->mRevision->mId;
		return $opts;
	}
	
	function getNextToPatrolHTML() {
		global $wgOut;

		if (!$this->mResult) {
			// nothing to patrol
			return null;
		}
		
		// construct the HTML to reply
		// load the page
		$t = $this->mTitle; // Title::newFromID($this->mResult->qc_page);
		if (!$t) {
			$this->deleteBad($this->mResult->qc_page);
			return "<!--{$this->mResult->qc_page}-->error creating title, oops, please <a href='#' onclick='window.location.reload()'>refresh</a>";
		}

		$r = Revision::newFromTitle($t);
		$d = new DifferenceEngine($t, $this->mResult->qc_old_rev_id, $this->mResult->qc_rev_id); 
		$d->loadRevisionData();
		// interesting
		$html = "";
		$changedby = self::getChangedBy("Rollback performed by: ");
		
		$wgOut->clearHTML();
		$d->showDiffPage(true);
		$html = "<div id='qc_box'>".$changedby.$html.$wgOut->getHTML()."</div>";
		$wgOut->clearHTML();
		$html .= "<div id='quickeditlink'></div>";
		$popts = $wgOut->parserOptions();
		$popts->setTidy(true);
		$magic = WikihowArticleHTML::grabTheMagic($r->getText());
		$html .= WikihowArticleHTML::processArticleHTML($wgOut->parse($r->getText(), $t, $popts), array('no-ads'=>1, 'ns' => $t->getNamespace(), 'magic-word' => $magic));
		$html .= "<input type='hidden' name='qc_id' value='{$this->mResult->qc_id}'/>";
		$html .= "<div id='numqcusers'>{$this->mUsers}</div>";
		return $html;
	}

	function rollbackChange($qcid) {
		// try to rollback the last edit if we can
		$dbr = wfGetDB(DB_SLAVE);
		$result = $dbr->selectRow('qc', '*', array('qc_id'=>$qcid));
		$t = self::getTitleFromQCID($qcid);
		$a = new Article($t);
		$last_rev = $a->getRevisionFetched();
		// is this it them most recent revision? if so, we can roll it back
		wfDebug("GOT " . print_r($a, true) . " {$last_rev->mId} vs {$result->qc_rev_id}\n");
		if ($last_rev->mId == $result->qc_rev_id) {
			$a->commitRollback($last_rev->mUserText, wfMsg('qc_editsummary_rollback', $last_rev->mUserText), false, $result);
		}
		return true;
	}
}

class QCRCPatrol extends QCRule {

	var $mRcids = null; 
	
	function __construct($article = null, $rcids = null) {
		$this->mArticle = $article;
		$this->mRcids = $rcids;
		$this->mKey	= "rcpatrol";
	}
	
	function flagAction() {
		global $wgMemc, $wgUser;
		$dbr = wfGetDB(DB_SLAVE);
		$key = wfMemcKey("patrolcount", $wgUser->getID());
		$count = (int)$wgMemc->get($key);
		if (!$count) {
			$count = $dbr->selectField('logging', 'count(*)', array('log_type'=>'patrol', 'log_user'=>$wgUser->getID()));	
			$wgMemc->set($key, $count, 3600);
		}
		// did this user recently revert this page? if so, let's not do this
		// because they patrol a shitty edit, but it's ok because they reverted it!
		$old = wfTimestamp(TS_MW, time() - 10*60); 
		$revert = $dbr->selectField('recentchanges', array('count(*)'), 
			array('rc_user'=>$wgUser->getID(), '(rc_comment like "Reverted edits%" OR rc_comment = "Quick edit while patrolling")', 
				'rc_cur_id'=>$this->mArticle->getTitle()->getArticleID())
			);
		if ($revert > 0) {
			return false;
		}

		// now, let's filter based on how much patrolling experience the user has
		// todo: could throw this in a global maybe? 
		$logqc = false;
		if ($count < 500) {
			$logqc = true;
		} elseif ($count >= 500 && $count < 1500 && rand(0,99) <= 60) {
			$logqc = true;
		} elseif (rand(0, 99) <= 2) {
			$logqc = true;
		}

		#debug $logqc = true;
		return $logqc; 
	}
	
	function getPrompt() {
		return wfMsg('qcprompt_rcpatrol'); 
	}	

	function getYesVotesRequired() {
		global $wgQCRCPatrolVotesRequired;
		return $wgQCRCPatrolVotesRequired["yes"];
	}

	function getNoVotesRequired() {
		global $wgQCRCPatrolVotesRequired;
		return $wgQCRCPatrolVotesRequired["no"];
	}

	function getEntryOptions() {
		// get the old and new rev_id based on rcids 
		$dbr = wfGetDB(DB_SLAVE); 
		$opts = array(); 
		$min_rev = $dbr->selectField('recentchanges', array('rc_last_oldid'), array('rc_id'=>min($this->mRcids)));
		$max_rev = $dbr->selectField('recentchanges', array('rc_this_oldid'), array('rc_id'=>max($this->mRcids)));
		$opts['qc_old_rev_id'] = $min_rev;
		$opts['qc_rev_id'] = $max_rev;
		$opts['qc_extra'] = min($this->mRcids) . "," . max($this->mRcids);
		return $opts;
	}
	
	function rollbackChange($qcid) {
		$dbw = wfGetDB(DB_MASTER); 
		$row = $dbw->selectRow('qc', array('*'), array('qc_id'=>$qcid));
		$t = Title::newFromID($row->qc_page);
		if (!$t) {
			return false;
		}

		$rcids = split(",", $row->qc_extra); 
		$dbw->update('recentchanges', array('rc_patrolled'=>0), 
			array('rc_cur_id'=>$t->getArticleID(), 'rc_id <= ' . $rcids[1], 'rc_id >= ' . $rcids[0])); 

		return true;
	}
	
	function getNextToPatrolHTML() {
		global $wgOut;

		if (!$this->mResult) {
			// nothing to patrol
			return null;
		}
		
		// construct the HTML to reply
		// load the page
		$t = $this->mTitle; // Title::newFromID($this->mResult->qc_page);
		if (!$t) {
			$this->deleteBad($this->mResult->qc_page);
			return "<!--{$this->mResult->qc_page}-->error creating title, oops, please <a href='#' onclick='window.location.reload()'>refresh</a>";
		}

		// get current revsion
		$r = Revision::newFromTitle($t);
		if (!$r) {
			return "Error creating revision";
		}
		
		$d = new DifferenceEngine($t, $this->mResult->qc_old_rev_id, $this->mResult->qc_rev_id); 
		$d->loadRevisionData();
		// interesting
		$html = "";
		$changedby = self::getChangedBy("Edits patrolled by: ");
		
		$wgOut->clearHTML();
		$d->showDiffPage(true);
		$html = "<div id='qc_box'>".$changedby.$html.$wgOut->getHTML()."</div>";
		$wgOut->clearHTML();
		$html .= "<div id='quickeditlink'></div>";
		$popts = $wgOut->parserOptions();
		$popts->setTidy(true);
		$magic = WikihowArticleHTML::grabTheMagic($r->getText());
		$html .= WikihowArticleHTML::processArticleHTML($wgOut->parse($r->getText(), $t, $popts), array('no-ads'=>1, 'ns' => $t->getNamespace(), 'magic-word' => $magic));
		$html .= "<input type='hidden' name='qc_id' value='{$this->mResult->qc_id}'/>";
		$html .= "<div id='numqcusers'>{$this->mUsers}</div>";
		return $html;
	}
}

/***********************
 *
 *  The rule for additions/removal of templates like stub and copyedit
 *
***********************/
class QCRuleTemplateChange extends QCRuleTextChange {

	function __construct($template, $revision = null, $article = null) {
		parent::__construct($template, $revision, $article);
		$this->mKey	= "changedtemplate_" . strtolower($this->mTemplate); 
	}

	function getPart() {
		return "\{\{" . $this->mTemplate;
	}

	function flagAction() {

		// check for a revision
		if (!$this->mRevision) {
			return false;
		}

		// check the title
		$title = $this->mArticle->getTitle();
		if (!$title || !in_array($title->getNamespace(), $this->mValidNamespaces)) {
			return false;
		}

		$part	 = $this->getPart();
		$oldtext = $this->getLastRevisionText();
		$newtext = $this->mRevision->getText();

		$ret = false;
		if ($this->textRemoved($part, $oldtext, $newtext)) {	
			$ret = true;
			$this->mAction = "removed";
		} elseif ($this->textAdded($part, $oldtext, $newtext)) {
			$ret = true;
			$this->mAction = "added";
		}

		wfDebug("QC: template change " . print_r($ret, true) . "\n");
		return $ret;
	}

	function getYesVotesRequired() {
		global $wgTemplateChangedVotesRequired;
		return $wgTemplateChangedVotesRequired[$this->mAction]["yes"];
	}

	function getNoVotesRequired() {
		global $wgTemplateChangedVotesRequired;
		return $wgTemplateChangedVotesRequired[$this->mAction]["no"];
	}

	function getNextToPatrolHTML() {
		global $wgOut;

		if (!$this->mResult) {
			// nothing to patrol
			return null;
		}

		// construct the HTML to reply
		// load the page
		$t = Title::newFromID($this->mResult->qc_page); 
		if (!$t) {
			$this->deleteBad($this->mResult->qc_page);
			return "<!--{$this->mResult->qc_page}-->error creating title, oops, please <a href='#' onclick='window.location.reload()'>refresh</a>";
		}

		// get current revsion
		$r = Revision::newFromTitle($t); 
		if (!$r) {
			return "Error creating revision";
		}
		
		$changedby = self::getChangedBy("Template " . $this->mResult->qc_action . " by: ");

		$html = "<div id='quickeditlink'></div>";
		$html .= "<div id='qc_box'>".$changedby.$html."</div>";
		$popts = $wgOut->parserOptions();
		$popts->setTidy(true);
		$magic = WikihowArticleHTML::grabTheMagic($r->getText());
		$html .= WikihowArticleHTML::processArticleHTML($wgOut->parse($r->getText(), $t, $popts), array('no-ads'=>1, 'ns' => $t->getNamespace(), 'magic-word' => $magic));
		$html .= "<input type='hidden' name='qc_id' value='{$this->mResult->qc_id}'/>";
		$html .= "<div id='numqcusers'>{$this->mUsers}</div>";
		return $html;
	}
	
	function getPrompt() {
		return wfMsg('qcprompt_template', preg_replace("@changedtemplate_@", "", $this->getKey()));
	}	

	// in this case, we want to apply the template to the page because it's been voted "yes" on
	function applyChange($qcid) {
		$dbr = wfGetDB(DB_SLAVE); 

		// load the revision text
		$pageid = $dbr->selectField('qc', array('qc_page'), array('qc_id'=> $qcid));
		$t = Title::newFromID($pageid);
		if (!$t) {
			return false;
		}

		$r = Revision::newFromTitle($t); 
		if (!$r) {
			return false;
		}

		$text = $r->getText(); 
		if (preg_match("@\{\{" . $this->mTemplate . "@", $text)) {
			return true;
		} 

		// add the template  since it doesn't already have it
		$a = new Article($t);
		$text = "{{{$this->mTemplate}}}" . $text;
		return $a->doEdit($text, wfMsg('qc_editsummary_template_add', $this->mTemplate));
	}

	function rollbackChange($qcid) {
		// roll back the chagne from the db
		$dbr = wfGetDB(DB_SLAVE); 

		// load the revision text
		$pageid = $dbr->selectField('qc', array('qc_page'), array('qc_id'=> $qcid));
		$t = Title::newFromID($pageid);
		if (!$t) {
			return false;
		}

		$r = Revision::newFromTitle($t); 
		if (!$r) {
			return false;
		}

		$text = $r->getText(); 
		$text = preg_replace("@\{\{" . $this->mTemplate . "[^\}]*\}\}@U", "", $text); 

		$a = new Article($t);
		return $a->doEdit($text, wfMsg('qc_editsummary_template', $this->mTemplate));
	}
}


/***********************
 *
 *  The rule for tips patrol
 *
***********************/
class QCRuleTip extends QCRule {

	var $mTipId = null;
	
	function __construct($article = null, $tipId = null) {
		$this->mArticle = $article;
		$this->mTipId = $tipId;
		$this->mKey	= "newtip";
		$this->mAction = "added";
	}
	
	function flagAction() {		
		// check the title
		$title = $this->mArticle->getTitle();
		if (!$title || !in_array($title->getNamespace(), $this->mValidNamespaces)) {
			return false;
		}
		
		return true;
	}
	
	function getPrompt() {
		return wfMsg('qcprompt_newtip'); 
	}	

	function getYesVotesRequired() {
		global $wgQCRCPatrolVotesRequired;
		return $wgQCRCPatrolVotesRequired["yes"];
	}

	function getNoVotesRequired() {
		global $wgQCRCPatrolVotesRequired;
		return $wgQCRCPatrolVotesRequired["no"];
	}

	function getEntryOptions() {
		// get the tip ID
		$opts = array(); 
		$opts['qc_extra'] = $this->mTipId;
		return $opts;
	}
	
	function rollbackChange($qcid) {
		//bad tip!
		$dbr = wfGetDB(DB_SLAVE);
		$tipid = $dbr->selectField('qc', array('qc_extra'), array('qc_id'=> $qcid));
		
		//use TipsPatrol's function
		TipsPatrol::deleteTipFromLog($tipid);
		return true;
	}
	
	function applyChange($qcid) {
		//grab tip data from tip id
		$dbr = wfGetDB(DB_SLAVE);		
		$res = $dbr->select('qc', array('qc_extra','qc_user'), array('qc_id' => $qcid), __METHOD__);
		foreach ($res as $row) {
			$tipId = $row->qc_extra;
			$tipUserId = $row->qc_user;
		}

		$tipData = TipsPatrol::getTipData($tipId, $tipUserId);
		$tipText = $tipData['tw_tip'];
		$tipPage = $tipData['tw_page'];
		
		//use TipsPatrol's functions
		$res = TipsPatrol::keepTip($tipId, $tipPage, $tipText);
		if ($res) {
			TipsPatrol::deleteTipFromLog($tipId);
		}		
		return $res;
	}
	
	function getNextToPatrolHTML() {
		global $wgOut;
		
		if (!$this->mResult) {
			// nothing to patrol
			return null;
		}
		
		// construct the HTML to reply
		// load the page
		$t = $this->mTitle; // Title::newFromID($this->mResult->qc_page);
		if (!$t) {
			$this->deleteBad($this->mResult->qc_page);
			return "<!--{$this->mResult->qc_page}-->error creating title, oops, please <a href='#' onclick='window.location.reload()'>refresh</a>";
		}

		// get current revsion
		$r = Revision::newFromTitle($t);
		if (!$r) return "Error creating revision";
		
		$html = "";
		
		//grab all that good tip stuff
		$tipData = TipsPatrol::getTipData($this->mResult->qc_extra, $this->mResult->qc_user);
		$the_tip = $tipData['tw_tip'];
		$tip_page = $tipData['tw_page'];

		$approvedby = self::getChangedBy("Tip approved by: ","qc_approvedby",$tip_user);
		
		$html = '<h3>New Tip</h3><br />'.
				'<div class="wh_block"><ul><li>'.$the_tip.'</li></ul></div>';
		
		$html = "<div id='qc_box'>".$addedby.$approvedby.$html."</div>";
		$wgOut->clearHTML();
		$html .= "<div id='quickeditlink'></div>";
		$popts = $wgOut->parserOptions();
		$popts->setTidy(true);
		$magic = WikihowArticleHTML::grabTheMagic($r->getText());
		$html .= WikihowArticleHTML::processArticleHTML($wgOut->parse($r->getText(), $t, $popts), array('no-ads'=>1, 'ns' => $t->getNamespace(), 'magic-word' => $magic));
		$html .= "<input type='hidden' name='qc_id' value='{$this->mResult->qc_id}'/>";
		$html .= "<div id='numqcusers'>{$this->mUsers}</div>";
		return $html;
	}
}


/***********************
 *
 *  The admin page for dealing with entries in the QC queue
 *
***********************/
class QG extends SpecialPage {

	function __construct() {
		parent::__construct( 'QG' );
	}

	function getUnfinishedCount(&$dbr){
		//$count = $dbr->selectField('qc', 'count(*) as C', array('qc_patrolled' => 0, 'qc_key IN ("changedintroimage","changedvideo")'));
		$count = $dbr->selectField('qc', 'count(*) as C', array('qc_patrolled' => 0, 'qc_key IN ("changedvideo")'));

		return $count;
	}

	function getQuickEditLink($title) {
		if ($title) {
			$url = $title->getFullText();
		}
		$editURL = Title::makeTitle(NS_SPECIAL, 'QuickEdit')->getFullURL() . '?type=editform&target=' . urlencode($url);
		$class = "class='button secondary buttonright'";
		$link =  "<a title='" . wfMsg("Editold-quick") . "' accesskey='e' href='' $class onclick=\"return initPopupEdit('".$editURL."') ;\">" .
			htmlspecialchars( wfMsg( 'Editold-quick' ) ) . "</a> ";
		return $link;
	}

	function getSubmenu() {
		$menu = "<div id='qg_submenu'><div id='qg_options'></div></div>";

		return $menu;
	}
	
	function getButtons() {
		$buttons =	"<div id='qc_head' class='tool_header'>
						<h1 id='question'></h1>
						<a href='#' class='button secondary' id='qc_skip'>".wfMsg('qc_skip_article')."</a>
						<a href='#' class='button primary' id='qc_yes'>Yes</a>
						<a href='#' class='button secondary' id='qc_no'>No</a>
						<div class='clearall'></div>
					</div>
					<input type='hidden' id='qcrule_choices' value='' />";
		return $buttons;
	}
	
	function getNextInnards($qc_type,$by_username) {
		// grab the next check
		$result = array(); 
		
		$c = QCRule::getNextToPatrol($qc_type,$by_username); 
		if ($c)  {
			// qc_vote, qc_skip
			$result['title'] 		= "<a href='{$c->mTitle->getLocalURL()}'>{$c->mTitle->getText()}</a>";
			$result['question'] 	= $c->getPrompt();
			$result['qctabs']		= $this->getTabs($qc_type);
			$result['choices' ]		= $this->getSubmenu();
			$result['buttons']		= $this->getButtons();
			$result['quickedit'] 	= $this->getQuickEditLink($c->mTitle); 
			$result['html'] 		= $c->getNextToPatrolHTML();
			$result['qc_id'] 		= $c->mResult->qc_id;
			$result['sql']			= $c->sql;
		} else {
			$result['done'] 		= 1;
			$result['title'] 		= wfMsg('quality_control');
			$result['qctabs']		= $this->getTabs($qc_type);
			$result['msg'] 			= "<div id='qg_submenu'><div id='qg_options'></div></div>
										<div id='qc_head' class='tool_header'>
										<p class='qc_alldone'>".wfMsg('qc_congrats')."</p>
										<p>".wfMsg('qc_congrats_2')."</p>
										</div>
										<div id='qc_box'></div>";
		}
		return $result;
	} 
	
	// generate the HTML for the rule selector checkboxes
	function getOptionMenu($menu_name,$chosen,$username) {	
		global $wgQCRulesToCheck,$wgUser;
		
		if ($menu_name == 'options') {
			//options menu
			$rules = $wgQCRulesToCheck;
			if (in_array('RCPatrol',$rules) && !in_array('sysop',$wgUser->getGroups())) {
				$rules = array_diff($rules,array('RCPatrol'));
			}
			
			$html = "<a href='#' class='button secondary' id='qcrules_submit'>Done</a>";
			$html .= '<div>';
			foreach ($rules as $key => $rule) {
				if (count($rules)/2 <= $key) $html .= '</div>';
				(preg_match("@{$rule}@i", $chosen) or empty($chosen)) ? $checked = true : $checked = false;
				//hack for unchecking the first RCPatrol view
				if (empty($chosen) and $rule == 'RCPatrol') $checked = false;
				$html .= '<p>'. Xml::checkLabel(wfMsg('qcrule_' . strtolower($rule)),'qcrule_choice','qcrule_' . strtolower($rule),$checked) .'</p>';
			}
			$html .= "</div>";
		}
		else {
			//QG by user
			$html = 'Username: ' . 
					Xml::input('qg_byuser_input',30,$username,array('type'=>'text','id'=>'qg_byuser_input')) .
					'<div id="qg_byuser_buttons">
						<a id="qg_byuser_off" class="button secondary" href="#">Off</a>
						<a id="qg_byuser_go" class="button secondary" href="#">Go</a>
					</div>';
		}

		return $html;
	}
	
	//tabs for options and checkboxes
	function getTabs($qc_type) {
	
		$html = "<div id='qg_tabs' class='tool_options_link'>
					<a href='#' id='qgtab_byuser'>" . wfMsg('qc_byuser') . "</a>
					<a href='#' id='qgtab_options'>" . wfMsg('qc_rulestocheck') . "</a>
				</div>";
	
		return $html;
	}

	//formatted sidenav box for QG voting
	function getVoteBlock($qc_id) {
		$dbr = wfGetDB(DB_SLAVE);
		$res = $dbr->select('qc', array('qc_yes_votes_req','qc_no_votes_req','qc_key'), array('qc_id' => $qc_id), 'QG::getVoteBlock');
		$row = $dbr->fetchObject($res);
		
		$html .= self::getYesNoVotes($qc_id, $row->qc_yes_votes_req, $row->qc_no_votes_req, $row->qc_key);

		return $html;
	}
	
	//get the yes/no boxes for voters
	function getYesNoVotes($qc_id, $req_y, $req_n, $qc_key) {
		$t = QCRule::getTitleFromQCID($qc_id);
		$link = "<a href='{$t->getFullURL()}' target='new'>" . wfMsg('howto', $t->getText()) . "</a>";
	
		$yes = array();
		$no = array();
		$status = '';
		
		$dbr = wfGetDB(DB_SLAVE);
		$res = $dbr->select('qc_vote', array('qcv_user','qcv_vote'), array('qcv_qcid' => $qc_id), 'QG::getVoteBlock', array('ORDER BY' => 'qcv_vote DESC'));
				
		while($row = $dbr->fetchObject($res)){
			if ($row->qcv_vote == '1')
				array_push($yes,$row->qcv_user);
			else
				array_push($no,$row->qcv_user);
		}
		
		$html .= "<div id='qc_vote_1'><div class='qc_vote_head'>Yes Votes</div>";
		
		//get yes boxes
		for ($i=0; $i<($req_y); $i++) {			
			$html .= self::getAvatar($yes[$i]);
		}
		
		$html .= "</div><div id='qc_vote_2'>";
		
		//get left arrow
		if ($req_y == count($yes)) {
			$html .= "<div class='qc_arrow qc_left_win'></div>";
			$status = 'approved';
		}
		else {
			$html .= "<div class='qc_arrow qc_left'></div>";
		}
		//get right arrow
		if ($req_n == count($no)) {
			$html .= "<div class='qc_arrow qc_right_win'></div>";
			$status = 'removed';
		}
		else {
			$html .= "<div class='qc_arrow qc_right'></div>";
		}
		$html .= "</div><div id='qc_vote_3'><div class='qc_vote_head qc_head_no'>No Votes</div>";
		
		//get no boxes
		for ($i=0; $i<($req_n); $i++) {		
			$html .= self::getAvatar($no[$i]);	
		}
		$html .= '</div>';
		
		if (($status == '') && (count($yes) == count($no))) {
			$status = 'tie';
		}
		
		//grab main image
		$img = "<div class='qc_vote_img qc_img_$status'></div>";
		
		//grab upper text
		if (($status == 'approved') || ($status == 'removed')) {
			$text = wfMsg('qcrule_'.$qc_key).' '.wfMsg('qcvote_'.$status);
		}
		else {
			$text = wfMsg('qcvote_'.$status);
		}
		
		//format the top part
		$top = "<div id='qc_vote_text'>$img<p class='first'>$text $link</p></div>";
		
		//add it all up
		$html = "$top<div id='qc_votes'>$html</div>";
		
		return $html;
	}
	
	function getAvatar($user_id) {
		if ($user_id) {
			$u = new User();
			$u->setID($user_id);

			$img = Avatar::getAvatarURL($u->getName());
			if ($img == '') {
				$img = Avatar::getDefaultPicture();
			}
			else {
				$img = "<img src='$img' />";
			}
			$avatar = "<div class='qc_avatar'><a href='{$u->getUserPage()->getFullURL()}' target='_blank' class='tooltip'>{$img}</a>";
			$avatar .= "<span class='tooltip_span'>Hi, I'm {$u->getName()}</span></div>";
		}
		else {
			$avatar = "<div class='qc_emptybox'></div>";
		}
		return $avatar;
	}

	function execute($par) {
		global $wgUser, $wgOut, $wgRequest;

		if ($wgUser->isBlocked()) {
			$wgOut->blockedPage();
			return;
		}

		if ($wgUser->getID() == 0) {
			$wgOut->setRobotpolicy( 'noindex,nofollow' );
			$wgOut->showErrorPage( 'nosuchspecialpage', 'nospecialpagetext' );
			return;
		}
		
		wfLoadExtensionMessages("QG"); 

		if ($wgRequest->getVal('fetchInnards')) {
			$wgOut->disable(); 
			header('Vary: Cookie' );
			$result = self::getNextInnards($wgRequest->getVal('qc_type'),$wgRequest->getVal('by_username'));
			print_r(json_encode($result));
			return;
			
		} elseif ($wgRequest->getVal('getOptions')) {
			$wgOut->setArticleBodyOnly(true);
			$wgOut->addHTML(self::getOptionMenu($wgRequest->getVal('menuName'),$wgRequest->getVal('choices'),$wgRequest->getVal('username')));
			return;
			
		} elseif ($wgRequest->getVal('getVoteBlock')) {
			$wgOut->setArticleBodyOnly(true);
			$wgOut->addHTML(self::getVoteBlock($wgRequest->getVal('qc_id')));
			return;
			
		} elseif ($wgRequest->wasPosted()) {
			if ($wgRequest->getVal('qc_skip', 0) == 1) {
				QCRule::skip($wgRequest->getVal('qc_id'));				
			} else {
				QCRule::vote($wgRequest->getVal('qc_id'), $wgRequest->getVal('qc_vote'));				
			}
			$wgOut->disable(); 
			$result = self::getNextInnards($wgRequest->getVal('qc_type'),$wgRequest->getVal('by_username'));
			header('Vary: Cookie' );
			print_r(json_encode($result));
			return;
		}

		/** 
		 * This is the shell of the page, has the buttons, etc. 
		 */ 
		$wgOut->setHTMLTitle('Quality Guardian');
		$wgOut->addJScode('qcj');
		$wgOut->addCSScode('qcc');
		$wgOut->addCSScode('diffc');
		$wgOut->addHTML(QuickNoteEdit::displayQuickEdit() . QuickNoteEdit::displayQuickNote(true));
		$wgOut->setHTMLTitle(wfMsg('quality_control'));
		$wgOut->setPageTitle(wfMsg('quality_control'));
		
		// add standings widget
		$group= new QCStandingsGroup();
		$indi = new QCStandingsIndividual();
		
		$indi->addStatsWidget(); 
		$group->addStandingsWidget();
	}
   
}

class NoVotesAgainst extends UnlistedSpecialPage {

	function __construct() {
		parent::__construct( 'NoVotesAgainst' );
	}

	function execute() {
		global $wgOut;
		$dbr = wfGetDB(DB_MASTER);
		$wgOut->addHTML("<h2>Top Users with No Votes for Rollback Edits</h2>");
		$res = $dbr->query("SELECT qc_user_text, count(*) as C from qc 
			left join qc_vote on qc_id=qcv_qcid
			WHERE qc_key='rollback' and qcv_vote=0
			GROUP BY qc_user_text ORDER BY C desc limit 50;");
		$wgOut->addHTML("<ul>");
		while ($row = $dbr->fetchObject($res)) {
			$wgOut->addHTML("<li>{$row->qc_user_text} - {$row->C} No votes\n</li>");
		}
		$wgOut->addHTML("</ul>");
	}

}

