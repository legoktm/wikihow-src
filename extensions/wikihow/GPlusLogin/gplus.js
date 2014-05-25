// hack to fix gp all.js bug where they appempt to call console.  Console is not available for all browsers
if (typeof console == "undefined" || typeof console.log == "undefined") var console = { log: function() {} }; 

WH = WH || {};
WH.GP = WH.GP || {};
WH.GP.pressed = false;

WH.GP.init = function() {
	//add the gapi script
	(function () {
		var po = document.createElement('script');
		po.type = 'text/javascript'; po.async = true;
		po.src = 'https://apis.google.com/js/client:plusone.js?onload=renderGPlusButtons';
		var s = document.getElementsByTagName('script')[0];
		s.parentNode.insertBefore(po, s);
	} ());
	
	//set the click listener
	$('#gplus_connect, #gplus_connect_head').click(function() {
		WH.GP.pressed = true;
		return false;
	});
	//set disco listener
	$('#pb-gp-disco').click(function() {
		var confirm = false;
		
		$('<div></div>').appendTo('body')
		.html('If you disconnect your G+ account, you won\'t be able to reconnect it. We highly advise against doing this. Are you sure you want to continue?')
		.dialog({
			modal: true, 
			title: 'Are you sure you want to do this?', 
			zIndex: 10000, 
			autoOpen: true,
			width: 400, 
			resizable: false,
			closeText: 'Close',
			buttons: {
				Cancel: function () {
					confirm = false;
					$(this).dialog("close");
				},
				"Disconnect": function () {
					confirm = true;
					$(this).dialog("close");
				}
			},
			close: function (event, ui) {
				$(this).remove();
				if (confirm) {
					WH.GP.disconnectUser();
				}
			}
		});
		return false;
	});
}

WH.GP.doWikiHowLogin = function(authResponseToken) {

	var request = gapi.client.plus.people.get( {'userId' : 'me'} );
	request.execute( function(profile) {
		//grab email
		gapi.client.load('oauth2', 'v2', function() {
			var request2 = gapi.client.oauth2.userinfo.get();
			request2.execute(function(obj) {
				if (obj['email']) {
					email = obj['email'];
					
					var submit_form = $(document.createElement('form'));
					var callback_url;
					if(wgContentLanguage == 'en') { 
						callback_url = '/Special:GPlusLogin';
					}
					else {
						callback_url = '/Special:GPlusLogin';	
					}
					submit_form.attr('method','post')
							.attr('action',callback_url)
							.attr('action','/Special:GPlusLogin')
							.attr('enctype','multipart/form-data')
							.append($('<input name="user_id" value="'+profile.id+'"/>'))
							.append($('<input name="user_name" value="'+profile.displayName+'"/>'))
							.append($('<input name="user_email" value="'+email+'"/>'))
							.append($('<input name="user_avatar" value="'+profile.image.url+'"/>'))
							.appendTo('body')
							.submit();
				}
			});
		});
	});
	
}

function onSignInCallback(authResult) {
	if (WH.GP.pressed) {
		gapi.client.load('plus','v1', function(){		
			if (authResult['access_token']) {
				gapi.auth.setToken(authResult);
				WH.GP.doWikiHowLogin(authResult['access_token']);
			} else if (authResult['error']) {
			  // There was an error, which means the user is not signed in.
			  // As an example, you can handle by writing to the console:
				console.log('There was an error: ' + authResult['error']);
			}
			console.log('authResult', authResult);
		});
	}
}

function renderGPlusButtons() {
	//call to initiate the buttons
    gapi.signin.render('gplus_connect_head', {
      'callback': 'onSignInCallback',
      'clientid': '475770217963-cj49phca8tqki2ggs0ttcaerhs8339eh.apps.googleusercontent.com',
      'cookiepolicy': 'http://wikihow.com',
	  'apppackagename': 'com.wikihow.wikihowapp',
      'requestvisibleactions': 'http://schemas.google.com/AddActivity',
      'scope': 'https://www.googleapis.com/auth/plus.login https://www.googleapis.com/auth/userinfo.email'
    });
    gapi.signin.render('gplus_connect', {
      'callback': 'onSignInCallback',
      'clientid': '475770217963-cj49phca8tqki2ggs0ttcaerhs8339eh.apps.googleusercontent.com',
      'cookiepolicy': 'http://wikihow.com',
	  'apppackagename': 'com.wikihow.wikihowapp',
      'requestvisibleactions': 'http://schemas.google.com/AddActivity',
      'scope': 'https://www.googleapis.com/auth/plus.login https://www.googleapis.com/auth/userinfo.email'
    });
    gapi.signin.render('gplus_disco_link', {
      'clientid': '475770217963-cj49phca8tqki2ggs0ttcaerhs8339eh.apps.googleusercontent.com',
      'cookiepolicy': 'http://wikihow.com',
      'requestvisibleactions': 'http://schemas.google.com/AddActivity',
      'scope': 'https://www.googleapis.com/auth/plus.login https://www.googleapis.com/auth/userinfo.email'
    });
}

WH.GP.disconnectUser = function() {
	var authResult = gapi.auth.getToken();
	var access_token = authResult['access_token'];
	
	if (access_token) {
	  var revokeUrl = 'https://accounts.google.com/o/oauth2/revoke?token=' + access_token;
	  // Perform an asynchronous GET request.
	  $.ajax({
		type: 'GET',
		url: revokeUrl,
		async: false,
		contentType: "application/json",
		dataType: 'jsonp',
		success: function(nullResponse) {
		  // Do something now that user is disconnected
		  // The response is always undefined.
		  //alert('success');
		  location.href='/Special:GPlusLogin?disconnect=user';
		},
		error: function(e) {
		  // Handle the error
		  // console.log(e);
		  // You could point users to manually disconnect if unsuccessful
		  // https://plus.google.com/apps
		  alert('error: Go to https://plus.google.com/apps to manually disconnect account.');
		}
	  });
	}
	else {
		alert('Could not find token');
	}
}
