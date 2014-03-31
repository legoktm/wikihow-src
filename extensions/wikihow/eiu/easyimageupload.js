/*
 * All the javascript required to display and use the Easy Image Upload
 * dialog box on the edit pages.
 */

/*
 *
 * WHCursorHelper class.
 *
 * Deals with manipulation of raw wikiHow article text,
 * such as dialogs with steps, etc.
 *
 */
function WHCursorHelper() {
	// default
	this.browser = null;
}

WHCursorHelper.prototype.getBrowser = function (txtarea) {
	if (!this.browser) {
		this.browser = (txtarea.selectionStart || txtarea.selectionStart == '0' ? 
			"ff" : (document.selection ? "ie" : false ) );
	}
	return this.browser;
};

WHCursorHelper.prototype.getCursorPos = function (txtarea) {
	var browser = this.getBrowser(txtarea);
	if (browser == "ie") { 
		strPos = this.ieGetCursorPos(txtarea);
	}
	else if (browser == "ff") strPos = txtarea.selectionStart;
	return strPos;
};

// this method was copied from here (contains explanation there):
// http://linebyline.blogspot.com/2006/11/textarea-cursor-position-in-internet.html
WHCursorHelper.prototype.ieGetCursorPos = function (textarea) {

	textarea.focus();
	var selection_range = document.selection.createRange().duplicate();

	// Create three ranges, one containing all the text before the selection,
	// one containing all the text in the selection (this already exists), and one containing all
	// the text after the selection.
	var before_range = document.body.createTextRange();
	before_range.moveToElementText(textarea);                    // Selects all the text
	before_range.setEndPoint("EndToStart", selection_range);     // Moves the end where we need it

	var after_range = document.body.createTextRange();
	after_range.moveToElementText(textarea);                     // Selects all the text
	after_range.setEndPoint("StartToEnd", selection_range);      // Moves the start where we need it

	var before_finished = false, selection_finished = false, after_finished = false;
	var before_text, untrimmed_before_text, selection_text, untrimmed_selection_text, after_text, untrimmed_after_text;

	// Load the text values we need to compare
	before_text = untrimmed_before_text = before_range.text;
	selection_text = untrimmed_selection_text = selection_range.text;
	after_text = untrimmed_after_text = after_range.text;

	// Check each range for trimmed newlines by shrinking the range by 1 character and seeing
	// if the text property has changed.  If it has not changed then we know that IE has trimmed
	// a \r\n from the end.
	do {
		if (!before_finished) {
			if (before_range.compareEndPoints("StartToEnd", before_range) == 0) {
				before_finished = true;
			} else {
				before_range.moveEnd("character", -1)
				if (before_range.text == before_text) {
					untrimmed_before_text += "\r\n";
				} else {
					before_finished = true;
				}
			}
		}
		if (!selection_finished) {
			if (selection_range.compareEndPoints("StartToEnd", selection_range) == 0) {
				selection_finished = true;
			} else {
				selection_range.moveEnd("character", -1)
				if (selection_range.text == selection_text) {
					untrimmed_selection_text += "\r\n";
				} else {
					selection_finished = true;
				}
			}
		}
		if (!after_finished) {
			if (after_range.compareEndPoints("StartToEnd", after_range) == 0) {
				after_finished = true;
			} else {
				after_range.moveEnd("character", -1)
				if (after_range.text == after_text) {
					untrimmed_after_text += "\r\n";
				} else {
					after_finished = true;
				}
			}
		}

	} while (!before_finished || !selection_finished || !after_finished);

	// Untrimmed success test to make sure our results match what is actually in the textarea
	// This can be removed once you're confident it's working correctly
	var untrimmed_text = untrimmed_before_text + untrimmed_selection_text + untrimmed_after_text;
	var untrimmed_successful = false;
	if (textarea.value == untrimmed_text) {
		untrimmed_successful = true;
	}
	// ** END Untrimmed success test

	var startPoint = untrimmed_before_text.length;
	var endPoint = startPoint + untrimmed_selection_text.length;
	var selected_text = untrimmed_selection_text;

	//alert("Start Index: " + startPoint + "\nEnd Index: " + endPoint + "\nSelected Text\n'" + selected_text + "'");
	return startPoint;
};

// Utility function to insert text into a textarea control
//
// Different similar functin at: http://alexking.org/blog/2003/06/02/inserting-at-the-cursor-using-javascript
// From: http://www.scottklarr.com/topic/425/how-to-insert-text-into-a-textarea-where-the-cursor-is/ blog
WHCursorHelper.prototype.insertAtCursor = function (areaId, text) {
	var txtarea = document.getElementById(areaId);
	var scrollPos = txtarea.scrollTop;
	var strPos = this.getCursorPos(txtarea);
	
	var front = (txtarea.value).substring(0, strPos);
	var back = (txtarea.value).substring(strPos, (txtarea.value).length); 
	txtarea.value = front + text + back;
	strPos = strPos + text.length;
	this.setFocusAndScroll(txtarea, strPos);
};

