
// generate toc from prefs form, fold sections
// XXX: needs testing on IE/Mac and safari
// more comments to follow
function tabbedprefs() {
	var prefform = document.getElementById('preferences');
	if (!prefform || !document.createElement) {
		return;
	}
	if (prefform.nodeName.toLowerCase() == 'a') {
		return; // Occasional IE problem
	}
	prefform.className = prefform.className + 'jsprefs';
	var sections = [];
	var children = prefform.childNodes;
	var seci = 0;
	for (var i = 0; i < children.length; i++) {
		if (children[i].nodeName.toLowerCase() == 'fieldset') {
			children[i].id = 'prefsection-' + seci;
			children[i].className = 'prefsection';
			//if (is_opera || is_khtml) {
			//	children[i].className = 'prefsection operaprefsection';
			//}
			var legends = children[i].getElementsByTagName('legend');
			sections[seci] = {};
			legends[0].className = 'mainLegend';
			if (legends[0] && legends[0].firstChild.nodeValue) {
				sections[seci].text = legends[0].firstChild.nodeValue;
			} else {
				sections[seci].text = '# ' + seci;
			}
			sections[seci].secid = children[i].id;
			seci++;
			if (sections.length != 1) {
				children[i].style.display = 'none';
			} else {
				var selectedid = children[i].id;
			}
		}
	}
	/*var toc = document.createElement('div');
	toc.id = 'preferences_tabs';
	toc.selectedid = selectedid;
	var tocUl = document.createElement('ul');
	tocUl.id = 'tabs';
	toc.appendChild(tocUl);
	for (i = 0; i < sections.length; i++) {
		var li = document.createElement('li');
		var a = document.createElement('a');
		if (i === 0) {
			a.className = 'on';
		}
		a.href = '#' + sections[i].secid;
		a.onmousedown = a.onclick = uncoversection;
		a.appendChild(document.createTextNode(sections[i].text));
		a.title = sections[i].text;
		a.id = "tab_"+sections[i].secid;
		li.appendChild(a);
		tocUl.appendChild(li);
	}

	var arttabsline = document.getElementById('article_tabs_line');
	prefform.parentNode.insertBefore(toc, prefform.parentNode.childNodes[0]);
	arttabsline.parentNode.insertBefore(toc, arttabsline);
	document.getElementById('prefsubmit').id = 'prefcontrol';*/
}

function uncoversection(elem) {
	var newsec = $("#" + $(elem).attr("id"));
	if(!$(newsec).hasClass("on")) {
		$("#article_tabs a").removeClass("on");
		$(".prefsection").hide();

		$(newsec).addClass("on");
		$("#" + $(newsec).attr("id").replace("tab_","")).show();
	}

	/*//var oldsecid = this.parentNode.parentNode.selectedid;
	var newsec = document.getElementById(this.id);
	if (newsec.className != 'on') {
		var a = document.getElementById('preferences_tabs');
		var as = a.getElementsByTagName('a');
		for (var i = 0; i< as.length; i++) {
			as[i].className = '';
			var section = document.getElementById( as[i].id.replace("tab_","") );
			section.style.display = 'none';
		}

		//document.getElementById(oldsecid).style.display = 'none';
		newsec.className = 'on';
		//newsec.style.display = 'block';
		var section = document.getElementById( newsec.id.replace("tab_","") );
		section.style.display = 'block';
		//ul.selectedid = this.secid;
		//oldsecid.className = '';

	}*/
	return false;
}


// in [-]HH:MM format...
// won't yet work with non-even tzs
function fetchTimezone() {
	// FIXME: work around Safari bug
	var localclock = new Date();
	// returns negative offset from GMT in minutes
	var tzRaw = localclock.getTimezoneOffset();
	var tzHour = Math.floor( Math.abs(tzRaw) / 60);
	var tzMin = Math.abs(tzRaw) % 60;
	var tzString = ((tzRaw >= 0) ? "-" : "") + ((tzHour < 10) ? "0" : "") + tzHour +
		":" + ((tzMin < 10) ? "0" : "") + tzMin;
	return tzString;
}

function guessTimezone(box) {
	document.getElementsByName("wpHourDiff")[0].value = fetchTimezone();
}

hookEvent( "load", tabbedprefs);
