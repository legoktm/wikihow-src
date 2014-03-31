// Create WH container obj if necessary
WH = WH || {};
WH.h5e = WH.h5e || {};

jQuery.extend(WH.h5e, (function ($) {

	/**
	 * Utilities module
	 *
	 * Random-ish self-contained methods that are used in multiple spots.
	 */
	function Utilities() {

		/**
		 * Converts the html encodable entities in a string into a string. For
		 * example, converts the string "Bob & Linda" into "Bob &amp; Linda".
		 */
		this.htmlEntitiesEncode = function (str) {
			return $('<div/>').text(str).html();
		};

		/**
		 * Test whether two text nodes are equal.  Returns true iff they 
		 * are equal.
		 */
		this.textNodesEqual = function (a, b) {
			return a && b &&
				typeof a.nodeType != 'undefined' &&
				typeof b.nodeType != 'undefined' &&
				a.nodeType == b.nodeType &&
				a.textContent == b.textContent;
		};

		/**
		 * Remove all "empty" (only white space) text nodes from a jQuery
		 * collection of nodes.
		 */
		this.filterEmptyTextNodes = function (tn) {
			return tn.filter(function (i) {
				var trimmed = $.trim(this.textContent);
				return trimmed != '';
			});
		};

		/**
		 * Do single-level check for any bold nodes, replace them inline 
		 * with their children.
		 */
		/*function removeBoldFromNode(li) {
			for (var i = 0; i < 2; i++) {
				$('b', li).each(function() {
					var child = $(this);
					var contents = child.contents();
					child.replaceWith(contents);
				});
			}
		}*/

		/**
		 * This is used to clear any auto-complete results that linger 
		 * or appear after a dialog's been closed.
		 */
		this.clearAutocompleteResults = function () {
			$('#completeDiv').remove();
			$('#completionFrame').remove();
		};

		/**
		 * Given a DOM node, returns the href of it if it's an <a> tag or 
		 * has any immediate parents that are an <a> tag.
		 *
		 * @return the href value or '' if none exists
		 */
		this.getAnchorNode = function (node) {
			var anchor = node;
			if (!node.is('a')) {
				anchor = node.parents('a').last();
			}
			if (anchor.length && !anchor.hasClass('h5e-no-edit-tooltip')) {
				return anchor;
			} else {
				return null;
			}
		};

		/**
		 * Change a site link like "/Article-Name" to an article name like
		 * "Article Name"
		 */
		this.getArticleFromLink = function (url) {
			url = url
				.replace(/^http:\/\/([^\/]*)(wikihow|wikidiy)\.com\//i, '');
			if (!url.match(/^http:\/\//)) {
				url = url
					.replace(/^\//, '')
					.replace(/-/g, ' ');
				return decodeURIComponent(url);
			} else {
				return url;
			}
		};

		/**
		 * Change an article name like "Article Name" to a site link like
		 * "/Article-Name"
		 */
		this.getLinkFromArticle = function (article) {
			if (!article.match(/http:\/\//)) {
				return '/' + encodeURIComponent(article.replace(/ /g, '-'));
			} else {
				return article;
			}
		};

		/**
		 * Shorten the display of a link from something like this:
		 *
		 * This is a really really really really really really long link name
		 *
		 * to this:
		 *
		 * This is a really real...ng link name
		 */
		this.getArticleDisplay = function (articleName, numChars) {
			if (!numChars) numChars = 45;
			articleName = articleName.replace(/^http:\/\//, '');
			if (articleName.length > numChars) {
				var start = Math.round(2*numChars / 3);
				var end = Math.round(1*numChars / 3);
				var re = new RegExp('^(.{' + start + '}).*(.{' + end + '})$');
				var m = articleName.match(re);
				return m[1] + '...' + m[2];
			} else {
				return articleName;
			}
		};

		/**
		 *
		 * Show an error in the error dialog
		 *
		 */
		this.showError = function(error) {
			$('.h5e-error-dialog .error-msg').html(error);
			$('.h5e-error-dialog').dialog({
				width: 250,
				minWidth: 250,
				modal: true,
				zIndex: editor.DIALOG_ZINDEX
			});
		}


		/**
		 * Unhide a fixed position div and display it centered vertically and
		 * horizontally in the browser.
		 */
		this.displayCenterFixedDiv = function (jDiv) {
			if (jDiv.length) {
				var w = jDiv.width();
				var h = jDiv.height();
				var winw = window.innerWidth;
				var winh = window.innerHeight;
				var nleft = Math.round(winw / 2 - w / 2);
				var ntop = Math.round(winh / 2 - h / 2);
				jDiv.css({
					'display': 'block',
					'top': ntop,
					'left': nleft
				});
			}
		};

	}

	var browser, cursor, drafts, editor, images, 
		inlineLinks, keyInput, newArticles, references,
		relatedWikihows, sections, toolbar, utilities;

	function singleton() {
		utilities = utilities || new Utilities();
		return utilities;
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
		utilities:
		  { init: init,
			singleton: singleton }
	};

})(jQuery) ); // exec anonymous function and return resulting class

