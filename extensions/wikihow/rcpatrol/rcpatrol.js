var nextrev = null;
var marklink = null;
var skiplink = null;
var loaded = false;
var backsize = 20;
var backurls = new Array(backsize);
var backindex = 0;
var rev = false;
var ns = -1;
var rc_user_filter = "";
var ignore_rcid = 0;

// refresh the leaderboard every n seconds
var RC_WIDGET_LEADERBOARD_REFRESH = 10 * 60;
var RC_WIDGET_USERSTATS_REFESH = 5 * 60;

var search = window.location.search.replace(/^\?/, "");
var parts = search.split("&");
for (i = 0; i < parts.length; i++) {
	var term = parts[i];
	var keyterm = term.split("=");
	if (keyterm.length == 2 && keyterm[0] == 'rc_user_filter') {
		rc_user_filter = keyterm[1];
	}
}

(function($) {

// Init shortcut key bindings
$(document).ready(function() {
	initToolTitle();

	var title = $('#articletitle').html();
	if (!title) return;
	$(".firstHeading").html(title);

	var mod = Mousetrap.defaultModifierKeys;
	Mousetrap.bind(mod + 'm', function() {$('#markpatrolurl').click();});
	Mousetrap.bind(mod + 's', function() {$('#skippatrolurl').click();});
	Mousetrap.bind(mod + 'e', function() {$('#qe_button').click();});
	Mousetrap.bind(mod + 'r', function() {$('#rb_button').click();});
	Mousetrap.bind(mod + 'b', function() {$('#gb_button').click();});
	Mousetrap.bind(mod + 't', function() {$('.thumbbutton').click();});
	Mousetrap.bind(mod + 'q', function() {$('#qn_button').click();});
});
})(jQuery);

function setRCLinks() {
	var e = document.getElementById('bodycontents2');
	var links = e.getElementsByTagName("a");
	for (i = 0; i < links.length; i++) {
		if (links[i].href != wgServer + "/" + wgPageName) {
			links[i].setAttribute('target','new');
		}
		/*
		if (links[i].getAttribute('accesskey')) {
			if (links[i].getAttribute('accesskey') == 'p'
				&& links[i].id != 'markpatrolurl') {
				links[i].setAttribute('accesskey',null);
			} else if (links[i].getAttribute('accesskey') == 's'
				&& links[i].id != 'skippatrolurl') {
				links[i].setAttribute('accesskey',null);
			}
		}
		*/
	}

	if ($('#numrcusers') && $('#numrcusers').html() != "1") {
		var e = $("#mw-diff-ntitle2 #mw-diff-oinfo");
		var ehtml = e.html();
		if (ehtml && ehtml.indexOf("and others") < 0) {
			$( "#mw-diff-ntitle2 #mw-diff-oinfo #mw-diff-ndaysago" ).before( "<b>and others</b>." );
		}
	}

	$('.button').each( function() {
			if ($(this).html() == "quick edit") {
				$(this).click(function () {
					hookSaveButton();
				});
				return;
			}
		}
	);
}

function incQuickEditCount() {
	// increment the active widget
	$("#iia_stats_group, #iia_stats_today_rc_quick_edits, #iia_stats_week_rc_quick_edits").each(function() {
			$(this).fadeOut();
			var cur = parseIntWH($(this).html());
			$(this).html(addCommas(cur + 1));
			$(this).fadeIn();
		}
	);
}

function hookSaveButton() {
	if ($("#wpSave").html() == null ) {
		setTimeout(hookSaveButton, 200);
		return;
	}
	$("#wpSave").click(function() {
		incQuickEditCount();
	});
}

