(function($) {
$('#fl_button_save').live('click', function(e) {
	e.preventDefault();
	WH.FB.doFBLogin(function(response) {
		var token = response.authResponse.accessToken;
		$.get('/Special:FBLink', {a: 'link', token: token}, function() {
			window.location.reload();
		});
	});
});

$('.fl_button_cancel').live('click', function(e) {
	$('#dialog-box').dialog('close');
});
}(jQuery));
