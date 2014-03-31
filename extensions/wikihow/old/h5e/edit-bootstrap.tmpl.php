<script>

// This global is used in the WH.h5e module.  During html5 editing 
// initialization, the previously clicked button is retrieved (and clicked),
// then the live handler below is removed.
var whH5EClickedEditButton = null;

(function ($) {
	$('.edit_article_button, #tab_edit, .editsectionbutton, .editsection')
		.live('click', function() {
			whH5EClickedEditButton = this;
			return false;
		});
	
	// This needs to happen because Meebo adds that "drag to share" image-over
	// thing if not
	if (window.location.href.indexOf('create-new-article=true') >= 0) {
		gHideAds = true;
	}
})(jQuery);

</script>