function setContentInner(html, fade) {
	$("#bodycontents2").html(html);
	if (fade) {
		$("#bodycontents2").fadeIn(300);
	} else {
		$("#bodycontents2").show();
	}

	var title = $('#articletitle').html();
	if (!title) return;
	$(".firstHeading").html(title);
	//$('h1').first().html(title);
	//document.title = title;

	var matches = html.match(/<div id='newrollbackurl'[^<]*<\/div>/);
	if (matches != null) {
		newlink = matches[0];
		gRollbackurl = newlink.replace(/<(?:.|\s)*?>/g, "");
	}
	setRCLinks();
	addBackLink();
	if (rev) {
		$("#reverse").prop('checked', true);
	}
	$("#namespace").val(ns);
	$("#rc_user_filter").val(rc_user_filter);
	if (rc_user_filter) openSubMenu('user');
	if (rev || ns >= 0) openSubMenu('ordering');
	// Fire even to initialize wikivideo
	$(document).trigger('rcdataloaded');
}

function setContent(html) {
	var e = document.getElementById('bodycontents2');
	if (navigator.appVersion.indexOf("MSIE") >= 0) {
		$("#bodycontents2").hide(300, function() {
			setContentInner(html,false);
		});
	} else {
		$("#bodycontents2").fadeOut(300, function() {
			setContentInner(html, true);
		});
	}
	return;
}

function resetRCLinks() {
	var matches = nextrev.match(/<div id='skiptitle'[^<]*<\/div>/);
	if (matches == null || matches.length == 0) {
		return;
	}
	var newlink = matches[0];
	var skiptitle = "&skiptitle=" + newlink.replace(/<(?:.|\s)*?>/g, "");

	/// set the mark link to the current contents
	if (navigator.userAgent.indexOf('MSIE') > 0) {
		marklink = document.getElementById('newlinkpatrol').innerText + skiptitle;
		skiplink = document.getElementById('newlinkskip').innerText + skiptitle;
	} else {
		marklink = document.getElementById('newlinkpatrol').textContent + skiptitle;
		skiplink = document.getElementById('newlinkskip').textContent + skiptitle;
	}
}

function setupTabs() {
	$('#rctab_advanced a').click(function() {
		openSubMenu('advanced');
		return false;
	});
	$('#rctab_ordering a').click(function() {
		openSubMenu('ordering');
		return false;
	});
	$('#rctab_user a').click(function() {
		openSubMenu('user');
		return false;
	});
	$('#rctab_help a').click(function() {
		openSubMenu('help');
		return false;
	});
}

function skip() {
	if (!loaded) {
		setTimeout(skip, 500);
		return;
	}

	sendMarkPatrolled(skiplink);
	resetQuickNoteLinks();
	return false;
}

function resetQuickNoteLinks() {
	$('#qnote_buttons').load("/Special:QuickNoteEdit/quicknotebuttons");
}

function changeReverse() {
	var tmp = $("input[name='reverse']:checked").val();
	ignore_rcid = 2;
	if (tmp == 1) {
		rev = true;
	} else {
		rev = false;
	}
	nextrev = null;
}

function changeUserFilter() {
	rc_user_filter = $("#rc_user_filter").val();
}

function modUrl(url) {
	url = url.replace(/reverse=[0-9]?/, "&");

	// If it's a test, let the special page know
	var RCTestObj = RCTestObj ||  null;
	if (RCTestObj) {
		url += "&rctest=1";
	}
	// If we're debugging, let the special page know
	var mode = extractParamFromUri(document.location.search, 'rct_mode');
	if (mode) {
		url += "&rct_mode=" + mode;
	}

	if (rev) {
		url += "&reverse=1";
	}
	if (ns >= 0) {
		url += "&namespace=" + ns;
	}
	if (ignore_rcid > 0) {
		url += "&ignore_rcid=1";
		ignore_rcid--;
	}
	url += "&rc_user_filter=" + encodeURIComponent(rc_user_filter);
	return url;
}

function loadData(url) {
	url = modUrl(url);
	loaded = false;
	$.get(url,
		function(data) {
			setContent(data['html']);
		},
		'json'
	);
	return false;
}

