<?

if (!defined('MEDIAWIKI')) exit;

class PostComment extends UnlistedSpecialPage {

	var $revId = null;

	function __construct() {
		parent::__construct( 'PostComment' );
	}

	function getForm($new_window = false, $title = null, $return_result = false) {
		$postbtn = " class= 'button primary' ";
		$prevbtn = " class= 'button secondary' ";
			
		if ($title == null)
			$title = $this->getTitle();

		if (!$title->userCan('edit', $this->getUser())) {
			return;
		}

		if ( !$this->getUser()->isAllowed('edit') ) {
			return;
		}

		$action = $this->getRequest()->getVal('action');

		// Only allow this extension on talk pages
		if (!$title->isTalkPage() || $action || $this->getRequest()->getVal('diff'))
			return;

		if (!$title->userCan('edit')) {
			echo  wfMsg('postcomment_discussionprotected');
			return;
		}

		$sk = $this->getSkin();

		$user_str = "";
		if ($this->getUser()->getID() == 0) {
			$user_str = wfMsg('postcomment_notloggedin');
		} else {
			$link = $sk->makeLinkObj($this->getUser()->getUserPage(), $this->getUser()->getName());
			$user_str = wfMsg('postcomment_youareloggedinas', $link);
		}

		$msg = wfMsg('postcomment_addcommentdiscussionpage');
		$previewPage = Title::makeTitle(NS_SPECIAL, "PostCommentPreview");
		$me = Title::makeTitle(NS_SPECIAL, "PostComment");

		$pc = Title::newFromText("PostComment", NS_SPECIAL);
		if ($title->getNamespace() == NS_USER_TALK)
			$msg = wfMsg('postcomment_leaveamessagefor',$title->getText());

		$id = rand(0, 10000);
		$newpage = $title->getArticleId() == 0 ? "true" : "false";

		$fc = null;
		$pass_captcha = true;
		if ($this->getUser()->getID()== 0) {
			 $fc = new FancyCaptcha();
		}
	   $result = "<div id='postcomment_newmsg_$id'></div>
			<script type='text/javascript'>
				var gPreviewText = \"" . wfMsg('postcomment_generatingpreview') . "\";
				var gPreviewURL = \"{$previewPage->getFullURL()}\";
				var gPostURL = \"{$me->getFullURL()}\";
				var gPreviewMsg = \"" . wfMsg('postcomment_previewmessage') . "\";
				var gNewpage = {$newpage};
			</script>
			<script type='text/javascript' src='" . wfGetPad('/extensions/min/f/extensions/wikihow/PostComment/postcomment.js?') . WH_SITEREV . "'></script>
			<div id='postcomment_progress_$id' style='display:none;'><center><img src='" . wfGetPad('/extensions/wikihow/PostComment/upload.gif') . "' alt='Sending...'/></center></div>
			";

		// Include google analytics tracking (gat)
		if ($this->getTitle()->getNamespace() == NS_TALK) {
			$result .= "<form id=\"gatDiscussionPost\" name=\"postcommentForm_$id\" method=\"post\" action=\"{$pc->getFullURL()}\" " . ($new_window ? "target='_blank'" :"") ." onsubmit='return postcommentPublish(\"postcomment_newmsg_$id\", document.postcommentForm_$id);'>" ;
		} else if ($this->getTitle()->getNamespace() == NS_USER_TALK) {
			$result .= "<form id=\"gatTalkPost\" name=\"postcommentForm_$id\" method=\"post\" action=\"{$pc->getFullURL()}\" " . ($new_window ? "target='_blank'" :"") ." onsubmit='return postcommentPublish(\"postcomment_newmsg_$id\", document.postcommentForm_$id);'>" ;
		} else {
			$result .= "<form name=\"postcommentForm_$id\" method=\"post\" action=\"{$pc->getFullURL()}\" " . ($new_window ? "target='_blank'" :"") ." onsubmit='return postcommentPublish(\"postcomment_newmsg_$id\", document.postcommentForm_$id);'>" ;
		}

		$avatar = Avatar::getAvatarURL($this->getUser()->getName());
		if ($avatar) $user_icon = 'background-image: url('.$avatar.')';
		
		$result .= "
			<input name=\"target\" type=\"hidden\" value=\"" . htmlspecialchars($title->getPrefixedDBkey()) . "\"/>
			<a name=\"postcomment\"></a>
			<a name=\"post\"></a>
			<textarea class=\"postcommentForm_textarea\" tabindex='3' rows='15' cols='100' name=\"comment_text_$id\" id=\"comment_text_$id\" placeholder=\"$msg\" style=\"$user_icon\"></textarea>
			<div class=\"postcommentForm_buttons\">
				<input tabindex='4' type='button' onclick='postcommentPreview(\"$id\");' value=\"".wfMsg('postcomment_preview')."\" {$prevbtn} />
				<input tabindex='5' type='submit' value=\"".wfMsg('postcomment_post')."\" id='postcommentbutton_{$id}' {$postbtn} />
			</div>
			<div class=\"postcommentForm_details\">
				$user_str
				"  . ($pass_captcha ? "" : "<br><br/><font color='red'>Sorry, that phrase was incorrect, try again.</font><br/><br/>") . "
				" . ($fc == null ? "" : $fc->getForm('') ) . "
			</div>
			</form>
			<div id='postcomment_preview_$id' class='postcomment_preview'></div>
			";
		
		//add anchor link
		$return = '<a name="leave-a-message" id="leave-a-message"></a>';
		
		if ($return_result)
			return $result;
		else
			echo $result;
	}

