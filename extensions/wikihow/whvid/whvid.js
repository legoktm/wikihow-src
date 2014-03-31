(function($) {

	var timer; // Used to minimize calls to toggleVideos.
	var WAIT_INTERVAL = 100;  // Timer interval

	var vidPlayerDivSelector = 'div[id^=player-whvid]';
	var largeImgSelector = 'div[id^=lrgimgurl]';
	var smallImgSelector = 'div[id^=smlimgurl]';
	var imgSelector = isLargeDisplay() ? largeImgSelector : smallImgSelector;

	var playersInit = {};  // A list of which players have been initialized

	var isOldIE = false;

	var isMobileDomain = window.location.hostname.match(/\bm\./) != null;
	var isMobile = null;

	/*
	* Initialize wikivideos on normal articles ready event
	*/
	$(document).ready(function() {
		initWHVid();
	});


	/*
	* Needed to init wikivideos in places where rc data is used (eg. RC Patrol and QG).  
	* On normal articles wikivideo is initialized at document.ready but that only happens
	* on the first time the tools are loaded. A $(document).trigger('rcdataloaded'); 
	* must be place in the js initialization of an rc item in tools such as this 
	* to ensure this event listener will fire
	*/
	$(document).on('rcdataloaded', function() { 
		initWHVid();
	});


	var initWHVid = function() {
		// IE6 and IE7 perf blows so we treat them as a special case
		isOldIE = $.browser.msie && $.browser.version < 8;
		if (isOldIE) {
			$('.whvid_cont').addClass('whvid_cont_ie');
		}

		$(vidPlayerDivSelector).each(function(i, div) {
			if (isMobileDomain && !isLargeDisplay()) {
				// Add mobile-specific css
				$(div).parent().addClass('whvid_cont_mobile');
			}
			if (!isMobileDevice() && !isOldIE) {
				// Turn off controls for desktop version of player
				$('.fp-ui').css('display', 'none !important');
			}
		});
		// Only initialize if we find whvid_cont classes
		if ($('div.whvid_cont').length) {
			$.ajaxSetup({cache: true}); // Turn off ajax cache busting

			if (isSlowDevice() || isOldIE || !isVideoCapable()) {
				displayFallbackImages();
				return;
			}

			// Global conf
			var conf = {
				key: '$472025115616477, $338652611203983, $336265611125012',
				swf: '/extensions/wikihow/common/flowplayer/flowplayer.swf',
				loop: true
			};

			if (isLargeDisplay()) {
				conf['logo'] = 'http://pad1.whstatic.com/skins/WikiHow/images/wikihow_watermark_new.png';
			}

			// Set specific width for ipad for formatting reason
			if (isLargeDisplay() && isMobileDomain) {
				$('.whvid_cont').css('width', '550px');
			}

			if (isMobileDomain || isMobileDevice()) {
				// Do a splash setup for mobile for faster loading
				conf.splash = true;
				flowplayer.conf = conf;
				flowplayer(function(api, root) {
					// Handle errors by loading the fallback image
					api.bind("error", function () {
						var imgDiv = $(this).parent().children(imgSelector).first();
						$(this).unload();
						$(this).remove();
						displayFallbackImage(imgDiv);
					});
				});
			} else {
				flowplayer.conf = conf;
				flowplayer(function (api, root) {
				   api.bind("ready", function () {
						if(isScrolledIntoView(root)) {
							// Adjust watermark for  16:9 videos
							var vid = $(root).find('video').get(0);
							if (vid.videoHeight / vid.videoWidth == flowplayer.defaults.ratio) {
								$(root).find('.fp-logo').css('right', '15px');
							}
							// Play videos when initialized
							playVid(root);
						}
					});
					// Hack for firefox to loop videos in flash player.  Doesn't fire for html5 video 
					// since the loop attribute is set
					api.bind("finish", function (e) {
						flowplayer(e.target).play();
					});

					// Handle errors by loading the fallback image
					api.bind("error", function () {
						var imgDiv = $(this).parent().children(imgSelector).first();
						$(this).remove();
						displayFallbackImage(imgDiv);
					});
				});
			}

			// Used to control initialization of players
			$(vidPlayerDivSelector).each(function(i, div) {
				var divId = $(div).attr('id');
				playersInit[divId] = false;
			});
			
			// Call once at first to initialize any videos that are currently visible
			toggleVideos();

			// On scroll complete toggle viewable videos to play state
			$(window).bind('scroll',function () {
				clearTimeout(timer);
				timer = setTimeout(toggleVideos, WAIT_INTERVAL);
			});
		}
	};

	function initPlayer(div) {
		if (isMobileDomain || isMobileDevice()) {
			// Set splash background
			var	imgIdPrefix = isLargeDisplay() ? 'lrgimgurl' : 'smlimgurl';
			var imgPath = $('#' + imgIdPrefix + '-' + $(div).attr('id').replace('player-', '')).text();
			$(div).css('background',  'url(' + imgPath + ') center no-repeat');
		}
		
		// Add video and source tags
		var vidPath = $('#vidurl-' + $(div).attr('id').replace('player-', '')).text();
		$(div).html('<video loop><source type="video/mp4" src="' + vidPath + '"><source type="video/flash" src="' + vidPath + '"></source></video>');

		// Initialize flowplayer
		$(div).flowplayer();
		log('initialize player: ' + $(div).attr('id'));
	}

	function playVid(div) {
		flowplayer(div).play();
		log('play vid: ' + $(div).attr('id'));
	}

	function pauseVid(div) {
		flowplayer(div).pause();
		log('pause vid: ' + $(div).attr('id'));
	}

	function getState(div) {
		var api = flowplayer(div);
		var state = undefined;
		if (!api) {
			state = undefined;
		} else if (api.playing) {
			state = 'PLAYING';
		} else if (api.paused) {
			state = 'PAUSED';
		} else if (api.loading) {
			state = 'LOADING';
		} else if (api.seeking) {
			state = 'SEEKING';
		} else {
			state = undefined;
		}
		return state;
	}

	var toggleVideos = function() {
		$(vidPlayerDivSelector).each(function(i, div) {
			var api = flowplayer(div);

			var divId = $(div).attr('id');
			var state = getState(div);
			if(isScrolledIntoView(div)) {
				if (!playersInit[divId] && api == undefined) {
					playersInit[divId] = true;
					initPlayer(div);
				} else if (api != undefined && api.ready && state != 'PLAYING') {
					if (!isMobileDomain && !isOldIE && !isMobileDevice()) {
						playVid(div);
					}
				}
			} else {
				if (api != undefined && api.ready) {
					pauseVid(div);
				}
			}
		});
	};

	function isScrolledIntoView(elem) {
		var docViewTop = $(window).scrollTop();
		var docViewBottom = docViewTop + $(window).height();

		var elemTop = $(elem).offset().top;
		var elemBottom = elemTop + $(elem).height();

		log('doctop ' + docViewTop);
		log('docbottom ' + docViewBottom);
		log('elemTop ' + elemTop);
		log('elemBottom ' + elemBottom);

		// add 400 so we can init videos that are just below the fold giving and 
		// making it appear the videos load vaster
  		return ((elemBottom >= docViewTop) && (elemTop <= docViewBottom + 400)
      		|| (elemBottom <= docViewBottom) &&  (elemTop >= docViewTop));
	}

	function log(msg) {
		//console.log(msg);
	}

	function isLargeDisplay() {
		var largeDisplay = true;
		var isLandscape = (document.documentElement.clientHeight < document.documentElement.clientWidth);
		if ((document.documentElement.clientWidth < 600) || (document.documentElement.clientHeight < 421 && isLandscape)) { 
			largeDisplay = false;
		}
		return largeDisplay;
	}

	function isMobileDevice() {
		if (null == isMobile) {
			log ('isMobile is null');
			if (navigator.userAgent.match(/iPhone/i)
				|| navigator.userAgent.match(/iPad/i)
				|| navigator.userAgent.match(/Android/i)
				|| navigator.userAgent.match(/Silk/i)
				|| navigator.userAgent.match(/webOS/i)
				|| navigator.userAgent.match(/iPod/i)
				|| navigator.userAgent.match(/BlackBerry/i)
				|| navigator.userAgent.match(/Windows Phone/i)
				|| navigator.userAgent.match(/Opera Mini/i)
				|| navigator.userAgent.match(/IEMobile/i)) {
				isMobile = true;
			} else {
				isMobile = false;
			}
		}
		return isMobile;
	}

	/*
	* Returns true if h264 or flash support is available, false otherwise
	*/
	function isVideoCapable() {
		var testEl = document.createElement("video");
		var h264 = false;
		if ( testEl.canPlayType ) {
			// Check for h264 support
			h264 = "" !== (testEl.canPlayType('video/mp4; codecs="avc1.42E01E"')
				|| testEl.canPlayType('video/mp4; codecs="avc1.42E01E, mp4a.40.2"'));
		}

		return h264 || flowplayer.support.flashVideo; 
	}


	/*
	* Check for certain older os versions as a proxy for old, slow hardware that won't 
	* perform well with video.
	*/
	function isSlowDevice() {
		var isSlowDevice = false;

		var matches = navigator.userAgent.match(/\bAndroid (\d+)\./i);
		// We'll call any android device slow if they have a version less than 4
		if (matches != null && matches[0] < 4) {
			isSlowDevice = true;
		}

		// All blackberries
		if (navigator.userAgent.match(/Blackberry/i) != null) {
			isSlowDevice = true;
		}

		// First gen kindle
		if (navigator.userAgent.match(/AppleWebKit\/533.1/) != null) {
			isSlowDevice = true;
		}

		return isSlowDevice;
	}

	function displayFallbackImages() {
		$(imgSelector).each(function(i, div) {
			displayFallbackImage(div);
		});
	}

	function displayFallbackImage(div) {
		var img = $('<img />'); 
		img.attr('src', $(div).text());
		$(div).after(img);
	}

})(jQuery);
