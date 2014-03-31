var uploadBtnWidth = 165;

function ImageUploadHandler() {
	this.titleText = '';
}

ImageUploadHandler.prototype.uploadOnStart = function() {
	this.clearStatus();
	setTimeout(this.animateButtonOut, 0);
	$('#image-upload-wheel').css('opacity', '100%');
	setTimeout(function() {
		$('#image-upload-wheel').css('display', 'block');
		$('#image-upload-wheel').show();
	}, 750);
	this.clearThumb();
	return true;
};

ImageUploadHandler.prototype.uploadOnComplete = function(response) {
	$('#image-upload-wheel').hide();
	// $('#image-upload-form').each(function() {
	// 	this.reset();
	// });

	// Safari on iOS 6 devices turns the timestamp into a telephone link (why?!?), so here's a quick fix
	if (rios6.test(navigator.userAgent)) {
		var rtimestamp = /"timestamp"\s*:\s*"<a\s+href="tel:[0-9]*">([0-9]*)<\/a>"/g;
		if (rtimestamp.test(response))
			response = response.replace(rtimestamp, "\"timestamp\":$1");
	}

	this.handleResponse(JSON.parse(response));
};

ImageUploadHandler.prototype.handleResponse = function(data) {
	if (data.hasOwnProperty('error')) {
		this.displayError(data.error);
	} else {
		this.displaySuccess();
		this.displayThumb(data.thumbURL, data.titleText, data.titlePreText);
	}
};

ImageUploadHandler.prototype.animateButtonOut = function() {
	$('#image-upload-input').animate({
		width: 0,
		opacity: 0
	}, 750, 'easeInOutQuart');
	$('#image-upload-button-p').animate({
		opacity: 0
	}, 400, 'easeInOutQuart');
	$('#image-upload-disclaimer').animate({
		opacity: 0
	}, 400, 'easeInOutQuart');
	setTimeout(function () {
		$('#image-upload-disclaimer').css('opacity', 0);
		$('#image-upload-disclaimer').hide();
		$('#image-upload-input-wrapper').hide();
		$('#image-upload-wheel').css('display', 'block');
		$('#image-upload-wheel').show();
	}, 750);
};

ImageUploadHandler.prototype.animateButtonIn = function() {
	$('#image-upload-wheel').hide();
	$('#image-upload-input-wrapper').show();
	$('#image-upload-disclaimer').show();
	$('#image-upload-input').width(0);
	$('#image-upload-input').animate({
		width: uploadBtnWidth + 'px',
		opacity: '100%'
	}, 600, 'easeInOutQuart');
	$('#image-upload-button-p').animate({
		opacity: '100%'
	}, 600, 'easeInOutQuart');
	$('#image-upload-disclaimer').animate({
		opacity: '100%'
	}, 600, 'easeInOutQuart');
	setTimeout(function() { 
		$('#image-upload-disclaimer').css('opacity', '100%');
	}, 600);
};

ImageUploadHandler.prototype.clearStatus = function() {
	$('#image-upload-error').hide();
	$('#image-upload-success').hide();
	$('#image-upload-delete-error').hide();
	$('#image-upload-delete-success').hide();
};

ImageUploadHandler.prototype.clearThumb = function() {
	$('#image-upload-thumb-area').hide();
	$('#image-upload-thumb').attr('src', '/extensions/wikihow/rotate.gif');
};

ImageUploadHandler.prototype.displayError = function(errorMsg) {
	errorMsg = errorMsg.replace(/\[\[LOGIN\]\]/i, '<a href="/Special:Userlogin" rel="nofollow">log in</a>');
	$('#image-upload-error').html('<br />' + errorMsg);
	$('#image-upload-error').css('display', 'block');
	$('#image-upload-error').show();
	setTimeout(this.animateButtonIn, 750);
};

ImageUploadHandler.prototype.displaySuccess = function() {
	$('#image-upload-success').html('Your image has been successfully uploaded!');
	$('#image-upload-success').css('display', 'block');
	$('#image-upload-success').show();
	setTimeout(function () {
		$('#image-upload-wheel').hide();
		$('#image-upload-wheel').css('opacity', '100%');
	}, 750);
};

ImageUploadHandler.prototype.displayDeleteError = function(errorMsg) {
	$('#image-upload-delete').hide();
	errorMsg += ' Please contact an administrator for assistance.';
	$('#image-upload-delete-error').html(errorMsg);
	$('#image-upload-delete-error').css('display', 'block');
	$('#image-upload-delete-error').show();
};

