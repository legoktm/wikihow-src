// Create WH container obj if necessary
WH = WH || {};
WH.h5e = WH.h5e || {};

jQuery.extend(WH.h5e, (function ($) {

	/**
	 * RelatedWikihows module
	 *
	 * Process the Related wikiHows section of the article (and the
	 * dialog attached to that functionality).
	 */
	function RelatedWikihows() {

		/**
		 * Grab the list of related wikiHows from the DOM
		 * @access public
		 */
		this.loadRelatedWikihows = function () {
			var related = [];
			var selection = $('#relatedwikihows ul li a');
			if (selection.length == 0) {
				selection = $('#relatedwikihows ul li');
			}
			selection.each(function() {
				var title = $(this).data('title');
				if (!title) {
					title = $(this).attr('title');
				}
				if (title) {
					related.push(title);
				}
			});
			return related;
		}

		/**
		 * Save related wikiHows so that the links look "active" while the
		 * user isn't in edit mode.
		 * @access public
		 */
		this.saveRelatedWikihowsActive = function (articles) {
			// Clear out whatever is currently there
			$('#relatedwikihows ul li').remove();
			$('#relatedwikihows .h5e-rel-wh-edit').remove();

			var ul = $('#relatedwikihows ul');
			$(articles).each(function() {
				var title = this;
				var howto = wfMsg('howto', title);
				var href = utilities.getLinkFromArticle(title);
				var tmpl = '<li><a title="$1" href="$2">$3</a></li>';
				var html = wfTemplate(tmpl, title.replace(/"/g, '&quot;'), href, howto);
				ul.append( $(html) );
			});
		}

		/**
		 * Save related wikiHows so that they look "inactive" while the
		 * user is in edit mode.
		 */
		this.saveRelatedWikihowsInactive = function (articles) {
			// Clear out whatever is currently there
			$('#relatedwikihows ul li').remove();
			$('#relatedwikihows .h5e-rel-wh-edit').remove();

			var ul = $('#relatedwikihows ul');
			if (!ul.length) {
				ul = $('<ul></ul>');
				$('#relatedwikihows').append(ul);
			}

			$(articles).each(function() {
				var title = this;
				var howto = wfMsg('howto', title);
				var tmpl = '<li><span class="h5e-rel-wh-disabled">$1</span></li>';
				var html = wfTemplate(tmpl, howto);
				var node = $(html);
				node.data('title', title);
				ul.append( node );
			});

			var tmpl_change = '<div class="h5e-rel-wh-edit"><input type="button" class="h5e-button button64 h5e-input-button" value="$1" /></div>';
			var tmpl_add = '<div class="h5e-rel-wh-edit h5e-rel-wh-add"><input type="button" class="h5e-button button64 h5e-input-button" value="$1" /></div>';
			var tmpl = articles.length > 0 ? tmpl_change : tmpl_add;
			var msg = articles.length > 0 ? wfMsg('h5e-rel-wh-edit') : wfMsg('h5e-search');
			var html = wfTemplate(tmpl, msg);
			ul.before(html);

			var that = this;
			$('.h5e-rel-wh-edit input').click(function() {
				that.showDialog();
				return false;
			});
		}

		/**
		 * Display the related wikiHows add/remove dialog to the user
		 */
		this.showDialog = function() {
			$('.h5e-related-sortable li').remove();
			var related = this.loadRelatedWikihows();
			$(related).each(function() {
				var title = this;
				var node = createRelatedWikihowSortableNode(title);
				$('.h5e-related-sortable').append(node);
			});
			$('.h5e-related-sortable')
				.sortable()
				.disableSelection();
			$('#related-wh-dialog').dialog({
				width: 500,
				modal: true,
				zIndex: editor.DIALOG_ZINDEX,
				open: function() {
					$('#h5e-toolbar-related').addClass('h5e-active');

					// remove any previous completedivs
					utilities.clearAutocompleteResults();

					// google-style auto-complete for related
					InstallAC(
						document['h5e-ac'],
						document['h5e-ac']['h5e-related-new'],
						document['h5e-ac']['h5e-related-add'],
						"/Special:TitleSearch?lim=10",
						"en");

					// customize ac results
					$('#completeDiv')
						.addClass('h5e-auto-complete');

					$('#h5e-related-new').focus();
				},
				close: function(evt, ui) {
					$('#h5e-toolbar-related').removeClass('h5e-active');

					utilities.clearAutocompleteResults();
				}
			});
		}

		/**
		 * In the Related wikiHows dialog, we use a jQuery UI sortable element
		 * to be able to add new related wikihows.	This method creates a list
		 * item for that sortable list.
		 * @access private
		 */
		function createRelatedWikihowSortableNode(title) {
			var tmpl = '<li class="h5e-related-li"><span class="related-wh-title">$1</span><div class="trash-icon"><a href="#"><img src="' + editor.CDN_BASE + '/skins/WikiHow/images/tiny_trash.gif" /></div></li>';
			var howto = wfMsg('howto', title);
			var shortened = utilities.getArticleDisplay(howto, 40);
			var node = $( wfTemplate(tmpl, shortened) );
			$('.related-wh-title', node).attr('title', howto);
			$('a', node).click(function() {
				var li = $(this).parentsUntil('li').last().parent();
				li.remove();
			});
			node.data('title', title);
			return node;
		}

		/**
		 * Attach the related wikiHows add/remove dialog event listeners
		 */
		this.attachDialogListeners = function () {
			var that = this;

			// Edit related wikihows dialog, Done button
			$('#h5e-related-done').click(function() {
				editor.setPageDirty();

				// save links to related wikihows section after dialog Done
				var related = [];
				$('.h5e-related-sortable li').each(function() {
					var title = $(this).data('title');
					related.push(title);
				});
				that.saveRelatedWikihowsInactive(related);
				$('#related-wh-dialog').dialog('close');
				return false;
			});

			$('#h5e-related-cancel').click(function() {
				$('#related-wh-dialog').dialog('close');
				return false;
			});

			$('#h5e-related-add').click(function() {
				var title = $('#h5e-related-new').val();
				title = $.trim(title);
				if (title != '') {
					var node = createRelatedWikihowSortableNode(title);
					$('.h5e-related-sortable').append(node);
				}
				$('#h5e-related-new')
					.val('')
					.focus();
				return false;
			});

			$('#h5e-related-new').keypress(function(evt) {
				if (evt.which == 13) { // 'Enter' key pressed
					$('#h5e-related-add').click();
					return false;
				}
			});

			$('.related-wh-overlay-edit button').click(function() {
				that.showDialog();
				return false;
			});
		}

	}

	var browser, cursor, drafts, editor, images, 
		inlineLinks, keyInput, newArticles, references,
		relatedWikihows, sections, toolbar, utilities;

	function singleton() {
		relatedWikihows = relatedWikihows || new RelatedWikihows();
		return relatedWikihows;
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
		relatedWikihows:
		  { init: init,
			singleton: singleton }
	};

})(jQuery) ); // exec anonymous function and return resulting class

