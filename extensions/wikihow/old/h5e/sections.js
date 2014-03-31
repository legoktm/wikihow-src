// Create WH container obj if necessary
WH = WH || {};
WH.h5e = WH.h5e || {};

// wikiHow's HTML 5 editor
jQuery.extend(WH.h5e, (function ($) {

	/**
	 * Sections module
	 *
	 * Methods relating to displaying and manipulating the
	 * add/remove sections dialog and alternate methods.
	 */
	function Sections() {

		/**
		 * List the possible wikihow sections
		 * @access private
		 */
		function getSectionsList() {
			var sectionsList = [
				{'key': 'ingredients', 'name': wfMsg('Ingredients'), 'editable': true},
				{'key': 'steps', 'name': wfMsg('Steps'), 'editable': false},
				{'key': 'video', 'name': wfMsg('Video'), 'editable': false},
				{'key': 'tips', 'name': wfMsg('Tips'), 'editable': true},
				{'key': 'warnings', 'name': wfMsg('Warnings'), 'editable': true},
				{'key': 'thingsyoullneed', 'name': wfMsg('thingsyoullneed'), 'editable': true}, 
				{'key': 'relatedwikihows', 'name': wfMsg('relatedwikihows'), 'editable': false},
				{'key': 'sources', 'name': wfMsg('sourcescitations'), 'editable': true}
			];
			return sectionsList;
		}

		/**
		 * Convert the names returned by getSectionsList() to id's that match
		 * those in the <a id="..." name="..."></a> article anchor elements.
		 * @access private
		 */
		function sectionNameToID(name) {
			name = name.replace(/ /g, '_');
			name = name.replace(/'/g, '.27');
			return name;
		}

		/**
		 * Remove some difficult HTML when we first start editing.
		 * @access public
		 */
		this.removeSectionAnchors = function() {
			var sectionsList = getSectionsList();
			$(sectionsList).each(function() {
				var section = this['name'];
				var id = sectionNameToID(section);
				$('a#'+id).remove();
			});
		}

		/**
		 * Display add/Remove sections and alternate methods dialog
		 * @access public
		 */
		this.displaySectionsDialog = function () {
			$('#h5e-sections-dialog').dialog({
				width: 400,
				minWidth: 400,
				modal: true,
				zIndex: editor.DIALOG_ZINDEX,
				open: function() {
					$('#h5e-toolbar-section').addClass('h5e-active');
				},
				close: function(evt, ui) {
					$('#h5e-toolbar-section').removeClass('h5e-active');
					cursor.focusCurrentSection(false);
				}
			});
			var div = $('#h5e-sections').html('');
			var sectionsList = getSectionsList();
			$(sectionsList).each(function(i, section) {
				var id = 'h5e-sections-' + section['key'];
				var isSteps = section['key'] == 'steps';
				var disabled = !section['editable'] ? ' disabled="disabled"' : '';
				var sectionPresent = $('#' + section['key']).length > 0;
				var checked = sectionPresent ? ' checked="checked"' : '';
				var input = $('<input type="checkbox" id="' + id + '" name="sections" value="' + section['key'] + '"' + disabled + checked + ' /> <label for="' + id + '">'  + section['name'] + '</label><br/>');
				input.appendTo(div);

				if (isSteps) {
					var amDiv = $('<div id="h5e-sections-am" class="h5e-alternate-methods"></div>');
					amDiv.appendTo(div);

					drawAlternateMethodsHTML();
				}
			});
		}

		/**
		 * Get the list of sections to add or remove from the section edit
		 * dialog.
		 * @access private
		 */
		function getSectionsToAddOrRemove() {
			var toRemove = [];
			var toAdd = [];
			var firstNewSection = null;

			var sectionsList = getSectionsList();
			$(sectionsList).each(function(i, section) {
				var sectionNode = $('#' + section['key']);
				var sectionPresent = sectionNode.length > 0;
				var id = 'h5e-sections-' + section['key'];
				var checked = $('#' + id + ':checked').val();
				if (sectionPresent && !checked) {
					toRemove.push( section['key'] );
				} else if (!sectionPresent && checked) {
					if (!firstNewSection) {
						firstNewSection = section['key'];
					}
					toAdd.push( section['key'] );
				}
			});

			return {
				'add': toAdd,
				'remove': toRemove,
				'first': firstNewSection
			};
		}

		// A static variable that's a list of content of removed sections, 
		// so the content can be retrieved and re-inserted into the DOM 
		// if the user re-adds the section and the page hasn't been reloaded.
		var removedSections = {};

		/**
		 * Inserts the new section into the HTML, deletes removed ones
		 * @access public
		 */
		this.addOrRemoveSections = function (toAdd, toRemove, saveRemoved) {
			var sectionsList = getSectionsList();
			saveRemoved = typeof saveRemoved == 'undefined' ? true : saveRemoved; // default true
			$(sectionsList).each(function(i, section) {
				if ($.inArray(section['key'], toRemove) >= 0) {
					// Remove a section, put it in the list of removed section
					var node = $('#' + section['key']);
					var nodes = node
						.add( node.prev() )
						.add( node.nextUntil('h2') )
						.detach();
					if (saveRemoved && nodes.length) {
						removedSections[ section['key'] ] = nodes;
					}
				} else if ($.inArray(section['key'], toAdd) >= 0) {
					// Add a new section
					if (typeof removedSections[ section['key'] ] == 'undefined') {
						var defaultContent = section['key'] != 'relatedwikihows' ? '<ul><li>' + wfMsg('h5e-new-section') + '</li></ul>' : '<ul></ul>';
						var addSection = $('<h2><span>' + section['name'] + '</span></h2><div id="' + section['key'] + '" class="article_inner editable" contenteditable="true">' + defaultContent + '</div>');
					} else {
						var addSection = removedSections[ section['key'] ];
					}
					var foundKey = false, foundSibling = false;
					$(sectionsList).each(function(j, sectionj) {
						if (sectionj['key'] == section['key']) {
							foundKey = true;
						} else if (foundKey && !foundSibling) {
							var node = $('#' + sectionj['key']);
							if (node.length) {
								foundSibling = true;
								if (node.prev().is('h2')) {
									node = node.prev();
								}
								node.before(addSection);
							}
						}
					});
					if (!foundSibling) {
						$('#bodycontents').append(addSection);
					}
				}
			});
		}

		/**
		 * Re-draw the alternate methods for the section change dialog, based on
		 * which alternate methods are in the DOM.
		 * @access private
		 */
		function drawAlternateMethodsHTML() {
			var div = $('#h5e-sections-am');
			div.html('');

			// get list of alternate methods
			var methods = $('#steps h3');

			// do html for alternate methods
			methods.each(function(i, method) {
				var id = 'h5e-am-' + i;
				var methodName = $(method).children().first().html();
				var checked = ' checked="checked"';
				var input = $('<input type="checkbox" id="' + id + '" name="sections" value="' + i + '"' + checked + ' /> <label for="' + id + '">' + methodName + '</label><br/>');
				input.appendTo(div);
			});

			var id = 'h5e-sections-add-method';
			var link = $('<a href="#" id="' + id + '">' + wfMsg('h5e-new-alternate-method') + '</a><br/>');
			link.appendTo(div);

			$('#' + id).click(function () {
				$('#h5e-am-name').val('');
				$('#h5e-am-dialog').dialog({
					width: 400,
					minWidth: 400,
					modal: true,
					zIndex: editor.DIALOG_ZINDEX
				});
				$('#h5e-am-name').focus();
				return false;
			});
		}

		/**
		 * Delete all the alternate methods that were unselected in the Section
		 * change dialog.
		 * @access private
		 */
		function deleteUnselectedAlternateMethods() {
			var methods = $('#steps h3');
			methods.each(function(i, method) {
				var id = 'h5e-am-' + i;
				var checked = $('#' + id + ':checked').val();
				if (!checked) {
					// Remove this alternate method
					var jmethod = $(method);
					jmethod.nextUntil('#steps h3').remove();
					jmethod.remove();
				}
			});
		}

		/**
		 * Add a new blank alternate method to the DOM
		 */
		function addAlternateMethod(name) {
			var methods = $('#steps h3');
			var escName = name.replace(/ /g, '_');
			var b_open = browser.settings['auto-bold'] ? '<b>' : '';
			var b_close = browser.settings['auto-bold'] ? '</b>' : '';
			var html = $('<p><a name="' + escName + '" id="' + escName + '"></a></p><h3><span>' + name + '</span></h3><ol class="steps_list_2"><li class="steps_li final_li"><div contenteditable="false" class="step_num">1</div>' + b_open + wfMsg('h5e-new-method') + b_close + '<div class="clearall"></div></li></ol>');
			$('#steps').append(html);
		}

		/**
		 * Attach dialog event listeners relating to sections and
		 * alternate methods.
		 * @access public
		 */
		this.attachDialogListeners = function () {
			var that = this;

			// Section dialog, user clicks change
			$('#h5e-sections-change').click(function () {
				editor.setPageDirty();
				var sectionsDiff = getSectionsToAddOrRemove();
				var firstNewSection = sectionsDiff['first'];
				that.addOrRemoveSections(sectionsDiff['add'], sectionsDiff['remove']);
				deleteUnselectedAlternateMethods();
				if (firstNewSection) {
					cursor.focusSection(firstNewSection);
				}
				$('#h5e-sections-dialog').dialog('close');
			});

			// Section dialog, user clicks cancel
			$('#h5e-sections-cancel').click(function() {
				$('#h5e-sections-dialog').dialog('close');
				return false;
			});

			// Section dialog -> Alternate method add, user clicks add
			$('#h5e-am-add').click(function() {
				addAlternateMethod($('#h5e-am-name').val());
				drawAlternateMethodsHTML();
				$('#h5e-am-dialog').dialog('close');
				return false;
			});

			$('#h5e-am-name').keypress(function(evt) {
				if (evt.which == 13) { // 'Enter' key pressed
					$('#h5e-am-add').click();
					return false;
				}
			});

			// Section dialog -> Alternate method add, user clicks cancel
			$('#h5e-am-cancel').click(function() {
				$('#h5e-am-dialog').dialog('close');
				return false;
			});
		}

	}

	var browser, cursor, drafts, editor, images, 
		inlineLinks, keyInput, newArticles, references,
		relatedWikihows, sections, toolbar, utilities;

	function singleton() {
		sections = sections || new Sections();
		return sections;
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
		sections:
		  { init: init,
			singleton: singleton }
	};

})(jQuery) ); // exec anonymous function and return resulting class

