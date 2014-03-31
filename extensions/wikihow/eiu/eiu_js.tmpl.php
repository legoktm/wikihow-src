<script>
	var wgAjaxLicensePreview = false;
	//jQuery.noConflict();
</script>

<? if (@$includeCSSandJS): ?>
	<link rel="stylesheet" type="text/css" href="<?= wfGetPad('/extensions/min/f/extensions/wikihow/eiu/easyimageupload.css&') . WH_SITEREV ?>">
	<script type="text/javascript" src="<?= wfGetPad('/extensions/min/f/extensions/wikihow/eiu/easyimageupload.js,/extensions/wikihow/common/json2.js&') . WH_SITEREV ?>"></script>
<? endif; ?>

<!-- template html -->
<div id="eiu-dialog" title="<?= wfMsg('image-uploader') ?>">
	<div id="eiu-dialog-inner"></div>
</div>

<!-- lang stuff -->
<?php
	$langKeys = array('eiu-network-error', 'eiu-user-name-not-found-error', 'eiu-insert', 'eiu-preview', 'cancel', 'special-easyimageupload', 'added-image', 'next-page-link', 'prev-page-link');
	print Wikihow_i18n::genJSMsgs($langKeys);
?>

<script>
    // load google search API script after DOM is ready
    jQuery(function() {
        var scr = document.createElement('script');
        scr.async = true;
        scr.type = 'text/javascript';
        scr.src = "http://www.google.com/jsapi?key=<?= $GOOGLE_SEARCH_API_KEY ?>&callback=eiuLoadGoogleSearch";
        document.getElementsByTagName("head")[0].appendChild(scr);
    });
</script>

