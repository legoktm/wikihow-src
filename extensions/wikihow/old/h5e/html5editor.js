// Avoid errors if debug stmts are left in
if (typeof console == 'undefined') console = {};
if (typeof console.log == 'undefined') console.log = function() {};

// Create WH container obj if necessary
WH = WH || {};
WH.h5e = WH.h5e || {};

// wikiHow's HTML 5 editor
jQuery.extend(WH.h5e, (function ($) {

	function Editor() {

		/**
		 * Defines the z-index of all jQuery UI dialogs we display.
		 */
		this.DIALOG_ZINDEX = 10000;

		/**
		 * The base of address of our CDN.  This is non-random right
		 * now because it's not used much.
		 */
		this.CDN_BASE = wgCDNbase;

		/**
		 * Defines the height of our toolbar because we need to know
		 * it in a few spots.
		 */
		this.TOOLBAR_HEIGHT_PIXELS = 63;

		/**
		 * These public vars are set by the get-vars AJAX call, and are 
		 * required when saving an article.
		 */
		this.editTime = null;
		this.editToken = null;

		/**
		 * Sometimes wgPageName changes value (to Special:Easyimageupload
		 * for instance), so we want to keep the original value around.
		 */
		this.targetPage = wgPageName;

		/**
		 * Set the page being edited as dirty so that a browser warning pops
		 * up about the doc not being saved before the user leaves (via
		 * closing the window, a refresh, following a link, hitting back, etc).
		 */
		this.setPageDirty = function () {
			if (!window.onbeforeunload) {
				$('#h5e-discard-changes')
					.add('#h5e-toolbar-publish')
					.removeClass('h5e-disabled');
				window.onbeforeunload = function() {
					return wfMsg('h5e-changes-to-be-discarded');
				};
			}

			// For new articles, we mark the current step or list item
			if (browser.settings['create-new-article']) {
				var cursorNode = cursor.getCursorNode();
				if (cursorNode) {
					cursorNode = $(cursorNode);
					if (cursorNode.hasClass('h5e-first-unchanged')) {
						cursorNode.removeClass('h5e-first-unchanged');
						cursorNode.parents().removeClass('h5e-first-unchanged');
					}
				}
			}

			// Draft needs to be saved now, so we present to the user the
			// option of saving it now
			if (drafts.draftsInit && !drafts.draftDirty) {
				drafts.createSaveDraftLink();
			}
		}

		/**
		 * Set the page being edited as clean, so there's no browser warning.
		 */
		this.setPageClean = function () {
			$('#h5e-discard-changes')
				.add('#h5e-toolbar-publish')
				.addClass('h5e-disabled');
			window.onbeforeunload = null;
		}

		/**
		 * Check to see whether we've flagged the page being edited as dirty.
		 */
		this.isPageDirty = function () {
			// cast to boolean
			return !!window.onbeforeunload;
		}

		/**
		 * Call to the server to save the html we've edited.  Call the method
		 * onFinish after save is complete.
		 * @access private
		 */
		function saveArticle(onFinish) {
			var contents = $('#bodycontents');
			var editSummary = toolbar.getEditSummary('#h5e-edit-summary-pre');
			var data = contents.html();
			drafts.stopSaveTimer();
			$.post('/Special:Html5editor',
				{ eaction: 'publish-html',
				  target: editor.targetPage,
				  summary: editSummary,
				  edittoken: editor.editToken,
				  edittime: editor.editTime,
				  html: data },
				function (result) {
					if (result && !result['error']) {
						contents.html(result['html']);
					} else if (!result) {
						result = { 'error': wfMsg('h5e-server-connection-error') };
					}
					if (onFinish) onFinish(result['error']);
				},
				'json'
			);

			return false;
		}

		/**
		 * Hide any article templates temporarily while editing.
		 */
		function hideTemplates() {
			var templates = $('.template');

			var vids = templates.filter(function() {
				return $('object', this).length > 0;
			});
			var nonVids = templates.not(vids);

			var tmpl = '<div class="h5e-hidden-video"><p>' + wfMsg('h5e-hidden-video') + '</p></div>';
			vids
				.addClass('opaque')
				.before(tmpl);

			$('.h5e-hidden-video')
				.css('width',vids.width())
				.css('height',vids.height());

			var tmpl = '<div class="h5e-hidden-template"><span>' + wfMsg('h5e-hidden-template') + '</span></div>';
			nonVids
				.fadeOut()
				.after(tmpl);

			$('.h5e-hidden-video p')
				.attr('contenteditable', false);
		}

		/**
		 * Show any previously hidden article templates.
		 */
		function showTemplates() {
			$('.h5e-hidden-video')
				.add('.h5e-hidden-template')
				.remove();
			$('.template').removeClass('opaque');
			$('.template').fadeIn();
		}

		/**
		 * Remove ads from the article, including Google ads and Meebo bar.
		 * Also, overrides the document.write() function so that page won't
		 * blank if an ad calls this function.  This happens if we reload
		 * an article's body after publishing and the body contains ads.
		 */
		function removeAds() {
			gHideAds = true;

			$('.wh_ad').fadeOut();

			// hide Meebo if it's on the page
			if (typeof Meebo != 'undefined') {
				Meebo('hide');
			}

			// We do a big hack to stop Meebo and Google messing with our page if
			// we're anonymous and trying to edit a new article.  We get strange
			// warnings and a blank page sometimes otherwise (from a post-dom-ready
			// document.write() call).
			document.write = function() {};
		}

		/**
		 * Add or remove a Publish box at the bottom of the article (which is
		 * inline in the article, immediately after the editable part).
		 *
		 * @param add true if adding the box, false if removing
		 */
		function addOrRemovePostPublish(add) {
			var tmpl = $('.h5e-inline-publish-template');
			if (add) {
				var copy = tmpl.clone();

				copy.insertAfter('#bodycontents');
				copy.show();

				$('.h5e-publish-publish', copy)
					.unbind('click')
					.click(function() {
						$('#h5e-toolbar-publish').click();
						return false;
					});

				$('.h5e-publish-save-draft', copy)
					.unbind('click')
					.click(function() {
						drafts.clickSaveDraft();
						return false;
					});

				$('.h5e-publish-cancel', copy)
					.unbind('click')
					.click(function() {
						$('.h5e-toolbar-cancel').last().click();
						return false;
					});
			} else {
				if (tmpl.length > 1) {
					tmpl.first()
						.remove();
				}
			}
		}

		/**
		 * Grey or un-grey most non-editable parts of the article.
		 */
		function highlightArticle(highlight) {
			var url, sel;

			// highlight or remove blue around article
			url = editor.CDN_BASE + '/skins/WikiHow/images/' + (highlight ? 'module_caps_hl.png' : 'module_caps.png');
			$('.article_top, .article_bottom').css('background-image', 'url(' + url + ')');
			url = editor.CDN_BASE + '/skins/WikiHow/images/' + (highlight ? 'article_bgs_hl.png' : 'article_bgs.png');
			$('#article, #last_question').css('background-image', 'url(' + url + ')');

			// grey or un-grey stuff
			sel = $('#header, #sidebar, #breadcrumb li, #originators, #article_info, #share_icons, #end_options, #embed_this, #last_question p, #page_rating, #footer_shell, #suggested_titles');
			if (highlight) sel.addClass('opaque');
			else sel.removeClass('opaque');

			// ribbons
			sel = $('#st_wrap .article_inner h2, #article_tools_header h2, #article_info_header')
			if (highlight) {
				sel.wrapInner('<span class="opaque"></span>');
			} else {
				sel = $('span.opaque', sel);
				sel.each(function() {
					var text = $(this).text();
					$(this).parent().text(text);
				});
			}
		}

		/**
		 * Method called when we enter HTML5 editing mode.
		 * @access private
		 */
		function startEditing(section, postEditFunc) {

			// used for debugging:
			/*$('.h5e-rs-console').mouseover(function () {
				var sel = window.getSelection();
				console.log('cursor', sel, sel.anchorNode, sel.anchorOffset, sel.focusNode, sel.focusOffset);
			});*/

			// turn off RC widget using one of their globals
			rcExternalPause = true;

			// make step numbers editable
			$('div.step_num').attr('contenteditable', false);

			// make all of the editable sections contenteditable=true, hide
			// templates, and set up key handler for the steps function
			$('.editable')
				.attr('contenteditable', true)
				.addClass('h5e-std-editing-outline');

			$('img, .mwimg, #video')
				.attr('contenteditable', false);
			$('.caption').attr('contenteditable', true);

			var tmpl = '<a class="h5e-adv-link" href="/index.php?title=' + editor.targetPage + '&action=edit&advanced=true">' + wfMsg('h5e-switch-advanced') + '</a>';

			$('.edit_article_button').after(tmpl);
			attachEditWikitextListener();

			removeAds();
			$('.search_results_article_page').hide();

			hideTemplates();

			// hide the table of contents
			$('#toc').fadeOut();
			$("h2").each(function() {
				if ($(this).html() == "Contents") $(this).fadeOut();
			});

			$('.h5e-tb-save-wrapper').css('display', 'none');
			$('.h5e-tb-function-wrapper').css('display', 'block');

			$('#h5e-edit-summary-pre, #h5e-edit-summary-post')
				.blur();

			toolbar.slide('down');

			// highlight article in blue
			highlightArticle(true);

			var howtoTitle = wfMsg('howto', wgTitle);
			var msg = browser.settings['create-new-article'] ? wfMsg('h5e-creating-title') : wfMsg('h5e-editing-title');
			var editingTitle = wfTemplate(msg, howtoTitle);
			$('.firstHeading').html(editingTitle);

			// listen to clicks in the html5 editing areas
			var callback = $.proxy(cursor.onCursorCheck, cursor);
			$('#bodycontents').click(callback);

			// listen to keystrokes in the html5 editing areas
			if (browser.settings['monitor-keydown-events']) {
				$('#bodycontents').keydown(keyInput.onKeystroke);
			} else {
				$('#bodycontents').keypress(keyInput.onKeystroke);
			}

			inlineLinks.attachTooltipLinkListener();
			hideEditListeners();

			$('.twitter-share-button, .like_button').hide();

			$('a.internal').click(function() { return false; });

			// Add a new related wikihows section if it didn't exist before
			if (!$('#relatedwikihows').length) {
				sections.addOrRemoveSections(['relatedwikihows'], []);
			}
			$('#relatedwikihows').attr('contenteditable', false);

			var related = relatedWikihows.loadRelatedWikihows();
			relatedWikihows.saveRelatedWikihowsInactive(related);

			if (!browser.settings['create-new-article']) {
				images.attachOverlayAll();
				$('#h5e-edit-summary-pre').show();
			} else {
				newArticles.addNewArticleFeatures();
				$('#h5e-edit-summary-pre').hide();
			}

			// add a click handler to all step numbers (including ones that don't
			// exist yet) because users sometimes click directly on them
			$('.step_num').live('click', function() {
				cursor.setCursorAfter(this);
				return false;
			});

			// replace all references with an "edit reference" button
			references.replaceAllOnEdit();

			sections.removeSectionAnchors();
			addOrRemovePostPublish(true);

			drafts.loadEditVars();
			drafts.startSaveTimer();

			// just in case the user navigates away from the page
			editor.setPageClean();

			// in case we are doing manual revert
			if (window.location.search.indexOf('oldid') >= 0) {
				setPageDirty();
			}

			if (section && section != 'relatedwikihows') {
				cursor.focusSection(section);
			}

			if (postEditFunc) {
				postEditFunc();
			}
		}

		/**
		 * TODO: when an editing conflict occurs...
		 */
		function showConflictWarning() {
			$("#h5e-message-console")
				.html("Ooops! Someone (Tderouin) has just edited and saved this page. Do you want to: <br/><a href=''>Save a draft</a>? .... or .... <a href=''>Save the page anyway?</a> ... or ... <a href=''>Continue editing</a>?")
				.show("slow", "swing");
		}

		/**
		 * Display the article saving message.
		 */
		this.displaySavingNotice = function (msg) {
			var savingNotice = $('.h5e-saving-notice');
			$('.saving-message', savingNotice).text(msg);
			utilities.displayCenterFixedDiv(savingNotice);
		}

		/**
		 * Hide article saving message.
		 */
		this.hideSavingNotice = function () {
			$('.h5e-saving-notice').hide();
		}

		/**
		 * Attach a listener to the "Edit wikitext" link so that
		 * the draft is saved then re-opened in the advanced editor.
		 */
		function attachEditWikitextListener() {
			$('.h5e-adv-link').click(function () {
				editor.displaySavingNotice( wfMsg('h5e-saving-draft') );
				drafts.saveDraft(function() {
					drafts.stopSaveTimer();
					editor.displaySavingNotice( wfMsg('h5e-loading-advanced-editor') );
					editor.setPageClean();

					var url = wgServer + '/index.php?title=' + editor.targetPage + '&action=edit&advanced=true';
					if (drafts.draftID) url += '&draft=' + drafts.draftID;
					window.location.href = url;
				});
				return false;
			});
		}

		/**
		 * Method called when we either exit HTML5 editing mode or when we
		 * first go to the page (while not in editing mode).
		 */
		this.stopEditing = function (saveIt, overrideChecks) {
			if (saveIt) {
				if (browser.settings['create-new-article']) {
					overrideChecks = typeof overrideChecks != 'undefined' ? overrideChecks : false;
					if (!overrideChecks && !newArticles.newArticleChecks()) {
						return;
					}
				}

				editor.displaySavingNotice( wfMsg('h5e-saving') );
			}

			$('#bodycontents')
				.attr('contenteditable', 'false')
				.unbind('keypress keydown click');
			$('.editable').attr('contenteditable', false);

			$('.h5e-adv-link').remove();

			showTemplates();

			$('.wh_ad').fadeIn();

			// un-highlight article
			highlightArticle(false);

			$('.twitter-share-button, .like_button').show();

			var howtoTitle = wfMsg('howto', wgTitle);
			$('.firstHeading').html(howtoTitle);

			// present when creating a new article
			if (browser.settings['create-new-article']) {
				$('.h5e-first-add-image').unbind('click');
				$('.h5e-first-remove-section').remove();
			}
			else {
				$('.rounders').css('border', 'none');
				$('.rounders').css('z-index', 0);
			}

			var related = relatedWikihows.loadRelatedWikihows();
			relatedWikihows.saveRelatedWikihowsActive(related);

			// remove related wh section if there aren't any related wikihows
			if (!$(related).length) {
				sections.addOrRemoveSections([], ['relatedwikihows']);
			}

			images.removeImageHoverListeners();
			$('#h5e-mwimg-mouseover').hide();

			addOrRemovePostPublish(false);

			// remove all click handlers from step numbers
			$('.step_num').die('click');

			// replace all "edit reference" links with real references
			references.replaceAllOnSave();

			attachEditListeners();

			if (saveIt) {
				newArticles.removeNewArticleFeatures();
				
				var timer = null;
				var onPublish = function(error) {
					if (timer) {
						clearTimeout(timer);
						timer = null;
					} else {
						return;
					}

					editor.hideSavingNotice();

					if (!error) {
						editor.setPageClean();
					} else {
						$('#dialog-box').html(error);
						$('#dialog-box').dialog({
							width: 250,
							minWidth: 250,
							modal: true,
							zIndex: editor.DIALOG_ZINDEX,
							title: wfMsg('h5e-error')
						});
						toolbar.slide('up');
					}

					attachEditListeners();

					if (!error && browser.settings['create-new-article']) {
						newArticles.postNewArticlePrompt();

						// If we were creating an article, after saving it then 
						// editing it again, on this edit we don't want to add 
						// the article creation features
						browser.settings['create-new-article'] = false;
					}
				};

				// if REST call lasts more than 16 seconds, we fail with an 
				// error message and ignore the result.  we chose 16 seconds 
				// because other stuff happens at 15 seconds.
				timer = window.setTimeout(function () {
					onPublish(wfMsg('h5e-publish-timeout'));
				}, 16000);
				saveArticle(onPublish);

				toolbar.promptEditSummary();
			}

		}

		/**
		 * Add click handlers to the DOM relating to HTML5 editing.
		 */
		function attachClickListeners() {
			editor.stopEditing(false);

			toolbar.attachClickListeners();

			// Add all the listeners to buttons in our editing dialogs
			inlineLinks.attachDialogListeners();
			sections.attachDialogListeners();
			relatedWikihows.attachDialogListeners();
			images.attachConfirmClickListeners();
			drafts.attachDialogListeners();
		}

		/**
		 * Attach the javascript listeners to the edit buttons that launch the
		 * HTML5 editor.
		 */
		function attachEditListeners() {
			$('.edit_article_button')
				.unbind('click')
				.fadeIn()
				.click(function() {
					startEditing('intro');
					return false;
				});

			$('.editsectionbutton, .editsection')
				.css('opacity', '1') // hack to make chrome fade work always
				.unbind('click')
				.fadeIn()
				.click(function() {
					var id = $(this).parent().next().attr('id');
					startEditing(id, function() {
						if (id == 'relatedwikihows') {
							relatedWikihows.showDialog();
						}
					});
					return false;
				});

			$('#tab_edit')
				.unbind('click')
				.removeClass('on')
				.click(function () {
					startEditing('intro');
					return false;
				});
			$('#tab_article').addClass('on');
		}

		/**
		 * De-attach the javascript listeners to the edit buttons that 
		 * launch the HTML5 editor.
		 */
		function hideEditListeners(callback) {
			$('.edit_article_button').fadeOut(400, function() {
				if (callback) {
					try {
						callback();
					} catch(e) {
						console.log('caught infinite error: ', e);
					}
					return false;
				}
			});
			$('.editsectionbutton, .editsection').fadeOut();

			$('#tab_edit')
				.unbind('click')
				.addClass('on')
				.attr('style', '')
				.click(function() {
					return false;
				});
			$('#tab_article').removeClass('on');
		}

		/**
		 * Add hover and mousedown handlers to the DOM relating to 
		 * HTML5 editing.
		 */
		function addButtonListeners() {
			$('.h5e-button').click(
				function (event) {
					event.preventDefault();
				}
			);
		}

		var browser, cursor, drafts, editor, images, 
			inlineLinks, keyInput, newArticles, references,
			relatedWikihows, sections, toolbar, utilities;

		// Bootstrap this module so that it can return its one instance
		// when singleton() is called.
		editor = this;

		/**
		 * Expose this instance to other modules.
		 */
		this.singleton = function() {
			return editor;
		}

		/**
		 * Initialize modules and get singleton references.
		 */
		function initModules() {
			browser = WH.h5e.browser.init();
			cursor = WH.h5e.cursor.init();
			drafts = WH.h5e.drafts.init();
			images = WH.h5e.images.init();
			inlineLinks = WH.h5e.inlineLinks.init();
			keyInput = WH.h5e.keyInput.init();
			newArticles = WH.h5e.newArticles.init();
			references = WH.h5e.references.init();
			relatedWikihows = WH.h5e.relatedWikihows.init();
			sections = WH.h5e.sections.init();
			toolbar = WH.h5e.toolbar.init();
			utilities = WH.h5e.utilities.init();
		}

		/**
		 * Initialize the html5 editor.  Called when page is loaded.
		 * startEditing() is the method called when editing actually starts.
		 *
		 * @access public
		 */
		this.init = function () {
			initModules();

			// remove previous bootstrap click event from edit buttons
			$('.edit_article_button, #tab_edit, .editsectionbutton, .editsection')
				.unbind('click');

			// do we have to load a draft? grab the draft ID if so
			var draftid = drafts.getURLParam();

			var isArticle = wgIsArticle 
				&& wgNamespaceNumber == 0
				&& ($('.noarticletext').length == 0 || draftid > 0);

			if (browser.isHtml5EditingCompatible()
				&& isArticle
				&& !wgForceAdvancedEditor
				&& browser.isArticleEditAllowed())
			{

				browser.populateEditSettings();

				attachClickListeners();
				attachEditListeners();

				addButtonListeners();

				var autoEditNow = browser.settings['create-new-article']
					|| browser.settings['start-editing']
					|| draftid > 0;

				if (whH5EClickedEditButton) {
					var button = whH5EClickedEditButton;
					whH5EClickedEditButton = null;
					if (!autoEditNow) {
						// Pause RC widget
						rcExternalPause = true;

						$(button).click();
					}
				}

				if (autoEditNow) {
					// Pause RC widget
					rcExternalPause = true;

					hideEditListeners(function () {
						var callback = function() {
							startEditing('intro');
						};
						if (draftid > 0) {
							drafts.loadDraft(draftid, callback);
						} else {
							callback();
						}
					});
				}
			} else {
				$('.edit_article_button, #tab_edit, .editsectionbutton, .editsection')
					.click(function () {
						var link = $(this).attr('href');
						window.location.href = link;
					});
			}
		}

	}

	// expose the public interface singleton
	return {
		editor: new Editor()
	};

})(jQuery) ); // exec anonymous function and return resulting class

// HTML5 init is run on the DOM ready event
jQuery(WH.h5e.editor.init);

