var isGuided = false;

$(window).load(function() {
	$('#weave_button').click(function(e){
		e.preventDefault();
		PopIt(document.editform.wpTextbox1);
	});

	$('#easyimageupload_button').click(function(e){
		e.preventDefault();
		easyImageUpload.doEIUModal('advanced');
	});
});
