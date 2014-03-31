var rcElements = [];
var rcReset = true;
var rcCurrent = 0;
var rcElementCount = 0;
var rcServertime;
var rcwDPointer = 0;
var rcwToggleDiv = true;
var rcInterval = '';
var rcReloadInterval = '';
var rcwGCinterval = '';
var rcUnpatrolled = 0;
var rcPause = false;
var rcExternalPause = false;
var rcUser = -1;

var rcwIsFull = 0;
var rcLoadCounter = 1;
var RCW_LOAD_COUNTER_MAX = 3;
var RCW_MAX_DISPLAY = 3;
var RCW_DEBUG_FLAG = false;
var RCW_DIRECTION = 'down';
var RCW_DEFAULT_URL = "/Special:RCWidget";
var RCW_ENGLISH = typeof wgContentLanguage == 'string' &&  wgContentLanguage == 'en';
var RCW_CDN_SERVER = typeof wgServer == 'string' ? wgServer : '';
var rcwTestStatusOn = false;

function getNextSpot() {
	if (rcwDPointer >= RCW_MAX_DISPLAY) {
		return 0;
	} else {
		return rcwDPointer;
	}
}

function getRCElem(listid, type) {

	if (typeof(rcElements) != "undefined") {
		var elem;

		var newelem = $('<div></div>');
		var newid = getNextSpot();
		var newdivid = 'welement'+newid;
		newelem.attr('id', newdivid);
		newelem.css('display', 'none');
		newelem.css('overflow', '');
		if (rcwToggleDiv) {
			newelem.attr('class', 'rc_widget_line even');
			rcwToggleDiv = false;
		} else {
			newelem.attr('class', 'rc_widget_line odd');
			rcwToggleDiv = true;
		}

		elem = "<div class='rc_widget_line_inner'>";

		elem += rcElements[ rcCurrent ].text + "<br />";
		//elem += "<span style='color: #AAAAAA;font-size: 11px;'>" + rcElements[ rcCurrent ].ts +" ("+rcCurrent+")</span>";
		elem += "<span class='rc_widget_time'>" + rcElements[ rcCurrent ].ts + "</span>";
		elem += "</div>";

		newelem.html($(elem));

		rcwDPointer = newid + 1;

		if (RCW_DIRECTION == 'down') {
			var firstChild = listid.children()[0];
			newelem.insertBefore(firstChild);
		} else {
			listid.append(newelem);
		}

		if (type == 'blind') {
			if (RCW_DIRECTION == 'down') {
				//new Effect.SlideDown(newelem);
				//newelem.show('blind', {direction: 'vertical'});
				//newelem.show('slide', {direction: 'up'});
				newelem.slideDown();
			} else {
				//new Effect.BlindDown(newelem);
				//newelem.show('blind', {direction: 'vertical'});
			}
		} else {
			//new Effect.Appear(newelem);
			newelem.fadeIn();
		}

		if (rcCurrent < rcElementCount - 1) {
			rcCurrent++;
		} else {
			rcCurrent = 0;
		}

		return newelem;
	} else {
		return "undefined";
	}
}

function rcUpdate() {
	if (rcPause || rcExternalPause) {
		return false;
	}

	var listid = $('#rcElement_list');

	if (rcwIsFull == RCW_MAX_DISPLAY) {
		var oldid = getNextSpot();
		var olddivid = $('#welement'+oldid);
	
		if (RCW_DIRECTION == 'down') {
			//new Effect.BlindUp(olddivid);
			//olddivid.effect('blind', {direction: 'up'});
			//olddivid.show('blind', {direction: 'vertical'});
		} else {
			//new Effect.SlideUp(olddivid);
			//olddivid.effect('slide', {direction: 'up'});
		}
		olddivid.attr('id','rcw_deleteme');
	}

	var elem = getRCElem(listid, 'blind');
	if (rcwIsFull < RCW_MAX_DISPLAY) { rcwIsFull++ }

}

var rcwRunning = true;
function rcTransport(obj) {
	var rcwScrollCookie = getCookie('rcScroll');

	obj = $(obj);
	if (rcwRunning) {
		setRCWidgetCookie('rcScroll','stop',1);
		rcStop();
		rcwRunning = false;
		obj.addClass('play');
	} else {
		deleteCookie('rcScroll');
		rcStart();
		obj.removeClass('play');
		rcwRunning = true;
   }
    
}

function rcStop() {
	clearInterval(rcInterval);
	clearInterval(rcReloadInterval);
	clearInterval(rcwGCinterval);

	rcInterval = '';
	rcReloadInterval = '';
	rcwGCinterval = '';
	rcGC();
	var obj = $('#play_pause_button');
	obj.addClass('play');
	rcwRunning = false;
}

function rcStart() {
	rcUpdate();
	rcLoadCounter = 1;
	if (rcReloadInterval == '') { rcReloadInterval = setInterval('rcwReload()', rc_ReloadInterval); }
	if (rcInterval == '') { rcInterval = setInterval('rcUpdate()', 3000); }
	if (rcwGCinterval == '') { rcwGCinterval = setInterval('rcGC()', 30000); }
}

function rcwReadElements(nelem) {
	var Current = 0;
	var Elements = [];
	var Servertime = 0;
	var ElementCount = 0;
	var Unpatrolled = 0;

	for (var i in nelem) {
		if (typeof(i) != "undefined") {
			if (i == 'servertime'){
				Servertime = nelem[i];
			} else if(i == 'unpatrolled'){
				Unpatrolled = nelem[i];
			}else {
				Elements.push(nelem[i]);
				ElementCount++;
			}
		}
	}
	Current = 0;

	rcServertime = Servertime;
	rcElements = Elements;
	rcElementCount = ElementCount;
	rcCurrent = Current;
	rcReset = true;
	rcUnpatrolled = Unpatrolled;
}

