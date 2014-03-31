var WH = WH || {};

WH.ThumbRatings = (function($) {

	var controller = '/Special:ThumbRatings';
	var clicked = [];

	$(document).on('click', 'a[class^=trvote_]', function(e) {
		e.preventDefault();
		var className = $(this).attr('class').split(' ')[0];
		var vals = className.split('_');
		if ($.inArray(vals[2], clicked) == -1) {
			clicked.push(vals[2]);

			var data = {'aid' : wgArticleId, 'hash' : vals[2], 'vote' : vals[1], 'type' : vals[3]};
			$.get(controller, data);

			if (_gaq) {
				var action = vals[3] + '-' + vals[1];
				_gaq.push(['_trackEvent', 'm-thumbrating', action, wgTitle]);
			}

			changeThumbRatingState(className, vals);	
		}
	});

	function changeThumbRatingState(className, vals) {
		$('a.' + className).each(function() {
			var tr_vote = $(this).find('span.tr_vote');
			var tr_box = $(this);
			var color = vals[1] == 'up' ? '#93b874' : '#b5ab98';
			var msg = vals[1] == 'up' ? getUpMsg() : getDownMsg();
			$(tr_vote).removeClass('nodisplay').html(parseInt($(tr_vote).html()) + 1);
			$(tr_box).css('background', color).css('border-color', color);
			$('span#tr_prompt_' + vals[2]).css('border-color', color).css('color', color).html(msg);
		});
	}

	function getUpMsg() {
		var msgs = ['Yay!', 'Hooray!', 'Awesome', 'Great!', 'Sweet!', 'Nice!', 
			'Thanks!', 'Woo-hoo!', 'Cheers', 'Score!', 'Beautiful', 'Wonderful',
			'Wondrous', 'Cool!', 'Fantastic', 'Splendid!', 'Bravo!', 'Zounds!', 
			'Booyah!'];

		return msgs[Math.floor(Math.random() * msgs.length)];
	}

	function getDownMsg() {
		var msgs = ['Apologies', 'Too bad', 'Bummer', 'Oh no!', 'Darn', 'Oops!', 
			'Sorry', 'Mea culpa', 'D’oh!', 'Our bad', 'Shucks', 'Oopsie', 'Uh oh!'];
		return msgs[Math.floor(Math.random() * msgs.length)];
	}

	function hideAll() {
		$('.trvote_box').hide();
	}

	return {
		hideAll: hideAll
	};

})(jQuery);