	function execute($par) {
		$this->writeOutput($par);

		if ($this->getRequest()->getVal('jsonresponse') == 'true') {
			$this->getRequest()->response()->header('Content-type: application/json');
			$this->getOutput()->disable();
			echo json_encode( array( 'html' => $this->getOutput()->getHTML(),
									'revId' => $this->revId ) );
		}
	}

	function writeOutput($par) {
		global $wgLang, $wgMemc, $wgDBname, $wgUser;
		global $wgSitename, $wgLanguageCode;
		global $wgFeedClasses, $wgFilterCallback, $wgWhitelistEdit, $wgParser;

		$this->getOutput()->setRobotpolicy( "noindex,nofollow" );

		$target = !empty($par) ? $par : $this->getRequest()->getVal("target");
		$t = Title::newFromDBKey($target);
		$update = true;

		if (!$t || !$t->userCan('edit')) {
			return;
		}

		if ( !$this->getUser()->isAllowed('edit') ) {
			return;
		}

		$article = new Article($t);

		$user = $this->getUser()->getName();
		$real_name = User::whoIsReal($this->getUser()->getID());
		if ($real_name == "") {
			$real_name = $user;
		}
		$dateStr = $wgLang->timeanddate(wfTimestampNow());

		$comment = $this->getRequest()->getVal("comment_text");
		foreach ($this->getRequest()->getValues() as $key=>$value) {
			if (strpos($key, "comment_text") === 0) {
				$comment = $value;
				break;
			}
		}
		$topic = $this->getRequest()->getVal("topic_name");

		//echo "$dateStr<br/>";

		// remove leading space, tends to be a problem with a lot of talk page comments as it breaks the
		// HTML on the page
		$comment = preg_replace('/\n[ ]*/', "\n", trim($comment));

		// Check to see if the user is also getting a thumbs up. If so, append the thumbs message and give a thumbs up
		if ($this->getRequest()->getVal('thumb')) {
			$comment .= "\n\n" . wfMsg('qn_thumbs_up');
			$userName = explode(":", $this->getRequest()->getVal('target'));
			ThumbsUp::quickNoteThumb($this->getRequest()->getVal('revold'), $this->getRequest()->getVal('revnew'), $this->getRequest()->getVal('pageid'), $userName[1]);
		}

		$formattedComment = wfMsg('postcomment_formatted_comment', $dateStr, $user, $real_name, $comment);

		if ($this->getRequest()->getVal('fromajax') == 'true') {
			$this->getOutput()->setArticleBodyOnly(true);
		}
		$text = "";
		$r = Revision::newFromTitle($t);
		if ($r) {
			$text = $r->getText();
		}

		$text .= "\n\n$formattedComment\n\n";
		$this->getOutput()->setStatusCode(409);

		//echo "updating with text:<br/> $text";
		//exit;
		$tmp = "";
		if ( $this->getUser()->isBlocked() ) {
			$this->getOutput()->blockedPage();
			return;
		}
		if ( !$this->getUser()->getID() && $wgWhitelistEdit ) {
			$this->userNotLoggedInPage();
			return;
		}
		if ( wfReadOnly() ) {
			$this->getOutput()->readOnlyPage();
			return;
		}

		if ($target == "Spam-Blacklist") {
			$this->getOutput()->readOnlyPage();
			return;
		}

		if ( $this->getUser()->pingLimiter() ) {
			$this->getOutput()->rateLimited();
			return;
		}

		$editPage = new EditPage($article);
		$contentModel = $t->getContentModel();
		$handler = ContentHandler::getForModelID( $contentModel );
		$contentFormat = $handler->getDefaultFormat();
		$content = ContentHandler::makeContent( $text, $t, $contentModel, $contentFormat );
		$status = Status::newGood();
		if (!wfRunHooks('EditFilterMergedContent', array($this->getContext(), $content, &$status, '', $wgUser, false))) {
			return;	
		}
		if (!$status->isGood()) {
			$errors = $status->getErrorsArray(true);
			foreach ($errors as $error) {
				if (is_array($error)) {
					$error = count($error) ? $error[0] : '';
				}
				if (preg_match('@^spamprotection@', $error)) {
					$message = 'Error: found spam link';
					$this->getOutput()->addHTML( $message );
					return;
				}
			}
			$message = 'EditFilterMergedContent returned an error -- cannot post comment';
			return;
		}

		$matches = array();
		$preg = "/http:\/\/[^] \n'\">]*/";
		$mod = str_ireplace('http://www.wikihow.com', '', $comment);
		preg_match_all($preg, $mod, $matches);

		if (sizeof($matches[0] ) > 2) {
			$this->getOutput()->showErrorPage("postcomment", "postcomment_urls_limit");
			return;
		}

		if (trim(strip_tags($comment)) == ""  ) {
			$this->getOutput()->showErrorPage( "postcomment", "postcomment_nopostingtoadd");
			return;
		}

		if ( !$t->userCan('edit')) {
		   $this->getOutput()->showErrorPage( "postcomment", "postcomment_discussionprotected");
		   return;
		}

		$watch = false;
		if ($this->getUser()->getID() > 0) {
		   $watch = $this->getUser()->isWatched($t);
		}

		$fc = new FancyCaptcha();
		$pass_captcha = $fc->passCaptcha();

		if(!$pass_captcha && $this->getUser()->getID() == 0) {
			$this->getOutput()->addHTML("Sorry, please enter the correct word. Click <a onclick='window.location.reload(true);'>here</a> to get a new one.<br/><br/>");
			return;
		}

		$article->doEdit($text, "");

		if ($this->getRequest()->getVal('jsonresponse') == 'true') {
			$this->revId = $article->getRevIdFetched();
		}

		// Notify users of usertalk updates
		if ( $t->getNamespace() == NS_USER_TALK ) {
			AuthorEmailNotification::notifyUserTalk($t->getArticleID(), $this->getUser()->getID(), $comment);
		}


		$this->getOutput()->setStatusCode(200);

		if ($this->getRequest()->getVal('fromajax') == 'true') {
			$this->getOutput()->redirect('');
			$this->getContext()->setTitle($t);
			$formattedComment = $wgParser->preSaveTransform($formattedComment, $t, $this->getUser(), new ParserOptions() );
			$this->getOutput()->addHTML($this->getOutput()->parse("\n" . $formattedComment));

			return;
		}
	}
}

class PostcommentPreview extends UnlistedSpecialPage {
	function __construct() {
		parent::__construct( 'PostcommentPreview' );
	}

	function execute($par) {
		global $wgLang;
		global $wgParser;

		$user = $this->getUser()->getName();
		$dateStr = $wgLang->timeanddate(wfTimestampNow());
		$real_name = User::whoIsReal($this->getUser()->getID());
		if ($real_name == "") {
			$real_name = $user;
		}
		$comment = $this->getRequest()->getVal("comment");
		$comment = preg_replace('/\n[ ]*/', "\n", trim($comment));
		$formattedComment = wfMsg('postcomment_formatted_comment', $dateStr, $user, $real_name, $comment);
		$formattedComment = $wgParser->preSaveTransform($formattedComment, $this->getTitle(), $this->getUser(), new ParserOptions() );
		$result = $this->getOutput()->parse($formattedComment);
		$this->getOutput()->setArticleBodyOnly(true);
		$this->getOutput()->addHTML($result);
	}
}
