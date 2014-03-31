var STU_BUILD = "4";
var WH = WH || {};

// BOUNCE TIMER MODULE
WH.ExitTimer = (function ($) {

var LOGGER_ENABLE = (wgNamespaceNumber == 0 && wgAction == "view");

var startTime = false;
var duration = 0;
var DEFAULT_PRIORITY = 0;
var fromGoogle = false;

function getTime() {
	return (new Date()).getTime();
}

function pingSend(priority, domain, message, doAsync) {
	var loggerUrl = '/Special:BounceTimeLogger?v=6';
	if (priority != DEFAULT_PRIORITY) {
		loggerUrl += '&_priority=' + priority;
	}
	loggerUrl += '&_domain=' + domain;
	loggerUrl += '&_message=' + encodeURI(message);
	loggerUrl += '&_build=' + STU_BUILD;
	$.ajax({url: loggerUrl, async: doAsync});
}

function getDomain() {
	if (fromGoogle) {
		var isMobile = !!(location.href.match(/\bm\./i));
		if (isMobile) {
			return 'vm'; // virtual domain mapping to mb and pv domains
		} else {
			return 'vw'; // virtual domain mapping to bt and pv domains
		}
	} else {
		return 'pv';
	}
}

function sendExitTime() {
	if (startTime) {
		//startTime may not be set if window was blurred, then close
		//without being brought to the foreground
		var viewTime = (getTime() - startTime);
		duration = duration + viewTime;
	}
	startTime = false;

	var message = wgPageName + " btraw " + (duration / 1000);
	var domain = getDomain();
	if ($.browser.msie && $.browser.version < 7) {
		return;
	}

	pingSend(DEFAULT_PRIORITY, domain, message, false);
}

function onUnload() {
	sendExitTime();
}

function onBlur() {
	var viewTime = getTime() - startTime;
	duration += viewTime;
	startTime = false;
}

function onFocus() {
	startTime = getTime();
}

function checkFromGoogle() {
	var ref = typeof document.referrer === 'string' ? document.referrer : '';
	var googsrc = !!(ref.match(/^[a-z]*:\/\/[^\/]*google/i));
	return googsrc;
}

function start() {
	if (LOGGER_ENABLE) {
		fromGoogle = checkFromGoogle();
		if (typeof WH.exitTimerStartTime == 'number'
			&& WH.exitTimerStartTime > 0)
		{
			startTime = WH.exitTimerStartTime;
			WH.exitTimerStartTime = 0;
		} else {
			startTime = getTime();
		}
		$(window).unload(function(e) {
			// flowplayer fires unload events erroneously. 
			// Don't call onUnload if triggered by flowplayer elements 
			if (!(typeof(e) !== undefined 
				&& typeof(e.target) !== undefined 
				&& $(e.target).attr('id') !== undefined 
				&& $(e.target).attr('id').indexOf('whvid-player'))) {
				onUnload();
			}
		});
		$(window).focus(onFocus);
		$(window).blur(onBlur);
	}
}

return {
	'start': start
};

})(jQuery);
