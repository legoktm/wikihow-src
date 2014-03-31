$('#fbc_x').live('click', function() {
	$('#fbc_faux_username').hide();
	$('#fbc_requested_username').css('display', 'inline-block').css('visibility', 'visible');
	$('input[name="email"]').removeAttr('readonly').removeClass('fbc_readonly');

	$('#fbc_header_default').hide();
	$('#fbc_header_prefill').css('display', 'inline-block').css('visibility', 'visible');
});

$('#fbc_prefilled').live('click', function() {
	$('#fbc_faux_username').show();
	$('#fbc_requested_username').hide();
	$('input[name="email"]').attr('readonly', 'readonly').addClass('fbc_readonly');
	$('#fbc_error').html('');

	$('#fbc_header_prefill').hide();
	$('#fbc_header_default').css('display', 'inline-block').css('visibility', 'visible');
});


$('#fbc_form').live('submit', function(e) {
	var isFaux = $('#fbc_faux_username').is(':visible');
	if(!isFaux && !$('input[name="requested_username"]').val()) {
		e.preventDefault();
		$('#fbc_error').html('Please enter a wikiHow username');
		return false;
	}
	else {
		return true;
	}
});
