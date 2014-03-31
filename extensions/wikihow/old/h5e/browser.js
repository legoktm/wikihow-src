// Create WH container obj if necessary
WH = WH || {};
WH.h5e = WH.h5e || {};

jQuery.extend(WH.h5e, (function ($) {

	/**
	 * Browser module
	 *
	 * Browser settings module that determines browser specific settings
	 * so that to abstract functionality easily
	 */
	function Browser() {

		/**
		 * A public var that should be treated as read-only.  This contains
		 * all the browser-specific settings that H5E needs.  See the
		 * populateEditSettings() method for details on the settings.
		 */
		this.settings = {};

		/**
		 * Check if the browser can handle html5 editing on wikihow.
		 */
		this.isHtml5EditingCompatible = function () {
			// We determine this by browser rather than by feature 
			// because we use so many features that this is easier.
			var webkit = isHtml5EditWebkitReady();
			var firefox = isHtml5EditFirefoxReady();
			return webkit || firefox;
		}

		/**
		 * Check if the browser is WebKit and can handle html5 editing 
		 * on wikihow.
		 */
		function isHtml5EditWebkitReady() {
			// Safari or Chrome
			var webkit = typeof $.browser['webkit'] != 'undefined'
				&& $.browser['webkit']
				&& parseInt($.browser['version'], 10) >= 500;
			return webkit;
		}

		/**
		 * Check if the browser is Firefox and can handle html5 editing 
		 * on wikihow.
		 */
		function isHtml5EditFirefoxReady() {
			var firefox = false;
			if (typeof $.browser['mozilla'] != 'undefined' && $.browser['mozilla']) {
				var m = $.browser['version'].match(/^([^A-Za-z]*)/); // should be 1.9 or higher
				if (m && m[0]) {
					var ver = parseInt(m[0].replace(/\./, ''), 10);
					firefox = ver >= 19; // FF3 or better
				}
			}
			return firefox;
		}

		/**
		 * Determine the browser settings/configuration that will customize
		 * how certain actions are performed in Javascript.
		 */
		this.populateEditSettings = function () {
			var config = {};
			var isWebkit = isHtml5EditWebkitReady();
			var isFirefox = isHtml5EditFirefoxReady();

			// Firefox needs an article section focus before changing the
			// cursor position.  Webkit doesn't.
			config['needs-section-focus'] = isFirefox;

			// Webkit browsers deal with the dialogclose event inconsistently,
			// based on the event source.  This timeout is needed if the
			// dialog was closed by pressing Esc or hitting an <input> element.
			config['needs-delay-after-dialog'] = isWebkit;

			var createNewArticle = !!document.location.href.match(/[?&]create-new-article=true/);
			config['create-new-article'] = !wgArticleExists && createNewArticle;

			var startEditing = !!document.location.href.match(/[?&]h5e=true/);
			config['start-editing'] = startEditing;

			// Chrome on Mac doesn't see Paste or Backspace events with 
			// .keypress, so this is necessary
			config['monitor-keydown-events'] = isWebkit;

			// Chrome on Mac doesn't allow you to position the cursor in 
			// an empty <b> tag.  FF doesn't do well with an extra space 
			// here or there.
			config['non-empty-steps'] = isWebkit;

			// We only want to do "auto-bolding" on edited articles, not 
			// new ones.
			config['auto-bold'] = !config['create-new-article'];

			// Webkit doesn't like it if you set the cursor just before a
			// node then delete that node.
			config['cursor-place-before-delete-node'] = !isWebkit;

			this.settings = config;
		}

		/**
		 * Look at the wgRestrictionEdit and wgUserGroups variables to
		 * figure out whether we can edit this article.
		 */
		this.isArticleEditAllowed = function () {
			var groups = {};
			var editAllowed = wgRestrictionEdit.length == 0;
			$(wgUserGroups).each(function () {
				groups[this] = true;
			});
			$(wgRestrictionEdit).each(function () {
				if (groups[this]) editAllowed = true;
			});
			return editAllowed;
		}

	}

	var browser, cursor, drafts, editor, images, 
		inlineLinks, keyInput, newArticles, references,
		relatedWikihows, sections, toolbar, utilities;

	function singleton() {
		browser = browser || new Browser();
		return browser;
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
		browser: 
		  { init: init,
		    singleton: singleton }
	};

})(jQuery) ); // exec anonymous function and return resulting class

