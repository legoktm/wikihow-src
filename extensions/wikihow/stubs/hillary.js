var WH = WH || {};

WH.Stubs = (function () {

	var BASE = '/Special:HillaryRest?pageid=' + wgArticleId;
	var BAR_HEIGHT = 120;
	var MSG_BAR_HEIGHT = 55;
	var PROG_BAR_HEIGHT = 100;
	var prevArticlePaddingTop = 0;

	var statics = [];

	// stub the gauge to start
	var gauge = {draw: function() {}};
	var gaugeTick = 4;

	function absSetTick(newTick) {
		gaugeTick = newTick;
	}

	function setGaugeTick() {
		var tick = 4 - gaugeTick;
		tick = Math.max(tick, gauge.config.minValue);
		tick = Math.min(tick, gauge.config.maxValue);
		gauge.setValue(tick);
	}

	function initGauge() {
		gauge = new Gauge({
			renderTo  : 'hill-can',
			maxValue  : 7,
			minValue  : 1,
			colors    : {
				needle: { start : 'rgba(34, 37, 37, 1)', end : 'rgba(110, 112, 112, .9)' }
			},
			animation : {
				delay : 10,
				duration: 1000,
				fn : 'bounce'
			}
		});

		setGaugeTick();
		gauge.draw();
	}

	function changeBarState(state) {
		if (state == 'prog') {
			$('.hill-meter').hide();
			$('.hill-prog').show();
			$('.hillary-static').css({height: PROG_BAR_HEIGHT + 'px'});
		} else if (state == 'vote') {
			$('.hill-prog').hide();
			$('.hill-vote').show();
		} else if (state == 'msg') {
			$('.hill-prog').hide();
			$('.hill-meter').hide();
			$('.hillary-static, .hill-space').css({height: MSG_BAR_HEIGHT + 'px'});
		} else if (state == 'hide') {
			$('.hillary-static, .hill-space').animate(
				{ height: '0' },
				{ complete: function () {
					$(this).hide();
					showHideExternalFeatures('show');
				} });
		}
	}

	// add white space at the top of the article so that there
	// is room for the hillary bar
	function padDocumentTop() {
		var html = '<div class="hill-space" style="height: 0; clear: both"></div>';
		$('body').prepend( $(html) );
	}

	// make all true links on the page open in a new browser
	// window so that users stay in the tool
	function openLinksInNew() {
		$('a').each(function () {
			var href = $(this).attr('href');
			if (href && href.indexOf('#') !== 0 && href.indexOf('http') !== 0) {
				$(this).attr('target', '_blank');
			}
		});
	}

	function startup() {
		showHideExternalFeatures('hide');

		padDocumentTop();

		// slide bar down
		$('.hillary-static')
			.show()
			.css({top: '-' + BAR_HEIGHT + 'px'})
			.animate({top: 0});

		$('.hillary-static').css({height: BAR_HEIGHT + 'px'});
		$('.hill-space').animate({height: BAR_HEIGHT + 'px'});

		$('.hmp-yeses, .hmp-nos').fadeOut();

		// Insert non-frequently used images here so that they aren't loaded for
		// all article pages
		$('.hill-prog')
			.append('<img src="' + wgCDNbase + '/extensions/wikihow/stubs/images/hillary_spin.gif" alt="" />')
			.hide();
		$('#hill-no')
			.after( '<img src="' + wgCDNbase + '/skins/WikiHow/images/wikihow_watermark.png" class="hillary-wikihow" alt="" />' );

		var fetchCallback = function (data) {
			statics = [];
			var msg = '';
			// hide tool for any non-hillary page
			if (typeof data == 'undefined'
				|| !data
				|| typeof data['pageid'] == 'undefined')
			{
				msg = 'Network error';
			} else if (typeof data['msg'] != 'undefined') {
				msg = data['msg'];
			} else if (typeof data['next_id'] == 'undefined') {
				msg = 'No more to rate!';
			} else if (!data['pageid']) {
				msg = 'Can&apos;t be rated &mdash; <a href="/' + data['next_url'] + '#review">next one</a>';
			} else {

				$('.hmp-yeses').html(data['pos']);
				$('.hmp-nos').html(data['neg']);
				$('.hmp-yeses, .hmp-nos').fadeIn();

				openLinksInNew();

				tick = data['pos'] - data['neg'];
				gaugeTick = tick;
				initGauge();
			}

			if (typeof data != 'undefined' && typeof data['next_id'] != 'undefined') {
				statics = data;
			}

			if (msg) {
				changeBarState('msg');
				$('.hill-msg').html(msg);
				$('.hill-text').html('wikiHow');
			}
		};

		$.getJSON(BASE + '&action=fetch', fetchCallback)
			.error(fetchCallback);

	}

	function clearMeterButtonState() {
		$('.hill-meter-piece')
			.removeClass('hmp-left hmp-right hmp-left-voted hmp-right-voted ' +
				'hmp-left-check hmp-right-check hmp-left-x hmp-right-x');
	}

	function attachHandlers() {
		$('.hill-btn-done')
			.click(function () {
				changeBarState('hide');
				return false;
			});

		$('.hill-vote')
			.click(function () {
				$('.hill-vote').off('click');
				$('.hill-vote').click( function () { return false; } );

				var id = $(this).attr('id');
				var vote = 0; // skip
				if (id.match(/-yes$/)) {
					vote = 1;
					$('.hmp-yeses').html(1 + 1*statics['pos']);

					clearMeterButtonState();
					$('#hill-yes').addClass('hmp-left-check');
					$('#hill-no').addClass('hmp-right');
				} else if (id.match('-no$')) {
					vote = -1;
					$('.hmp-nos').html(1 + 1*statics['neg']);

					clearMeterButtonState();
					$('#hill-yes').addClass('hmp-left');
					$('#hill-no').addClass('hmp-right-check');
				}

				var timeElapsed = false;
				var nextURL = null;

				var voteCallback = function (data) {
					var msg = '';
					if (typeof data == 'undefined' || !data) {
						msg = 'Connection error while voting';
					} else if (typeof data['msg'] != 'undefined') {
						msg = data['msg'];
					} else {
						if (!statics
							|| typeof statics['next_url'] == 'undefined'
							|| !statics['next_url'])
						{
							msg = 'No more to rate right now! Yay!';
						} else {
							nextURL = '/' + statics['next_url'] + '#review';
							if (timeElapsed) {
								location.href = nextURL;
							}
						}
					}
					if (msg) {
						changeBarState('msg');
						$('.hill-text').html(msg);
					}
				};

				var saveVoteFunc = function () {
					$.getJSON(BASE + '&action=vote&vote=' + vote, voteCallback)
						.error(voteCallback);
				};

				if (vote != 0) {
					gaugeTick += vote;
					setGaugeTick();
					saveVoteFunc();

					// allow animation some time to occur (using
					// timeElapsed and nextURL as semaphores)
					setTimeout(function () {
						timeElapsed = true;
						if (nextURL) {
							location.href = nextURL;
						} else {
							// wait 2s total before showing the progress spinner
							setTimeout( function () {
								changeBarState('prog');
							}, 1200);
						}
					}, 800);
				} else {
					changeBarState('prog');
					$('.hill-msg').hide();
					timeElapsed = true;
					saveVoteFunc();
				}
				return false;
			});

	}

	// show/hide existing page features like search bar
	function showHideExternalFeatures(action) {
		if (action == 'hide') {
			if (typeof WH.ThumbRatings != 'undefined') {
				WH.ThumbRatings.hideAll();
			}
			var paddingTop = $('#article').css('padding-top');
			if (paddingTop) {
				prevArticlePaddingTop = paddingTop;
				$('#article').animate({'padding-top': '20px'});
			}
			$('.header_static, .search_static, #header').fadeOut();
			$('.wh_ad, .wh_ad_inner, .search, .addTipElement').slideUp();
		} else {
			if (prevArticlePaddingTop) {
				$('#article').animate({'padding-top': prevArticlePaddingTop});
			}
			$('.header_static, .search_static, #header').fadeIn();
			$('.wh_ad, .wh_ad_inner, .search, .addTipElement').show();
		}
	}

	// interface
	return {
		init: function() {
			$(document).ready( function() {
				if (location.href.indexOf('#review') != -1) {
					startup();
					attachHandlers();
				}
			});
		},

		setGaugeTick: setGaugeTick,
		absSetTick: absSetTick,
		initGauge: initGauge
	};
})();

WH.Stubs.init();

