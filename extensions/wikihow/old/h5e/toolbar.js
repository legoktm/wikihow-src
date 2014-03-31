// Create WH container obj if necessary
WH = WH || {};
WH.h5e = WH.h5e || {};

jQuery.extend(WH.h5e, (function ($) {

	/**
	 * Toolbar module
	 *
	 * A collection of methods that relate to the functions of the
	 * toolbar that slides down when editing.
	 */
	function Toolbar() {

		/**
		 * Add click listeners to the editing toolbar elements.
		 */
		this.attachClickListeners = function () {
			var that = this;

			// Toolbar add link button clicked.  Converts selected text 
			// into a link, or inserts a new link with default text if 
			// no text is selected.
			$('#h5e-toolbar-a').click(function () {
				if (!cursor.focusCurrentSection(true)) return;
				inlineLinks.addNew();
			});

			$('#h5e-toolbar-img').click(function () {
				images.addImage();
			});

			$('#h5e-toolbar-italics').click(function () {
				if (!cursor.focusCurrentSection(true)) return;
				editor.setPageDirty();
				document.execCommand('italic', false, '');
				cursor.onCursorCheck();
			});

			$('#h5e-toolbar-indent').click(doIndent);
			$('#h5e-toolbar-outdent').click(function() {
				// exit if button is disabled
				if ($(this).hasClass("h5e-disabled")) return;
				doOutdent();
			});

			// brings up the section add pop up
			$('#h5e-toolbar-section').click(function () {
				sections.displaySectionsDialog();
			});

			// toolbar add a reference button was clicked
			$('#h5e-toolbar-ref').click(function () {
				if (!cursor.focusCurrentSection(true)) return;
				references.addNew();
			});

			// toolbar button to show Related wikiHows dialog
			$('#h5e-toolbar-related').click(function () {
				relatedWikihows.showDialog();
			});

			$('#h5e-toolbar-publish').click(function() {
				//exit if button is disabled
				if ($(this).hasClass("h5e-disabled")) return;

				cursor.focusCurrentSection(false);
				editor.stopEditing(true);
				return false;
			});

			$('.h5e-toolbar-cancel').click(function() {
				//exit if button is disabled
				if ($(this).hasClass("h5e-disabled")) return;

				drafts.stopSaveTimer();

				var displayNotice = false;
				if (browser.settings['create-new-article']) {
					displayNotice = true;
					window.location.href = '/Special:CreatePage';
				} else if (editor.isPageDirty()) {
					displayNotice = true;
					// force a refresh
					window.location.href = window.location.href;
				} else {
					editor.stopEditing(false);
					that.slide('up');
				}

				if (displayNotice) {
					editor.displaySavingNotice( wfMsg('h5e-canceling-edit') );
					// dismiss after 10 seconds, if user hasn't left page
					window.setTimeout(editor.hideSavingNotice, 10000);
				}

				return false;
			});

			$('#h5e-edit-summary-pre').blur(function() {
				if ($('#h5e-edit-summary-pre').val() == '') {
					$('#h5e-edit-summary-pre')
						.val( wfMsg('h5e-enter-edit-summary') )
						.addClass('h5e-example-text');
				}
			});
			$('#h5e-edit-summary-pre').focus(function() {
				if ($('#h5e-edit-summary-pre').val() == wfMsg('h5e-enter-edit-summary')) {
					$('#h5e-edit-summary-pre')
						.removeClass('h5e-example-text')
						.val('');
				}
			});
			$('#h5e-edit-summary-pre').keypress(function(evt) {
				if (evt.which == 13) { // 'Enter' pressed
					$('#h5e-toolbar-publish').click();
					return false;
				}
			});

			$('#h5e-edit-summary-post').blur(function() {
				if ($('#h5e-edit-summary-post').val() == '') {
					$('#h5e-edit-summary-post')
						.val( wfMsg('h5e-edit-summary-examples') )
						.addClass('h5e-example-text');
				}
			});
			$('#h5e-edit-summary-post').focus(function() {
				if ($('#h5e-edit-summary-post').val() == wfMsg('h5e-edit-summary-examples')) {
					$('#h5e-edit-summary-post')
						.removeClass('h5e-example-text')
						.val('');
				}
			});

			$('#h5e-edit-summary-save').click(function() {
				var postSaveSummaryFunc = function() {
					that.slide('up', function() {
						$('#h5e-edit-summary-pre')
							.add('#h5e-edit-summary-post')
							.val('');
					});
				};

				var editSummary = that.getEditSummary('#h5e-edit-summary-post');
				if (editSummary) {
					$.post('/Special:Html5editor',
						{ eaction: 'save-summary',
						  target: editor.targetPage,
						  summary: editSummary
						},
						postSaveSummaryFunc
					);
				} else {
					postSaveSummaryFunc();
				}
			});
			$('#h5e-edit-summary-post').keypress(function(evt) {
				if (evt.which == 13) { // 'Enter' pressed
					$('#h5e-edit-summary-save').click();
					return false;
				}
			});

			$('.h5e-error-confirm').click(function () {
				$('.h5e-error-dialog').dialog('close');
				return false;
			});
		}

		/**
		 * Called when toolbar indent button is used.
		 * @access private
		 */
		function doIndent() {
			if (!cursor.focusCurrentSection(true)) return;
			editor.setPageDirty();

			// get the existing list for this section, if there is one
			var currentSection = cursor.getCurrentCursorSection();
			if (currentSection != 'intro') {
				var appendNode = cursor.getCursorLi();
				var existingList = $('ul', appendNode).first();
			} else {
				var node = $( cursor.getCursorNode() );
				var parents = node.parentsUntil('#bodycontents * ul');
				if (parents.length) {
					var rootNode = $(parents).last();
					if (rootNode.is('li')) {
						var existingList = rootNode.parent();
					} else {
						var existingList = [];
					}
				} else {
					var existingList = node.parent();
				}

				var appendNode = node.parentsUntil('#bodycontents').last();
				if (!appendNode.length) {
					appendNode = node;
				}
			}

			// create a new list node
			var newItem = $('<li><br/></li>');
			var newList = $('<ul></ul>');
			newList.append(newItem);
			var br = $('br', newItem);

			// if there's an existing list in the section, we want to append to
			// this list rather than creating a new <ul> after it
			if (existingList.length) {
				var node = $( cursor.getCursorNode() );
				var li, parentLi = node.parentsUntil('ul').last();
				if (parentLi.length) {
					li = parentLi.first();
				} else {
					li = node;
				}
				// are we already inside a list?
				if (li.is('li')) {
					// if so, add bullet point to that list
					li.append(newList);
				} else {
					// if not, append to top level bullets
					existingList.append(newItem);
				}
			} else {
				$(appendNode).append(newList);
			}
			cursor.setCursorBefore(br);

			cursor.onCursorCheck();
		}

		/**
		 * Toolbar "outdent" button
		 * @access private
		 */
		function doOutdent() {
			if (!cursor.focusCurrentSection(true)) return;
			editor.setPageDirty();

			if (!cursor.isStepsCursorTopLevel()) {
				document.execCommand('outdent', false, '');
				cursor.onCursorCheck();
			}
		}

		/**
		 * Get the edit summary from the given selector (representing a unique
		 * html input element).
		 */
		this.getEditSummary = function (selector) {
			var editSummary = $(selector).val();
			editSummary = $.trim(editSummary);
			if (editSummary == wfMsg('h5e-enter-edit-summary') ||
				editSummary == wfMsg('h5e-edit-summary-examples'))
			{
				editSummary = '';
			}
			return editSummary;
		}

		/**
		 * Add enough pixels to the top of the page so that the toolbar
		 * fits up there while editing.  (Or remove these extra pixels if
		 * no longer editing.)
		 *
		 * @param direction a string, either 'down' or 'up'
		 * @access private
		 */
		function slideWholePage(direction) {
			// add or remove pixels to the top of the first div on the page
			// so that it doesn't feel like the edit bar is covering anything
			// that can't be found any longer
			var topMargin = $('#header').css('margin-top');
			topMargin = parseInt(topMargin.replace(/px/), 10);
			if (direction == 'down' && topMargin < editor.TOOLBAR_HEIGHT_PIXELS ||
				direction == 'up' && topMargin > -editor.TOOLBAR_HEIGHT_PIXELS)
			{
				var sign = direction == 'down' ? '+' : '-';
				$('#header').animate({'margin-top': sign + '=' + editor.TOOLBAR_HEIGHT_PIXELS + 'px'}, 'slow');
			}
		}

		// This variable tracks whether the toolbar is showing so 
		// that is can't be "double shown" or "double hidden" as 
		// I've seen in certain weird error cases
		var isToolbarShowing = false;

		/**
		 * Bring in the editing toolbar (or hide it).
		 *
		 * @param direction a string, either 'down' or 'up'
		 * @param func callback function after it's hidden
		 */
		this.slide = function (direction, func) {
			if (direction != 'up' && direction != 'down')
				throw 'bad param: direction';

			if (!func) func = function() {};

			var willToolbarShow = direction == 'down';
			if (isToolbarShowing == willToolbarShow) {
				return;
			}
			isToolbarShowing = willToolbarShow;

			if (direction == 'down') {
				$('#h5e-editing-toolbar')
					.css('top', '-' + editor.TOOLBAR_HEIGHT_PIXELS + 'px')
					.show()
					.animate(
						{'top': '+=' + editor.TOOLBAR_HEIGHT_PIXELS + 'px'},
						{ duration: 'slow',
						  complete: func }
					);
			} else {
				$('#h5e-editing-toolbar')
					.css('top', '0')
					.animate(
						{'top': '-=' + editor.TOOLBAR_HEIGHT_PIXELS + 'px'},
						{ duration: 'slow',
						  complete: function() { $(this).hide(); func(); } }
					);
			}
			slideWholePage(direction);
		}

		/**
		 * If the edit summary has already been provided by the user,
		 * this method hides the toolbar.  If there's no edit summary
		 * yet, this method hides the edit functionality and prompts
		 * the user more directly (and hides this prompt after
		 * 15 seconds.
		 */
		this.promptEditSummary = function() {
			var that = this;
			var editSummary = that.getEditSummary('#h5e-edit-summary-pre');
			if (!editSummary) {
				$('.h5e-tb-function-wrapper').fadeOut('fast', function() {
					$('.h5e-tb-save-wrapper').fadeIn();
				});

				// If the user hasn't started entering an edit summary or
				// put focus on the edit summary, hide the box
				window.setTimeout(function () {
					if ($('#h5e-edit-summary-post').val() == wfMsg('h5e-edit-summary-examples')
						&& $('#h5e-edit-summary-post').hasClass('h5e-example-text')
						&& $('#h5e-edit-summary-post:visible').length
						&& $('#h5e-editing-toolbar:visible').length)
					{
						that.slide('up');
					}
				}, 15000);
			} else {
				that.slide('up', function() {
					$('#h5e-edit-summary-pre')
						.add('#h5e-edit-summary-post')
						.val('');
				});
			}
		}

	}

	var browser, cursor, drafts, editor, images, 
		inlineLinks, keyInput, newArticles, references,
		relatedWikihows, sections, toolbar, utilities;

	function singleton() {
		toolbar = toolbar || new Toolbar();
		return toolbar;
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
		toolbar:
		  { init: init,
			singleton: singleton }
	};

})(jQuery) ); // exec anonymous function and return resulting class

