var WH = WH || {};

// copied from wikibits.js since this is loaded first now
WH.browserDetect = function() {
	var ua = navigator.userAgent.toLowerCase();

	// intentionally set global vars
	is_ff = ua.indexOf('firefox') != -1;
	is_safari = ua.indexOf('applewebkit') != -1;
	is_chrome = ua.indexOf('chrome') != -1;
	is_ie6 = false;
	is_ie7 = false;
	is_ie8 = false;
	is_ie9 = false;
	isiPad = ua.indexOf('ipad');
	isiPhone = ua.indexOf('iphone');

	if (/msie (\d+\.\d+);/.test(ua)) { // test for MSIE x.x;
		var iev=new Number(RegExp.$1); // capture x.x portion and store as a number
		if(iev>=9) is_ie9 = true;
		else if (iev>=8) is_ie8 = true;
		else if (iev>=7) is_ie7 = true;
		else if (iev>=6) is_ie6 = true;
	}
};

// Returns true if referrer is a search engine
WH.isFromSearch = function() {
    var ref = document.referrer;

	// Google check
	if (ref.indexOf('https://www.google.com/') == 0 // SSL-based searches in chrome have a bare https://www.google.com/ referrer
		|| (ref.indexOf('google.') != -1 && ref.indexOf('mail.google.com') == -1 && ref.indexOf('url?q=') == -1 && ref.indexOf('q=') != -1)) {
		//console.log('google search');
		return true;
	}

    // Bing check
    if (ref.indexOf('bing.com') != -1 && ref.indexOf('q=') != -1) {
        //console.log('bing search');
        return true;
    }

    // Yahoo check
    if (ref.indexOf('yahoo.com') != -1 && ref.indexOf('p=') != -1) {
        //console.log('yahoo search');
        return true;
    }

    // Ask.com check
    if (ref.indexOf('ask.com') != -1 && ref.indexOf('q=') != -1) {
        //console.log('ask search');
        return true;
    }

    // Aol search check
    if (ref.indexOf('aol.com') != -1 && ref.indexOf('q=') != -1) {
        //console.log('aol search');
        return true;
    }

    // Baidu check
    if (ref.indexOf('baidu.com') != -1 && ref.indexOf('wd=') != -1) {
        //console.log('baidu search');
        return true;
    }

    // Yandex check
    if (ref.indexOf('yandex.com') != -1 && ref.indexOf('text=') != -1) {
        //console.log('yandex search');
        return true;
    }

    // No search engine referral detected. Return false
    return false;
};

WH.wikihowAds = (function () {
	
	var adsSet = false,
		adArray = {},
		adIndex = 0,
		adUnitArray = {},
		adCount = {'intro':1, '0':1, '1':3, '2':3, '2a':3, '5':1, '7':2, 'docviewer3':2},
		isHorizontal = {'7':true, 'docviewer3':true};

	function checkHideAds() {
		var ca = document.cookie.split(';');
		var hideAds = false;
		for(var i=0;i < ca.length;i++) {
			var c = ca[i];
			var pair = c.split('=');
			var key = pair[0];
			var value = pair[1];
			key = key.replace(/ /, '');
			if (key == 'wiki_hideads') {
				if (value == '1') {
					hideAds = true;
				}
			}
		}
		return hideAds;
	}
	
	return {
		checkHideAds: checkHideAds
	};
	
})();

WH.browserDetect();
var fromsearch = WH.isFromSearch();

var gHideAds = WH.wikihowAds.checkHideAds(),
	gchans = '',
	radChan1 = '',
	radPos1 = '',
	adPadding = '',
	adColor = '',
	adUrl = '',
	adTitle = '',
	adText = '',
	showImageAd = false,
	adNum = 0;

