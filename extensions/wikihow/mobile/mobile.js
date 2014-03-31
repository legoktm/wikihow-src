var WH = WH || {};
WH.mobile = WH.mobile || {};

WH.mobile = jQuery.extend(WH.mobile,

(function () {

	var contentTabs = ['ingredients', 'steps', 'thingsyoullneed', 'tips', 'warnings', 'video'];
	var contentDropdowns = ['ingredients', 'steps', 'thingsyoullneed', 'tips', 'warnings', 'video', 'relatedwikihows', 'sources', 'article_info'];

	function initHillaryCTA() {
		if (document.location.href.indexOf('#review') != -1) return; // No CTA if already in tool
		if (document.location.href.indexOf('//m.wiki') == -1) return; // No CTA on non-english
		if (Math.random() > 0.1) return; // only 10 percent chance of getting this CTA

		$('<div style="line-height:30px">&nbsp;</div>').insertBefore('#cta');
		$('#article').css({'padding-top': '29px'});
		$('#cta').css({'padding-top': '25px', 'padding-left': '15px'});
		$('#cta').html('Hey! We need your help <a href="/Special:ArticleQualityGuardian?utm_source=mobileweb&utm_medium=mobile10cta&utm_campaign=hillary" rel="nofollow">rating articles</a>!');
		$('#cta').delay(200).slideDown();
		$('#article').animate({'padding-top': '15px'}, 'slow');
	}

	/*
	function initQGCTA() {
		return;
		if (document.location.pathname.indexOf('Special:MQG') != -1) {
			return;
		}
		var now = new Date();

		var pageIds = [35488, 4322, 10011, 55442, 49236, 221266, 22372, 9221, 11468,
			74588, 14093, 12791, 8041, 12391, 33339, 57203, 37616, 76696, 8487,
			131241, 398956, 29052, 38546, 106548, 169325, 25987, 19104, 286729,
			15230, 109874, 35948, 7536, 27318, 13169, 1690, 38440, 177210, 157117,
			25405, 1946, 32659, 214862, 20597, 1639, 4357 ];

		var revIds = [7949901,7965821,7951641, 7947082, 7832227, 7921696, 7961780,
			7962680, 7964001, 7970530, 7954975, 7965869, 7970561, 7946114, 7971224,
			7973763, 7979301, 7938585, 8077961, 7886177, 8149953, 8172319, 8104746,
			7903502, 8159910, 8147651, 7941903, 8074801, 8136885, 8170100, 8169696,
			8101894, 8161357, 8172515, 8149819, 7406960, 8133044, 8052024, 8183909,
			8121643, 8163708, 8057398, 7953573, 8178011, 8177980 ];
		var pageIndex = pageIds.indexOf(parseInt(wgArticleId));

		// Show the CTA 100% of the time
		if (pageIndex != -1) { //&& now.getMilliseconds() % 2 == 0) {
			$.get('/Special:MQG', {'log' : 'tipcta' + ',' + wgArticleId});
			var ctaLink = $('#qg_intro_link');
			var pos = Math.floor(Math.random() * 5);
			var queryString = '?c=2&qc_type=tip&qc_rev_id=' + revIds[pageIndex] + '&qc_aid=' + wgArticleId;
			var href = ctaLink.attr('href') + queryString;
			ctaLink.attr('href', href);
			$('#qg_cta').delay(200).slideDown('fast');
		}
	}
	*/

	/*
	function initAppLink() {
		var uagent = navigator.userAgent || '';
		if (uagent.match(/android/i)) {
			$('#mobile_app_android').show();
		} else if (uagent.match(/iphone/i)) {
			$('#mobile_app_iphone').show();
		}
	}
	*/

	// If we are on iPhone, scroll down and hide URL bar
	function iphoneHideUrlBar() {
		if (/iphone|ipod/i.test(navigator.userAgent) > 0) {
			setTimeout( function () {
				window.scrollTo(0, 1);
			}, 0);
		}
	}

	function hideDropHeadings(clickedTab) {
		$('[id^=drop-content-]').each(function(i, content) {
			var id = $(content).attr('id');
			var heading = $('#' + id.replace('drop-content', 'drop-heading'));
			// Special case for tips tab. Warning included here. Fake warnings id to be same as tips
			// to match below
			if (clickedTab == 'tips' && id == 'drop-content-warnings') {
				id = 'drop-content-tips';
			}

			if (id == 'drop-content-' + clickedTab) {
				if ($(content).is(':visible')) {
					heading.click();
				}
				heading.hide();
			} else {
				heading.show();
			}
		});
	}

	function addClickHandlersTabs() {
		$.each(contentTabs, function(i, clickedTab) {
			$('#tab-' + clickedTab).click( function(e) {
				var content = $('#drop-content-' + clickedTab);
				var heading = $('#drop-heading-' + clickedTab);

				//get the speed
				var pixelDiff = heading.offset().top - $('#article_tabs').offset().top;
				var scrollTime = interpPixelsScrollTime(pixelDiff);

				$('html, body').animate({
					scrollTop: heading.offset().top
				},scrollTime,'easeInOutExpo');

				if (!content.is(':visible')) {
					content.animate({
						height: 'show',
						opacity: 'show'
					},'slow');
					$('#drop-heading-' + clickedTab + ' .drop-heading-expander').addClass('d-h-show');
				}

				if (_gaq) {
					_gaq.push(['_trackEvent', 'm-article', 'tab-selected', wgTitle]);
				}
				return false;
			});
		});
	}

	function addClickHandlersDropdowns() {
		$.each(contentDropdowns, function(i, clickedDrop) {
			var drop = $('#drop-heading-' + clickedDrop);
			if (drop !== null) {
				$('#drop-heading-' + clickedDrop + ' .drop-heading-edit').click( function() {
					//alert('edit');
					return false;
				});
				drop.click( function() {
					var pixels = $('#drop-content-' + clickedDrop).height() +
							   $('#drop-heading-' + clickedDrop).height();
					var millis = interpPixelsScrollTime(pixels);
					$('#drop-heading-' + clickedDrop + ' .drop-heading-expander').toggleClass('d-h-show');
					$('#drop-content-' + clickedDrop).animate({
						height: 'toggle',
						opacity: 'toggle'
					}, millis, 'easeInOutQuint');

					if (_gaq) {
						_gaq.push(['_trackEvent', 'm-article', 'dropdown-section', wgTitle]);
					}

					return false;
				});
			}
		});
	}

	function addClickHandlersImages() {
		// add image preview click handlers
		$('.image-zoom').click( function(e) {
			var id = e.currentTarget.id;
			var detailsID = id.replace(/^image-zoom/, 'image-details');
			var jsonDetails = $('#' + detailsID).html();
			var details = $.parseJSON(jsonDetails);
			//alert('preview: url=' + details.url + ' width=' + details.width + ' height=' + details.height);
			e.preventDefault();

			//for tablet size, just send them to the page
			if (isBig) {
				window.location.href = details.credits_page;
				return;
			}

			var image_obj = $('#image-src');
			image_obj.attr("src", "");
			image_obj.attr("src", details.url);
			var image_credits_link = $("#image-src-credits");
			image_credits_link.attr("href",details.credits_page);

			var offsetCurrent = $(e.currentTarget).offset();
			var offsetArticle = $('#article').offset();
			var preview_obj = $('#image-preview');
			preview_obj.css("top", $(window).scrollTop() + ($(window).height()/5));
			preview_obj.show();

			if (_gaq) {
				_gaq.push(['_trackEvent', 'm-article', 'zoomimage', wgTitle]);
			}
		});
	}

	/*
	 * open up Sources & Citations when a [1] reference is clicked
	 * so the page has somewhere to drop down to
	 */
	function addClickHandlersFootnotes() {
		$('.reference a').click(function() {
			$('#drop-content-sources').show();
		});
	}

	function addSocialSharing() {
		//only doing this if the final section is showing
		if ($('#final_section').length == 0 || $('#final_section').css('display') == 'none') return;

		//the twitter
		if ($('.twitter-share-button').length) {
			// Load twitter script
			$.getScript("//platform.twitter.com/widgets.js", function() {
				twttr.events.bind('tweet', function(event) {
					if (event) {
						var targetUrl;
						if (event.target && event.target.nodeName == 'IFRAME') {
							targetUrl = extractParamFromUri(event.target.src, 'url');
						}

						if (_gaq) {
							_gaq.push(['_trackSocial', 'twitter', 'tweet', targetUrl]);
						}
					}
				});

			});
		}

		if ($('.gplus1_button').length) {
			var node2 = document.createElement('script');
			node2.type = 'text/javascript';
			node2.async = true;
			node2.src = '//apis.google.com/js/plusone.js';
			$('body').append(node2);
		}

		// Init Facebook components
		if ($('.fb-like').length) {
			(function(d, s, id) {
				var js, fjs = d.getElementsByTagName(s)[0];
				if (d.getElementById(id)) return;
				js = d.createElement(s); js.id = id;
				var facebook_locale = wfMsg('facebook_locale');
				js.src = "//connect.facebook.net/" + facebook_locale + "/all.js#xfbml=1";
				js.async = true;
				fjs.parentNode.insertBefore(js, fjs);
			}(document, 'script', 'facebook-jssdk'));
		}

		//pinterest
		if ($('#pinterest').length) {
			var node3 = document.createElement('script');
			node3.type = 'text/javascript';
			node3.async = true;
			node3.src = '//assets.pinterest.com/js/pinit.js';
			$('body').append(node3);
		}
	}

	function resizeVideo() {
		//tablets...
		if ($(window).width() > 700) {
			new_width = 600;
			old_width = $('#video object').attr('width');
			old_height = $('#video object').attr('height');
			new_height = Math.round((new_width*old_height) / old_width);

			$('#video object, #video embed').attr('width',new_width);
			$('#video object, #video embed').attr('height',new_height);
		}
	}

	function checkSvgSupport() {
		if (($.browser.webkit) && (parseInt($.browser.version, 10) < 534)) {
			//client can't do SVGs
			//Author's note: BOO!
			$('#header_search .cse_sa').css('background-image','url(/extensions/wikihow/mobile/images/mag_glass.png)');
			$('.drop-heading-expander').css('background-image','url(/extensions/wikihow/mobile/images/expand.png)');
			$('.d-h-show').css('background-image','url(/extensions/wikihow/mobile/images/collapse.png)');
			$('#footer_links li').css('background-image','url(/extensions/wikihow/mobile/images/bullet.png)');
			$('#footer_links li.nodot').css('background-image','none');
			$('.step_checkbox .checkwhite').css('background-image','url(/extensions/wikihow/mobile/images/checkmark_grey.png)');
			$('#article .steps_list_2 li li').css('background-image','url(/extensions/wikihow/mobile/images/arrow.png)');
			$('#tips li').css('background-image','url(/extensions/wikihow/mobile/images/tip.png)');
			$('#warnings li').css('background-image','url(/extensions/wikihow/mobile/images/warning.png)');
			$('#relatedwikihows li').css('background-image','url(/extensions/wikihow/mobile/images/arrow.png)');
			$('#thingsyoullneed li').css('background-image','url(/extensions/wikihow/mobile/images/bullet.png)');
			$('#header_login').css('background-image','url(/extensions/wikihow/mobile/images/login.png)');
			$('#footbar li#footbar_random img').attr('src','/extensions/wikihow/mobile/images/random.png');
			$('#footbar li.foot_edit img').attr('src','/extensions/wikihow/mobile/images/pencil_blue.png');

			//fixed footer is an issue too
			$('#footbar ul').css('position','absolute').css('bottom','auto');
			$('#article').css('padding-bottom','0');
		}

		var ua = navigator.userAgent;
		if ((ua) && ((ua.indexOf('Kindle Fire') > 0) || (ua.indexOf('Silk-Accelerated') > 0))) {
			//fixed header and footer for Kindle Fire
			$('#header').css('position','absolute');
			$('#footbar ul').css('position','absolute').css('bottom','auto');
			$('#article').css('padding-bottom','0');
		}
	}

	// input is pixels to scroll, output is length of time in milliseconds
	function interpPixelsScrollTime(pixels) {
		// linearly interpolate
		// from a range [xa, xb], xa < xb
		// to a range [ya, yb], ya < yb
		// variable: xa <= x <= xb.
		// outputs y: ya <= y <= yb
		var linear = function(xa, xb, ya, yb, x) {
			var diffx = xb - xa;
			var diffy = yb - ya;
			y = ya + diffy * ((x - xa) / diffx);
			return y;
		};
		var x = pixels;
		if (x <= 1000) {         // [-inf,1000] -> 1500
			y = 1500;
		} else if (x <= 1500) {  // [1000,1500] -> [1500,2000]
			y = linear(1000,  1500, 1500, 2000, x);
		} else if (x <= 2500) {  // [1500,2500] -> [2000,2500]
			y = linear(1500,  2500, 2000, 2500, x);
		} else if (x <= 4500) {  // [2500,4500] -> [2500,3000]
			y = linear(2500,  4500, 2500, 3000, x);
		} else if (x <= 8500) {  // [4500,8500] -> [3000,3500]
			y = linear(4500,  8500, 3000, 3500, x);
		} else if (x <= 15000) { // [8500,15000] -> [3500,4000]
			y = linear(8500, 15000, 3500, 4000, x);
		} else {                 // [15000,inf] -> 4000
			y = 4000;
		}
		// ensure formulas and error in floating points
		y = Math.max(1500, y);
		y = Math.min(4000, y);
		return Math.round(y);
	}

	function supplementAnimations() {
		//add our slick easing
		$.extend($.easing,
		{
			easeInOutQuad: function (x, t, b, c, d) {
				if ((t/=d/2) < 1) return c/2*t*t + b;
				return -c/2 * ((--t)*(t-2) - 1) + b;
			},
			easeInOutQuint: function (x, t, b, c, d) {
				if ((t/=d/2) < 1) return c/2*t*t*t*t*t + b;
				return c/2*((t-=2)*t*t*t*t + 2) + b;
			},
			easeInOutQuart: function (x, t, b, c, d) {
				if ((t/=d/2) < 1) return c/2*t*t*t*t + b;
				return -c/2 * ((t-=2)*t*t*t - 2) + b;
			},
			easeInOutExpo: function (x, t, b, c, d) {
				if (t==0) return b;
				if (t==d) return b+c;
				if ((t/=d/2) < 1) return c/2 * Math.pow(2, 10 * (t - 1)) + b;
				return c/2 * (-Math.pow(2, -10 * --t) + 2) + b;
			},
		});
	}

	function initSiteSearchListen() {
		$('.cse_sa').click( function() {
			if (_gaq) {
				_gaq.push(['_trackEvent', 'm-article', 'site-search', wgTitle]);
			}
		});
	}

	function initBackToTopListen() {
		$('.backtotop').click( function() {
			if (_gaq) {
				_gaq.push(['_trackEvent', 'm-article', 'backtotop-link', wgTitle]);
			}
		});
	}

	function addSearchCorrection() {
		if (/iphone|ipod|ipad/i.test(navigator.userAgent) > 0) {
			$('#header_search .cse_q')
				.focus(function() {
					//fix for keyboard pulling header down to middle of screen bug
					var top = $(window).scrollTop();
					setTimeout( function () {
						$(window).scrollTop(top);
					}, 0);
				});
		}
		else if (/android/i.test(navigator.userAgent) > 0) {
			$('#header_search .cse_q')
				.focus(function() {
					//fix for portrait+keyboard disappearing header bug
					$('#header').css('position','fixed');
				});
		}
	}

	function modifyForIPhoneApp() {
		if (document.location.href.indexOf('platform=iphoneapp') == -1) return;
		isBig = false;
		$('#article').css({'padding-top': '5px'});
	}

	// singleton class
	return {
		startup: function() {
			$(document).ready( function() {
				checkSvgSupport();
				supplementAnimations();

				modifyForIPhoneApp();

				if (typeof uciSetup === 'function') {
					uciSetup(); // in ./usercompletedimages.js
				}

				//initQGCTA();
				//initAppLink();
				if (typeof WH.CheckMarks != "undefined") {
					WH.CheckMarks.init();
				}

				// Hillary -- article rating tool
				initHillaryCTA();

				// add click handlers -- tabs
				addClickHandlersTabs();

				// add click handlers -- dropdowns
				addClickHandlersDropdowns();

				// add image preview click handlers
				addClickHandlersImages();

				// add click handlers -- footnotes
				addClickHandlersFootnotes();

				resizeVideo();

				// fix the static header when user focuses to type a search
				addSearchCorrection();

				// add google analytics event tracking on certain user actions
				initSiteSearchListen();
				initBackToTopListen();
			});

			$(window).load(addSocialSharing);
		}
	};
})()

);

