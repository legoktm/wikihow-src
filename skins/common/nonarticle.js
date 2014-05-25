/*
 * On certain tools we allow anonymous users,
 * but we also want to prompt the user to create an account
 * every so often.
 */
function checkAnonPrompt(cookiename,prompt_interval,returnto) {
	//is this an anon user?
	if (wgUserName == null) {
		if (!cookiename || !prompt_interval) return;
		
		var count = parseInt(getCookie(cookiename));
		if (isNaN(count)) count = 0;
		setCookie(cookiename,++count,30);
		
		//are we prompting a login?
		if (count > 0 && (count % prompt_interval) == 0) {
			//Zoidberg says, "How about logging in maybe?"
			showLoginPrompt(returnto);
		}
	}
}

/*
 * login box modal dialog
 */
function showLoginPrompt(returnto) {
	if (returnto != '' && returnto != 'undefined') returnto = '?returnto='+returnto;
	var url = '/Special:UserLoginBox'+returnto;
	
	$('#dialog-box').load(url, function() {
		$('#dialog-box').dialog( {
			modal: true,
			width: 450,
			title: "wikiHow is better when you Log in!",
			closeText: 'Close'
		});
		
		//kickstart the necessary js
		initTopMenu();
		WH.GP.init();
	});
}