// Create WH container obj if necessary
WH = WH || {};
WH.h5e = WH.h5e || {};

jQuery.extend(WH.h5e, (function ($) {

	/**
	 * InlineLinks class
	 *
	 * Deals with inline links and editing them, and the various dialog 
	 * events that relate.
	 */
	function InlineLinks() {

		/**
		 * Insert a new link when the user clicks that button on the toolbar
		 * @access public
		 */
		this.addNew = function () {
			var cursorText = document.getSelection() + ''; // cast to string
			showEditLinkDialog('add', cursorText, '',
				function(text, link) { // when 'Change' button is clicked
					var title = utilities.getArticleFromLink(link);
					var html = '<a href="' + link + '" title="' + title + '">' + text + '</a>';
					if (cursorText == '') html += ' ';
					document.execCommand('inserthtml', false, html);
				}
			);
		}

		/**
		 * Show edit/add link dialog
		 * @access private
		 */
		function showEditLinkDialog(action, linkText, href, onSaveFunc) {
			// a static var to indicate whether we actually want to save
			// the results of the link edit dialog
			showEditLinkDialog.saveLink = false;

			var pos = cursor.saveCursorPos();

			$('#h5e-link-text').val(linkText);
			var article = utilities.getArticleFromLink(href);
			$('#h5e-link-article').val(article);

			var isExternal = article.match(/^http:\/\//);
			$('#h5e-link-article').prop('disabled', isExternal);
			var showHide = isExternal ? 'inline' : 'none';
			var msg = isExternal ? wfMsg('h5e-external-link-editing-disabled') : '';
			$('.h5e-external-link-editing-disabled span')
				.css('display', showHide)
				.html(msg);

			if (action == 'change' && !isExternal) {
				var title = wfMsg('h5e-edit-link');
			} else if (action == 'change' && isExternal) {
				var title = wfMsg('h5e-edit-link-external');
			} else if (action == 'add') {
				var title = wfMsg('h5e-add-link');
			}

			$('#h5e-link-dialog').dialog({
				width: 400,
				minWidth: 400,
				modal: true,
				zIndex: editor.DIALOG_ZINDEX,
				open: function() {
					if (!isExternal) {
						// remove any previous completedivs
						utilities.clearAutocompleteResults();

						// google-style auto-complete for links
						InstallAC(
							document['h5e-ac-link'],
							document['h5e-ac-link']['h5e-link-article'],
							"",
							"/Special:TitleSearch?lim=10",
							"en");

						// customize ac results
						$('#completeDiv')
							.addClass('h5e-auto-complete');
					}

					$('#h5e-link-dialog').dialog('option', 'title', title);
				},
				close: function(evt, ui) {
					utilities.clearAutocompleteResults();

					cursor.focusCurrentSection(false, pos['node']);
					cursor.loadCursorPos(pos, function() {
						if (showEditLinkDialog.saveLink) {
							var text = $('#h5e-link-text').val();
							var article = $('#h5e-link-article').val();
							var link = utilities.getLinkFromArticle(article);

							if (onSaveFunc) {
								var func = function() {
									onSaveFunc(text, link);
								};
								if (browser.settings['needs-delay-after-dialog']) {
									window.setTimeout(func, 0);
								} else {
									func();
								}
							}
						}
					});
				}
			});

			if (linkText == '' || isExternal) {
				$('#h5e-link-text').focus();
			} else {
				$('#h5e-link-article').focus();
			}

		}

		/**
		 * Starts listening to click and keypress events so that tooltips
		 * pop up over links (to allow editing of them).
		 * @access private
		 */
		this.attachTooltipLinkListener = function () {
			$('#bodycontents').bind('keypress click', tooltipLinkListener);
		}

		/**
		 * Called in edit mode when you move to or click on a link, to show the
		 * link tooltip that allows editing.
		 * @access public
		 */
		function tooltipLinkListener() {
			var startNode = $( cursor.getCursorNode() );
			// a static var to store the current link node that's the 
			// primary subject of the call
			tooltipLinkListener.tooltipCurrentLinkNode = utilities.getAnchorNode(startNode);
			var newShowLink = tooltipLinkListener.tooltipCurrentLinkNode ? tooltipLinkListener.tooltipCurrentLinkNode.attr('href') : '';

			// check to see if tooltipOldAnchorEditLink has changed, modify the
			// hrefs and css only if it has
			if (typeof tooltipLinkListener.tooltipOldAnchorEditLink == 'undefined'
				|| newShowLink !== tooltipLinkListener.tooltipOldAnchorEditLink)
			{
				// A static var to show change link bar when key or mouse 
				// onto a link
				tooltipLinkListener.tooltipOldAnchorEditLink = newShowLink;
				var editLink = $('.h5e-edit-link-options-over');
				if (newShowLink) {
					editLink
						.css({
							top: startNode.offset().top - editLink.height() - 25,
							left: startNode.offset().left
						})
						.show()
						.data('node', tooltipLinkListener.tooltipCurrentLinkNode);
					var href = newShowLink;
					var article = utilities.getArticleFromLink(href);
					var linkDisplay = $('#h5e-editlink-display');
					linkDisplay.text( utilities.getArticleDisplay(article) );
					linkDisplay.attr('title', article);
					linkDisplay.attr('target', '_blank');
					linkDisplay.attr('href', href);
					var innerWidth = $('.h5e-edit-link-inner').width() + 31;
					editLink.width(innerWidth + 5);
				} else {
					editLink.fadeOut('fast');
				}
			}
		}

		/**
		 * Attach event listeners to the edit links and related dialogs.
		 * @access public
		 */
		this.attachDialogListeners = function () {
			$('.h5e-edit-link-options-over').hide();

			// Edit link pop-in, called when a user changes a link in the text
			// that's selected
			$('#h5e-editlink-change').click(function() {
				tooltipLinkListener.tooltipOldAnchorEditLink = '';
				var editLink = $('.h5e-edit-link-options-over');
				editLink.hide();

				var href = tooltipLinkListener.tooltipCurrentLinkNode.attr('href');
				var text = tooltipLinkListener.tooltipCurrentLinkNode.text();
				showEditLinkDialog('change', text, href,
					function(text, link) { // when 'Change' button is clicked
						// replace current link and text
						tooltipLinkListener.tooltipCurrentLinkNode.attr('href', link);
						tooltipLinkListener.tooltipCurrentLinkNode.attr('title', utilities.getArticleFromLink(text));
						var startNode = editLink.data('node');
						startNode.text(text);
					}
				);
				return false;
			});

			// Edit link pop-in, when the user chooses to remove the link
			// from some text
			$('#h5e-editlink-remove').click(function() {
				editor.setPageDirty();

				var text = tooltipLinkListener.tooltipCurrentLinkNode.text();
				tooltipLinkListener.tooltipCurrentLinkNode.replaceWith('<span>'+text+'</span>');

				tooltipLinkListener.tooltipOldAnchorEditLink = '';
				$('.h5e-edit-link-options-over').fadeOut('fast');
				return false;
			});

			// Edit link pop-in, when the user clicks cancel (to close pop-in)
			$('#h5e-editlink-cancel').click(function() {
				tooltipLinkListener.tooltipOldAnchorEditLink = '';
				$('.h5e-edit-link-options-over').fadeOut('fast');
				return false;
			});

			// Change link dialog, when user clicks Change button
			$('#h5e-link-change').click(function() {
				editor.setPageDirty();

				$('#h5e-link-article').removeClass('h5e-url-warning');
				var article = $.trim( $('#h5e-link-article').val() );
				if (!$('#h5e-link-article').prop('disabled') &&
					article.indexOf('/') >= 0 && article.indexOf(' ') < 0)
				{
					$('#h5e-link-article')
						.addClass('h5e-url-warning')
						.focus();

					var msg = wfMsg('h5e-external-links-warning');
					$('.h5e-external-link-editing-disabled span')
						.css('display', 'inline')
						.html(msg);

					return false;
				} else {
					showEditLinkDialog.saveLink = true;
					$('#h5e-link-dialog').dialog('close');
				}
			});

			// Change link dialog, when user clicks Cancel button
			$('#h5e-link-cancel').click(function() {
				$('#h5e-link-dialog').dialog('close');
				return false;
			});

			$('#h5e-link-preview').click(function() {
				var article = $('#h5e-link-article').val();
				var link = utilities.getLinkFromArticle(article);
				$('#h5e-link-preview').attr('href', link);
			});

			$('#h5e-link-article').keypress(function(evt) {
				$('#h5e-link-article').removeClass('h5e-url-warning');
				if (evt.which == 13) { // 'Enter' key pressed
					$('#h5e-link-change').click();
					return false;
				}
			});

			$('.h5e-link-external-help a').click(function() {

				$('#h5e-external-url-msg-dialog').dialog({
					width: 250,
					minWidth: 250,
					zIndex: editor.DIALOG_ZINDEX,
					modal: true
				});

				return false;
			});

			$('#h5e-external-url-msg-dialog input').click(function() {
				$('#h5e-external-url-msg-dialog').dialog('close');
				return false;
			});
		}

	}

	var browser, cursor, drafts, editor, images, 
		inlineLinks, keyInput, newArticles, references,
		relatedWikihows, sections, toolbar, utilities;

	function singleton() {
		inlineLinks = inlineLinks || new InlineLinks();
		return inlineLinks;
	}

	function init() {
		// get singletons
		browser = WH.h5e.browser.singleton();
		cursor = WH.h5e.cursor.singleton();
		drafts = WH.h5e.drafts.singleton();
		editor = WH.h5e.editor.singleton();
		images = WH.h5e.images.singleton();
		inlineLinks = WH.h5e.inlineLinks.singleton();
		keyInput = WH.h5e.keyInput.singleton();
		newArticles = WH.h5e.newArticles.singleton();
		references = WH.h5e.references.singleton();
		relatedWikihows = WH.h5e.relatedWikihows.singleton();
		sections = WH.h5e.sections.singleton();
		toolbar = WH.h5e.toolbar.singleton();
		utilities = WH.h5e.utilities.singleton();
		return singleton();
	}

	// expose the public interface
	return {
		inlineLinks:
		  { init: init,
			singleton: singleton }
	};

})(jQuery) ); // exec anonymous function and return resulting class

