// Create WH container obj if necessary
WH = WH || {};
WH.h5e = WH.h5e || {};

jQuery.extend(WH.h5e, (function ($) {

	/**
	 * Images module
	 *
	 * All image adding / processing related functions.  Includes calling
	 * the "Easyimageupload" special page functionality and the mouseover
	 * overlays to allow you remove an image.
	 */
	function Images() {

		// private class vars used in addImage
		var preEIUSection, preEIUCursorLi, preEIUCursorNode, preEIUAddToIntro;

		/**
		 * Store the position of the cursor and call the Image Upload dialog.
		 * @access public
		 */
		this.addImage = function (preAddCallback, preAddToIntro) {
			var cursorError = !preAddToIntro;
			var result = cursor.focusCurrentSection(cursorError);
			if (cursorError && !result) return;

			// These static vars store the position of the cursor and 
			// cursor-related details before entering the Image Upload dialog.
			preEIUSection = cursor.getCurrentCursorSection();
			preEIUCursorLi = cursor.getCursorLi();
			preEIUCursorNode = cursor.getCursorNode();
			preEIUAddToIntro = typeof preAddToIntro != 'undefined' && preAddToIntro;

			// pushed-in look
			$('#h5e-toolbar-img').addClass('h5e-active');

			easyImageUpload.setCompletionCallback(
				// this callback is called by the image upload dialog finishes
				function (success, details) {
					$('#h5e-toolbar-img').removeClass('h5e-active');

					if (success) {
						if ($.isFunction(preAddCallback)) preAddCallback();
						var html = generateImageHtml(details);
						insertImageHtml(details, html);
					}
				}
			);
			easyImageUpload.doEIUModal();
		}

		/**
		 * Builds the HTML for the image to be inserted from the details of a
		 * wikitext image tag.
		 *
		 * @param details all of the image tag
		 * @return the html to be inserted
		 * @access private
		 */
		function generateImageHtml(details) {
			var width = details['chosen-width'];
			var height = details['chosen-height'];
			var encCaption = utilities.htmlEntitiesEncode(details['caption']);
			var isThumb = encCaption != '';
			var encFilename = utilities.htmlEntitiesEncode(encodeURIComponent(details['filename']));
			var encTag = utilities.htmlEntitiesEncode(details['tag']);
			var rlayout = details['layout'] == 'right';
			if (isThumb) {
				var ltag1 = '<div style="width: ' + (parseInt(width, 10) + 2) + 'px;" class="thumb ' + (rlayout ? 'tright' : 'tnone') + '">';
				var rtag1 = '</div>';
				var ltag2 = '';
				var rtag2 = '';
			} else {
				var ltag1 = '<div class="' + (rlayout ? 'floatright' : 'floatnone') + '">';
				var rtag1 = '</div>';
				var ltag2 = '<span>';
				var rtag2 = '</span>';
			}
			var captionHtml = encCaption ? '<a title="Enlarge" class="internal" href="/Image:' + encFilename + '"><img width="16" height="16" alt="" src="' + editor.CDN_BASE + '/skins/common/images/magnify-clip.png"></a> <span contenteditable="true" class="caption">' + encCaption + '</span>' : '';
			var html = '<div contenteditable="false" class="mwimg">' + ltag1 + '<div style="width: ' + width + 'px; height: ' + height + 'px;" class="rounders">' + ltag2 + '<a title="' + encCaption + '" class="image" href="/Image:' + encFilename + '">' + details['html'] + '</a>' + rtag2 + '<div class="corner top_left"></div><div class="corner top_right"></div><div class="corner bottom_left"></div><div class="corner bottom_right"></div></div>' + captionHtml + '<input type="hidden" name="h5e_image" value="' + encTag + '" />' + rtag1 + '</div>';

			return html;
		}

		/**
		 * Inserts an image into the DOM, using the image details (the 
		 * layout) and the previously generated html for the image.
		 *
		 * @note addImage static vars used: preEIUSection, preEIUCursorLi, 
		 *   preEIUCursorNode
		 * @access private
		 */
		function insertImageHtml(details, html) {
			editor.setPageDirty();
			var newHtml = $(html);

			// for the "steps" section
			if (!preEIUAddToIntro && preEIUSection == 'steps') {
				var currentli = preEIUCursorLi;

				if (details['layout'] == 'center') {
					var lastdiv = $('div.clearall', currentli).last();
					if (lastdiv.length) {
						lastdiv.before(newHtml);
					} else {
						currentli.append(newHtml);
					}
				} else {
					var currentli = preEIUCursorLi;
					var thisStep = $('div.step_num', currentli).first();
					thisStep.after(newHtml);
				}

			} else {
				if (preEIUAddToIntro) {
					var node = $('#bodycontents').find('p:first');
				} else {
					var node = $(preEIUCursorNode);
				}
				var container = null;
				if (node.is('li')) {
					container = node;
				} else {
					var parents = node.parentsUntil('#bodycontents');
					parents.each( function(i, node) {
						var par = $(node);
						if (par.is('li')) {
							container = par;
						}
					});
					if (!container) {
						container = parents.last();
					}
				}

				if (details['layout'] == 'center') {
					container.append(newHtml);
				} else {
					container.prepend(newHtml);
				}
			}

			$('.rounders', newHtml).mouseenter(imageHoverIn);
			$('a.internal', newHtml).click(function() { return false; });
		}

		/**
		 * Attach the image hover overlay to all images in the article
		 * @access public
		 */
		this.attachOverlayAll = function () {
			$('.rounders').mouseenter(imageHoverIn);
		}

		/**
		 * Called when we mouse over an image in editing mode.
		 * @access private
		 */
		function imageHoverIn() {
			// A static var to store the last image div we hovered over, 
			// to be used by the imageHoverIn() and imageHoverOut() methods.
			imageHoverIn.mouseHoverDiv = this;

			// Unbind this event since our mouse will be over the new opaque
			// div instead of the img one (causes flickering otherwise)
			$(this).unbind('mouseenter');

			// Add the opaque div over top of the image our mouse is over
			var h = $(this).outerHeight();
			var w = $(this).outerWidth();
			var offset = $(this).offset();

			// note: Chrome doesn't like the jquery offset() setter (the offsets
			// get mangled), so we set top and left using css instead
			$('#h5e-mwimg-mouseover')
				.css({
					'top': Math.round(offset['top']) + 'px',
					'left': Math.round(offset['left']) + 'px'
				})
				.show()
				.height(h)
				.width(w)
				.mouseleave(imageHoverOut);

			$('#h5e-mwimg-mouseover div')
				.addClass('h5e-img-mouseover')
				.show()
				.height(h)
				.width(w);

			// Bind to the "Remove Image" link or icon in the opaque div
			$('#h5e-mwimg-mouseover a')
				.css('margin-top', (h/3)*-2)
				.css('margin-left', (w-87)/2)
				.show()
				.click(showRemoveImageConfirm);

			// Bind to the remove confirmation div
			$('#h5e-mwimg-mouseover-confirm')
				.css('margin-top', (h/3)*-2)
				.css('margin-left', (w-178)/2)
				.hide();
		}

		/**
		 * Called when a mouse is no longer over the image or opaque div that's
		 * over top the image.
		 */
		function imageHoverOut() {
			$(this).unbind('mouseleave');
			$(imageHoverIn.mouseHoverDiv).mouseenter(imageHoverIn);
			$('#h5e-mwimg-mouseover').fadeOut('fast');
		}

		/**
		 * Show "remove image" confirmation: YES | NO
		 */
		function showRemoveImageConfirm() {
			$('#h5e-mwimg-mouseover a').fadeOut('fast');
			$('#h5e-mwimg-mouseover-confirm').fadeIn('fast');
			return false;
		}

		/**
		 * Attach the click listeners to the remove confirmation overlay
		 * that is displayed when prompted by the user.
		 * @access public
		 */
		this.attachConfirmClickListeners = function() {
			$('.h5e-mwimg-confirm-no').click(function() {
				showRemoveImageLink();
				return false;
			});

			$('.h5e-mwimg-confirm-yes').click(function() {
				removeImage();
				return false;
			});
		}

		/**
		 * Show remove image link instead of confirmation
		 */
		function showRemoveImageLink() {
			$('#h5e-mwimg-mouseover-confirm').fadeOut('fast');
			$('#h5e-mwimg-mouseover a').fadeIn('fast');
			return false;
		}

		/**
		 * Remove an image from the DOM
		 */
		function removeImage() {
			editor.setPageDirty();

			var rmDiv = $(imageHoverIn.mouseHoverDiv);
			var chain = rmDiv.parentsUntil('.mwimg');
			// make sure there is actually a parent with class mwimg
			if (chain.length < 3) {
				rmDiv = chain.last().parent();
			}
			rmDiv.remove();
			$('#h5e-mwimg-mouseover').css('display', 'none');
			return false;
		}

		/**
		 * Remove image editing listeners
		 * @access public
		 */
		this.removeImageHoverListeners = function () {
			$('.rounders').unbind('mouseenter');
		}

	}

	var browser, cursor, drafts, editor, images, 
		inlineLinks, keyInput, newArticles, references,
		relatedWikihows, sections, toolbar, utilities;

	function singleton() {
		images = images || new Images();
		return images;
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
		images:
		  { init: init,
			singleton: singleton }
	};


})(jQuery) ); // exec anonymous function and return resulting class

