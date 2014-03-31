WH = WH || {};

WH.imageFeedback = function() {
	// Add feedback link to article images
	var link = "<a class='rpt_img' href='#'><span class='rpt_img_ico'></span>Helpful?</a>";
	$('div.mwimg').prepend(link);

	$('.rpt_img').on('click', function(e) {
		var url = "/extensions/wikihow/common/jquery-ui-1.9.2.custom/js/jquery-ui-1.9.2.custom.min.js";
		var rpt_img = this;
		$.getScript(url, function() {
			e.preventDefault();

			var msg = 'This image:';
			var style = "<link type='text/css' rel='stylesheet' href='" + wfGetPad('/extensions/wikihow/common/jquery-ui-themes/jquery-ui.css?rev=' + wgWikihowSiteRev + "'") + "' />";
			var inputs = '<p class="rpt_margin_5px"><input type="radio" name="voteType" value="good" checked> Is helpful<input class="rpt_input_spacing" type="radio" name="voteType" value="bad"> Needs improvement</p>';
			inputs += '<p class="rpt_margin_20px">Please provide as much information as you can.<textarea name="rpt_reason" class="rpt_reason input_med"></textarea>';
			var buttons = '<p class="rpt_controls"><input type="button" class="button primary rpt_button" value="Submit"></input><a href="#" class="rpt_cancel">Cancel</a></s></p>';
			$("#dialog-box").html(style + '<p>' + msg + '</p>' + inputs + '' + buttons);
			$("#dialog-box").dialog( {
				modal: true,
				title: "Send Image Feedback",
				width: 400,
				position: 'center',
				closeText: 'Close'
			});

			$('.rpt_button').on('click', function(e) {
				var reason = $('.rpt_reason').val();
				if (reason.length) {
					var url = $(rpt_img).parent().children('a.image').first().attr('href');
					$.post('/Special:ImageFeedback', {imgUrl: url, aid: wgArticleId, 'reason': reason, 'voteType' : $('input[name=voteType]:checked').val()});
					$('#dialog-box').dialog('close');
				}
				return false;
			});
			$('.rpt_cancel').on('click', function(e) {
				e.preventDefault();
				$('#dialog-box').dialog('close');
				return false;
			});
			return false;
		});
	});

	var timer = 0;
	$('div.mwimg').hover(function() {
		var img = this;
		timer = setTimeout(function(){$(img).find('.rpt_img').fadeIn();}, 500);
	}, function() {
		clearTimeout(timer);
		$(this).find('.rpt_img').hide();
	});
};