function setUnpatrolled(count) {
	$("#rcpatrolcount h3").fadeOut(400, function() {
		$("#rcpatrolcount h3").html(count)
			.fadeIn();
	});
}

function setPreloaded(data) {
	nextrev = data['html'];
	resetRCLinks();
	loaded = true;
	setUnpatrolled(data['unpatrolled']);
}

function sendMarkPatrolled(url) {
	url = modUrl(url);
	if (nextrev) {
		loaded = false;
		$.get(url,
			function(data) {
				setPreloaded(data);
			},
			'json'
		);
		addBackLink();
		setContent(nextrev);
	} else {
		loadData(url);
	}
	return false;
}

function markPatrolled() {
	if (!loaded) {
		setTimeout(markPatrolled, 500);
		return;
	}

	var numedits = parseIntWH($('#numedits').html());
	$("#iia_stats_today_rc_edits, #iia_stats_week_rc_edits, #iia_stats_all_rc_edits").each(function(index, elem) {
		$(this).fadeOut();
		var cur = parseIntWH($(this).html());
		$(this).html(addCommas(cur + numedits));
		$(this).fadeIn();
	});
	sendMarkPatrolled(marklink);

	//change quick note links
	resetQuickNoteLinks();
	return false;
}

function preloadNext(url) {
	url = modUrl(url);
	$.get(url,
		function(data) {
			setPreloaded(data);
		},
		'json'
	);
	return false;
}

function addBackLink() {
	// If it's a test, don't add this revision to the back links
	if (WH.RCTest) {
		return;
	}
	var link = $('#permalink').val();
	backurls[backindex % backsize] = link;
	backindex++;
}

function goback() {
	if (backindex > 0) {
		backindex--;
		var index = backindex-1;
		var backlink = backurls[index % backsize];
		loadData(backlink);
	} else {
		alert('No diff to go back to, sorry!');
	}
	return false;
}

function handleQESubmit() {
	incQuickEditCount();
}

function updateLeaderboard() {
	updateWidget("#iia_standings_table", "QuickEditStandingsGroup");
	var min = RC_WIDGET_LEADERBOARD_REFRESH / 60;
	$("#stup").html(min);
	setTimeout(updateLeaderboard, 1000 * RC_WIDGET_LEADERBOARD_REFRESH);
	return false;
}

function updateTimers() {
	updateTimer("stup");
	setTimeout(updateTimers, 1000 * 60);
}

function openSubMenu(menuName){
	var menu = $("#rc_" + menuName);
	if(menu.is(":visible")){
		menu.hide();
		$("#rctab_" + menuName).removeClass("on");
	} else {
		$(".rc_submenu").hide();
		menu.show();
		$("#rc_subtabs div").removeClass("on");
		$("#rctab_" + menuName).addClass("on");
	}
}

function changeUser(user) {
	if (user)
		window.location.href = "/Special:RCPatrol?rc_user_filter=" + encodeURIComponent($("#rc_user_filter").val());
	else
		window.location.href = "/Special:RCPatrol";
}

$(document).ready(function() {
	if (rc_user_filter) {
		$('#rc_user_filter').val(rc_user_filter);
		openSubMenu('user');
	}

	setTimeout(updateLeaderboard, 1000 * RC_WIDGET_LEADERBOARD_REFRESH);
	setTimeout(updateTimers, 1000 * 60);

	if ($('#rcpatrolcount').length == 0) {
		$('#article').prepend('<div id="rcpatrolcount" class="tool_count"><h3></h3></div>');
	}
	gPostRollbackCallback = function () {
		span = $('#rollback-status');
		if (span.length && span.html().indexOf('Reverted edits by') >= 0) {
			// Special hack for BR because he didn't like not being able
			// to quick edit after a rollback
			var exceptionsList = ['BR', 'JuneDays', 'Zack', 'KommaH'];
			if ($.inArray(wgUserName, exceptionsList) == -1) {
				setTimeout( markPatrolled, 250 );
			}
		}
	};
});

