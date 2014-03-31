WH = WH || {};
WH.ThumbsUp = WH.ThumbsUp || {};

WH.ThumbsUp = (function($) {

function assignHandlers() {
	$('.th_close').click(function() {
		var revId = $(this).attr('id');
		var giverIds = $('#th_msg_' + revId).find('.th_giver_ids').html();
		var url = '/Special:ThumbsNotifications?rev=' + revId + '&givers=' + giverIds;

		$.get(url, function(data) {});
		$('#th_msg_' + revId).hide();	
	});

	$('.th_avimg').hover(
		function() {
			getToolTip(this,true);
		},
		function() {
			getToolTip(this,false);
		}
	);

	$('.th_twitter').click(function() {
		tn_share(this, 'twitter');
	});

	$('.th_facebook').click(function() {
		tn_share(this, 'facebook');
	});
}

function tn_share(context, outlet) {
	var url = $(context).parent().parent().find('.th_t_url:first').attr('href');
	url = 'http://www.wikihow.com' + url;

	switch (outlet) {
		case 'facebook':
			url = url + '?fb=t';
			tn_share_facebook(url);
			break;
		case 'twitter':
			tn_share_twitter(url);
			break;
	}
}

function tn_share_twitter(url) {
	status = "Awesome! I just received a thumbs up on @wikihow for my edit on";
	window.open('https://twitter.com/intent/tweet?text=' + status  + ' ' + url);
	return false;
}

function tn_share_facebook(url, msg) {
	// share the article
	var d = document,
		f = 'http://www.facebook.com/share',
		l = d.location,
		e = encodeURIComponent,
		p = '.php?src=bm&v=4&i=1178291210&u=' + e(url); 
	try { 
		if (!/^(.*\.)?facebook\.[^.]*$/.test(l.host))
			throw(0);
		//share_internal_bookmarklet(p)
	} catch(z) {
		var a = function() {
			if (!window.open(f+'r'+p,'sharer','toolbar=0,status=0,resizable=0,width=626,height=436'))
				l.href=f+p
		};
		if (/Firefox/.test(navigator.userAgent)) {
			setTimeout(a,0);
		} else {
			a()
		}
	}
	void(0);
}

return {
	'assignHandlers': assignHandlers
};

})(jQuery);

