// Create WH container obj if necessary
WH = WH || {};
WH.h5e = WH.h5e || {};

jQuery.extend(WH.h5e, (function ($) {

	/**
	 * NewArticles module
	 *
	 * New articles have different features, and these
	 * methods add them, or provide some service to them.
	 */
	function NewArticles() {

		/**
		 * Adds features to this article if we're creating the article.
		 */
		this.addNewArticleFeatures = function () {

			// Only display "Add Image" class to action for logged in users
			var isAnon = !wgUserID;
			if (isAnon) {
				$('.h5e-first-add-image').remove();
			} else {
				$('.h5e-first-add-image').click(function () {
					images.addImage(function () {
						$('.h5e-first-add-image').remove();
					}, true);
					return false;
				});
			}

			// Add "Remove section" links to optional sections
			$('#bodycontents h2').each(function() {
				var heading = $('span', this).text();
				if (heading != wfMsg('Steps') && heading != wfMsg('relatedwikihows')) {
					$(this).append('<span class="h5e-first-remove-section"><a href="#" class="h5e-first-remove-section-a">' + wfMsg('h5e-remove-section') + '</a></span>');
				}
			});

			// Add actions to "Remove section" links in the optional sections
			$('.h5e-first-remove-section').click(function() {
				editor.setPageDirty();
				removeSectionConfirm(this);
				return false;
			});

			// Make it so that default article text goes away easily
			$('.h5e-first-remove-onclick').click(function() {
				if (browser.settings['cursor-place-before-delete-node']) {
					cursor.setCursorBefore(this);
					$(this).remove();
				} else {
					var prev = $(this).prev();
					var par = $(this).parent();
					$(this).remove();
					if (prev.length) {
						cursor.setCursorAfter(prev);
					} else {
						cursor.setCursorNode(par);
					}
				}
				return false;
			});

			// Populate default edit summary
			$('#h5e-edit-summary-pre')
				.removeClass('h5e-example-text')
				.val(wfMsg('h5e-create-new-article'));
		}

		/**
		 * Removes special "features" added to the article if it was being
		 * created.
		 */
		this.removeNewArticleFeatures = function () {
			$('.h5e-first-unchanged').remove();

			var sourcesText = $('#sources').text();
			if ($.trim(sourcesText) == wfMsg('h5e-new-source')) {
				$('#sources').remove();
			}

			$('#bodycontents h2').each(function() {
				var h2 = $(this), next = h2.next();
				if (!next.length || next.is('h2')) h2.remove();
			});
		}

		/**
		 * Brings up the section removal confirmation
		 * @access private
		 */
		function removeSectionConfirm(obj) {
			var removeID = $(obj).parent().next().attr('id');

			$('#h5e-sections-confirm').dialog({
				width: 400,
				minWidth: 400,
				modal: true,
				zIndex: editor.DIALOG_ZINDEX,
				close: function(evt, ui) {
					cursor.focusCurrentSection(false);
				}
			});

			// listen for the answer
			$('#h5e-sections-confirm-remove')
				.unbind('click')
				.click(function() {
					sections.addOrRemoveSections([], [removeID], false);
					$('#h5e-sections-confirm').dialog('close');
					return false;
				});
			$('#h5e-sections-confirm-cancel')
				.unbind('click')
				.click(function() {
					$('#h5e-sections-confirm').dialog('close');
					return false;
				});
		}

		/**
		 * Check a newly created article to see if it passes a series of
		 * tests that wikiHow has for new articles to make sure they
		 * have a minimum level of quality.
		 */
		this.newArticleChecks = function () {
			var bodyCopy = $('#bodycontents').clone();
			$('.h5e-first-unchanged', bodyCopy).remove();
			$('h2', bodyCopy).each(function() {
				var h2 = $(this), next = h2.next();
				if (!next.length || next.is('h2')) h2.remove();
			});
			var intro = bodyCopy.children().first();
			var steps = $('#steps', bodyCopy);
			$('h2', bodyCopy).remove();

			var text = {
				intro: intro.text(),
				steps: steps.text(),
				full: bodyCopy.text()
			};
			for (var i in text) {
				if (text.hasOwnProperty(i)) {
					text[i] = $.trim( text[i].replace(/(\s|\n)+/g, ' ') );
				}
			}

			var countWords = function(text) {
				text = $.trim(text);
				if (text) {
					var words = text.split(' ');
					return words.length;
				} else {
					return 0;
				}
			};

			var warnURLParams = '';
			var introWords = countWords(text['intro']);
			if (introWords <= 4) {
				warnURLParams = '?warn=intro&words=' + introWords;
			} else {
				var allWords = countWords(text['full']);
				if (allWords <= 100) {
					warnURLParams = '?warn=words&words=' + allWords;
				} else {
					var upCount = text['full'].match(/[A-Z]/g).length;
					var lowCount = text['full'].match(/[a-z]/g).length;
					var puncCount = text['full'].match(/[-!.,?]/g).length;
					var ratio = upCount / (upCount + lowCount);
					if (ratio >= 0.10) {
						var rounded = Math.round(ratio*1000)/1000;
						warnURLParams = '?warn=caps&ratio=' + rounded;
					} else if (puncCount <= 10) {
						warnURLParams = '?warn=sentences&sen=' + puncCount;
					}
				}
			}

			if (warnURLParams) {
				$('#dialog-box').html('');
				$('#dialog-box').load('/Special:CreatepageWarn' + warnURLParams, function() {
					$('#dialog-box input')
						.attr('onclick', '')
						.unbind('click')
						.click(function() {
							clickshare(28);
							$('#dialog-box').dialog('close');
							return false;
						});
					$('#dialog-box a').last()
						.attr('onclick', '')
						.unbind('click')
						.click(function() {
							clickshare(29);
							$('#dialog-box').dialog('close');
							editor.stopEditing(true, true);
							return false;
						});
				});
				$('#dialog-box').dialog({
					width: 600,
					modal: true,
					zIndex: editor.DIALOG_ZINDEX,
					title: wfMsg('warning')
				});
				return false;
			} else {
				return true;
			}
		}

		/**
		 * After publishing a new article, display this message.
		 */
		this.postNewArticlePrompt = function () {
			var isAnon = !!wgUserID;
			var dialogWidth = isAnon ? 750 : 560;
			$('#dialog-box').html('');
			$('#dialog-box').load('/Special:CreatepageFinished');
			$('#dialog-box').dialog({
				width: dialogWidth,
				modal: true,
				zIndex: editor.DIALOG_ZINDEX,
				title: wfMsg('congrats-article-published')
			});
		}

	}

	var browser, cursor, drafts, editor, images, 
		inlineLinks, keyInput, newArticles, references,
		relatedWikihows, sections, toolbar, utilities;

	function singleton() {
		newArticles = newArticles || new NewArticles();
		return newArticles;
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
		newArticles:
		  { init: init,
			singleton: singleton }
	};

})(jQuery) ); // exec anonymous function and return resulting class