function rcwReload() {
	if (rc_URL == '') rc_URL = RCW_DEFAULT_URL;
	rcLoadCounter++;

	if (rcLoadCounter > RCW_LOAD_COUNTER_MAX) {
		rcStop();
		if (rcwTestStatusOn) $('#teststatus').innerHTML = "Reload Counter...Stopped:"+rcLoadCounter;
		return true;
	} else {
		if (rcwTestStatusOn) $('#teststatus').innerHTML = "Reload Counter..."+rcLoadCounter;
	}

	var url = RCW_CDN_SERVER + rc_URL + '?function=rcwOnReloadData';
	rcwLoadUrl(url);
}

function rcwOnReloadData(data) {
	rcwReadElements(data);
	rcwLoadWeather();
}

function rcwLoad() {
	if (rc_URL == '') rc_URL = RCW_DEFAULT_URL;

	var listid = $('#rcElement_list');
	listid.css('height', (RCW_MAX_DISPLAY * 65) + 'px');
	listid.css('overflow', 'hidden');
	if (RCW_DEBUG_FLAG) { $('#rcwDebug').css('display', 'block'); }

	if (listid) {
		listid.mouseover(function(e) {
			rcPause = true;
		});
		listid.mouseout(function(e) {
			rcPause = false;
		});
	}


	var url = RCW_CDN_SERVER + rc_URL + '?function=rcwOnLoadData';
	if(rcUser != -1)
		url += "&userId=" + rcUser;
	rcwLoadUrl(url);
}

function rcwLoadUrl(url) {
	if (url.indexOf('?') >= 0) {
		url += '&' + wgWikihowSiteRev;
	} else {
		url += '?' + wgWikihowSiteRev;
	}
	if (rcExternalPause) return false;
	var activateWidget = true;
	if (/MSIE (\d+\.\d+);/.test(navigator.userAgent)){ //test for MSIE x.x;
		var ieversion = new Number(RegExp.$1) // capture x.x portion and store as a number
		// don't activate rcwidget for IE6
		if (ieversion < 7) {
			activateWidget = false;
		}
	}

	if (activateWidget) {
		// We need to change ajax caching to true so that jQuery won't append
		// the _ timestamp param to files loaded (which busts our cache).
		//
		// from: http://bugs.jquery.com/ticket/4898
		$.ajaxSetup( {cache: true} ); 
		$('#rcwidget_divid').after( $('<script src="' + url + '"></script>') );
		$.ajaxSetup( {cache: false} ); 
	}
}

function rcwOnLoadData(data) {
	rcwReadElements(data);

	var listid = $('#rcElement_list');
	if (rcwTestStatusOn) $('#teststatus').innerHTML = "Nodes..."+listid.childNodes.length;
	var rcwScrollCookie = getCookie('rcScroll');

	if (!rcwScrollCookie) {
		var elem = getRCElem(listid, 'new');
		if (rcwIsFull < RCW_MAX_DISPLAY) { rcwIsFull++ }

		rcStart();
	} else {
		for (i = 0; i < RCW_MAX_DISPLAY; i++) {
			var elem = getRCElem(listid, 'new');
			if (rcwIsFull < RCW_MAX_DISPLAY) { rcwIsFull++ }
		}
		rcStop();
	}

	rcwLoadWeather();
}

function rcwLoadWeather() {
	var rcWeather = jQuery('.weather');
	rcWeather.removeClass('sunny partlysunny cloudy rainy');
	if(rcUnpatrolled < 150) //sunny
		rcWeather.addClass("sunny");
	else if(rcUnpatrolled < 500) //sunny/cloudy
		rcWeather.addClass("partlysunny");
	else if(rcUnpatrolled < 1000) //cloudy
		rcWeather.addClass("cloudy");
	else //rainy
		rcWeather.addClass("rainy");
	rcWeather.html(rcUnpatrolled);
}

function rcGC() {
	if (rcwTestStatusOn) {
		var tmpHTML = $('#teststatus').innerHTML;
		$('#teststatus').innerHTML = "Garbage collecting...";
	}
	$('#rcElement_list #rcw_deleteme').remove();

	/*var listid = $('#rcElement_list');
	var listcontents = $('#rcElement_list div');
	for (i = 0; i < listcontents.length; i++) {
		if ($(listcontents[i]).attr('id') == 'rcw_deleteme') {
			//listid.removeChild( listcontents[i] );
			$(listcontents[i]).remove();
		}
	}*/
	if (rcwTestStatusOn) $('#teststatus').innerHTML = tmpHTML;
}

function setRCWidgetCookie(c_name, value, expiredays) {
	var exdate = new Date();
	exdate.setDate(exdate.getDate() + expiredays);
	document.cookie = c_name + "=" + escape(value) + (expiredays == null ? "" : ";expires=" + exdate.toGMTString());
}

function getCookie(c_name) {
	if (document.cookie.length > 0) {
		var c_start = document.cookie.indexOf(c_name + "=");
		if (c_start != -1) {
			c_start = c_start + c_name.length + 1;
			var c_end = document.cookie.indexOf(";", c_start);
			if (c_end == -1) 
				c_end = document.cookie.length;
			return unescape( document.cookie.substring(c_start, c_end) );
		}
	}
	return "";
}

function deleteCookie(name) {
	if (getCookie(name)) 
		document.cookie = name + "=" + ";expires=Thu, 01-Jan-1970 00:00:01 GMT";
}

