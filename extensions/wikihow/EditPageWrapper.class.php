<?php

if ( !defined('MEDIAWIKI') ) exit;

# Splitting edit page/HTML interface from Article...
# The actual database and text munging is still in Article,
# but it should get easier to call those from alternate
# interfaces.
global $IP;
require_once("$IP/includes/EditPage.php");

class EditPageWrapper extends EditPage {

	var $whow = null;

	function __construct( $article ) {
		parent::__construct( $article );
		$this->mGuided = true;
	}

	static function onCustomEdit($page, $user) {
		global $wgRequest;

		return self::handleEditHooks($wgRequest, $page->mTitle, $page, 'edit', $user);
	}

	public static function onMediaWikiPerformAction( $output, $article, $title, $user, $request, $wiki ) {
		$action = $request->getVal('action');
		if($action != 'submit2') {
			return true;
		}

		if( session_id() == '' ) {
			// Send a cookie so anons get talk message notifications
			wfSetupSession();
		}

		return self::handleEditHooks($request, $title, $article, $action, $user);
	}

	static function handleEditHooks($request, $title, $article, $action, $user) {
		if ($request->getVal('advanced') != 'true') {
			$newArticle = false;
			// if it's not new, is it already a wikiHow?
			$validWikiHow = false;
			if ($title->getNamespace() == NS_MAIN && $request->getVal('section', null) == null && $request->getVal('wpSection', null) == null) {
				if ( $request->getVal( "title" ) == "") {
					$newArticle = true;
				} else if ($title->getArticleID() == 0) {
					$newArticle = true;
				}
				if (!$newArticle) {
					$validWikiHow = WikihowArticleEditor::useWrapperForEdit($article);
				}
			}

			// use the wrapper if it's a new article or
			// if it's an existing wikiHow article
			$t = $request->getVal('title', null);
			$editor = $user->getOption('defaulteditor', '');
			if (empty($editor)) {
				$editor = $user->getOption('useadvanced', false) ? 'advanced' : 'visual';
			}

			if ($t != null
				&& $t != wfMsg('mainpage')
				&& $editor == 'advanced'
				&& !$request->getVal('override', null))
			{
				// use advanced if they have already set a title
				// and have the default preference setting
				#echo "uh oh!";
			} else if ($action != "submit") {
				if ($newArticle || $action == 'submit2' ||
					($validWikiHow &&
						($editor != 'advanced'
							|| $request->getVal("override", "") == "yes" )))
				{
					$editor = new EditPageWrapper( $article );
					$editor->edit();
					return false;
				} else {
					#echo "uh oh!";
				}
			}
		}

		return true;
	}

	function getCategoryOptions($default, $cats) {
		wfGetCategoryOptionsForm($default, $cats);
	}

	function getCategoryOptions2($default = "") {
		global $wgUser;

		// only do this for logged in users
		if ($wgUser->getID() <= 0) return "";

		$t = Title::makeTitle(NS_PROJECT, "Categories");
		$r = Revision::newFromTitle($t);
		if (!$r)
			return '';
		$cat_array = explode("\n", $r->getText());
		$s = "";
		foreach($cat_array as $line) {
			$line = trim($line);
			if ($line == "" || strpos($line, "[[") === 0) continue;
			$top = false;
			if (strpos($line, "*") !== 0) continue;
			$line = substr($line, 1);
			if (strpos($line, "*") !== 0) $top = true;
			$val = trim(str_replace("*", "", $line));
			$display = str_replace("*", "&nbsp;&nbsp;&nbsp;&nbsp;", $line);
			$s .= "<OPTION " ;
			if ($top) $s .= " style='font-weight: bold;'";
			$s .= " VALUE=\"" . $val . "\">" . $display . "</OPTION>\n";
		}
		$s = str_replace("\"$default\"", "\"$default\" SELECTED", $s);
		return $s;
	}

	# Old version
	function edit() {
		global $wgRequest;
		$this->importFormData($wgRequest);
		EditPage::edit();
	}

	function importContentFormData( $request ) {
		if( $request->wasPosted() && !$request->getVal('wpTextbox1')) {
			$whow = WikihowArticleEditor::newFromRequest($request);
			$whow->mIsNew = false;
			$this->whow = $whow;
			$content = $this->whow->formatWikiText();
			return $content;
		} else {
			return parent::importContentFormData($request);
		}
	}

	function importFormData( &$request ) {
		# These fields need to be checked for encoding.
		# Also remove trailing whitespace, but don't remove _initial_
		# whitespace from the text boxes. This may be significant formatting.
		EditPage::importFormData($request);
	}

	# Since there is only one text field on the edit form,
	# pressing <enter> will cause the form to be submitted, but
	# the submit button value won't appear in the query, so we
	# Fake it here before going back to edit().  This is kind of
	# ugly, but it helps some old URLs to still work.
	function submit2() {
		if( !$this->preview ) $this->save = true;
		$this->easy();
	}