/*
function createCallback(that, func) {
	var params = $.makeArray(arguments).slice(2, arguments.length);
	return function() {
		func.apply(that, params);
	};
}
*/

function closeImagePreview(){
	$('#image-preview').hide();
}

/**
 * Copied from wikibits.js
 */
var gRated = false;
function rateItem(r, itemId, type) {
	if (!gRated) {
		$.ajax(
			{ url: '/Special:RateItem?page_id=' + itemId+ '&rating=' + r + '&type=' + type
			}
		).done(function(data) {
				$('#' + type + '_rating').html(data);
			});
	}
	gRated = true;
}

/**
 * Translates a MW message (ie, 'new-link') into the correct language text.  Eg:
 * wfMsg('new-link', 'http://mylink.com/');
 *
 * - loads all messages from WH.lang
 * - Copied from wikibits.js
 */
function wfMsg(key) {
	if (typeof WH.lang[key] === 'undefined') {
		return '[' + key + ']';
	} else {
		var msg = WH.lang[key];
		if (arguments.length > 1) {
			// matches symbols like $1, $2, etc
			var syntax = /(^|.|\r|\n)(\$([1-9]))/g;
			var replArgs = arguments;
			msg = msg.replace(syntax, function(match, p1, p2, p3) {
				return p1 + replArgs[p3];
			});
			// This was the old prototype.js Template syntax
			//var template = new Template(msg, syntax);
			//var args = $A(arguments); // this has { 1: '$1', ... }
			//msg = template.evaluate(args);
		}
		return msg;
	}
}
