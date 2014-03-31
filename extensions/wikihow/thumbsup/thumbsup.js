$('.thumbbutton:not(.clickbound)').addClass('clickbound').on('click',  function (c) {
	c.preventDefault();
	// only allow one click
	$(this).unbind('click');

	var thumbsurl = $(this).siblings('#thumbUp').html().replace(/\&amp;/g,'&');

	$('#thumbsup-status').html(wfMsg('rcpatrol_thumb_msg_pending')).fadeIn('slow', function(n){});
	$.get(thumbsurl, {}, function(html){
		$('#thumbsup-status').css('background-color','#CFC').html(wfMsg('rcpatrol_thumb_msg_complete'));
		$(c.target).addClass("clicked");
	});
});