ImageUploadHandler.prototype.displayDeleteSuccess = function() {
	$('#image-upload-delete-success').html('<br/>Your image has been deleted.');
	$('#image-upload-delete-success').css('display', 'block');
	$('#image-upload-delete-success').show();
};

ImageUploadHandler.prototype.displayThumb = function(thumbURL, titleText, imageURL) {
	this.titleText = titleText;
	$('#image-upload-delete').show();
	$('#image-upload-thumb-wheel').css('display', 'block');
	$('#image-upload-thumb-area').css('display', 'block');
	$('#image-upload-thumb-area').show();
	var img = $('<img src="' + thumbURL + '" />');
	img.load(function () {
		$('#image-upload-thumb').attr('src', thumbURL);
		$('#image-upload-thumb-wheel').css('display', 'none');
		$('#image-upload-thumb').css('display', 'block');
	});
};

// singleton instance of this class
var imageUploadHandler = new ImageUploadHandler();

var rios6 = /iP(ad|od|hone).*OS\s*6.*AppleWebKit/;

function uciSetup() {
	if (!$('#image-upload-container').length) {
		return;
	}

	// Hide image upload for devices known not to support it.
	// User agent test from: http://viljamis.com/blog/2012/file-upload-support-on-mobile/
	var isFileInputSupported = (function () {
		if (navigator.userAgent.match(/(Android (1.0|1.1|1.5|1.6|2.0|2.1))|(Windows Phone (OS 7|8.0))|(iP(ad|od|hone).*OS\s*(1|2|3|4|5))|(XBLWP)|(ZuneWP)|(w(eb)?OSBrowser)|(webOS)|(Kindle\/(1.0|2.0|2.5|3.0))/)) {
			return false;
		}
		var el = document.createElement("input");
		el.type = "file";
		return !el.disabled;
	})();

	// Currently a bit buggy on some browsers. These may get reenabled after more testing, possibly with a simplified version of the uploader.
	var isWeirdBrowser = (function() {
		if (navigator.userAgent.match(/(Opera Mini)/))
			return true;
		return false;
	})();

	if (!isFileInputSupported || isWeirdBrowser) {
		$('#image-upload-container').hide();
	} else {
		// Resize image button based on device width, bounds at 240-75 px and 420-75 px
		var maxWidth = ((window.innerWidth > 0) ? window.innerWidth : screen.width) - 75;
		var inputWidth = Math.min(Math.max(maxWidth, 165), 345);

		$('#image-upload-input').width(inputWidth + 'px');
		$('#image-upload-button-div').width(inputWidth-55 + 'px');

		// Resize image button text if text is too wide
		var buttonp = $('#image-upload-button-p');
		var desiredWidth = $('#image-upload-button-div').width() - 10,
			html = '<span style="white-space:nowrap"></span>',
			line = buttonp.wrapInner(html).children()[0],
			size = 16;

		while(buttonp.width() > desiredWidth && size > 6) {
			buttonp.css('font-size', size--);
		}
		buttonp.width(desiredWidth);

		uploadBtnWidth = $('#image-upload-input').width();

		$('#image-upload-delete').click(function(e) {
			$('#image-upload-delete').hide();
			$('#image-upload-delete-confirm').css('display', 'inline');
			$('#image-upload-delete-confirm').show();
			return false;
		});
		$('#image-upload-delete-confirm-no').click(function(e) {
			$('#image-upload-delete-confirm').hide();
			$('#image-upload-delete').css('display', 'inline');
			$('#image-upload-delete').show();
			return false;
		});
		$('#image-upload-delete-confirm-yes').click(function(e) {
			$.post(
				'/Special:ImageUploadHandler',
				{'delete': imageUploadHandler.titleText},
				function (data) {
					$('#image-upload-delete-confirm').hide();
					data = JSON.parse(data);
					if (data.hasOwnProperty('error')) {
						imageUploadHandler.displayDeleteError(data.error);
					} else if (data.hasOwnProperty('success')) {
						setTimeout(imageUploadHandler.animateButtonIn, 500);
						imageUploadHandler.clearStatus();
						imageUploadHandler.clearThumb();
						imageUploadHandler.displayDeleteSuccess();
					} else {
						imageUploadHandler.displayDeleteError('An unknown error has occurred.');
					}
					return false;
				}
			);
			return false;
		});
	}
}
