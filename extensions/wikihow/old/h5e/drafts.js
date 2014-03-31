// Create WH container obj if necessary
WH = WH || {};
WH.h5e = WH.h5e || {};

jQuery.extend(WH.h5e, (function ($) {

	/**
	 * Drafts module
	 *
	 * Handles the user-prompted and automatic saving and 
	 * processing of draft article saves
	 */
	function Drafts() {

		var DRAFTS_TIMER_SECS = 60; // in seconds

		// private variables
		var draftToken = null,
			saveDraftTimer = null,
			oldDraftID = 0;

		// public variables
		this.draftsInit = false;
		this.draftDirty = false;
		this.draftID = null;

		/**
		 * Load "edit" variables from the server, such as whether or not there's
		 * a draft available to load, and the edittoken and edittime that 
		 * stop XSRF attacks and prevent conflicts.
		 *
		 * @access public
		 */
		this.loadEditVars = function () {
			// grab the js variables from the server
			$.post('/Special:Html5editor',
				{ eaction: 'get-vars',
				  target: editor.targetPage
				},
				function (result) {
					if (result) {
						draftToken = result['drafttoken'];
						editor.editToken = result['edittoken'];
						editor.editTime = result['edittime'];
						oldDraftID = result['olddraftid'];
						if (oldDraftID > 0) {
							var saveDraftLink = $('#h5e-toolbar-savedraft');
							if (saveDraftLink.html() == '') {
								var tmpl = '<a href="#">$1</a>';
								saveDraftLink
									.html( wfTemplate(tmpl, wfMsg('h5e-loaddraft')) )
									.unbind('click')
									.click(function() {
										loadDraftConfirm(oldDraftID);
										return false;
									});
							}
						}
					}
				},
				'json'
			);
		};

		/**
		 * Starts the save draft timer so that drafts automatically will be
		 * saved every DRAFTS_TIMER_SECS seconds.
		 *
		 * @access public
		 */
		this.startSaveTimer = function () {
			if (wgUserID && !saveDraftTimer) {
				var callback = $.proxy(this.saveDraft, this);
				saveDraftTimer = window.setInterval(callback, DRAFTS_TIMER_SECS * 1000);
				this.draftsInit = true;
			}
		};

		/**
		 * Stops the save draft timer so that drafts stop being saved
		 * automatically.
		 *
		 * @access public
		 */
		this.stopSaveTimer = function () {
			if (saveDraftTimer) {
				window.clearTimeout(saveDraftTimer);
				saveDraftTimer = null;
			}
		};

		/**
		 * Change the link in the toolbar to allow a user to save a draft
		 * right away when they click it.
		 *
		 * @access public
		 */
		this.createSaveDraftLink = function () {
			var tmpl = '<a href="#">$1</a>';
			this.draftDirty = true;
			var that = this;
			$('#h5e-toolbar-savedraft')
				.html( wfTemplate(tmpl, wfMsg('h5e-savedraft')) )
				.unbind('click')
				.click(function() {
					that.saveDraft();
					return false;
				});
		};

		/**
		 * Extracts the draftid parameter from the URL if there is one.
		 * 
		 * @return the draftid parameter as an int, or 0 if one isn't found
		 * @access public
		 */
		this.getURLParam = function () {
			var draftMatch = window.location.search.match(/draft=([0-9]+)/);
			var draftid = 0;
			if (draftMatch) {
				draftid = parseInt(draftMatch[1], 10);
			}
			return draftid;
		};

		/**
		 * Save the draft for the article to the server
		 *
		 * @access public
		 */
		this.saveDraft = function (callback) {
			// only save the draft if the page has been changed
			if (!this.draftsInit || !editor.isPageDirty() || !this.draftDirty) {
				if ($.isFunction(callback)) callback();
				return;
			}

			this.stopSaveTimer();

			var contents = $('#bodycontents').clone();
			$('.h5e-rel-wh-edit', contents).remove();
			$('.h5e-first-unchanged', contents).remove();
			var data = contents.html();

			var tmpl = '<span class="h5e-nonlink">$1</span>';
			var saveDraftLink = $('#h5e-toolbar-savedraft');
			saveDraftLink
				.html( wfTemplate(tmpl, wfMsg('h5e-saving-lc')) )
				.unbind('click')
				.click( function() { return false; } );
			var editSummary = toolbar.getEditSummary('#h5e-edit-summary-pre');
			var that = this;
			$.post('/Special:Html5editor',
				{ eaction: 'save-draft',
				  target: editor.targetPage,
				  summary: editSummary,
				  edittoken: editor.editToken,
				  drafttoken: draftToken,
				  edittime: editor.editTime,
				  draftid: this.draftID,
				  editsummary: editSummary,
				  html: data },
				function (result) {
					var isError = !result || result['error'];
					if (!isError) {
						that.draftDirty = false;
						contents.html(result['html']);
					}

					that.startSaveTimer();

					var newDraftID = result && result['draftid'];
					if (newDraftID) that.draftID = newDraftID;

					var currentLinkText = $('span', saveDraftLink).html();
					if (currentLinkText == wfMsg('h5e-saving-lc')) {
						var msg = wfMsg('h5e-draftsaved');
						saveDraftLink.html( wfTemplate(tmpl, msg) );
					}

					if ($.isFunction(callback)) callback();
				},
				'json'
			);
		}

		/**
		 * Click the save draft link in the toolbar, if it exists
		 *
		 * @access public
		 */
		this.clickSaveDraft = function () {
			var saveDraftLink = $('#h5e-toolbar-savedraft');
			if (saveDraftLink.length) {
				var a = $('a', saveDraftLink);
				if (a.length && a.html() == wfMsg('h5e-savedraft')) {
					a.click();
				}
			}
		};

		/**
		 * Displays a dialog to the user after they click the "load draft" link
		 *
		 * @access private
		 */
		function loadDraftConfirm(id) {
			// static var to store the draft ID to load (which happens in a
			// different function)
			loadDraftConfirm.loadDraftID = id;

			$('#h5e-loaddraft-confirm').dialog({
				width: 400,
				minWidth: 400,
				modal: true,
				zIndex: editor.DIALOG_ZINDEX,
				close: function(evt, ui) {
					cursor.focusCurrentSection(false);
				}
			});
		}

		/**
		 * Loads the draft for the article from the server
		 *
		 * @param id The draft ID -- a number
		 */
		this.loadDraft = function (id, callbackFunc) {
			var contents = $('#bodycontents');
			$.post('/Special:Html5editor',
				{ eaction: 'load-draft',
				  userid : wgUserID,
				  target: editor.targetPage,
				  draftid: id
				},
				function (result) {
					if (!result['error']) {
						contents.html(result['html']);

						// populate these sections of the draft after it's
						// loaded, if there are empty sections
						$(['tips', 'warnings', 'sources']).each(function () {
							var sectionName = this;
							var section = $('#' + sectionName);
							if (section.length) {
								// is the section empty?
								if ( ! $.trim(section.text()) ) {
									var msg;
									if (sectionName == 'tips')
										msg = wfMsg('h5e-new-tip');
									if (sectionName == 'warnings')
										msg = wfMsg('h5e-new-warning');
									if (sectionName == 'sources')
										msg = wfMsg('h5e-new-source');
									var tmpl = '<ul><li>' + msg + '</li></ul>';
									$('#' + sectionName).html(tmpl);
								}
							}
						});
					} else {
						contents.html('');
						drafts.stopSaveTimer();
						editor.stopEditing(false, true); 
						utilities.showError(result['error']);
						return;
					}
					if ($.isFunction(callbackFunc)) {
						callbackFunc();
					}
				},
				'json'
			);
		};

		/**
		 * Dialog event listeners relating to the drafts dialogs.
		 */
		this.attachDialogListeners = function () {
			// listen for the answer from the user
			$('#h5e-loaddraft-confirm-load').click(function() {
				// discard any article changes, no prompt
				editor.setPageClean();

				$(this)
					.prop('disabled', true)
					.unbind('click');

				var url = wgServer + '/index.php?title=' + editor.targetPage + '&h5e=true&draft=' + loadDraftConfirm.loadDraftID + '&create-new-article=true';
				$('#h5e-loaddraft-confirm').dialog('close');
				editor.displaySavingNotice( wfMsg('h5e-loading-draft') );
				window.location.href = url;
				return false;
			});
			$('#h5e-loaddraft-confirm-cancel').click(function() {
				$('#h5e-loaddraft-confirm').dialog('close');
				return false;
			});
		};

	}

	var browser, cursor, drafts, editor, images, 
		inlineLinks, keyInput, newArticles, references,
		relatedWikihows, sections, toolbar, utilities;

	function singleton() {
		drafts = drafts || new Drafts();
		return drafts;
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
		drafts:
		  { init: init,
			singleton: singleton }
	};

})(jQuery) ); // exec anonymous function and return resulting class