WHCursorHelper.prototype.setFocusAndScroll = function (txtarea, strPos) {
	var browser = this.getBrowser(txtarea);
	if (browser == "ie") { 
		txtarea.focus();
		var range = document.selection.createRange();
		range.moveStart('character', -(txtarea.value).length);
		range.moveStart('character', strPos);
		range.moveEnd('character', 0);
		range.select();
	} else { //if (browser == "ff") {
		txtarea.selectionStart = strPos;
		txtarea.selectionEnd = strPos;
		txtarea.focus();
	}
	//txtarea.scrollTop = scrollPos;
};

WHCursorHelper.prototype.removeNonViewableText = function (text) {
	text = text.
		replace(/^[#*]*/m, ''). // remove starting ##* 
		replace(/\[\[Image:[^\]]+\]\]/g, ''). // remove Image: wikitags
		replace(/\[\[([^\]]+)(\|([^\]]+))*\]\]/g, '$3'). // remove link wikitags, but leave visible link text
		replace(/<[^>]+>/g, ''). // remove html tags
		replace(/''+/g, ''); // remove quotes from '''bold''' etc
	return text;
};

WHCursorHelper.prototype.splitSteps = function (text) {
	var steps = text.split(/^#/m);
	if (steps.length && steps[0] === '') {
		steps.shift();
	}
	steps = jQuery.map(steps, function (step) {
		return '#' + step;
	});
	return steps;
};

WHCursorHelper.prototype.removeIntroTemplate = function (intro) {
	intro = intro.replace(/\{\{IntroNeedsImage\}\}/, '');
	return intro;
};

WHCursorHelper.prototype.insertIntoIntro = function (intro, tag) {
	intro = intro.replace(/^((\s*|\{\{[^}]+\}\}|\[\[Image:[^\]]+\]\])*)/, '$1' + tag);
	intro = this.removeIntroTemplate(intro);
	return intro;
};

WHCursorHelper.prototype.addToStep = function (stepsText, stepNum, tag) {
	var steps = this.splitSteps(stepsText);
	var step = steps[stepNum - 1];
	var newStep = this.insertIntoStep(step, tag);
	steps[stepNum - 1] = newStep;
	stepsText = steps.join('');
	return stepsText;
};

