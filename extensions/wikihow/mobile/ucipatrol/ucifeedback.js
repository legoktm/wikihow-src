WH = WH || {};

WH.uciFeedback = function() {
	// Add feedback link to article images
	var link = "<a class='uci_img' href='#'><span class='uci_img_ico'></span>Flag</a>";
	$('div.uci_thumbnail').prepend(link);

	$('.uci_thumbnail').on('click', function(e) {
		e.preventDefault();
		//TODO show full size image?
	});

	$('.uci_img').on('click', function(e) {
		e.preventDefault();
		var url = "/extensions/wikihow/common/jquery-ui-1.9.2.custom/js/jquery-ui-1.9.2.custom.min.js";
		var uci_img = this;
		$.getScript(url, function() {
			var style = "<link type='text/css' rel='stylesheet' href='" + wfGetPad('/extensions/wikihow/common/jquery-ui-themes/jquery-ui.css?rev=' + wgWikihowSiteRev + "'") + "' />";
			var msg = '<p class="uci_margin_5px">Are you sure you want to flag this image as inappropriate?</p>';
			var buttons = '<p class="uci_controls"><input type="button" class="button primary uci_button" value="Submit"></input><a href="#" class="uci_cancel">Cancel</a></s></p>';
			$("#dialog-box").html(style + msg + '' + buttons);
			$("#dialog-box").dialog( {
				modal: true,
				title: "Flag inappropriate image",
				width: 340,
				position: 'center',
				closeText: 'Close',
			});

			$('.uci_button').on('click', function(e) {
				var imagePageId = $(uci_img).parent().attr('pageid');
				$.post('/Special:PicturePatrol', {
					flag: true,
					pageId: imagePageId,
					hostPage: wgTitle
					},
					function (result) {
						$("#uci_images").html(result);
						// this will set up the flagging again since we regenerated the html
						WH.uciFeedback();
					},
					'html'
				);

				$('#dialog-box').dialog('close');
				return false;
			});
			$('.uci_cancel').on('click', function(e) {
				e.preventDefault();
				$('#dialog-box').dialog('close');
				return false;
			});
			return false;
		});
	});

	var timer = 0;
	$('div.uci_thumbnail').hover(function() {
		var img = this;
		timer = setTimeout(function(){$(img).find('.uci_img').fadeIn();}, 500);
	}, function() {
		clearTimeout(timer);
		$(this).find('.uci_img').hide();
	});
};
