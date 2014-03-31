
var ad_units = Array('adunit1', 'adunit2', 'adunit3', 'adunit4');
var sh_links = Array("showads");
function sethideadscookie(val) {
    var date = new Date();
    if (val == 1)
		date.setTime(date.getTime()+(1*24*60*60*1000));
	else
		date.setTime(date.getTime()-(30*24*60*60*1000));
    var expires = "; expires="+date.toGMTString();
    document.cookie = "wiki_hideads="+val+expires+"; path=/";
}

function showorhideads(hide) {
	var style = 'display: inline;';
	if (hide) {
		style = 'display: none;';
	}
	for (var i = 0; i < ad_units.length; i++) {
		var e = document.getElementById(ad_units[i]);
		if (e) {
			setStyle(e, style);
		}
	}
	for (var i = 0; i < sh_links.length; i++) {
		var e = document.getElementById(sh_links[i]);
		if (!e) continue;
		if (hide) {
			style = 'display: inline;';
		} else {
			style = 'display: none;';
		}
		setStyle(e, style);
	}
}

function hideads() {
	sethideadscookie(1);
	showorhideads(true);
	clickshare(20);
}

function showads() {
	sethideadscookie(0);
	showorhideads(false);
	window.location.reload();
 }

var ca = document.cookie.split(';');
var gHideAds = false;
for(var i=0;i < ca.length;i++) {
    var c = ca[i];
    var pair = c.split('=');
    var key = pair[0];
    var value = pair[1];
    key=key.replace(/ /, '');
    if (key == 'wiki_hideads') {
		if (value == '1') {
			// gHideAds = true will take care of showing 0 units
			document.observe("dom:loaded", function() {
				showorhideads(true);
            });
			gHideAds = true;
		}
    }
}
var google_analytics_domain_name = ".wikihow.com"