WHCursorHelper.prototype.insertIntoStep = function (step, tag) {
 	var json = jQuery('#ImageUploadImageDetails').val();
	var imageDetails = jQuery.parseJSON(json);
	if (imageDetails['sizing'] == 'automatic' && imageDetails['layout'] == 'center') {
		if (step.indexOf('\n') == step.length - 1) {
			step = step.substring(0, step.length - 1);
		}
		step = step + '<br><br>' + tag + '\n';
	} else {
		step = step.replace(/^((\s*[#* ]*\s*)?(\{\{[^}]+\}\}\s*|\[\[Image:[^\]]+\]\]\s*)*)/, '$1' + tag);
	}
	return step;
};

WHCursorHelper.prototype.summarizeStep = function (step, numWords) {
	step = this.removeNonViewableText(step);
	var words = step.split(/ /);
	var summaryWords = [];
	jQuery(words).each(function (i, word) {
		if (summaryWords.length < numWords && word.match(/\w+/)) {
			summaryWords.push(word);
		}
	});
	return summaryWords.join(' ');
};

// singleton instance
var whCursorHelper = new WHCursorHelper();

/*
 *
 * EasyImageUpload class
 *
 */

// class to do "easy image uploads" 
function EasyImageUpload() {

	// psuedo-private vars (we want them accessible by public methods,
	// so we don't use Crockford's private var pattern)
	this.m_textAreaID = 0;
	this.m_origin = '';
	this.m_postCallback = null;
	this.m_postCallbackParams = null;
	this.m_currentStep = 0;
	this.m_currentImageSource = 'flickr';
	if (this.m_lastQuery == '')
		this.m_lastQuery = wgTitle;
}

// put up the modal dialog
EasyImageUpload.prototype.doEIUModal = function (origin, currentStep) {

	// save current text field selected
	if (typeof this.m_postCallback == 'function') {
		origin = 'callback';
	}
	this.m_origin = origin;
	if (origin === 'advanced') {
		this.m_textAreaID = 'wpTextbox1';
	} else if (origin === 'intro') {
		this.m_textAreaID = 'summary';
	} else if (origin === 'steps') {
		this.m_textAreaID = 'steps_text';
	} else if (origin === 'ingredients') {
		this.m_textAreaID = 'ingredients_text';
	} else if (origin === 'tips') {
		this.m_textAreaID = 'tips_text';
	} else if (origin === 'warnings') {
		this.m_textAreaID = 'warnings_text';
	} else if (origin === 'thingsyoullneed') {
		this.m_textAreaID = 'thingsyoullneed_text';
	}

	this.logAction('Show_img_dialog', origin);

	// save current step
	if (typeof currentStep === 'number' && currentStep === 0) {
		this.m_currentStep = this.getCurrentStep();
	}
	else {
		this.m_currentStep = 0;
	}

	// set up post- dialog load callback
	var showBox = this.m_currentStep !== 0;
	var that = this;
	var onloadFunc = function () {
		var url = '/' + wfMsg('special-easyimageupload') + '?article-title=' + encodeURIComponent(wgTitle);
		jQuery('#eiu-dialog-inner').load(url, function (data) {

			that.showStepBox(showBox);
			if (!wgUserName) {
				that.displayError('eiu-user-name-not-found-error');
				// disable all possible actions on this page
				jQuery('#eiu_uploadform').hide();
				jQuery('#eiu-image-search').hide();
				jQuery('#eiu-step-selection').hide();
				jQuery('#eiu_recently_uploaded').hide();
				jQuery('#easyimageupload h3').hide();
			}

			// load initial flickr search results
			var html = that.htmlBusyWheel();
			jQuery('#eiu_recently_uploaded').html(html);
			that.loadImages('flickr', wgTitle, 1);
		});
		
		//move down 90px so it doesn't interfere w/ our html5 toolbar
		var dialog_box = jQuery('#eiu-dialog').parent('.ui-dialog');
		if (dialog_box.offset().top == 0) 
			dialog_box.offset({top: 92});
	};

	jQuery('#eiu-dialog-inner').html('');
	jQuery('#eiu-dialog').dialog({
		width: 750,
		height: 600,
		modal: true,
		closeText: 'Close',
		dialogClass: 'modal2',
		//this messes up Chrome/Safari zIndex: 1000000,
		open: onloadFunc,
		close: function() {
			if (that.m_postCallback) {
				if (that.m_postCallbackParams) {
					that.m_postCallback(true, that.m_postCallbackParams);
				} else {
					that.m_postCallback(false);
				}
				that.m_postCallback = null;
				that.m_postCallbackParams = null;
			}
		}
	});
};

EasyImageUpload.prototype.setCompletionCallback = function (callback) {
	this.m_postCallback = callback;
};

EasyImageUpload.prototype.insertWikiTag = function (wikitag) {
	var textarea = jQuery('#' + this.m_textAreaID);
	if (this.m_origin === 'intro') {
		var intro = textarea.val();
		intro = whCursorHelper.insertIntoIntro(intro, wikitag);
		textarea.val(intro);
	} else if (this.m_origin === 'steps') {
		var steps = textarea.val();
		var currentStep = this.m_currentStep > 0 ? this.m_currentStep : 1;
		steps = whCursorHelper.addToStep(steps, currentStep, wikitag);
		textarea.val(steps);
	} else {
		whCursorHelper.insertAtCursor(this.m_textAreaID, wikitag);
	}
};

// shows the step box select control, if specified by showBox
EasyImageUpload.prototype.showStepBox = function (showBox) {
	if (showBox) {
		var text = jQuery('#' + this.m_textAreaID).val();
		var steps = whCursorHelper.splitSteps(text);

		var that = this;
		var selectControl = jQuery('#eiu-step-selection');
		// clear drop down
		selectControl.html('');
		
		// populate with new step summaries
		var internalStepNum = 1, displayStepNum = 1;
		steps = jQuery.makeArray(steps);
		if (steps.length > 0) {
			jQuery.each(steps, function(i, step) { 
				var step = steps[i];
				var numWords = 5;
				var summary = whCursorHelper.summarizeStep(step, numWords) + '...';
				var opt = jQuery('<option value="' + internalStepNum + '"></option>');
				var dropdownTabStr = "\xa0\xa0"; // &nbsp; character x 2
				var hashes = step.
					replace(/(\s|\n|\r)+/g, '').
					replace(/^([#*]*)[^#*].*$/, '$1');
				var subStepLevel = hashes.length;
				if (subStepLevel <= 1) {
					var text = 'Step #' + displayStepNum + ': ' + summary;
					displayStepNum++;
				} else {
					var text = '';
					for (var j = 0; j < subStepLevel; j++) {
						text += dropdownTabStr;
					}
					text += summary;
				}
				opt.html(text);
				selectControl.append(opt);
				internalStepNum += 1;
			});
			// select the step that the cursor is on
			selectControl.selectedIndex = this.m_currentStep - 1;
		} else {
			// don't show the box if there are no steps in it
			showBox = false;
		}
	}
	jQuery('#eiu-step-box').css('display', (showBox ? 'block' : 'none'));
};

// for the onchange event in the control
EasyImageUpload.prototype.selectStep = function () {
	var selectControl = jQuery('#eiu-step-selection')[0];
	this.m_currentStep = selectControl.selectedIndex + 1;
};

// called when the dialog starts being displayed and we want to
// find the current step a user is editing
EasyImageUpload.prototype.getCurrentStep = function () {
	if (this.m_textAreaID) {
		var txtarea = jQuery('#' + this.m_textAreaID);
		var text = txtarea.val();
		var strPos = whCursorHelper.getCursorPos(txtarea[0]);
		var startText = text.substring(0, strPos);

		var startSteps = whCursorHelper.splitSteps(startText);
		var currentStep = 1;
		var len = 0;
		jQuery(startSteps).each(function (i, step) {
			len += step.length;
			if (len < strPos) currentStep++;
		});
		return currentStep;
	}
	else {
		return 0;
	}
};

EasyImageUpload.prototype.displayNetworkError = function () {
	this.displayError('eiu-network-error');
};

EasyImageUpload.prototype.displayError = function (msgID) {
	jQuery('#eiu-error-message').css('display', 'block');
	var msg = wfMsg(msgID);
	jQuery('#eiu-error-message').html(msg);
};

EasyImageUpload.prototype.resetError = function () {
	var errDiv = jQuery('#eiu-error-message');
	if (errDiv.length) {
		errDiv.css('display', 'none');
	}
};

// called when the AJAX file upload is starting
EasyImageUpload.prototype.uploadOnStart = function () {
	jQuery('#eiu-wheel-upload').css('display', 'block');
	this.resetError();
};

// called when the AJAX file upload has finished, with the resulting HTML
EasyImageUpload.prototype.uploadOnComplete = function (response) {
	this.dialogOnLoaded(response);
};

/**
 * Communicates with server after each successive dialog in the image upload
 * process.
 */
EasyImageUpload.prototype.storeImageDetails = function (formName, postData) {
	if (typeof postData === 'undefined') {
		postData = jQuery('#' + formName).serialize();
	}
	var that = this;
	var url = '/' + wfMsg('special-easyimageupload');
	jQuery.post(url, postData, function(html) {
		that.dialogOnLoaded(html);
	});

	
};

EasyImageUpload.prototype.dialogOnLoaded = function (html) {
	if (html) {
		jQuery('#easyimageupload').html(html);

		// ImageUploadSource is on the image details and if it has the
		// string 'upload' then it was a user upload and we need to show
		// the licensing html
		var that = this;
		var callback = function () {
			var node = jQuery('#ImageUploadSource');
			if (node.length) {
				var source = node.html();
				that.logAction('Select_img', source);
				if (source == 'upload') {
					jQuery('#ImageUploadSection').css('display', 'block');
				}
			}

			// ImageAttribution is on the image details and conflict dialogs
			var node = jQuery('#ImageAttribution');
			if (node.length) {
				eiuImageAttributionComment = node.val();
			}

			// eiu-image-details-page is only on the image details dialog
			if (jQuery('#eiu-image-details-page').length) {
				that.loadSlider();
			}

			// if eiu-go-back button exists, there was an error displayed
			if (jQuery('.eiu-finish').length) {
				// Do reset on button click
				jQuery('.eiu-finish').click(function() {
					jQuery('#eiu-dialog').dialog('close');
				});
			}

			// ImageUploadTag is on the upload summary dialog
			var node = jQuery('#ImageUploadTag');
			if (node.length) {
				if (that.m_origin == 'callback') {
					var json = jQuery('#ImageUploadImageDetails').val();
					var imageDetails = jQuery.parseJSON(json);
					that.m_postCallbackParams = imageDetails;
					jQuery('#eiu-dialog').dialog('close');
				} else {
					var wikitag = node.val();
					that.insertWikiTag(wikitag);

					// auto-populate edit summary if empty
					var node = jQuery('#wpSummary1');
					if (node.length && node.val() === '') {
						var newSummary = wfMsg('added-image');
						var chosenImageFilename = jQuery('#ImageUploadFilename');
						if (chosenImageFilename.length) {
							newSummary += ': ' + chosenImageFilename.val();
						}
						node.val(newSummary);
					}
				}

				that.logAction('Add_img');
			}
		};
		setTimeout(callback, 0);
	} else {
		this.displayNetworkError();
	}
};


EasyImageUpload.prototype.insertImage = function (src, imgID) {

	var image;
	if(jQuery('#eiu-preview').css('display') == "none")
		image = jQuery('#thumb-'+imgID);
	else
		image = jQuery('#eiu-preview-image');
	image.attr('src', '/extensions/wikihow/rotate.gif');
	image.css('margin-bottom', '15px');
	var imageDetails = jQuery('#' + imgID).html();

	var postData = 'uploadform1=1&src=' + src + '&img-details=' + encodeURIComponent(imageDetails);

	this.storeImageDetails(false, postData);

	return false;
};

EasyImageUpload.prototype.previewImage = function (src, imgID, insertLink, insertMessage) {
	var imageDetails = jQuery('#' + imgID).html();
	var details = jQuery.parseJSON(imageDetails);
	var imgURL = details['url'];

	// resize images from WM using our servers since their sizes aren't standard
	if (/^http:..upload.wikimedia.org/.test(imgURL)) {
		imgURL = wgServer + '/Special:Easyimageupload?preview-resize=1&url=' + encodeURIComponent(imgURL);
	}

	// do display imgURL here in a dialog
	jQuery('#eiu-preview-image').attr('src', '/extensions/wikihow/rotate.gif');
	jQuery('#eiu-preview').css('display', 'inline');
	jQuery('#eiu-preview-links').html('<a href="#" onclick="' + insertLink + '"> ' + insertMessage + '</a> | <a href="#" onclick="return easyImageUpload.closePreview();">' + wfMsg('cancel') + '</a>');
	
	var newImage = new Image();
	newImage.onload = function(){
		jQuery('#eiu-preview-image').attr('src', this.src);
		if (jQuery('#easyimageupload').length)
			var outsideWidth = jQuery('#easyimageupload').width();
		else
			var outsideWidth = jQuery('#introimageupload').width();
		jQuery('#eiu-preview').css({
			'left': (outsideWidth - this.width)/2 + 'px',
			'width': this.width + 'px',
			'height': this.height + 45 + 'px'
		});
	}
	newImage.src = imgURL;

	return false;
};

EasyImageUpload.prototype.closePreview = function(){
	jQuery('#eiu-preview').css('display', 'none');
	return false;
}


// set inside the image selection or image_details dialog (depending whether
// it's an upload or a scrape)
var eiuImageAttributionComment = '';

EasyImageUpload.prototype.resolveImageConflictRename = function () {
	jQuery('#ImageUploadRename').val('1');
	jQuery('#ImageAttribution').val(eiuImageAttributionComment);
	this.storeImageDetails('ConflictImageForm');
};

EasyImageUpload.prototype.resolveImageConflictExisting = function () {
	jQuery('#ImageUploadUseExisting').val('1');
	this.storeImageDetails('ConflictImageForm');
};

EasyImageUpload.prototype.loadImagesSetCurrent = function (src) {
	if (typeof src === 'string' && src !== 'current')
		this.m_currentImageSource = src;
	return false;
};

EasyImageUpload.prototype.loadImages = function (src, query, page) {
	if (typeof src === 'string' && src !== 'current')
		this.m_currentImageSource = src;
	if (src === 'current')
		src = this.m_currentImageSource;
	if (typeof query === 'string')
		this.m_lastQuery = query;

	if (src === 'flickr') {
		this.setActiveTab('eiu-flickr-link');
		this.loadImagesFlickr(query, page);
	}
	else if (src == 'wiki') {
		this.setActiveTab('eiu-this-wiki-link');
		this.loadImagesWikimedia(query, page);
	}
	else if (src == 'local') {
		this.setActiveTab('eiu-local-link');
		this.loadImagesUpload();
	}
};

EasyImageUpload.prototype.loadImagesFlickr = function (query, page) {
	var params = 'src=' + this.m_currentImageSource + '&q=' + encodeURIComponent(this.m_lastQuery) + '&page=' + page;
	var that = this;
	var url = '/Special:Findimages?' + params;
	jQuery.get(url, function(jsonText) {
		if (jsonText) {
			var data = jQuery.parseJSON(jsonText);
			that.htmlImageResultsCallback('flickr', data);
		} else {
			that.displayNetworkError();
		}
	});
};

var EIU_WIKIMEDIA_SEARCH_SITE = 'wikimedia.org';
var EIU_RESULTS_PER_PAGE = 8;
var eiuImageSearch = null;

// called by the google API script load
function eiuLoadGoogleSearch() {
	google.load('search', '1', {callback: eiuLoadGoogleDone});
}

// called after google.load call completes
function eiuLoadGoogleDone() {
}

EasyImageUpload.prototype.loadImagesWikimedia = function (query, page) {
	// Our ImageSearch instance.
	eiuImageSearch = new google.search.ImageSearch();

	// Restrict to wikimedia.org search
	eiuImageSearch.setSiteRestriction(EIU_WIKIMEDIA_SEARCH_SITE);

	// Restrict to extra large images only
	// also available: google.search.ImageSearch.IMAGESIZE_LARGE
	eiuImageSearch.setRestriction(google.search.ImageSearch.RESTRICT_IMAGESIZE,
								  google.search.ImageSearch.IMAGESIZE_EXTRA_LARGE);

	// Here we set a callback so that anytime a search is executed, 
	// it will call the searchComplete function and pass it our 
	// ImageSearch searcher.  When a search completes, our 
	// ImageSearch object is automatically populated with the results.
	eiuImageSearch.setSearchCompleteCallback(this, this.htmlImageResultsCallback, ['wiki']);

	// define the number of results returned
	eiuImageSearch.setResultSetSize(google.search.Search.LARGE_RESULTSET);

	// execute search
	eiuImageSearch.execute(query);
};



EasyImageUpload.prototype.loadImagesUpload = function () {
	var url = '/Special:Easyimageupload?getuploadform=1';
	jQuery.get(url, function(data) {
		if (data) {
			jQuery('#eiu_recently_uploaded').html(data);
		}
	});
};

EasyImageUpload.prototype.htmlImageResultsCallback = function (src, data) {
	if (src == 'flickr') {
		var results = data.photos;
		var page = data.page;
		var next_available = data.next_available;
		var msg = data.msg;
		var changePageFunc = 'flickrImageSearchChangePage';
	} else {
		var results = eiuImageSearch.results;
		var cursor = results.length ? eiuImageSearch.cursor : null;
		var total_results = cursor ? cursor.estimatedResultCount : 0;
		var index = cursor ? cursor.currentPageIndex : 0;
		var page = index + 1;
		var next_available = (cursor && index + 1 < cursor.pages.length ? Math.min(EIU_RESULTS_PER_PAGE, total_results - cursor.pages[index + 1].start) : 0);
		var msg = EIU_WIKIMEDIA_SEARCH_SITE + ' (' + total_results + ' results, approx)';
		var changePageFunc = 'wikimediaImageSearchChangePage';
	}

	var html = 
		'<table width="100%" align="center"><tr>' +
		'<td class="recently_uploaded_title" colspan="2">' + msg + '</td>' +
		'<td colspan="2" class="recently_uploaded_next">';
	if (page > 1)
		html +=
			'<a href="#" onclick="easyImageUpload.' + changePageFunc + '(' + (page - 1) + '); return false;">' + wfMsg('prev-page-link', EIU_RESULTS_PER_PAGE) + '</a>';
	if (page > 1 && next_available > 0)
		html += ' | ';
	if (next_available > 0)
		html += '<a href="#" onclick="easyImageUpload.' + changePageFunc + '(' + (page + 1) + '); return false;">' + wfMsg('next-page-link', next_available) + '</a>';
	html +=
		'</td>' +
		'</tr><tr>';
	jQuery(results).each( function(i, result) {
		if (i % 4 == 0 && i > 0)
			html += '</tr><tr>';
		if (src == 'flickr') {
			var detailsStr = result.details;
			var tbUrl = result.thumb_url;
		} else {
			var name = result.url.replace(/^.*\/(File:)?([^\/]+)$/, '$2');
			if (name === '')
				name = result.titleNoFormatting;
			var details = {
				'name': name,
				'url': result.url
			};
			var detailsStr = JSON.stringify(details);
			var tbUrl = result.tbUrl;
		}

		if (wgUserName)
			var link = '<a href="#" onclick="return easyImageUpload.insertImage(\'' + src + '\', \'eiu-flickr-img-' + i + '\');">' + wfMsg('eiu-insert') + '</a> | <a href="#" onclick="return easyImageUpload.previewImage(\'' + src + '\', \'eiu-flickr-img-' + i + '\', \'return easyImageUpload.insertImage(\\\'wiki\\\', \\\'eiu-flickr-img-' + i + '\\\');\', \'' + wfMsg('eiu-insert') + '\');">' + wfMsg('eiu-preview') + '</a>';
		else
			var link = wfMsg('eiu-insert') + ' | ' + wfMsg('eiu-preview');

		html +=
			'<td class="rresult">' +
			'<img src="' + tbUrl + '" id="thumb-eiu-flickr-img-' + i + '" /><br/>' +
			link +
			'<div style="display: none;" id="eiu-flickr-img-' + i + '">' + detailsStr + '</div>' +
			'</td>';
	});
	html += 
		'</tr>' +
		'</table>';

	jQuery('#eiu_recently_uploaded').html(html);
};


EasyImageUpload.prototype.wikimediaImageSearchChangePage = function (page) {
	// retrieve a particular page
	eiuImageSearch.gotoPage(page - 1);
};

EasyImageUpload.prototype.flickrImageSearchChangePage = function (page) {
	this.loadImagesFlickr(this.m_lastQuery, page);
};

/*
// utility function to take a wiki tag and insert it into the doc
function insertWikiTagAndClose(tagID) {
	var tag = jQuery('#' + tagID).html();
	easyImageUpload.insertWikiTag(tag);
	closeModal();
	return false;
}
*/

// utility function to set the find box links to toggle class depending
// what was clicked
EasyImageUpload.prototype.setActiveTab = function (linkname) {
	//clear
	jQuery('#eiu-this-wiki-link').parent().removeClass('eiu-selected');
	jQuery('#eiu-flickr-link').parent().removeClass('eiu-selected');
	jQuery('#eiu-local-link').parent().removeClass('eiu-selected');
	//add
	jQuery('#'+linkname).parent().addClass('eiu-selected');
}

EasyImageUpload.prototype.cssSetFindTabsWeight = function(wikiActive) {
	if (wikiActive) {
		if (!jQuery('#eiu-this-wiki-link').hasClass('on')) {
			jQuery('#eiu-this-wiki-link').addClass('on');
			jQuery('#eiu-flickr-link').removeClass('on');
		}
	} else {
		if (!jQuery('#eiu-flickr-link').hasClass('on')) {
			jQuery('#eiu-this-wiki-link').removeClass('on');
			jQuery('#eiu-flickr-link').addClass('on');
		}
	}
}

EasyImageUpload.prototype.cssShowHideAttribution = function () {
	var pos = jQuery('#wpLicense')[0].selectedIndex;
	var showIndexes = [5, 6, 7, 8, 10, 11, 12, 14];
	var showHide = showIndexes.indexOf(pos) >= 0;
	var newValue = showHide ? 'visible' : 'hidden';
	jQuery('#eiu-attribution-header').css('visibility', newValue);
	jQuery('#eiu-attribution-input').css('visibility', newValue);
}

var eiuImageDetails = '';
EasyImageUpload.prototype.imageDetailsSave = function () {
	jQuery('#eiu_insert').css('display', 'none');
	jQuery('#eiu-wheel-details').css('display', 'block');
	eiuImageDetails = jQuery('#eiu-insert-image').serialize();
	this.addImageDetailsToForm();
}

EasyImageUpload.prototype.addImageDetailsToForm = function () {
	jQuery('#eiu-image-details').val(eiuImageDetails);
}

var eiuOrigFullWidth, eiuOrigFullHeight;
var eiuMaxDisplayThumbWidth, eiuMaxDisplayThumbHeight;
var eiuAutomaticWidthPercentRight, eiuAutomaticWidthPercentCenter;
var CENTER_MIN_DIM = 400;

EasyImageUpload.prototype.loadSlider = function () {
	eiuOrigFullWidth = jQuery('#eiu-image-orig-width').html();
	// Default to right alignment if
	// - non-steps section
	// - image width is smaller than the required minimum centering width dimensino
	if ((this.m_origin != 'steps' && this.m_origin != 'advanced') || eiuOrigFullWidth < CENTER_MIN_DIM) {
		jQuery('input:radio[name=layout]')[0].checked = true;
	}
	eiuOrigFullHeight = jQuery('#eiu-image-orig-height').html();
	eiuMaxDisplayThumbWidth = jQuery('#eiu-thumb-img').width();
	eiuMaxDisplayThumbHeight = jQuery('#eiu-thumb-img').height();
	if (this.m_origin === 'intro') {
		var maxw = 250, maxh = 300;
	} else {
		var maxw = 180, maxh = 300;
	}

	// scale images to their "automatic" sizes, using maxw and maxh
	var eiuOrigThumbWidth = eiuOrigFullWidth;
	var eiuOrigThumbHeight = eiuOrigFullHeight;
	if (eiuOrigThumbWidth > maxw) {
		eiuOrigThumbWidth = maxw;
		eiuOrigThumbHeight = maxw * eiuOrigFullHeight / eiuOrigFullWidth;
	}
	if (eiuOrigThumbHeight > maxh) {
		eiuOrigThumbWidth = maxh * eiuOrigThumbWidth / eiuOrigThumbHeight;
		eiuOrigThumbHeight = maxh;
	}
	if (eiuOrigThumbWidth >= eiuOrigFullWidth) {
		eiuAutomaticWidthPercentRight = 100;
	} else {
		eiuAutomaticWidthPercentRight = Math.round(100 * eiuOrigThumbWidth / eiuOrigFullWidth);
	}


	// Change to 550px if image is for steps section
 	var json = jQuery('#ImageUploadImageDetails').val();
 	var json = jQuery('#ImageUploadImageDetails').val();
    var imageDetails = jQuery.parseJSON(json);
	this.m_postCallbackParams = imageDetails;

    var imageDetails = jQuery.parseJSON(json);
	this.m_postCallbackParams = imageDetails;

	CENTER_MAX_DIM = this.m_origin == 'steps' ? 550 : 400;
	if (eiuOrigFullWidth <= CENTER_MAX_DIM) {
		eiuAutomaticWidthPercentCenter = 100;
	} else {
		eiuAutomaticWidthPercentCenter = Math.round(100 * CENTER_MAX_DIM / eiuOrigFullWidth);
	}

	jQuery('#slider-converted-value').val(eiuAutomaticWidthPercentRight);

	var that = this;
	var changeFunc = function (evt, ui) {
		jQuery('#slider-converted-value').val(Math.round(ui.value));
		that.cssResizeImagePreview();
	};
	jQuery('#eiu-slider-track').slider({
		value: eiuAutomaticWidthPercentRight,
		min: 1,
		max: 100,
		step: 1,
		range: 'min',
		slide: changeFunc
	});

	jQuery('#slider-converted-value').keypress(function(evt) {
		// set the value when the 'return' key is detected
		if (evt.which === 13) {
			var v = parseFloat(this.value, 10);
			v = Math.round(v);
			v = (v < 1 ? 1 : (v > 100 ? 100 : v));
			this.value = v;

			// convert the real value into a pixel offset
			jQuery('#eiu-slider-track').slider('value', v);

			// resize image preview
			that.cssResizeImagePreview();
			return false;
		}
	});

	this.cssResizeImagePreview();
}

EasyImageUpload.prototype.cssResizeImagePreview = function () {
	var sizing = jQuery("input[name='sizing']:checked").val();
	var layout = jQuery("input[name='layout']:checked").val();
	var showHide = sizing == 'manual';
	var newValue = showHide ? '' : 'none';
	jQuery('#ImageWidthRow').css('display', newValue);

	if (sizing == 'automatic' && layout == 'right') {
		var percent = eiuAutomaticWidthPercentRight;
	} else if (sizing == 'automatic' && layout == 'center') {
		var percent = eiuAutomaticWidthPercentCenter;
	} else if (sizing == 'manual') {
		var percent = jQuery('#slider-converted-value').val();
	}
	var w = Math.round(percent * eiuOrigFullWidth / 100);
	var h = Math.round(percent * eiuOrigFullHeight / 100);

	jQuery('#chosen-width-display').html(w);
	jQuery('#chosen-height-display').html(h);
	w = this.m_origin == 'steps' && 
		sizing == 'automatic' && 
		layout == 'center' && 
		eiuOrigFullWidth >= CENTER_MAX_DIM 
		? CENTER_MAX_DIM : w; 

	jQuery('#chosen-width').val(w);
	jQuery('#chosen-height').val(h);

	w = Math.min(eiuMaxDisplayThumbWidth, w);
	h = Math.min(eiuMaxDisplayThumbHeight, h);
	jQuery('#eiu-thumb-img').width(w);
	jQuery('#eiu-thumb-img').height(h);
}

EasyImageUpload.prototype.closeUploadDialog = function () {
	jQuery('#eiu-dialog').dialog('close');
	if (window.location.href.indexOf('subaction=add-image-to-intro') >= 0) {
		needToConfirm = false;
		jQuery('#editform').submit();
	}
};

EasyImageUpload.prototype.htmlBusyWheel = function () {
	var html = '<img src="/extensions/wikihow/rotate.gif" alt="" />';
	return html;
};

EasyImageUpload.prototype.logAction = function (action, value) {
	var category = 'Registered_Editing';
	var label = 'Add_image_dialog';
	if (typeof value !== 'undefined') {
		gatTrack(category, action, label, value);
	} else {
		gatTrack(category, action, label);
	}
};

// singleton instance of this class
var easyImageUpload = new EasyImageUpload();

/**
 *
 *  AJAX IFRAME METHOD (AIM)
 *  http://www.webtoolkit.info/
 *
 */
AIM = {
 
	frame : function(c) {
 
		var n = 'f' + Math.floor(Math.random() * 99999);
		var d = document.createElement('DIV');
		d.innerHTML = '<iframe style="display:none" src="about:blank" id="'+n+'" name="'+n+'" onload="AIM.loaded(\''+n+'\')"></iframe>';
		document.body.appendChild(d);
 
		var i = document.getElementById(n);
		if (c && typeof(c.onComplete) == 'function') {
			i.onComplete = c.onComplete;
		}
 
		return n;
	},
 
	form : function(f, name) {
		f.setAttribute('target', name);
	},
 
	submit : function(f, c) {
		AIM.form(f, AIM.frame(c));
		if (c && typeof(c.onStart) == 'function') {
			return c.onStart();
		} else {
			return true;
		}
	},
 
	loaded : function(id) {
		var i = document.getElementById(id);
		if (i.contentDocument) {
			var d = i.contentDocument;
		} else if (i.contentWindow) {
			var d = i.contentWindow.document;
		} else {
			var d = window.frames[id].document;
		}
		if (d.location.href == "about:blank") {
			return;
		}
 
		if (typeof(i.onComplete) == 'function') {
			i.onComplete(d.body.innerHTML);
		}
	}
 
}

