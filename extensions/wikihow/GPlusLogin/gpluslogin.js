//chose not to use the default username 
$(document).on('click', '#gpl_x', function() {
	$('#gpl_faux_username').hide();
	$('#gpl_requested_username').show();
	$('input[name="email"]').removeAttr('readonly').removeClass('gpl_readonly');
});

$(document).on('click', '#gpl_prefilled', function() {
	$('#gpl_faux_username').show();
	$('#gpl_requested_username').hide();
	$('input[name="email"]').attr('readonly', 'readonly').addClass('gpl_readonly');
	$('#gpl_error').html('').hide();
});

$(document).on('submit', '#gpl_form', function(e) {
	var isFaux = $('#gpl_faux_username').is(':visible');
	if(!isFaux && !$('input[name="requested_username"]').val()) {
		e.preventDefault();
		$('#gpl_error').html('Please enter a wikiHow username').show();
		return false;
	}
	else {
		return true;
	}
});


