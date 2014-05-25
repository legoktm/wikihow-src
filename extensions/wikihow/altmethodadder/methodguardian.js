var toolURL = "/Special:MethodGuardian";
var methodId;
var articleId;
var LEADERBOARD_REFRESH = 10 * 60;

$( document ).on( "click", "#method_delete", function(e) {
	e.preventDefault();
	if (!jQuery(this).hasClass('clickfail')) {
		clearTool();
		$.post(toolURL, {
			deleteMethod: true,
			methodId: methodId,
			articleId: articleId
			},
			function (result) {
				//updateStats();
				loadResult(result);
				incrementStats();
			},
			'json'
		);
		window.oTrackUserAction();
	}
});

$( document ).on( "click", "#method_keep", function(e) {
	e.preventDefault();

	if (!jQuery(this).hasClass('clickfail')) {
		clearTool();
		$.post(toolURL, {
			keepMethod: true,
			articleId: articleId,
			methodId: methodId
			},
			function (result) {
				//updateStats();
				loadResult(result);
				incrementStats();
			},
			'json'
		);
		window.oTrackUserAction();
	}
});

$( document ).on( "click", "#method_skip", function(e) {
	e.preventDefault();
	if (!jQuery(this).hasClass('clickfail')) {
		clearTool();
		$.post(toolURL, {
			skipMethod: true,
			methodId: methodId,
			articleId: articleId
			},
			function (result) {
				//updateStats();
				loadResult(result);
			},
			'json'
		);
	}
});

$(document).ready(function(){
	initToolTitle();
	$("#article").prepend("<div id='method_count' class='tool_count'><h3></h3><span>methods remaining</span></div>");
	getNextMethod();
	window.setTimeout(updateStandingsTable, 100);

	var mod = Mousetrap.defaultModifierKeys;
	Mousetrap.bind(mod + 'd', function() {$('#method_delete').click();});
	Mousetrap.bind(mod + 's', function() {$('#method_skip').click();});
	Mousetrap.bind(mod + 'e', function() {$('#method_keep').click();});

	$("#method_keys").click(function(e){
		e.preventDefault();
		$("#method_info").dialog({
			width: 500,
			minHeight: 300,
			modal: true,
			title: 'Method Guardian Keys',
			closeText: 'Close',
			position: 'center',
		});
	});
});
	
// asks the backend for a new article
//to edit and loads it in the page
function getNextMethod() {
	$.get(toolURL,
		{getNext: true,
		ts: new Date().getTime() 
		},
		function (result) {
			loadResult(result);
		},
		'json'
	);
}

function loadResult(result) {
	$("#method_waiting").hide();
	if (result['error']) {
		$("#method").hide();
		$("#method_error").show();
		$("#method_count").hide();
	}
	else {
		$("#method_article").html(result['article']);
		//$("#method_method").html(result['method']).focus();
		newHtml = $('<div></div>').html(result['steps']);
		$("#method_steps").html($(newHtml).html()); //should we munge the steps?
		$("h1.firstHeading").html(result['articleTitle']);
		methodId = result['methodId'];
		articleId = result['articleId'];
		$("#method_header a").removeClass("clickfail");
		setCount(result['methodCount']);
	}
}

function setCount(count) {
	$("#method_count h3").fadeOut(400, function() {
		$("#method_count h3").html(count).fadeIn();
	});
}

function clearTool() {
	$("#method_waiting").show();
	$("#method_article").html("");
	$("h1.firstHeading").text("Method Guardian");
	$("#method_header a").addClass("clickfail");
	
}

updateStandingsTable = function() {
	var url = '/Special:Standings/MethodGuardianStandingsGroup';
	$.get(url, function (data) {
		$('#iia_standings_table').html(data['html']);
	}, 'json');
	$("#stup").html(LEADERBOARD_REFRESH / 60);
	window.setTimeout(updateStandingsTable, 1000 * LEADERBOARD_REFRESH);
}

function incrementStats() {
	var statboxes = '#iia_stats_today_methodguardiantool_indiv1,#iia_stats_week_methodguardiantool_indiv1,#iia_stats_all_methodguardiantool_indiv1,#iia_stats_group';
	$(statboxes).each(function(index, elem) {
			$(this).fadeOut(function () {
				var cur = parseInt($(this).html());
				$(this).html(cur + 1);
				$(this).fadeIn();
			});
	});
}
