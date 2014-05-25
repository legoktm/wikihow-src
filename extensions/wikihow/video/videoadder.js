var updateInterval = 10;

// This function was added by Reuben for testing/debugging live
function whdbg(str, obj) {
	if (typeof console != 'undefined' && typeof console.log == 'function') {
		console.log('wh:', str, obj);
	}
}

function loadNext() {
	
	var url = "/Special:Videoadder/getnext";
	var cat = $("#va_cat").val();
	var page =  $("#va_page_id").val();
	var vid = $("#va_vid_id").val();
	var src = $("#va_src").val();
	var skip = $("#va_skip").val();
	var title = $("#va_page_title").val();
	var url = $("#va_page_url").val();
	$("#va_guts").html("<center><img src='/extensions/wikihow/rotate.gif'/></center>");
	$("#va_article").html("");
	
	$.get("/Special:Videoadder/getnext",
		{
			va_cat: cat,
			va_page_id: page,
			va_vid_id: vid,
			va_src: src,
			va_skip: skip
		},
		function (result) {
			loadResult(result);
		},
		'json'
	);
	/*$("#va_guts").load("/Special:Videoadder/getnext",
		{
			va_cat: cat,
			va_page_id: page,
			va_vid_id: vid, 
			va_src: src, 
			va_skip: skip
		},
		va_setLinks
	);*/
	if (skip == 0 && $.cookie("wiki_sharedVANoDialog") != "yes") {
		article = "<a href='" + url + "' target='new'>How to " + title + "</a>";
		congrats_msg = wfMsg('va_congrats');
		congrats_msg = congrats_msg.replace(/\$1/gi, article);
		$("#dialog-box").html( congrats_msg + " <br/><br/><input type='checkbox' id='dontshow' style='margin-right:5px;'> " + wfMsg('va_check') + " <a onclick='va_closeD();' class='button white_button_100 ' style='float:right;' onmouseover='button_swap(this);' onmouseout='button_unswap(this);'>OK</a>");
		$("#dialog-box").dialog( {
			modal: true,
			title: 'Congratulations',
			show: 'slide',
			closeOnEscape: true,
			jposition: 'center',
			height: 200,
			width: 400
		});
	}
}

function loadResult(results) {
	$("#va_guts").html(results['guts']);
	$("#va_article").html(results['article']);
	$(".tool_options").html(results['options']);
	va_setLinks();
}

function va_setLinks(){
	jQuery('#va_yes').click(function(e){e.preventDefault()});

	//wait 30 seconds before showing the buttons
	var delayms = 30000;
	// For debugging, Krystle and Reuben have shorter delays
	if (typeof wgUserName != 'undefined' 
		&& (wgUserName == 'Reuben' || wgUserName == 'Reuben24' || wgUserName == 'Krystle'))
	{
		delayms = 1000;
	}

	jQuery('#va_notice').delay(delayms).slideUp(function() {
		jQuery('#va_yes')
			.removeClass('disabled')
			.click(function(){
				va_submit(true);
				window.oTrackUserAction();
				return false;
			});
	});
	
	jQuery('#va_no').click(function(){
		va_submit(false);
		window.oTrackUserAction();
		return false;
	});
	jQuery('#va_introlink').click(function(){
		if(jQuery('#va_articleintro').is(':visible')){
			jQuery('#va_articleintro').hide();
			jQuery('#va_more').addClass('off');
			jQuery('#va_more').removeClass('on');
		}
		else{
			jQuery('#va_articleintro').show();
			jQuery('#va_more').addClass('on');
			jQuery('#va_more').removeClass('off');
		}
		return false;
	});

	//move the article title to the top
	articleLink = $("#va_title a");
	articleLink.remove();
	$(".firstHeading").html(articleLink);
}

function va_inc(id) {
	$("#" + id).fadeOut(400, function() {
		count = parseInt($("#" + id).html());
		$("#" + id).html(count + 1);
		$("#" + id).fadeIn();
	}
	);
}

function va_submit(accept) {
	if (accept)
		$("#va_skip").val(0);
	else
		$("#va_skip").val(1);
	va_inc("iia_stats_today_videos_reviewed");
	va_inc("iia_stats_week_videos_reviewed");
	va_inc("iia_stats_all_videos_reviewed");
	loadNext();
	return false;
}

function va_skip() {
	$("#va_skip").val(2);
	loadNext();
	return false;
}

jQuery(document).ready(function(){
	initToolTitle();
	addOptions();
	loadNext();

	setInterval('updateReviewersTable()', updateInterval*60*1000);
	window.setTimeout(updateWidgetTimer, 60*1000);
});

function updateReviewersTable() {
	var url = '/Special:Videoadder?fetchReviewersTable=true';

	jQuery.get(url, function (data) {
		jQuery('#top_va').html(data);
	});
}

function chooseCat() {
	window.location.href = '/Special:Videoadder?cat='+ encodeURIComponent($("#va_category").val());
}

function va_closeD () {
	if ($("#dontshow").is(':checked')) {
		$.cookie("wiki_sharedVANoDialog", "yes", {expires: 31});
	}
	//loadNext();
	$("#dialog-box").dialog("close");
}

function updateWidgetTimer() {
	updateTimer('stup');
	window.setTimeout(updateWidgetTimer, 60*1000);
}

