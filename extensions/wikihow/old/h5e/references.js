// Create WH container obj if necessary
WH = WH || {};
WH.h5e = WH.h5e || {};

jQuery.extend(WH.h5e, (function ($) {

	/**
	 * References module
	 *
	 * Add, remove or process references in an article.
	 */
	function References() {

		/**
		 * Add a new reference when the user clicks the Add Ref button
		 * on the toolbar.
		 */
		this.addNew = function() {
			editor.setPageDirty();

			var tmpl = '<a id="h5e-new-ref" class="h5e-button edit-reference" href="#">' + wfMsg('h5e-edit-ref') + '</a>';
			var refhtml = wfTemplate(tmpl, wfMsg('h5e-ref'));

			// if text is selected, de-select it and place cursor at end
            var select = document.getSelection();
			if (select.length > 0) {
				window.getSelection().collapseToEnd();
			}

			document.execCommand('inserthtml', false, refhtml);

			var newref = $('#h5e-new-ref');
			prepEditRefNode(newref, '');
			newref.attr('id', '');
			newref.click();
		}

		/**
		 * Pull the original reference text out of the button of the article's
		 * reference list.
		 * @access private
		 */
		function getOrigRefText(refnode) {
			var refid = $('a', refnode).attr('href').replace(/^#/, '');
			var li = $('li#' + refid).clone();
			$('a:first', li).remove();
			var reftext = '';
			var specialFlattenRef = function(i,n) {
				if (n.nodeName == '#text') {
					reftext += n.textContent;
				} else if ($(n).is('a')) {
					reftext += $(n).attr('href');
				} else {
					$(n).contents().each(specialFlattenRef);
				}
			};
			li.contents().each(specialFlattenRef);
			reftext = $.trim(reftext);
			return reftext;
		}

		/**
		 * Replace all [ref-num] links with Edit Ref buttons when the 
		 * article is edited.
		 */
		this.replaceAllOnEdit = function () {
			$('sup.reference').each(function(i, refnode) {
				var reftext = getOrigRefText(refnode);
				var tmpl = '<a id="h5e-new-ref" class="h5e-button edit-reference" href="#">' + wfMsg('h5e-edit-ref') + '</a>';
				var newref = $(tmpl);
				$(refnode).replaceWith(newref);
				prepEditRefNode(newref, reftext);
			});

			var msg = wfMsg('h5e-references-removed');
			var html = $('<div class="sources-removed" contenteditable="false">' + msg + '</div>');
			$('ol.references').replaceWith(html);
		}

		/**
		 * Replace all Edit Ref buttons with [ref] links when the article
		 * is saved.
		 */
		this.replaceAllOnSave = function () {
			$('.edit-reference').each(function(i, ref) {
				var reftext = $(ref).data('editref');
				var refhtml = $('<sup><a href="#" onclick="return false;">[ref]</a></sup><input type="hidden" id="h5e-ref-' + i + '" value="' + reftext + '"/>');
				$(ref).replaceWith(refhtml);
			});
		}

		/**
		 * Add click listeners, etc, to add/edit reference dialog
		 * @access private
		 */
		function prepEditRefNode(newref, reftext) {
			newref.data('editref', reftext);

			newref.click(function() {
				var button = this;
				var reftext = $(button).data('editref');
				$('#ref-edit')
					.val(reftext)
					.unbind('keypress')
					.keypress(function(evt) {
						if (evt.which == 13) { // 'Enter' pressed
							$('#ref-edit-change').click();
							return false;
						}
					});


				$('#ref-edit-change')
					.unbind('click')
					.click(function() {
						editor.setPageDirty();
						$(button).data('editref', $('#ref-edit').val() );
						$('#edit-ref-dialog').dialog('close');
					});

				$('#ref-edit-cancel')
					.unbind('click')
					.click(function() {
						$('#edit-ref-dialog').dialog('close');
						return false;
					});

				var pos = cursor.saveCursorPos();
				$('#edit-ref-dialog').dialog({
					width: 400,
					minWidth: 400,
					modal: true,
					zIndex: editor.DIALOG_ZINDEX,
					open: function() {
						$('#h5e-toolbar-ref').addClass('h5e-active');

						// Set correct dialog title
						if ($('#ref-edit').val() == '') {
							var title = wfMsg('h5e-add-reference');
							var button = wfMsg('h5e-add');
						} else {
							var title = wfMsg('h5e-edit-reference');
							var button = wfMsg('h5e-change');
						}
						$('#edit-ref-dialog').dialog('option', 'title', title);
						$('#ref-edit-change').val(button);

						$('#ref-edit').focus();
					},
					close: function(evt, ui) {
						$('#h5e-toolbar-ref').removeClass('h5e-active');

						cursor.focusCurrentSection(false, pos['node']);
						cursor.loadCursorPos(pos);
						if ($.trim( $(button).data('editref') ) == '') {
							$(button).remove();
						}
					}
				});

				return false;
			});

			newref.attr('contenteditable', 'false');
		}

	}

	var browser, cursor, drafts, editor, images, 
		inlineLinks, keyInput, newArticles, references,
		relatedWikihows, sections, toolbar, utilities;

	function singleton() {
		references = references || new References();
		return references;
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
		references:
		  { init: init,
			singleton: singleton }
	};

})(jQuery) ); // exec anonymous function and return resulting class

