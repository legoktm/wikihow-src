// Create WH container obj if necessary
WH = WH || {};
WH.h5e = WH.h5e || {};

jQuery.extend(WH.h5e, (function ($) {

	/**
	 * Cursor module
	 *
	 * A collection of functions that deal with manipulating
	 * the cursor inside contenteditable elements in the DOM.
	 */
	function Cursor() {

		/**
		 * Move the keyboard cursor to the start of a specified node (or at
		 * the specified position if one is present).
		 * @access public
		 */
		this.setCursorNode = function (node, position) {
			if (node instanceof jQuery) {
				node = node.get(0);
			}
			var range = document.createRange();
			if (typeof position == 'undefined') {
				range.selectNodeContents(node);
			} else {
				range.setStart(node, position);
				range.setEnd(node, position);
			}
			var select = window.getSelection();
			select.removeAllRanges();
			select.addRange(range);
		}

		/**
		 * Place the cursor immediately after a given DOM node.
		 */
		this.setCursorAfter = function (node) {
			if (node instanceof jQuery) {
				node = node.get(0);
			}
			var range = document.createRange();
			range.setStartAfter(node);
			range.setEndAfter(node);
			var select = window.getSelection();
			select.removeAllRanges();
			select.addRange(range);
		}

		/**
		 * Place the cursor immediately after a given DOM node.
		 */
		this.setCursorBefore = function (node) {
			if (node instanceof jQuery) {
				node = node.get(0);
			}
			var range = document.createRange();
			range.setStartBefore(node);
			range.setEndBefore(node);
			var select = window.getSelection();
			select.removeAllRanges();
			select.addRange(range);
		}

		/**
		 * In the steps section, get the step where the keyboard cursor is
		 * currently placed.
		 */
		this.getCursorLi = function (anchor) {
			if (typeof anchor == 'undefined') {
				var select = window.getSelection();
				anchor = select.anchorNode;
			}
			anchor = $(anchor);
			var currentLi = anchor.parentsUntil('#steps ol').last();
			if (!currentLi.length && anchor.is('li')) {
				currentLi = anchor;
			}
			return currentLi;
		}

		/**
		 * Return the text node where the keyboard cursor is currently placed.
		 * @access private
		 */
		function getCursorTextNode() {
			var select = window.getSelection();
			var anchor = select.anchorNode;
			if (anchor.nodeName == '#text') {
				return anchor;
			} else {
				var firstTextNode = $(anchor).textNodes(true).first();
				if (firstTextNode.length) {
					return firstTextNode[0];
				} else {
					return anchor.nextSibling;
				}
			}
		}

		// static vars used only in the onCursorCheck function
		var cursorHasItalics = false,
			cursorHasIndent = false;

		/**
		 * Used to check whether the cursor has italics, is indented, etc, 
		 * to update the toolbar icons as the user clicks on different 
		 * text to edit.
		 */
		this.onCursorCheck = function () {
			var italics = document.queryCommandState('italic');
			if (italics && !cursorHasItalics) {
				cursorHasItalics = true;
				$('#h5e-toolbar-italics').addClass('h5e-button-italics-enabled');
			} else if (!italics && cursorHasItalics) {
				cursorHasItalics = false;
				$('#h5e-toolbar-italics').removeClass('h5e-button-italics-enabled');
			}

			var select = window.getSelection();
			var parentLast = $(select.anchorNode).parentsUntil('ul').last();
			if (!parentLast.length || !parentLast.is('html')) {
				var indented = true;
			} else {
				var indented = false;
			}
			if (indented && !cursorHasIndent) {
				cursorHasIndent = true;
				$('#h5e-toolbar-outdent').removeClass('h5e-disabled');
			} else if (!indented && cursorHasIndent) {
				cursorHasIndent = false;
				$('#h5e-toolbar-outdent').addClass('h5e-disabled');
			}
		}

		/**
		 * If the cursor is in the steps section, but happens to be at a spot
		 * that we're not supposed to be able to edit (ie, outside one of the
		 * step numbers), we try to place it back in the right spot.
		 */
		this.replaceCursorInSteps = function () {
			var node = this.getCursorNode();
			var jnode = $(node);
			if (jnode.is('ol.steps_list_2')) {
				var first = jnode.find('li').first();
				if (first.length) {
					var div = $('.step_num', first);
				} else {
					var msg = wfMsg('h5e-first-step');
					var tmpl = '<li class="steps_li final_li"><div class="step_num" contenteditable="false">1</div>' + msg + '</li>';
					var newNode = $(tmpl);
					jnode.append(newNode);
					var div = $('.step_num', newNode);
				}
				this.setCursorAfter(div);
			}
		}

		/**
		 * Check whether the current li element is in fact a steps-level 
		 * element.
		 * @return true iff it is current li is a steps-level li.
		 */
		this.isStepsCursorTopLevel = function () {
			var select = window.getSelection();
			var anchor = $(select.anchorNode);
			var parents = anchor.parentsUntil('#bodycontents * ol');
			var gotUL = false;
			parents.each(function(i, node) {
				if ($(node).is('ul')) {
					gotUL = true;
					return false;
				}
			});
			if (gotUL && anchor.is('li') && parents.length == 2 && $(parents[0]).is('ul')) {
				return true;
			} else {
				return !gotUL;
			}
		}

		/**
		 * Determine's the high-level section where the cursor currently 
		 * resides.
		 *
		 * @return the section, as a word, where the cursor currently resides.
		 *	 e.g. 'intro', 'steps', 'tips', etc.
		 */
		this.getCurrentCursorSection = function () {
			var select = window.getSelection();
			var anchor = $(select.anchorNode);
			var parents = anchor.parentsUntil('#bodycontents');
			if (parents.length) {
				var sectionDiv = parents.last();
			} else {
				var sectionDiv = anchor;
			}
			var id = sectionDiv.attr('id');
			if (id !== '') {
				return id;
			} else {
				return 'intro';
			}
		}

		/**
		 * Returns true if and only if the keyboard cursor is at the start 
		 * of a top level li in a step. Used so that we can collapse two 
		 * steps into one when user hits backspace at the start of a step.
		 */
		this.isCursorAtListItemStart = function () {
			var select = window.getSelection();
			var li = this.getCursorLi();
			var clone = li.clone();
			$('div.step_num', clone).remove();
			var textNodes = clone.textNodes(true);
			textNodes = utilities.filterEmptyTextNodes(textNodes);
			if (textNodes.length) {
				var firstTextNode = textNodes[0];
				var cursorTextNode = getCursorTextNode();
				if (utilities.textNodesEqual(firstTextNode, cursorTextNode)) {
					return select.anchorOffset == 0;
				} else {
					return false;
				}
			} else {
				return true;
			}
		}

		/**
		 * Returns true if and only if the start of the selection is on
		 * one step and the end of the selection is on another.
		 */
		this.getSelectionSteps = function () {
			var select = window.getSelection();
			var start = this.getCursorLi(select.anchorNode).get(0);
			var end = this.getCursorLi(select.focusNode).get(0);
			return {'start' : start, 'end' : end};
		}

		/**
		 * Brings the cursor back to the section we're editing after a toolbar
		 * button was pressed.  This call fails if the section where the
		 * cursor resides isn't editable.
		 *
		 * @param failLoudly indicates whether an error should be presented
		 *   to the user if this method fails to place the cursor
		 * @param currentNode provide a node -- uses cursor.getCursorNode()
		 *   if this param is not present
		 * @return true iff the cursor was placed successfully
		 */
		this.focusCurrentSection = function (failLoudly, currentNode) {
			var node = !currentNode ? cursor.getCursorNode() : currentNode;
			if (!isNodeContentEditable(node)) {
				if (failLoudly) {
					var error = wfMsg('h5e-no-cursor-error');
					utilities.showError(error);
				}
				return false;
			}

			var parents = $(node).parentsUntil('#bodycontents');
			// note: the 'parents' set is empty when cursor is in intro -- use
			// current node instead
			var focusNode = parents.length ? parents.last() : $(node);
			if (browser.settings['needs-section-focus']) {
				focusNode.focus();
			}
			return true;
		}

		/**
		 * Test whether a node is contenteditable.  This may involve looking
		 * ancestors of the node if the node doesn't indicate this attribute
		 * with certainty.
		 * @access private
		 */
		function isNodeContentEditable(node) {
			if (!(node instanceof jQuery)) {
				node = $(node);
			}
			var editable = node.attr('contentEditable');
			if (typeof editable != 'string' || editable == 'inherit') {
				var parents = node.parents();
				for (var i = 0; i < parents.length; i++) {
					var parentEdit = $(parents[i]).attr('contentEditable');
					if (typeof parentEdit == 'string' && parentEdit != 'inherit') {
						editable = parentEdit;
						break;
					}
				}
			}
			return editable == 'true';
		}

		/**
		 * Move the keyboard cursor to the start of a section specified.  Make
		 * sure the browser view scrolls to that section if it's not there.
		 */
		this.focusSection = function (section) {
			var sectionDiv, firstText;
			if (section == 'intro') {
				sectionDiv = $('#bodycontents .article_inner:first');
				// TODO: need to pull out first real non-blank text 
				// node (ignoring all div.mwimg)
				firstText = $('p', sectionDiv).textNodes(true).first();
			} else if (section == 'relatedwikihows') {
				sectionDiv = [];
			} else if (section == 'steps') {
				sectionDiv = $('#steps');
				var div = $('#steps ol.steps_list_2 li');
				div = div.children().not('div.mwimg').not('div.step_num');
				firstText = div.textNodes(true).first();
			} else {
				sectionDiv = $('#' + section);
				var div = $('ul li', sectionDiv);
				if (!div.length) {
					div = sectionDiv;
				}
				firstText = div.textNodes(true).first();
			}
			if (sectionDiv.length) {
				if (browser.settings['needs-section-focus']) {
					sectionDiv.focus();
				}
				if (firstText.length) {
					this.setCursorNode(firstText[0], 0);
				}
			}
		}

		/**
		 * Return the current cursor position in the document, so that it can be
		 * saved and loaded later.
		 *
		 * @return A tuple to be loaded with the loadCursorPos() function.
		 */
		this.saveCursorPos = function () {
			var sel = window.getSelection();
			var savedNode = sel.anchorNode;
			var savedOffset = sel.anchorOffset;
			var range = savedNode ? sel.getRangeAt(0) : null;
			if (range && range.startOffset == range.endOffset) {
				range = null;
			}
			return { 'node': savedNode, 'offset': savedOffset, 'range': range };
		}

		/**
		 * Load the current cursor position from a save tuple.
		 *
		 * @param pos A tuple which was saved with saveCursorPos()
		 */
		this.loadCursorPos = function (pos, callback) {
			var that = this;
			var func = function() {
				if (pos && pos['node']) {
					that.setCursorNode(pos['node'], pos['offset']);
					if (pos['range']) {
						var sel = window.getSelection();
						sel.addRange(pos['range']);
					}
				}

				if (callback) callback();
			}
			if (browser.settings['needs-delay-after-dialog']) {
				window.setTimeout(func, 0);
			} else {
				func();
			}
		}

		/**
		 * Get DOM node where the cursor currently lies in the html5-edited
		 * document.
		 */
		this.getCursorNode = function () {
			if (window.getSelection) { // should work in webkit/ff
				var node = window.getSelection().anchorNode;
				var startNode = (node && node.nodeName == "#text" ? node.parentNode : node);
				return startNode;
			} else {
				return null;
			}
		}

	}

	var browser, cursor, drafts, editor, images, 
		inlineLinks, keyInput, newArticles, references,
		relatedWikihows, sections, toolbar, utilities;

	function singleton() {
		cursor = cursor || new Cursor();
		return cursor;
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
		cursor:
		  { init: init,
			singleton: singleton }
	};

})(jQuery) ); // exec anonymous function and return resulting class

