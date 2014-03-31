// Create WH container obj if necessary
WH = WH || {};
WH.h5e = WH.h5e || {};

jQuery.extend(WH.h5e, (function ($) {

	/**
	 * KeyInput module
	 * 
	 * This module helps the editor code control and act
	 * on what's entered via keyboard input.
	 */
	function KeyInput() {

		/**
		 * Check to see whether a keyboard event was a "special" key like the
		 * arrow keys or Esc
		 * @access private
		 */
		function isEditingKeyEvent(evt) {
			// assume it's an edit key to start
			var ret = true;

			// in FF, when some special key is used, such as Esc, evt.which == 0
			if (evt.which == 0) {
				// Delete key
				if (typeof evt.keyCode != 'undefined' || evt.keyCode != 46) {
					ret = false;
				}
			}

			// in Chrome, evt.which is set sometimes when it's not set in FF,
			// such as for Esc, alt-key (by itself), ctrl key and arrow keys
			if (evt.which == 17 || evt.which == 18 ||
				evt.which == 27 || evt.which == 37 ||
				evt.which == 38 || evt.which == 39 ||
				evt.which == 40)
			{
				ret = false;
			}

			// when the Mac command key is pushed, metaKey is set
			if (typeof evt.ctrlKey != 'undefined' && !evt.ctrlKey &&
				typeof evt.altKey != 'undefined' && !evt.altKey &&
				typeof evt.metaKey != 'undefined' && evt.metaKey)
			{
				// except if paste, undo or cut are being used
				if (evt.which != 118 &&
					evt.which != 120 &&
					evt.which != 122)
				{
					ret = false;
				}
			}

			// ignore ctrl keys that are pushed
			if (typeof evt.ctrlKey != 'undefined' && evt.ctrlKey &&
				typeof evt.altKey != 'undefined' && !evt.altKey)
			{
				// Ctrl-y pastes on Mac
				if (evt.which != 89) {
					ret = false;
				}
			}

			return ret;
		}

		/**
		 * Was the key event an 'Enter' keypress?
		 * @access private
		 */
		function isEnterKeyEvent(evt) {
			return evt.which == 13 && typeof evt.isSimulated == 'undefined';
		}

		/**
		 * Was the key event an 'Backspace' keypress?
		 * @access private
		 */
		function isBackspaceKeyEvent(evt) {
			return evt.which == 8;
		}

		/**
		 * Was the key event an 'Tab' keypress?
		 * @access private
		 */
		function isTabKeyEvent(evt) {
			return evt.which == 0 && evt.keyCode == 9;
		}

		/**
		 * Tracks keystrokes and inserts new step numbers when a user 
		 * presses enter, or deletes them on backspace.  This method is 
		 * only called for certain keystrokes for efficiency.
		 * @access private
		 */
		this.onKeystroke = function (evt) {
			var propagate = true;

			// In new articles, we monitor all keypresses in areas so 
			// that we can automatically remove any nodes with the
			// h5e-first-remove-onclick class.
			if (browser.settings['create-new-article']) {
				var currentNode = $( cursor.getCursorNode() );
				if (currentNode.is('.h5e-first-remove-onclick')) {
					currentNode.click(); // this removes the node
					return true;
				}
			}

			if (isEditingKeyEvent(evt)) {
				// if array key was not hit, mark document changed so user
				// gets a warning when leaving page without saving
				editor.setPageDirty();
			} else {
				cursor.onCursorCheck();
			}


			if (isEnterKeyEvent(evt)) {
				if (cursor.getCurrentCursorSection() == 'steps') {
					propagate = createNewStepOnEnter(evt);
				}
				cursor.onCursorCheck();
			} else if (isBackspaceKeyEvent(evt)) {
				var section = cursor.getCurrentCursorSection();
				if (section == 'steps') {
					propagate = removeStepOnBackspace(evt);

					// check if the cursor ended up in bad spot and move it
					// if so
					window.setTimeout(function() {
						cursor.replaceCursorInSteps();
					}, 250);
				}

				// check if user deleted all steps -- recreate first one if
				// they did
				window.setTimeout(function() {
					replaceRemovedLi(section);
				}, 250);

				cursor.onCursorCheck();
			} else if (isTabKeyEvent(evt)) {
				// ignore the tab key if pressed in the editable section
				return false;
			} else {
				var chr = String.fromCharCode(evt.which);
				if (chr == '.' || chr == '?' || chr == ':' || chr == '!') {
					if (cursor.getCurrentCursorSection() == 'steps' &&
						document.queryCommandState('bold'))
					{
						document.execCommand('bold', false, '');
					}
				}
			}

			return propagate;
		}

		/**
		 * User has hit 'Enter' key, so we want to create a new list item or new
		 * step.
		 * @access private
		 */
		function createNewStepOnEnter(evt) {
			// if we're inside a UL element, don't create a new step
			if (!cursor.isStepsCursorTopLevel()) {
				return true;
			}

			var li = cursor.getCursorLi();

			var b_open = browser.settings['auto-bold'] ? '<b>' : '';
			var b_close = browser.settings['auto-bold'] ? '</b>' : '';
			var stepFilling = browser.settings['non-empty-steps'] ? '&nbsp;' : '';
			var newstep_tmpl = '<li class="steps_li"><div class="step_num" contenteditable="false">1</div>' + b_open + stepFilling + b_close + '<br/><div class="clearall"></div></li>';
			li.after(newstep_tmpl);
			var newli = li.next();

			if (li.hasClass('final_li')) {
				li.removeClass('final_li');
				newli.addClass('final_li');
			}

			var clear = $('div.clearall', li);
			if (!clear.length) {
				li.append('<div class="clearall"></div>');
			}

			if (browser.settings['auto-bold']) {
				var node = $('b', newli);
			} else {
				var node = $('div.step_num', newli);
			}
			if (node.length) {
				cursor.setCursorAfter(node);
			}

			renumberSteps();

			return false;
		}


		/**
		 * Remove the current step if the backspace key is hit and the 
		 * keyboard cursor is at the start of a step.
		 * @access private
		 */
		function removeStepOnBackspace(evt) {
			var select = document.getSelection();
			if (select.length > 0) {
				var selSteps = cursor.getSelectionSteps();
				if (selSteps['start'] != selSteps['end']) {
					// need to re-number steps AFTER delete event has 
					// propagated
					window.setTimeout(function() {
						renumberSteps();
					}, 250);
				}

				// propagate 'delete'
				return true;
			}

			if (!cursor.isCursorAtListItemStart()) {
				// if we're in the middle of the step, don't remove step, 
				// just backspace
				return true;
			}

			// get current li under cursor
			var currentli = cursor.getCursorLi();
			var prevli = currentli.prev();

			if (!prevli.length) {
				// if we're at the start of the first li, don't do anything
				return false;
			}

			var clear = $('div.clearall', currentli);
			if (!clear.length) {
				currentli.append('<div class="clearall"></div>');
			}

			// position cursor at preview li
			var textNodes = prevli.textNodes(true);
			textNodes = utilities.filterEmptyTextNodes(textNodes);
			if (textNodes.length >= 2) {
				// position cursor at end of last text node of previous li
				var node = textNodes.last().get(0);
				var position = node.length;
				cursor.setCursorNode(node, position);
			} else {
				// if there's no text in the last node, position cursor
				// right after the number div
				var node = $('div', prevli).get(0);
				cursor.setCursorAfter(node);
			}

			$('.clearall', prevli).remove();
			//removeBoldFromNode(currentli);

			if (currentli.hasClass('final_li')) {
				prevli.addClass('final_li');
			}

			// store the rest of the content of li which the cursor is on,
			// then remove it
			$('div.step_num', currentli).remove();
			var extraStepContents = currentli.contents();
			extraStepContents = extraStepContents.filter(function(i) {
				return !$(this).is('br');
			});
			prevli.append(extraStepContents);
			currentli.remove();

			renumberSteps();

			evt.preventDefault();
			return true;
		}

		/**
		 * Re-numbers steps after a new step has been inserted, etc.
		 * @access private
		 */
		function renumberSteps() {
			var steps = $('ol.steps_list_2', '#steps');
			steps.each(function(i, list) {
				var divs = $('div.step_num', list);
				var step = 1;
				divs.each( function(i, div) {
					$(div).html(step);
					step++;
				});
			});
		}

		/**
		 * If user deletes all of the step section, or tips or warnings,
		 * we want to add the correct structure back to the document 
		 * automatically.
		 * @access private
		 */
		function replaceRemovedLi(sectionName) {
			var section = $('#' + sectionName);
			var html = section.html();
			var hasLi = $('li', section).length > 0;
			// check if steps are empty, after backspace event propagation
			if (html == '' || html == '<br>' || !hasLi) {
				if (sectionName == 'steps') {
					var b_open = browser.settings['auto-bold'] ? '<b>' : '';
					var b_close = browser.settings['auto-bold'] ? '</b>' : '';
					var msg = wfMsg('h5e-first-step');
					var tmpl = '<ol class="steps_list_2"><li class="steps_li final_li"><div class="step_num" contenteditable="false">1</div>' + b_open + msg + b_close + '</li></ol>';
					$('#' + sectionName).html(tmpl);
					var len = msg.length;
					var newli = $('#steps ol li');
					cursor.loadCursorPos({'node': newli.contents().get(1), 'offset': len});
				} else if (sectionName == 'tips' || sectionName == 'warnings' || sectionName == 'sources') {
					if (sectionName == 'tips') var msg = wfMsg('h5e-new-tip');
					if (sectionName == 'warnings') var msg = wfMsg('h5e-new-warning');
					if (sectionName == 'sources') var msg = wfMsg('h5e-new-source');
					var tmpl = '<ul><li>' + msg + '</li></ul>';
					$('#' + sectionName).html(tmpl);
					var len = msg.length;
					var newli = $('#' + sectionName + ' ul li');
					cursor.loadCursorPos({'node': newli.contents().get(0), 'offset': len});
				}
			}
		}

	}

	var browser, cursor, drafts, editor, images, 
		inlineLinks, keyInput, newArticles, references,
		relatedWikihows, sections, toolbar, utilities;

	function singleton() {
		keyInput = keyInput || new KeyInput();
		return keyInput;
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
		keyInput:
		  { init: init,
			singleton: singleton }
	};

})(jQuery) ); // exec anonymous function and return resulting class

