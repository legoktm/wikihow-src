// hack to fix fb all.js bug where they appempt to call console.  Console is not available for all browsers
if (typeof console == "undefined" || typeof console.log == "undefined") var console = { log: function() {} }; 

// Bootstrap function to catch clicks for yet to be initialized wikiHow Facebook features.
(function($) {
	$('#fb_login_head,#fb_login').live('click', function(e) {
		e.preventDefault();
		alert('Facebook login still loading. Please try again when page is fully loaded.');
	});
})(jQuery);

WH = WH || {};
WH.FB = WH.FB || {};
WH.FB.permsExtended = 'email,user_interests,user_likes,user_work_history,user_education_history,user_location';
WH.FB.permsBasic = 'email';

WH.FB.doWikiHowLogin = function(authResponse) {
	var today = new Date();
	var expires_date = (new Date( today.getTime() + 3600*1000 )).toGMTString();
	document.cookie = 'wiki_fbuser=' + escape(authResponse.userID) + ';expires=' + expires_date;
	document.cookie = 'wiki_fbtoken=' + escape(authResponse.accessToken) + ';expires=' + expires_date;
	//document.cookie = 'wiki_returnto=' + escape(document.referrer) + ';expires=' + expires_date;
	//alert(document.cookie + " -- " + authResponse.userID + " -- " + wgFBAppId);
	window.location='/Special:FBLogin';
}

WH.FB.storeContact = function(accessToken) {
	jQuery.get('/Special:FBAppContact?token=' + accessToken);
}

WH.FB.doLogin = function() {
	// jQuery('#fb_connect_header').html('[Logging in <img src="' + wgCDNbase + '/skins/WikiHow/images/fb_loading.gif"/>]');
	jQuery('#bubbles #login').html('[Logging in <img src="' + wgCDNbase + '/skins/WikiHow/images/fb_loading.gif"/>]');

	WH.FB.doFBLogin(function(response) {
		WH.FB.doWikiHowLogin(response.authResponse);
	});
}

WH.FB.initEventListeners = function() {
	jQuery('#fb_login_head,#fb_login').die('click').live('click', function() {
		WH.FB.doLogin();	
	});

	// Event listener for fb account linking
	jQuery('#fl_enable_acct').live('click', function(e) {
		e.preventDefault();
		WH.FB.doFBLogin(function(response) {
			$('#dialog-box').html('<img src="/extensions/wikihow/rotate.gif" alt="" />');
			jQuery('#dialog-box').dialog({
				width: 750,
				modal: true,
				closeText: 'Close',
				position: '10px',
				title: 'Are you sure you want to Enable Facebook Login?'
			});
			$('#dialog-box').load('/Special:FBLink', {token: response.authResponse.accessToken, a: 'confirm'});
		}, WH.FB.permsExtended);
	});
}

WH.FB.initLikeButtons = function(){
   if ($.browser.msie && $.browser.version <= 7) {
		$(".like_button").html("");
   }
}

WH.FB.init = function(debug) {
	window.fbAsyncInit = function () {
		chUrl = wgServer + '/extensions/wikihow/xd_receiver.htm';
		FB.init({ 
			appId:wgFBAppId, cookie:true, 
			status:true, xfbml:true, oauth:true,
			channelUrl: chUrl
		});
		//FB.UIServer.setActiveNode = function(a,b){FB.UIServer._active[a.id]=b;} // IE hack to correct FB bug
		WH.FB.initEventListeners();

		//code for tracking fb in analytics
		FB.Event.subscribe('edge.create', function(targetUrl) {
		  _gaq.push(['_trackSocial', 'facebook', 'like', targetUrl]);
		});

		FB.Event.subscribe('edge.remove', function(targetUrl) {
		  _gaq.push(['_trackSocial', 'facebook', 'unlike', targetUrl]);
		});
	};

	var locale = 'en_US';
	if (wgUserLanguage != 'en') {
		locale = wgUserLanguage + '_' + wgUserLanguage.toUpperCase();
	}

	// Set pt.wikihow.com to brazillian portuguese
	if (wgUserLanguage == 'pt') {
		locale = 'pt_BR';
	}

	WH.FB.initLikeButtons();

	(function () {
		var fbs  = '//connect.facebook.net/' + locale + '/all.js';
		var e = document.createElement('script');
		e.type = 'text/javascript';
		e.src = document.location.protocol + fbs;
		e.async = true;
		document.getElementById('fb-root').appendChild(e);
	} ());
}

// Log in to facebook. If successful, invoke the callback function with the response object
WH.FB.doFBLogin = function(callback, perms) {
	// Default value for scopePerms string
	perms = perms == null ? WH.FB.permsBasic : perms;
	FB.getLoginStatus(function(response) {
		if (response.authResponse) {
			callback(response);
		} else {
			FB.login(function(response) {
				if (response.authResponse) {
					callback(response);
				} else {
					alert("Oops. We can't connect you to Facebook. Please check back later");
				}
			}, {scope: perms});
		}
	});
}
