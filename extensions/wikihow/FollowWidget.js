/*
 * All the javascript required to display and use the Follow Widget
 */


/*
 *
 * Follow Widget class
 *
 */

// class to do "save emails" 

var overrideWinpopFuncs = {
	'replaceLinks': null,
	'resizeModal': null,
	'closeModal': null
};

function FollowWidget() {
	
}

// put up the modal dialog
FollowWidget.prototype.doFollowModal = function () {
	jQuery('#dialog-box').html('');
	url = '/Special:FollowWidget?article-title=' + encodeURIComponent(wgTitle);
	jQuery('#dialog-box').load(url, function(){
		jQuery('#dialog-box').dialog({
		   width: 450,
		   modal: true,
		   title: 'Email List'
		});
	});
	
};


FollowWidget.prototype.displayError = function (msgID) {
	jQuery('#eiu-error-message').css('display', 'block');
	var msg = wfMsg(msgID);
	msg = (msg != '' ? msg : msgID);
	jQuery('#eiu-error-message').html(msg);
};

FollowWidget.prototype.resetError = function () {
	var errDiv = jQuery('#eiu-error-message');
	if (errDiv.length) {
		errDiv.css('display', 'none');
	}
};

FollowWidget.prototype.submitEmail = function (email) {
	var params = 'email=' + email;
	var that = this;
	jQuery.getJSON(
		'/Special:SubmitEmail',
		{newEmail: email},
		function(data){
			if (data.success) {
				alert(data.message);
				jQuery('#dialog-box').dialog('close');
			}
			else{
				alert(data.message);
				
			}
		}
	);
};


FollowWidget.prototype.htmlBusyWheel = function () {
	var html = '<img src="/extensions/wikihow/rotate.gif" alt="" />';
	return html;
};

// singleton instance of this class
var followWidget = new FollowWidget();


