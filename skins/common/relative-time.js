if (!WH) WH = {};

// Note: this function is used and modified for the wikiHow: Tweet It Forward 
// tool
//
// this was cut up from here:
// http://timeago.yarp.com/jquery.timeago.js
WH.relativeTime = function (ts) {
	var substitute = function (str, value) {
		return str.replace(/%d/i, value);
	};

	var $l = {
		prefixAgo: null,
		prefixFromNow: null,
		suffixAgo: "ago",
		suffixFromNow: "from now",
		recently: "recently",
		ago: null, // DEPRECATED, use suffixAgo
		fromNow: null, // DEPRECATED, use suffixFromNow
		seconds: "less than a minute",
		minute: "1 minute",
		minutes: "%d minutes",
		hour: "1 hour",
		hours: "%d hours",
		day: "1 day",
		days: "%d days",
		month: "1 month",
		months: "%d months",
		year: "1 year",
		years: "%d years"
	};

	var prefix = $l.prefixAgo;
	var suffix = $l.suffixAgo || $l.ago;
	var distanceMillis = (new Date()).getTime() - parseInt(ts,10) * 1000;
	if (distanceMillis < 0) {
		//prefix = $l.prefixFromNow;
		//suffix = $l.suffixFromNow || $l.fromNow;

		// if event looks like it's in the future, just say "recently"
		return $l.recently;
	}
	distanceMillis = Math.abs(distanceMillis);

	var seconds = distanceMillis / 1000;
	var minutes = seconds / 60;
	var hours = minutes / 60;
	var days = hours / 24;
	var years = days / 365;

	var words = seconds < 45 && substitute($l.seconds, Math.round(seconds)) ||
		seconds < 90 && substitute($l.minute, 1) ||
		minutes < 45 && substitute($l.minutes, Math.round(minutes)) ||
		minutes < 90 && substitute($l.hour, 1) ||
		hours < 24 && substitute($l.hours, Math.round(hours)) ||
		hours < 48 && substitute($l.day, 1) ||
		days < 30 && substitute($l.days, Math.floor(days)) ||
		days < 60 && substitute($l.month, 1) ||
		days < 365 && substitute($l.months, Math.floor(days / 30)) ||
		years < 2 && substitute($l.year, 1) ||
		substitute($l.years, Math.floor(years));

	return ([prefix, words, suffix].join(" "));
};

/**
 * Every minute, refresh the relative times in an html page.  (ie, change
 * strings like "1 minute ago" to "2 minutes ago".)  In each node in "selector"
 * there should be a jQuery data string called "dataKey" (which has the
 * default value "timestamp").  This function should be called after
 *
 * E.g. Initial HTML might look like:
 * <span class="reltime">2 minutes ago<input type="hidden" name="unix_timestamp" value="1750505559"/></span> ...
 *
 * @param string selector a CSS selector to select all nodes that have
 *   a relative date as its content.
 */
WH.autoRefreshTimes = function (selector) {
	var dataKey = 'wh_auto_timestamp';
	$(selector).each( function () {
		var node = $(this);
		var ts = $('input', node).val();
		if (ts) {
			node.data(dataKey, ts);
			node.html( WH.relativeTime(ts) );
		}
	});
	var intervalCallback = function () {
		$(selector).each( function () {
			var node = $(this);
			var ts = node.data(dataKey);
			if (ts) {
				node.html( WH.relativeTime(ts) );
			} else {
				// reformat newly added DOM nodes
				var ts = $('input', node).val();
				node.data(dataKey, ts);
				node.html( WH.relativeTime(ts) );
			}
		});
	};
	setInterval(intervalCallback, 60 * 1000);
};

