$(document).ready(function () {
	var pendingRequest = false;
	$(window).scroll(function () {
		if (!pendingRequest && $(window).height() + $(window).scrollTop() >= $(document).height() - 400) {
			pendingRequest = true;
			$('#bodycontents').append('<div id="cat-content-' + gScrollContext + '"></div><div id="spinner-' + gScrollContext + '" class="cat-spinner"></div>');
			$.get('/' + wgPageName,
				{restaction: 'pull-chunk', start: gScrollContext},
				function (data) {
					pendingRequest = false;
					if (data && data['html']) {
						$('#spinner-' + gScrollContext).remove();
						$('#cat-content-' + gScrollContext).html(data['html']);
					}
				},
				'json');
		}
	});
});
