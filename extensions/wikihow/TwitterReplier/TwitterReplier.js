// declare wikiHow module
if (!WH) var WH = {};

// define WH.TwitterReplier module
WH.TwitterReplier = (function ($) {
	// constants
	var POLL_PERIOD_SECS = 20,
		SPECIAL_PAGE = 'Special:TweetItForward',
		EXTENSION_URL = '/' + SPECIAL_PAGE,
		MAX_INT = 2147483647, // 2^31 - 1
		REFRESH_DATA_THROTTLE_INTERVAL_1 = 60, // poll for updates every 60 seconds after the first 5 minutes have gone by
		REFRESH_DATA_THROTTLE_START_1 = 300,
		REFRESH_DATA_THROTTLE_INTERVAL_2 = 300, // poll for updates every 5 minutes after the first 20 minutes have gone by
		REFRESH_DATA_THROTTLE_START_2 = 1200,
		MAX_TWEET_CHARS = 140;

	// global
	var responded,
		lastActivity = 1000 * MAX_INT,
		lastNetwork = 0;

	function pollTwitter()
	{
		var now = new Date().getTime();
		var userActivityDelta = timeInSecs( now - lastActivity );
		var netActivityDelta = timeInSecs( now - lastNetwork );

		if (( userActivityDelta > REFRESH_DATA_THROTTLE_START_2 &&
			  netActivityDelta < REFRESH_DATA_THROTTLE_INTERVAL_2 ) ||
			( userActivityDelta > REFRESH_DATA_THROTTLE_START_1 &&
			  netActivityDelta < REFRESH_DATA_THROTTLE_INTERVAL_1 ))
		{
			//console.log('no poll', userActivityDelta, netActivityDelta);
			return;
		}
		else {
			lastNetwork = now;
		}

		var twitter_id = $('#tweets_ticker li:first').attr("id").split('_');
	
		$.post( '/' + SPECIAL_PAGE, {
			action: 'latest',
			returnType: 'json',
			lastTwitterId: twitter_id[1]
		}, function( tw ) {
			if( tw && tw.tweets && tw.tweets.length > 0 ) {
				var clickMsg = tw.tweets.length > 1 ?
				tw.tweets.length + ' new tweets' :
				tw.tweets.length + ' new tweet';
				
				$("#new_tweets")
					.show()
					.html( clickMsg )
					.unbind( 'click' ) // first unbind any previous click
					.click( function() { // rebind click
						$('.new_bottom').removeClass('new_bottom');
						$("#new_tweets").hide();

						// animate incoming tweets
						var newTweets = $('<div class="new_tweets_fadein">' + tw.html + '</div>');
						newTweets.hide();
						$("#tweets_ticker").prepend( newTweets );
						newTweets.fadeIn(function () {
							newTweets.children().first().unwrap();

							readjustRightRailHeight();
						});

					});
					
				if( !$("#new_tweets").is(":visible") ) {
					$("#new_tweets").effect( 'highlight' );
				}
			}
		},
		'json');
	}

	function displayDialogue( title, msg, width )
	{
		if( isNaN( width ) ) {
			width = 400;
		}
	
		var inside = '<p>'+msg+'</p>';
		inside += '<hr><p class="ok_container"><a href="#" class="button white_button ok_button" onmouseover="button_swap(this);" onmouseout="button_unswap(this);">OK</a></p>';
		$("#dialog-box").html( inside );
		$("#dialog-box").dialog({
			width: width,
			modal: true,
			title: title,
			open: function() {
				$(".ok_button").click(function () {
					$("#dialog-box").dialog("close");
					return false;
				});
			}
		});
	}

	function displayAuthentication( response )
	{
		var obj = $.parseJSON( response );
	
		$("#authorizationContainer").dialog({
			width: 471,
			height: 263,
			modal: true
		});
	
		$("#authorizationContainer a").attr("href", obj.authorizationUrl);
	}

	/**
	 * Choose one of three random msgs, and construct the tweet.
	 */
	function randomizeMessage( user, title, url )
	{
		var msg = Array();
		var i = Math.floor( Math.random() * 3 );
	
		user = $.trim( user );
		title = wfMsg('howto', $.trim(title));
		url = $.trim( url );
	
		msg[0] = '@' + user + ' maybe this will help, ' + title + ' ' + url;
		msg[1] = '@' + user + ' try these tips from @wikihow ' + title + ' ' + url;
		msg[2] = '@' + user + ' let me know if the @wikihow on ' + title + ' helps you. ' + url;
	
		return msg[i];
	}

	/**
	 * Listen for all browser activity and populate the lastActivity variable.
	 */
	function listenAllActivity() 
	{
		$(document).bind('mousemove mousedown keydown scroll', function(evt) {
			lastActivity = evt.timeStamp ? evt.timeStamp : new Date().getTime();
		});
	}

	/**
	 * Utility function to return the time in seconds instead of milliseconds.
	 */
	function timeInSecs(time) 
	{
		return Math.round(time / 1000.0);
	}

	/**
	 * Called outside of this module once the DOM is read.  Sets up
	 * the URL event listeners and new tweet polling.
	 */
	function onDOMReady() 
	{
		registerUIEventListeners();
		checkForOldBrowsers();
		WH.autoRefreshTimes('.reltime');
		listenAllActivity();
		setInterval( pollTwitter, 1000 * POLL_PERIOD_SECS );
	}

	/**
	 * Calculate the actual tweet length, which takes into account the
	 * t.co URL shortening that the Twitter API does for us.
	 */
	function calcReplyTweetLength(reply) 
	{
		var len = reply.length;
		var shortenBy = 'http://t.co/V6eR7AAx'.length; // length of example URL
		var newReply = reply.replace(/http:\/\/[^ ]*/, '');
		var newLen = newReply.length + shortenBy;
		if( newLen < len ) {
			len = newLen;
		}
		return len;
	}

	/**
	 * Show a negative red number when the length of a tweet goes over
	 * the max twitter tweet length.
	 */
	function setOverLimit( tweet ) 
	{
		var len = calcReplyTweetLength( tweet );
		var left = MAX_TWEET_CHARS - len;
		var lim = $("#char_limit");
		lim.html( left );
		if( left <= 10 ) {
			lim.addClass('over_limit');
		}
		else {
			lim.removeClass('over_limit');
		}
	}

	/**
	 * Show (or hide) the spinner that happens when the user clicks 
	 * the Tweet It Forward button.
	 */
	function showHideSpinner( showHide ) 
	{
		if( 'hide' == showHide ) {
			$("#reply").prop("disabled", false);
			$("#reply_spinner").hide();
		} else if( 'show' == showHide ) {
			$("#char_limit").html("");
			$("#reply").prop("disabled", true);
			$("#reply_spinner").show();
		}
	}

	/**
	 * If the user is using an old version of MSIE, warn and chastize them.
	 */
	function checkForOldBrowsers()
	{
		if( $.browser.msie && $.browser.version < 8.0 ) {
			displayDialogue( 'Warning', 'We notice that you are using an older version of Microsoft Internet Explorer.  Unfortunately, the Tweet It Forward app is a suboptimal experience in this browser.  If you really like the app and cannot <a href="http://www.google.com/chrome" target="_blank">upgrade your web browser</a>, complain loudly in the wikiHow bug forums and we may address some issues.' );
		}
	}

	function readjustRightRailHeight()
	{
		// TODO: this is hack till reply_content container can be changed around
		$("#reply_container").height( $("ul#tweets_ticker").height() );
	}

	function registerUIEventListeners() 
	{
		readjustRightRailHeight();
		
		setupScrolling();
		
		$('#twitter_poll').click( function(e) {
			pollTwitter();
		
			return false;
		});
	
		$("#twitter_retrieve").click( function(e) {
			$.post( EXTENSION_URL, {
				action: 'retrieve'
			}, function(response) {
				//alert( response );
				});
	
			return false;
		});
	
		// handles default text for search input
		$("input[name='trSearch']").focus( function(e) {
			if( $(this).val() == wfMsg('default-search-title') ) {
				$(this)
					.removeClass('default_search_input')
					.val('');
			}
		});
	
		// handles default text for search input
		$("input[name='trSearch']").blur( function(e) {
			if( $(this).val() == '' ) {
				$(this)
					.addClass('default_search_input')
					.val( wfMsg('default-search-title') );
			}
		});
	
		var doWikihowSearch = function (query) {
			if ($.trim(query) == '') return false;

			$("#suggestedTitles tbody").html( '<tr><td>&nbsp;</td><td>Searching...</td></tr>' );

			resetCustomizeTweet();

			$.post( EXTENSION_URL, {
				action:'searchWikihow', 
				tweet: query
			}, function( response ) {
				$("#suggestedTitles tbody").html( response );
				$("#suggestedTitles * a").attr("target", "_blank");
				$(".suggest_article_header").show();
			});
		};

		$("#trSearchForm input").click( function(e) {
			var node = $(this);
			var x = e.pageX - node.offset().left;
			var width = node.innerWidth();
			var percent = 90;
			if( 100 * x / width >= percent ) {
				var query = node.val();
				doWikihowSearch(query);
				return false;
			}
			return true;
		});

		// hitting enter onsearch box
		$("input[name='trSearch']").keydown(function(e) {
			if( e.keyCode == '13' ) {
				var query = $(this).val();
				doWikihowSearch(query);
				return false;
			}
		});
	
		// create suggested tweet
		$(".suggested_article").live('click', function() {
		
			if( $(this).is(":checked" ) ) {
				var article = $.parseJSON( $(this).val() );
				var user = $(".reply_to_user").html();
				var msg = randomizeMessage(user, article.title, article.url);
									
				$("#reply_tweet").val( msg );
				setOverLimit( $("#reply_tweet").val() );

				$("#respond_to, .customize_header").removeClass('grey');
				$("#reply_tweet")
					.removeClass("light_grey")
					.prop("disabled", false)
					.focus();
				$("#reply").prop("disabled", false);
			}
		});

		$("#reply_tweet").bind('keypress keyup', function () {
			setOverLimit( $(this).val() );
		});
	
		// tweets hover
		$("#tweets_ticker li").live('mouseover mouseout', function(e) {
			if( e.type == 'mouseover' ) {
				$(this).addClass( 'hover' );
			}
			else {
				if( !$(this).hasClass( 'locked' ) ) {
					$(this).removeClass( 'hover' );
				}
			}
		});
	
		// closes reply container
		$(".reply_close").click( function() {
			// hide reply container
			$("#reply_content").hide();
			$("#instructions").show();
			// remove locked class
			var liId  = $(".locked").attr("id"); 
		
			// unlock tweet
			var eTweetId = liId.split('_');
		
			//unlockTweet( eTweetId[1] );
		
			// change background color to white
			$("#"+liId).css( 'background-color', 'white' );

			return false;
		});
	
		// clicking on a tweet
		$("#tweets_ticker li").live( 'click', function() {
			var liId = $(this).attr("id");
			var eTweet = liId.split('_');
			tweetId = eTweet[1];
			
			if( responded )
			{
				$("#twitter_" + responded).removeClass( 'hover' );
				$("#twitter_" + responded).removeClass( 'locked' );
			}
			
			$(this).addClass( 'hover' );
			$("#"+liId).addClass( 'locked' );
			setOverLimit('');
		
			displayReplyContainer( tweetId );
			
			responded = tweetId;
		//unlockTweet( responded, tweetId );
		//		if( responded > 0 ) {
		//			unlockTweet( responded, tweetId );
		//			$.ajaxSetup({
		//				async:false
		//			});
		//					
		//			unlockTweets();
		//					
		//			$.ajaxSetup({
		//				async:true
		//			});
		//		}
		
		//		$.post( EXTENSION_URL, {
		//			action: 'lockTweet',
		//			tweetId: tweetId
		//		}, function( response ) {
		//					
		//			var json = $.parseJSON( response );
		//
		//			if( json.lock ) {
		//				responded = tweetId;
		//				$("#reply_tweet").val( '' );
		//				$("input[name='trSearch']").val( wfMsg('default-search-title') );
		//				$("#reply_container .reply_to_user").html( handle );
		//				$("#instructions").hide();
		//				$("#reply_content").show();
		//				$("#reply_status_id").html( tweetId );
		//				$("#suggestedTitles tbody").html('');
		//				/* SUGGESTED TITLES CODE
		//					$("#suggestedTitles tbody").html( '<tr><td>&nbsp;</td><td>Searching wikiHow</td></tr>' );
		//
		//					$.post( EXTENSION_URL, {
		//						action: 'searchWikihow',
		//						searchCategoryId: searchCategoryId,
		//						tweet: $("#twitter_" + tweetId + "_" + searchCategoryId + " span .tweet").html()
		//					}, function( response ){
		//
		//						$("#suggestedTitles tbody").html( response );
		//						$("input[name=trSearch]").val( $("#searchTerms").html() );
		//					})
		//				*/
		//			}
		//			else {
		//				$("#"+liId).removeClass( 'locked' );
		//				displayDialogue( 'Error', 'Someone is already responding to this tweet' );
		//			}
		//		})
		});
	
		// reply to user 
		$("#reply").click( function(e) {
			showHideSpinner('show');
			$.post( EXTENSION_URL, {
				action: 'authenticate'
			}, function ( response ) {
				if( response.length > 0 ) {
					showHideSpinner('hide');
					displayAuthentication( response );
				}
				else {
					var replyTweet = $("#reply_tweet").val();
					var replyStatusId = $("#reply_status_id").html();
					var replyLen = calcReplyTweetLength( replyTweet );
				
					if( replyLen > 0 && replyLen <= MAX_TWEET_CHARS ) {
						var origAuthor = $.trim( $('#twitter_' + replyStatusId + ' span .twitter_handle').html() );
						var origTweet = $.trim( $('#twitter_' + replyStatusId + ' span .tweet').html() );

						$.post( EXTENSION_URL, {
							action: 'reply', 
							tweet: replyTweet, 
							replyStatusId: replyStatusId,
							origAuthor: origAuthor,
							origTweet: origTweet
						}, function( response ) {
							if( response.length == 0 ) {
								// TODO: move hiding reply container into function
								$("#reply_content").hide();
								$("#instructions").show();
								$("#twitter_" + replyStatusId).hide();
								$("#suggestedTitles tbody").html('');
							
								var profileImage = $("#profileImage").html();
								var screenName = $("#screenName").html();
							
								$.post( EXTENSION_URL, {
									action:'displayTweet', 
									replyTweet: replyTweet, 
									profileImage: profileImage, 
									screenName: screenName
								}, function( response ) {
									var tweet = $('<div>' + response + '</div>');
									$('.tweet', tweet)
										.removeClass('tweet')
										.addClass('success_tweet');
									showHideSpinner('hide');
									displayDialogue( "Success", "<p>You've tweeted the following: </p>" + tweet.html(), 692 );
								});
							
							}
							else {
								showHideSpinner('hide');
								displayDialogue( 'Error', response );
							}
						});
					}
					else if( replyLen > MAX_TWEET_CHARS ) {
						showHideSpinner('hide');
						var msg = 'Your reply is <b>too long</b> &mdash; your tweet was not sent.  Try shortening it and tweeting again.';
						displayDialogue( 'Error', msg );
					}
					else {
						showHideSpinner('hide');
						var msg = 'Reply is too short.  Your tweet was not sent.';
						displayDialogue( 'Error', msg );
					}
				}
			});
			return false;
		});
   
		// TODO: remove once we no longer need stream compare
		$('input[name="streamSubmit"]').click( function() {
			var keywords = $('input[name=keywords]').val();
			var numResults = $("select[name=numResults]").val();
			var inboxType = $("select[name=inboxType]").val();
	   
			$("#streamLeft").html( 'Loading ...' );
			$("#streamRight").html( 'Loading ...' );
			if( keywords.length > 0 ) {
				$.post( EXTENSION_URL, {
					action: 'searchInbox', 
					keywords: keywords, 
					inboxType: inboxType, 
					numResults: numResults
				}, function( response ) {
					$("#streamLeft").html( response );
				});
			
				$.post( EXTENSION_URL, {
					action:'searchTwitter', 
					keywords: keywords, 
					numResults: numResults
				}, function( response ) {
					$("#streamRight").html( response );
				});
			}
			else {
				displayDialogue( 'Error', 'Please enter keywords' );
			}
		});
		
		$("#authorizeMe").click( function( e ) {
			e.preventDefault();
			
			window.open( $(this).attr( 'href' ), "twitterAuthWindow", "width=800,height=700" );
			$("#authorizationContainer").dialog('close' );
		})
		
		$('.unlink_action').click( function(e) {
			e.preventDefault();
			$.post( EXTENSION_URL, {
				action: 'deauthenticate'
			}, function ( data ) {
				if (data && data['removed']) {
					displayDialogue( 'Accounts Unlinked', 'Information about your Twitter account has been removed from wikiHow' );
					$(".reply_as").hide();
				}
			},
			'json');
			return false;
		});
	}
	
	function onWindowUnload() {
	//	if( responded.length > 0 ) {
	//		$.ajaxSetup({
	//			async:false
	//		});
	//		
	//		unlockTweets();
	//		
	//		$.ajaxSetup({
	//			async:true
	//		});
	//	}
	}

	/*
function unlockTweets()
{
	if( responded.length > 0 ) {
		for( i = 0; i < responded.length; i++ ) {
//			$("#twitter_" + responded[i]).removeClass( 'locked');
//			
//			$("#twitter_" + responded[i]).css( 'background-color', 'white' );
				
			unlockTweet( responded[i] );

			responded.splice( i, 1);
		}
	}
}
*/
	function setupScrolling()
	{
		var replyContainer = $("#reply_container");
		var replyContent = $("#reply_content");
		var windowObj = $(window);

		var topOffset = replyContainer.offset().top;
		var scrollTop = windowObj.scrollTop();
		var newBottom = scrollTop + replyContent.height() + 5;
		
		windowObj.scroll( function() {
			topOffset = replyContainer.offset().top;
			scrollTop = windowObj.scrollTop();
			newBottom = scrollTop + replyContent.height();
			var bottomOffset = topOffset + replyContainer.height();
			
			// we should be scrolling
			if( scrollTop >= topOffset - 10 && newBottom <= bottomOffset )
			{
				replyContent.addClass( 'scroll' )
					.removeClass( 'bottom' )
					.removeClass( 'top' );
			}
			// we've reached bottom of container
			else if( newBottom >= bottomOffset ) {
				replyContent.addClass( 'bottom' )
					.removeClass( 'top' )
					.removeClass( 'scroll' );
			}
			// we're reach top of container
			else {
				replyContent.addClass( 'top' )
					.removeClass( 'bottom' )
					.removeClass( 'scroll' );
			}
			
//console.log( 'scrollTop: ' + scrollTop + ' topOffset: ' + topOffset + ' bottomOffset: ' + bottomOffset + ' bottomPosition: ' + newBottom );
		});
	}
	
	function displayReplyContainer( tweetId )
	{
		var handle = $.trim( $("#twitter_" + tweetId + " span .twitter_handle").html() );
		
		$("input[name='trSearch']")
			.addClass('default_search_input')
			.val( wfMsg('default-search-title') );
		$("#reply_container .reply_to_user").html( handle );
		$("#instructions").hide();

		// add animation to showing reply_content when user want to reply
		var reply = $("#reply_content");
		if ( !reply.is(":visible") ) {
			var fullWidth = reply.outerWidth();
			reply
				.css({
					width: 0,
					whiteSpace: 'nowrap' })
				.show();
			reply.animate({ width: fullWidth },
				function () {
					reply.css( { whiteSpace: 'normal' } );
				});
		}

		$("#reply_status_id").html( tweetId );
		$("#suggestedTitles tbody").html('');
		$(".suggest_article_header").hide();

		resetCustomizeTweet();
		
		responded = tweetId;
	}

	function resetCustomizeTweet() {
		$("#respond_to, .customize_header").addClass("grey");
		setOverLimit('');
		$("#reply_tweet")
			.val('')
			.addClass("light_grey")
			.attr("disabled", "disabled");
		$("#reply").attr("disabled", "disabled");
	}
	
	function lockTweet( tweetId )
	{
		var handle = $.trim( $("#twitter_" + tweetId + " span .twitter_handle").html() );
	
		$.post( EXTENSION_URL, {
			action: 'lockTweet',
			tweetId: tweetId
		}, function( json ) {
				
			if( json.lock ) {
				responded = tweetId;
				setOverLimit('');
				$("#reply_tweet").val('');
				$("input[name='trSearch']")
					.addClass('default_search_input')
					.val( wfMsg('default-search-title') );
				$("#reply_container .reply_to_user").html( handle );
				$("#instructions").hide();
				$("#reply_content").show();
				$("#reply_status_id").html( tweetId );
				$("#suggestedTitles tbody").html('');
			// SUGGESTED TITLES CODE
			//			$("#suggestedTitles tbody").html( '<tr><td>&nbsp;</td><td>Searching wikiHow</td></tr>' );
			//
			//			$.post( EXTENSION_URL, {
			//				action: 'searchWikihow',
			//				searchCategoryId: searchCategoryId,
			//				tweet: $("#twitter_" + tweetId + "_" + searchCategoryId + " span .tweet").html()
			//			}, function( response ){
			//
			//				$("#suggestedTitles tbody").html( response );
			//				$("input[name=trSearch]").val( $("#searchTerms").html() );
			//			})
			}
			else {
				$("#twitter_" + tweetId ).removeClass( 'locked' );
				displayDialogue( 'Error', 'Someone is already responding to this tweet' );
			}
		},
		'json');
	}

/*
	function unlockTweet( tweetId, newTweetId )
	{
		if( tweetId && tweetId.length > 0 ) {
			$.post( EXTENSION_URL, {
				action: 'unlockTweet', 
				tweetId: tweetId
			}, function( json ) {
				response = $.parseJSON( json );
				if( response.unlock ) {
					
					$("#twitter_" + tweetId).removeClass( 'locked' );
					$("#twitter_" + tweetId).css( 'background-color', 'white' );

				//lockTweet( newTweetId );
				}
				else {
					if( !response.unlock ) {
						displayDialogue( 'Error', 'Unable to unlock tweet: ' + tweetId + "\n Server response:" + json );
					}
				}
			});
		}
		//else {
		lockTweet( newTweetId );
	//}
	}
*/

	// external interface to WH.TwitterReplier module
	return {
		onDOMReady: onDOMReady,
		onWindowUnload: onWindowUnload
	};

})(jQuery);