	# Extend showEditForm. Make most of conflict handling, etc of Editpage::showEditForm
	# but use our own display
	function showEditForm( $formCallback=null ) {
		global $wgOut, $wgLanguageCode, $wgRequest, $wgTitle, $wgUser, $wgLang;
		global $wgScriptPath;

		$whow = null;

		// conflict resolution
		if (!$wgRequest->wasPosted()) {
			EditPage::showEditForm();
		}
		$wgOut->clearHTML();

		//echo $this->textbox1; exit;
		wfRunHooks( 'EditPage::showEditForm:initial', array( &$this ) ) ;

		if( $this->showHeader() === false) {
			return;	
		}
		// are we called with just action=edit and no title?
		$newArticle = false;
		if ( ($wgRequest->getVal( "title" ) == "" || $wgTitle->getArticleID() == 0)
				&& !$this->preview) {
			$newArticle = true;
		}

		$sk = $wgUser->getSkin();
		if(!$this->mTitle->getArticleID() && !$this->preview) { # new article
			$wgOut->addHTML(wfMsg("newarticletext"));
		}


		// do we have a new article? if so, format the title if it's English
		$new_article = $wgRequest->getVal("new_article");
		if ($new_article && $wgLanguageCode == "en") {
			$title = $this->mTitle->getText();
			$old_title = $title;
			$title = $this->formatTitle($title);
			$titleObj = Title::newFromText($title);
			$this->mTitle = $titleObj;
			$this->mArticle = new Article($titleObj);
		}

		$conflictWikiHow = null;
		$conflictTitle = false;
		if ( $this->isConflict ) {
			$s = wfMsg( "editconflict", $this->mTitle->getPrefixedText() );
			$wgOut->setPageTitle( $s );
			if ($new_article) {
				$wgOut->addHTML("<b><font color=red>".wfMsg('page-name-exists')."</b></font><br/><br/>");
				$conflictTitle = true;
			} else {
				$this->edittime = $this->mArticle->getTimestamp();
			    $wgOut->addHTML( wfMsg( "explainconflict" ) );
				// let the advanced editor handle the situation
				if ($this->isConflict)  {
					EditPage::showEditForm();
					return;
				}
			}

			$this->textbox2 = $this->textbox1;
			$conflictWikiHow = WikihowArticleEditor::newFromText($this->textbox1);
			$this->textbox1 = $this->mArticle->getContent( true, true );
			$this->edittime = $this->mArticle->getTimestamp();
		} else {
			if ($this->mTitle->getArticleID() == 0)
				$s = wfMsg('creating',"\"" . wfMsg('howto',$this->mTitle->getPrefixedText()) . "\"");
			else
				$s = wfMsg('editing',"\"" . wfMsg('howto',$this->mTitle->getPrefixedText()) . "\"");
			if( $this->section != "" ) {
				if( $this->section == "new" ) {
					$s.=wfMsg("commentedit");
				} else {
					$s.=wfMsg("sectionedit");
				}
				if(!$this->preview) {
					$sectitle=preg_match("/^=+(.*?)=+/mi",
					$this->textbox1,
					$matches);
					if( !empty( $matches[1] ) ) {
						$this->summary = "/* ". trim($matches[1])." */ ";
					}
				}
			}
			$wgOut->setPageTitle( $s );
			if ( $this->oldid ) {
				$this->mArticle->setOldSubtitle($this->oldid);
				//message already displayed so commenting this out [sc - 1/29/2014]
				//$wgOut->addHTML( wfMsg( "editingold" ) );
			}
		}


		if( wfReadOnly() ) {
			$wgOut->addHTML( "<strong>" .
			wfMsg( "readonlywarning" ) .
			"</strong>" );
		} elseif ( $isCssJsSubpage and "preview" != $formtype) {
			$wgOut->addHTML( wfMsg( "usercssjsyoucanpreview" ));
		}

		if( !$newArticle && $this->mTitle->isProtected( 'edit' ) ) {
			if( $this->mTitle->isSemiProtected() ) {
				$notice = wfMsg( 'semiprotectedpagewarning' );
				if( wfEmptyMsg( 'semiprotectedpagewarning', $notice ) || $notice == '-' ) {
					$notice = '';
				}
			} else {
				$notice = wfMsg( 'protectedpagewarning' );
			}
			$wgOut->addHTML( "<div class='article_inner'>\n " );
			$wgOut->addWikiText( $notice );
			$wgOut->addHTML( "</div>\n" );
		}



		$q = "action=submit2&override=yes";
		#if ( "no" == $redirect ) { $q .= "&redirect=no"; }
		$action = $this->mTitle->escapeLocalURL( $q );
		if ($newArticle) {
			$main = str_replace(' ', '-', wfMsg('mainpage'));
			$action = str_replace("&title=".$main, "", $action);
		}

		$summary = wfMsg( "summary" );
		$subject = wfMsg("subject");
		$minor = wfMsg( "minoredit" );
		$watchthis = wfMsg ("watchthis");
		$save = wfMsg( "savearticle" );
		$prev = wfMsg( "showpreview" );

		$cancel = $sk->link( $this->mTitle->getPrefixedText(),
		  wfMsg( "cancel" ) );
		$edithelpurl = Skin::makeInternalOrExternalUrl( wfMsgForContent( 'edithelppage' ));
		$edithelp = '<a target="helpwindow" href="'.$edithelpurl.'">'.
			htmlspecialchars( wfMsg( 'edithelp' ) ).'</a> '.
			htmlspecialchars( wfMsg( 'newwindow' ) );
		$copywarn = wfMsg( "copyrightwarning", $sk->link(
		  wfMsg( "copyrightpage" ) ) );


		$minoredithtml = '';

		if ( $wgUser->isAllowed('minoredit') ) {
			$minoredithtml =
				"<input tabindex='11' type='checkbox' value='1' name='wpMinoredit'".($this->minoredit?" checked='checked'":"").
				" accesskey='".wfMsg('accesskey-minoredit')."' id='wpMinoredit' />\n".
				"<label for='wpMinoredit' title='".wfMsg('tooltip-minoredit')."'>{$minor}</label>\n";
		}

		$watchhtml = '';

		if ( $wgUser->isLoggedIn() ) {
			$watchhtml = "<input tabindex='12' type='checkbox' name='wpWatchthis'".
				($this->watchthis?" checked='checked'":"").
				" accesskey=\"".htmlspecialchars(wfMsg('accesskey-watch'))."\" id='wpWatchthis'  />\n".
				"<label for='wpWatchthis' title=\"" .
					htmlspecialchars(wfMsg('tooltip-watch'))."\">{$watchthis}</label>\n";
		}

		$checkboxhtml = $minoredithtml . $watchhtml;

		$tabindex = 14;
		$buttons = $this->getEditButtons( $tabindex );

		$footerbuttons = "";
		$buttons['preview'] = "<span id='gatGuidedPreview'>{$buttons['preview']}</span>";
		if ($wgUser->getOption('hidepersistantsavebar',0) == 0) {
			$footerbuttons .= "<span id='gatPSBSave'>{$buttons['save']}</span>";
			$footerbuttons .= "<span id='gatPSBPreview'>{$buttons['preview']}</span>";
		}
		$saveBtn = str_replace('accesskey="s"', "", $buttons['save']);
		$buttons['save'] = "<span id='gatGuidedSave'>{$saveBtn}</span>";

		$buttonshtml = implode( $buttons, "\n" );

		# if this is a comment, show a subject line at the top, which is also the edit summary.
		# Otherwise, show a summary field at the bottom
		$summarytext = htmlspecialchars( $wgLang->recodeForEdit( $this->summary ) ); # FIXME
		$editsummary1 = "";
		if ($wgRequest->getVal('suggestion')) {
			$summarytext .= ($summarytext == "" ? "" : ", ") .  wfMsg('suggestion_edit_summary');
		}
		if( $this->section == "new" ) {
			$commentsubject="{$subject}: <input tabindex='1' type='text' value=\"$summarytext\" name=\"wpSummary\" id='wpSummary' maxlength='200' size='60' />";
			$editsummary = "";
		} else {
			$commentsubject = "";
			if ($wgTitle->getArticleID() == 0 && $wgTitle->getNamespace() == NS_MAIN && $summarytext == "")
				$summarytext = wfMsg('creating_new_article');
			$editsummary="<input tabindex='10' type='text' value=\"$summarytext\" name=\"wpSummary\" id='wpSummary' maxlength='200' size='60' /><br />";
			$editsummary1="<input tabindex='10' type='text' value=\"$summarytext\" name=\"wpSummary1\" id='wpSummary1' maxlength='200' size='60' /><br />";
		}

		// create the wikiHow
		if ($conflictWikiHow == null) {
			if ($this->textbox1 != "") {
				$whow = WikihowArticleEditor::newFromText($this->textbox1);
			} else {
				$whow = WikihowArticleEditor::newFromTitle($this->mArticle->getTitle());
			}
		} else {
			$whow = $conflictWikiHow;
		}

//********** SETTING UP THE FORM
//
//
//
//
		$confirm = "window.onbeforeunload = confirmExit;";
		if ($wgUser->getOption('useeditwarning') == '0') {
			$confirm = "";
		}
		$wgOut->addHTML("<script language=\"JavaScript\">
				var isGuided = true;
				var needToConfirm = false;
				var checkMinLength = true;
				{$confirm}
				function confirmExit() {
					if (needToConfirm)
						return \"".wfMsg('all-changes-lost')."\";
				}
				function addrows(element) {
					if (element.rows < 32)  {
						element.rows += 4;
					}
				}
				function removerows(element) {
					if (element.rows > 4)  {
						element.rows -= 4;
					} else {
						element.rows = 4;
					}
				}
				function saveandpublish() {
					window.onbeforeunload = null;
					document.editform.submit();
				}
				(function ($) {
					$(document).ready(function() {
						$('.button').click(function () {
							var button = $(this).not('.submit_button');
							if (button.length) {
								needToConfirm = true;
							}
						});
						$('textarea').focus(function () {
							needToConfirm = true;
						});
					});
					$('#ep_cat').live('click', function(e) {
						e.preventDefault();
						var title = 'Categorize ' + wgTitle;
						if (title.length > 54) {
							title = title.substr(0, 54) + '...';
						}
						jQuery('#dialog-box').html('');
						
						jQuery('#dialog-box').load('/Special:Categorizer?a=editpage&id=' + wgArticleId, function() {
							jQuery('#dialog-box').dialog({
								width: 673,
								height: 600,
								modal: true,
								title: title,
								closeText: 'Close',	
								dialogClass: 'modal2',
							});
							reCenter = function() {
								jQuery('#dialog-box').dialog('option', 'position', 'center');
							}
							setTimeout('reCenter()', 100);
							
						});
					});
				})(jQuery);
			</script>
			<script type=\"text/javascript\" src=\"" . wfGetPad('/extensions/min/f/skins/common/clientscript.js,/skins/common/ac.js,/extensions/wikihow/video/importvideo.js&rev=') . WH_SITEREV . "\"></script>

		");

		if( !$this->preview ) {
			# Don't select the edit box on preview; this interferes with seeing what's going on.
			//BEBETH: commenting this out b/c haven't figured out a new function for it
			//$wgOut->setOnloadHandler( "document.editform.title.focus(); load_cats();" );
		}
		$title = "";
		//$wgOut->setOnloadHandler( "' onbeforeunload='return confirm(\"Are you sure you want to navigate away from this page? All changes will be lost!\");" );

		$suggested_title = "";
		if (isset($_GET["requested"])) {
			$t = Title::makeTitle(NS_MAIN, $_GET["requested"] );
			$suggested_title = $t->getText();
		}


		if ($wgRequest->getVal('title',null) == null || $conflictTitle || $suggested_title != "") {
			$title = "<div id='title'><h3>".wfMsg('title')."</h3><br/>" . wfMsg('howto','')." &nbsp;&nbsp;&nbsp;
			<input autocomplete=\"off\" size=60 type=\"text\" name=\"title\" id=category tabindex=\"1\" value=\"$suggested_title\"></div>";
		}


		$steps = htmlspecialchars( $wgLang->recodeForEdit( $whow->getSteps(true) ), ENT_QUOTES);
		$video = htmlspecialchars( $wgLang->recodeForEdit( $whow->getSection(wfMsg('video')) ) );
		$tips = htmlspecialchars( $wgLang->recodeForEdit( $whow->getSection(wfMsg('tips')) ) );
		$warns = htmlspecialchars( $wgLang->recodeForEdit( $whow->getSection(wfMsg('warnings'))) );

		$related_text = htmlspecialchars( $wgLang->recodeForEdit( $whow->getSection(wfMsg('relatedwikihows')) ) );

		$summary = htmlspecialchars( $wgLang->recodeForEdit($whow->getSummary()) );

		if ($newArticle || $whow->mIsNew) {
			if ($steps == "") $steps = "#  ";
			if ($tips == "") $tips = "*  ";
			if ($warns == "") $warns = "*  ";
			if ($ingredients == "") $ingredients = "*  ";
		}

		$cat = $whow->getCategoryString();

		$advanced = "";

		$cat_array = explode("|", $whow->getCategoryString());
		$i = 0;
		$cat_string = "";
		foreach ($cat_array as $cat) {
			if ($cat == "")
				continue;
			if ($i != 0)
				$cat_string .= "," . $cat;
			else
				$cat_string = $cat;
			$i++;
		}
		$removeButton = "";
		$cat_advisory = "";
		if ($cat_string != "") {
			$removeButton = "<input type=\"button\" name=\"change_cats\" onclick=\"removeCategories();\" value=\"".wfMsg('remove-categories')."\">";
		} else {
			$cat_advisory = wfMsg('categorization-optional');
		}

		//$cat_string = str_replace("|", ", ", $whow->getCategoryString());
		//$cat_string = implode(", ", $raa);
		if (!$newArticle && !$whow->mIsNew && !$conflictTitle) {
			$oldparameters = "";
			if ($wgRequest->getVal("oldid") != "") {
				$oldparameters = "&oldid=" . $wgRequest->getVal("oldid");
			}
			if (!$this->preview)
				$advanced = "<a class='' href='{$wgScript}?title=" . $wgTitle->getPrefixedURL() . "&action=edit&advanced=true$oldparameters'>".wfMsg('advanced-editing')."</a>";
		} elseif ($newArticle && $wgRequest->getVal('title', null) != null) {
			$t = Title::newFromText("CreatePage", NS_SPECIAL);
			 //$advanced = str_replace("href=", "class='guided-button' href=", $sk->makeLinkObj($t, wfMsg('advanced-editing'))) . " |";
			//$advanced = "<a href='{$wgScript}?title=" . $wgTitle->getPrefixedURL() . "&action=edit&advanced=true$oldparameters';\">".wfMsg('advanced-editing')."</a>";
			$advanced = "<a class='button secondary' style='float:left;' href='{$wgScript}?title=" . $wgTitle->getPrefixedURL() . "&action=edit&advanced=true$oldparameters'>".wfMsg('advanced-editing')."</a>";
		}

		$section_class = 'minor_section';

		// MODIFIED FOR POPUP
		$categoryHTML = "";
		if ($wgUser->getID() > 0) {
			$ctitle = $this->mTitle->getText();
			$css = HtmlSnips::makeUrlTags('css', array('categoriespopup.css'), 'extensions/wikihow', false);
			if ($wgLanguageCode == 'en') {
				$editCatMore = "<a href=\"{$wgScriptPath}/Writer%27s-Guide?section=2#" . wfMsg('more-info-categorization') . "\" target=\"new\">" . wfMsg('moreinfo') ."</a>";
				$editCatHtml = "<a href='#' id='ep_cat'>[".wfMsg('editcategory')."]</a><strong>$editCatLink</strong>";
			}
			$categoryHTML = "
				$css
				<div id='categories'>
					<h5>".wfMsg('add-optional-categories') . "$editCatMore</h5>
					<div id='option_cats'>
					$editCatHtml" . Categoryhelper::getCategoryOptionsForm2($cat_string, $whow->mCategories) .
				"	</div>
				</div>";
		}


		$requested = "";
		if (isset($_GET['requested'])) {
			$requested = $_GET['requested'];
		}

		$related_vis = "hide";
		$related_checked = "";
		$relatedHTML = "";
		if ($whow->getSection(wfMsg('relatedwikihows')) != "") {
			$related_vis = "show";
			$relatedHTML = $whow->getSection(wfMsg('relatedwikihows'));
			$relatedHTML = str_replace("*", "", $relatedHTML);
			$relatedHTML = str_replace("[[", "", $relatedHTML);
			$relatedHTML = str_replace("]]", "", $relatedHTML);
			$lines = explode("\n", $relatedHTML);
			$relatedHTML = "";
			foreach ($lines as $line) {
				$xx = strpos($line, "|");
				if ($xx !== false)
					$line = substr($line, 0, $xx);
				// Google+ hack.  We don't normally allow + but will for the Goog
				if(false === stripos($line, 'Google+')) {
					$line = trim(urldecode($line));
				}
				if ($line == "") continue;
				$relatedHTML .= "<OPTION VALUE=\"" . htmlspecialchars($line) . "\">$line</OPTION>\n";
			}
			$related_checked = " CHECKED ";
		}

		$vidpreview_vis = "hide";
		$vidbtn_vis = "show";
		$vidpreview = "<img src='" . wfGetPad('/extensions/wikihow/rotate.gif') . "'/>";
		if ($whow->getSection(wfMsg('video')) != "") {
			$vidpreview_vis = "show";
			$vidbtn_vis = "hide";
			try {
				#$vt = Title::makeTitle(NS_VIDEO, $this->mTitle->getText());
				#$r = Revision::newFromTitle($vt);
				$vidtext = $whow->getSection(wfMsg('video'));
				$vidpreview = $wgOut->parse($vidtext);
			} catch (Exception $e) {
				$vidpreview = "Sorry, preview is currently not available.";
			}
		}  else {
			$vidpreview = wfMsg('video_novideoyet');
		}
		$video_disabled = "";
		$vid_alt = "";
		$video_msg = "";
		$wasNew = "false";
		if ($newArticle) {
			$wasNew = "true";
		}
		$video_button ="<a id='gatVideoImportEdit' type='button' onclick=\"changeVideo('". urlencode($wgTitle->getDBKey()) . "', ". $wasNew ."); return false;\" href='#' id='show_preview_button' class='button secondary'  >" . wfMsg('video_change') . "</a>";
		if ($wgUser->getID() == 0) {
			$video_disabled = "disabled";
			$video_alt = "<input type='hidden' name='video' value=\"" . htmlspecialchars($video) . "\"/>";
			$video_msg = wfMsg('video_loggedin');
			$video_button = "";
		}

		$things_vis = "hide";
		$things = "*  ";
		$things_checked = "";
		$tyn = $whow->getSection(wfMsg("thingsyoullneed"));
		if ($tyn != '') {
			$things_vis = "show";
			$things = $tyn;
			$things_checked = " CHECKED ";
		}
		$ingredients_vis = "hide";
		$section = $whow->getSection(wfMsg("ingredients"));
		$ingredients_checked = "";
		if ($section != '') {
			$ingredients_vis = "show";
			$ingredients = $section;
			$ingredients_checked = " CHECKED ";
		}

		$sources_vis = "hide";
		$sources = "*  ";
		$sources_checked = "";
		$sources = $whow->getSection(wfMsg("sources"));
		$sources = str_replace('<div class="references-small"><references/></div>', '', $sources);
		$sources = str_replace('{{reflist}}', '', $sources);
		if ($sources != "") {
			$sources_vis = "show";
			$sources_checked = " CHECKED ";
		}
		$new_field = "";
		if ($newArticle || $new_article) {
			$new_field="<input type=hidden name=new_article value=true>";
		}

		$lang_links = htmlspecialchars($whow->getLangLinks());
		$vt = Title::makeTitle(NS_VIDEO, $this->mTitle->getText());
		$vp = SpecialPage::getTitleFor("Previewvideo", $vt->getFullText());

		$newArticleWarn = '<script type="text/javascript" src="' . wfGetPad('/extensions/min/f/extensions/wikihow/winpop.js?') . WH_SITEREV . '"></script>';
		$popup = Title::newFromText("UploadPopup", NS_SPECIAL);

		if ( $wgUser->isLoggedIn() )
			$token = htmlspecialchars( $wgUser->editToken() );
		else
			$token = EDIT_TOKEN_SUFFIX;

		$show_weave = false;
		if ( 'preview' == $this->formtype ) {
			$previewOutput = $this->getPreviewText();
			$this->showPreview( $previewOutput );
			$show_weave = true;
		} else {
			$wgOut->addHTML( '<div id="wikiPreview"></div>' );
		}

		if ( 'diff' == $this->formtype ) {
			$wgOut->addCSScode('diffc');
			$this->showDiff();
			$show_weave = true;
		}

		$weave_links = '';
		if ( $show_weave ) {
			$relBtn = $wgLanguageCode == 'en' ? PopBox::getGuidedEditorButton() : '';
			$relHTML = PopBox::getPopBoxJSGuided() . PopBox::getPopBoxDiv() . PopBox::getPopBoxCSS();
			$weave_links = $relHTML.'<div class="wh_block editpage_sublinks">'.$relBtn.'</div>';
		}


		$undo = '';
		if ($wgRequest->getVal('undo', null) != null) {
			$undo_id = $wgRequest->getVal('undo', null);
			$undo =  "\n<input type='hidden' value=\"$undo_id\" name=\"wpUndoEdit\" />\n";
		}
		$wgOut->addHTML( Easyimageupload::getUploadBoxJS() );

		$wgOut->addHTML( "	
$newArticleWarn

<div id='editpage'>
<form id=\"editform\" name=\"editform\" method=\"post\" action=\"$action\"
enctype=\"application/x-www-form-urlencoded\"  onSubmit=\"return checkForm();\">		");

		if( is_callable( $formCallback ) ) {
			call_user_func_array( $formCallback, array( &$wgOut ) );
		}

		$hidden_cats = "";
		if (!$wgUser->isLoggedIn())
			$hidden_cats = "<input type=\"hidden\" name=\"categories22\" value=\"{$cat_string}\">";

		$token1 = md5($wgUser->getName() . $this->mTitle->getArticleID() . time());
		wfTrackEditToken($wgUser, $token1, $this->mTitle, $this instanceof EditPageWrapper);

		$wgOut->addHTML ("
		{$new_field}
		{$hidden_cats}
		<input type='hidden' value=\"{$this->starttime}\" name=\"wpStarttime\" />\n
		<input type=\"hidden\" name=\"requested\" value=\"{$requested}\">
		<input type=\"hidden\" name=\"langlinks\" value=\"{$lang_links}\">
		<input type='hidden' value=\"{$this->edittime}\" name=\"wpEdittime\" />\n

		{$commentsubject}
		{$title}
		<br clear='all'/>
<script language='javascript'>
	var vp_URL = '{$vp->getLocalUrl()}';
</script>
<script language='javascript' src='" . wfGetPad('/extensions/min/f/extensions/wikihow/previewvideo.js?rev=') . "'></script>
<style type='text/css' media='all'>/*<![CDATA[*/ @import '" . wfGetPad('/extensions/min/f/extensions/wikihow/editpagewrapper.css,/extensions/wikihow/winpop.css,/extensions/wikihow/video/importvideo.css,/extensions/wikihow/cattool/categorizer.css,/extensions/wikihow/cattool/categorizer_editpage.css&rev=') . WH_SITEREV . "'; /*]]>*/</style>

	{$weave_links}

	<div id='introduction' class='{$section_class}'>
		<h2>" . wfMsg('introduction') . "
			<div class='head_details'>" . wfMsg('summaryinfo') . "</div>
			<a href=\"{$wgScriptPath}/".wfMsg('writers-guide-url')."?section=2#".wfMsg('introduction-url')."\" target=\"new\">" . wfMsg('moreinfo') . "</a>
		</h2>
		<textarea rows='4' cols='100' name='summary' id='summary' tabindex=\"2\" wrap=virtual>{$summary}</textarea>
		<!--a href='#' class='button secondary add_image_button' onclick='easyImageUpload.doEIUModal(\"intro\"); return false;'>".wfMsg('eiu-add-image-to-introduction')."</a-->
		<div class='clearall'></div>
	</div>


	<div id='ingredients' class='{$ingredients_vis} {$section_class}'>
		<h2>" . wfMsg('ingredients') . "
			<div class='head_details'>" . wfMsg('ingredients_tooltip') . "</div>
			<a href=\"{$wgScriptPath}/".wfMsg('writers-guide-url')."?section=2#".wfMsg('ingredients')."\" target=\"new\">" . wfMsg('moreinfo') . "</a>
		</h2>
		<textarea name='ingredients' rows='4' cols='100' onKeyUp=\"addStars(event, document.editform.ingredients);\" tabindex='3' id='ingredients_text'>{$ingredients}</textarea>
		<a href='#' class='button secondary add_image_button'  onclick='easyImageUpload.doEIUModal(\"ingredients\"); return false;'>".wfMsg('eiu-add-image-to-ingredients')."</a>
	</div>

	<div id='steps' class='{$section_class}'>
		<h2>" . wfMsg('steps') . "
			<div class='head_details'>" . wfMsg('stepsinfo') . "</div>
			<a href=\"{$wgScriptPath}/".wfMsg('writers-guide-url')."?section=2#".wfMsg('steps')."\" target=\"new\">" . wfMsg('moreinfo') . "</a>
		</h2>
		<textarea name='steps' rows='{$wgRequest->getVal('txtarea_steps_text', 12)}' cols='100' wrap='virtual' onKeyUp=\"addNumToSteps(event);\" tabindex='4' id='steps_text'>{$steps}</textarea>
		<a href='#' class='button secondary add_image_button' onclick='easyImageUpload.doEIUModal(\"steps\", 0); return false;'>".wfMsg('eiu-add-image-to-steps')."</a>
	</div>");

		$wgOut->addHTML("<div id='video' class='{$section_class}'>
		<h2>" . wfMsg('video') . "
			<div class='head_details'>" . wfMsg('videoinfo') . "</div>
			<a href=\"{$wgScriptPath}/".wfMsg('writers-guide-url')."?section=2#".wfMsg('video')."\" target=\"new\">" . wfMsg('moreinfo') . "</a>
		</h2>
		{$video_alt}
		<input type='text' name='video{$video_disabled}' size='60' id='video_text' style='float:left;' value=\"{$video}\" {$video_disabled}/><br />
		{$video_button}
		<a href='javascript:showHideVideoPreview();' id='show_preview_button' class='button secondary {$vidbtn_vis}'>" . wfMsg('show_preview') . "</a>
		{$video_msg}
	</div>
	<div id='viewpreview' class='{$vidpreview_vis} {$section_class}' style='text-align: center; margin-top: 5px;'>
		<center><a onclick='showHideVideoPreview();'>" . wfMsg('ep_hide_preview') . "</a></center><br/>
		<div id='viewpreview_innards'>{$vidpreview}</div>
	</div>

	<div id='tips' class='{$section_class}'>
		<h2>" . wfMsg('tips') . "
			<div class='head_details'>" . wfMsg('listhints') . "</div>
			<a href=\"{$wgScriptPath}/".wfMsg('writers-guide-url')."?section=2#".wfMsg('tips')."\" target=\"new\">" . wfMsg('moreinfo') . "</a>
		</h2>
		<textarea name='tips' rows='{$wgRequest->getVal('txtarea_tips_text', 12)}' cols='100' wrap='virtual' onKeyUp='addStars(event, document.editform.tips);' tabindex='5' id='tips_text'>{$tips}</textarea>
		<a href='#' class='button secondary add_image_button' onclick='easyImageUpload.doEIUModal(\"tips\"); return false;'>".wfMsg('eiu-add-image-to-tips')."</a>
	</div>

	<div id='warnings' class='{$section_class}'>
		<h2>" . wfMsg('warnings') . "
			<div class='head_details'>". wfMsg('optionallist') . "</div>
			<a href=\"{$wgScriptPath}/".wfMsg('writers-guide-url')."?section=3#".wfMsg('warnings')."\" target=\"new\">" . wfMsg('moreinfo') . "</a>
		</h2>
		<textarea name='warnings' rows='{$wgRequest->getVal('txtarea_warnings_text', 4)}' cols='100' wrap='virtual' onKeyUp='addStars(event, document.editform.warnings);' id='warnings_text' tabindex=\"6\" id='warnings_text'>{$warns}</textarea>
		<a href='#' class='button secondary add_image_button' onclick='easyImageUpload.doEIUModal(\"warnings\"); return false;'>".wfMsg('eiu-add-image-to-warnings')."</a>
	</div>

	<div id='thingsyoullneed' class='{$things_vis} {$section_class}'>
		<h2>" . wfMsg('thingsyoullneed') ."
			<div class='head_details'>". wfMsg('items') . "</div>
			<a href=\"{$wgScriptPath}/".wfMsg('writers-guide-url')."?section=4#" . wfMsg('thingsyoullneed') . "\" target=\"new\">" . wfMsg('moreinfo') . "</a>
		</h2>
		<textarea name='thingsyoullneed' rows='4' cols='65' wrap='virtual' onKeyUp='addStars(event, document.editform.thingsyoullneed);' tabindex='7' id='thingsyoullneed_text'>{$things}</textarea>
		<a href='#' class='button secondary add_image_button' onclick='easyImageUpload.doEIUModal(\"thingsyoullneed\"); return false;'>".wfMsg('eiu-add-image-to-thingsyoullneed')."</a>
	</div>

	<div id='relatedwikihows' class='{$related_vis} {$section_class}'>
		<h2>" . wfMsg('relatedarticlestext') . "
			<div class='head_details'>" . wfMsg('relatedlist') . "</div>
			<a href=\"{$wgScriptPath}/".wfMsg('writers-guide-url')."?section=5#".wfMsg('related-wikihows-url')."\" target=\"new\">" . wfMsg('moreinfo') . "</a>
		</h2>
		<div id='related_buttons'>
			<a href='#'  class='button secondary' onclick='moveRelated(true);return false;' >" . wfMsg('epw_move_up') . "</a>
			<a href='#' class='button secondary' onclick='moveRelated(false);return false;'>" . wfMsg('epw_move_down') . "</a>
			<a href='#' class='button red' onclick='removeRelated(); return false;'>" . wfMsg('epw_remove') . "</a>
			<br />
			<br />
		</div>
		<input type=hidden value=\"\" name=\"related_list\">
		<select size='4' name='related' id='related_select' ondblclick='viewRelated();'>
			{$relatedHTML}
		</select>
		<br />
		<br />
		<br class='clearall'/>
		<div>
			<b>" . wfMsg('addtitle') . "</b><br />
			<input type='text' autocomplete=\"off\" maxLength='256' name='q' value='' onKeyPress=\"return keyxxx(event);\" tabindex='8'>
		</div>
		<a href='#' id='add_button' class='button secondary' onclick='add_related();return false;'>" . wfMsg('epw_add') . "</a>
		<br class='clearall'/>
	</div>

<script language=\"JavaScript\">
	var js_enabled = document.getElementById('related');
		 if (js_enabled != null) {
				 js_enabled.className = 'display';
			}
	</script>
	<noscript>
		<input type='hidden' name='no_js' value='true'>
		<div id='related'>
			<textarea name='related_no_js' rows='4' cols='65' wrap='virtual' onKeyUp='addStars(event, document.editform.related_no_js);' id='related_no_js' tabindex='8'>{$related_text}</textarea>
		</div>
	</noscript>

	<div id='sources' class='$sources_vis $section_class'>
		<h2>" . wfMsg('sources') . "
			<div class='head_details'>" . wfMsg('linkstosites') . "</div>
			<a href=\"{$wgScriptPath}/".wfMsg('writers-guide-url')."?section=2#".wfMsg('sources-links-url')."\" target=\"new\"> " . wfMsg('moreinfo') . "</a>
		</h2>
		<textarea name='sources' rows='3' cols='100' wrap='virtual' onKeyUp='addStars(event, document.editform.sources);' id='sources' tabindex='9'>{$sources}</textarea>
	</div>

	<div class='{$section_class}'>
		<h2>".wfMsg('optional_options')."</h2>
		{$categoryHTML}

		<div id='optional_sections'>
			<h5>" . wfMsg('optionalsections') . "</h5>
			<ul>
				<li><input type='checkbox' id='thingsyoullneed_checkbox' name='thingsyoullneed_checkbox' onclick='showhiderow(\"thingsyoullneed\", \"thingsyoullneed_checkbox\");' {$things_checked} /> <label for='thingsyoullneed_checkbox'>" . wfMsg('thingsyoullneed') . "</label></li>
				<li><input type='checkbox' id='related_checkbox' name='related_checkbox' onclick='showhiderow(\"relatedwikihows\", \"related_checkbox\");' {$related_checked} > <label for='related_checkbox'>" . wfMsg('relatedwikihows') . "</label></li>
				<li><input type='checkbox' id='sources_checkbox' name='sources_checkbox' onclick='showhiderow(\"sources\", \"sources_checkbox\");' {$sources_checked} > <label for='sources_checkbox'>" . wfMsg('sources') . "</label></li>
				<li><input type='checkbox' id='ingredients_checkbox' name='ingredients_checkbox' onclick='showhiderow(\"ingredients\", \"ingredients_checkbox\");' {$ingredients_checked} > <label for='ingredients_checkbox'>" . wfMsg('ingredients_checkbox') . "</label></li>
			</ul>
		</div>
	</div>
	
	<div class='{$section_class}'>
		<div class='editOptions'>
			<h2>" . wfMsg('editdetails') . "
				<div class='head_details'>" . wfMsg('summaryedit') . "</div>
				<a href=\"{$wgScriptPath}/".wfMsg('writers-guide-url')."?section=2#".wfMsg('summary')."\" target=\"new\"> " . wfMsg('moreinfo') . "</a>
			</h2>
			$editsummary
			$checkboxhtml
			$undo
			<input type='hidden' value=\"$token\" name=\"wpEditToken\" />
			<input type='hidden' value=\"$token1\" name=\"wpEditTokenTrack\" />
			<div class='editButtons'>
				<a href=\"javascript:history.back()\" id=\"wpCancel\" class=\"button secondary\">".wfMsg('cancel')."</a>
				{$buttonshtml}
			</div>
			$copywarn
		</div>
	</div>
	<input type='hidden' value=\"" . htmlspecialchars( $this->section ) . "\" name=\"wpSection\" />\n" );


		if ( $this->isConflict ) {
			require_once( "DifferenceEngine.php" );
			$wgOut->addCSScode('diffc');
			$wgOut->addHTML( "<h2>" . wfMsg( "yourdiff" ) . "</h2>\n" );
				DifferenceEngine::showDiff( $this->textbox2, $this->textbox1,
			  wfMsg( "yourtext" ), wfMsg( "storedversion" ) );
		}

		if ($wgUser->getOption('hidepersistantsavebar',0) == 0) {
			$wgOut->addHTML(" <div id='edit_page_footer'>
				<table class='edit_footer'><tr><td class='summary'>
				" . wfMsg('editsummary') . ": &nbsp; {$editsummary1}</td>
				<td class='buttons'>{$footerbuttons}</td></tr></table>
				</div> ");
		}

		$wgOut->addHTML( "</form></div>\n" );
	}

	function formatTitle($title) {
		global $wgLanguageCode;

		// INTL: Only format the title for English pages
		if ($wgLanguageCode == 'en') {

			// Google+ hack.  We don't normally allow + but will for the Google
			if (false === stripos($title, 'Google+')) {
				// cut off extra ?'s or whatever
				while (preg_match("/[[:punct:]]$/u", $title)
						&& !preg_match("/[\")]$/u", $title) && strlen($title) > 2)
				{
					$title = substr($title, 0, strlen($title) - 1);
				}
			}

			// check for high ascii
			for ($i = 0; $i < strlen($title); $i++) {
				if (ord(substr($title, $i, 1)) > 128) {
					return trim( strval($title) );
				}
			}

			// on, off, in and out are all problematic in this list because
			// they are both prepositions and often parts of phrasal verbs.
			// NOTE: removed off and out intentionally because MOST are wrong
			$prepositions = array(
				'a','an','and','at','but','by','for','from',
				'if','in','nor','of','or','on','over',
				'per','than','the','then','to','via','vs','when','with',
			);

			$specialCase = array(
				'AAC','BSc','CS2','CS3','DNS','IM','MatLab','MP3',
				'MySpace','.NET','PhD','PHP','PS3','SAT','XML',

				// Generated from the titles already on the site
				'ADHD','AIM','AP','BB','BBQ','BMX','CD','CS','DJ','DS',
				'DSi','DVD','DVDs','eBay','eBook','FP','GIMP','GPS','GTA','HD',
				'HP','HTML','ID','iMovie','iOS','IP','iPad','iPhone','iPod','IRC',
				'ISO','iTunes','IV','LCD','LEGO','LLC','MediaWiki','MP','MS','MSN',
				'MySpace','OS','PayPal','PC','PDF','PHP','PlayStation','PowerPoint','PS','PSP',
				'PVC','RAM','RPG','RSS','RuneScape','RV','SEO','SketchUp','SMS','TV',
				'UK','US','USA','USB','wikiHow','WWE','XP','YouTube',

				// Other common ones I saw
				'VoIP', 'CSS', 'iGoogle', 'StumbleUpon', 'IMDb', 'TweetDeck', 'GIMP', 'VCRs',
				'AdSense', 'MySQL', 'eBooks', 'PCs', 'pH', 'AutoCAD', 'BMW', 'WordPress',
				'LinkedIn', 'WiFi', 'MP4', 'AVI', 'PPT', 'PDFs', 'SWF',

				// From 10k top queries
				'MKV', '3GS', '4G', 'iMessage', 'WAV', 'WMA',
				'JPG', '3D', 'BMI', 'MLA', 'M4A', 'APA',
				'GPA', 'iCloud', 'NBA', 'NFL', 'NHL', 'MBA',
				'MLB',
			);

			$domains = array(
				'.com', '.org', '.net', '.tv',
			);

			// Compress multiple spaces/tabs/newlines into 1 space
			$title = preg_replace('@\s+@', ' ', $title);

			// Remove spacing from start and end of title
			$title = trim($title);

			// Remove any "quotes" from surrounding -- common mistake
			$title = preg_replace('@^"(.*)"$@', '$1', $title);
			$words = explode(' ', $title);

			// Remove To from start -- common mistake
			if (count($words) >= 1
				&& strcasecmp($words[0], 'to') === 0)
			{
				array_shift($words);
			}

			// Remove "How to" from start -- common mistake
			if (count($words) >= 2
				&& strcasecmp($words[0], 'how') === 0
				&& strcasecmp($words[1], 'to') === 0)
			{
				array_splice($words, 0, 2);
			}

			// Count the upper-case and lower-case characters in the title
			$lower_count = preg_match_all('@[a-z]@', $title, $m);
			$upper_count = preg_match_all('@[A-Z]@', $title, $m);
			// if a title is mostly upper-case
			$mostly_upper = $upper_count > $lower_count;

			// Precomputations for the word loop -- domain regexp
			$quoted_domains = array_map("preg_quote", $domains);
			$domain_re = '@(' . join('|', $quoted_domains) . ')$@i';

			// Precompute hash of special case word casing
			foreach (array_merge($prepositions, $specialCase) as $word) {
				$special_map[ strtolower($word) ] = $word;
			}

			// Go through each word in the title and maybe change the case
			foreach ($words as &$word) {
				// leave domain names alone
				if (!preg_match($domain_re, $word)) {

					// split word along punctuation boundaries, to handle things like
					// "Good", (USA), and In/Out
					$parts = preg_split('@(\w+)@', $word, -1, PREG_SPLIT_DELIM_CAPTURE);

					$lastpart = '';
					foreach ($parts as $i => &$part) {
						// If an entire title isn't upper-case, and a single word
						// is all upper-case, it's probably an intentional acronym
						$exclude = !$mostly_upper
							&& preg_match('@[A-Z]@', $part)
							&& !preg_match('@[a-z]@', $part);

						// Check if we have a word with "special" title case
						$lower = strtolower($part);
						if ( isset( $special_map[$lower] ) ) {
							$part = $special_map[$lower];
						} else {
							if (!$exclude) {
								if ($i >= 2 && $lastpart == "'") {
									// If word is something like "You're", don't
									// capitalize the "re"
									$part = $lower;
								/*
								} elseif (in_array($part, array('-', '--'))
									&& $i >= 2 && $lastpart)
								{
									// We want to insert a real dash:
									// http://www.wikihow.com/Insert-a-Hyphen-into-a-wikiHow-Title
									//$part = "\u2010";
									$part = json_decode('"\u2010"');
								*/
								} else {
									// Capitalize first character in lower-case word
									$part = ucfirst($lower);
								}
							}
						}

						$lastpart = $part;
					}

					$word = join('', $parts);
				}
			}

			$title = join(' ', $words);

		} else {
			// INTL: Trim whitespace and that's it.
			$title = trim($title);
		}
		return $title;
	}

	function getEditButtons($tabindex) {
		global $wgLivePreview, $wgUser;

		$buttons = array();

		$temp = array(
			'id'        => 'wpSave',
			'name'      => 'wpSave',
			'type'      => 'submit',
			'tabindex'  => ++$tabindex,
			'value'     => wfMsg('savearticle'),
			'accesskey' => wfMsg('accesskey-save'),
			'title'     => wfMsg( 'tooltip-save' ).' ['.wfMsg( 'accesskey-save' ).']',
			//XXCHANGED
			'onclick'   => 'needToConfirm = false',
			'class'     => 'button primary submit_button wpSave',
		);
		$buttons['save'] = XML::element('input', $temp, '');

		$temp = array(
			'id'        => 'wpPreview',
			'name'      => 'wpPreview',
			'type'      => 'submit',
			'tabindex'  => ++$tabindex,
			'value'     => wfMsg('showpreview'),
			'accesskey' => wfMsg('accesskey-preview'),
			'title'     => wfMsg( 'tooltip-preview' ).' ['.wfMsg( 'accesskey-preview' ).']',
			//XXCHANGED
			'onclick'   => 'needToConfirm = false; checkMinLength = false; checkSummary();',
			'class'     => 'button secondary submit_button',
		);
		$buttons['preview'] = XML::element('input', $temp, '');
		$buttons['live'] = '';

		$temp = array(
			'id'        => 'wpDiff',
			'name'      => 'wpDiff',
			'type'      => 'submit',
			'tabindex'  => ++$tabindex,
			'value'     => wfMsg('showdiff'),
			'accesskey' => wfMsg('accesskey-diff'),
			'title'     => wfMsg( 'tooltip-diff' ).' ['.wfMsg( 'accesskey-diff' ).']',
			//XXCHANGED
			'onclick'   => 'needToConfirm = false; checkMinLength = false; checkSummary();',
			'class'     => 'button secondary submit_button',
		);
		$buttons['diff'] = XML::element('input', $temp, '');

		wfRunHooks( 'EditPageBeforeEditButtons', array( &$this, &$buttons, &$tabindex ) );


		return $buttons;
	}
	
	//keep the advanced editor chosen on preview and show changes
	function addHiddenFormInputs($that, $wgOut, &$tabindex) {
		global $wgRequest;
		$adv = ($wgRequest->getVal('advanced')) ? 'true' : '';
		$wgOut->addHTML('<input type="hidden" name="advanced" value="'.$adv.'" />');
		return true;
	}
}

