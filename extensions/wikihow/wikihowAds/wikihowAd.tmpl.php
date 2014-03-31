<script type="text/javascript"><!--

if (!gHideAds) {
    if(<?= ($adId === "intro"?"true":"false") ?> && !fromsearch) {
		//This is the intro section, but not from search, so don't show
	}
    else {
		document.write('<div class="wh_ad_inner" id="wikihowad_<?= $adId ?>"></div>');

		WH.wikihowAds.addUnit('<?= $adId ?>');

		if(WH.wikihowAds.getAdsSet() == false) {
			 google_ad_channel = '<?= $channels ?>' + gchans;
			 google_ad_output = 'js';
			 google_ad_type = 'text';
			 google_feedback = 'on';
			 google_ad_region = "test";
			 //google_ad_format = '250x250_as';
			 google_max_num_ads = '11';
			 google_image_size = '';
			 google_ad_width = '';
			 google_ad_height = '';
			 google_ad_client = "pub-9543332082073187";
			 document.write('<script type="text/javascript" src="http://pagead2.googlesyndication.com/pagead/show_ads.js"></' + 'script>');
		
			 function google_ad_request_done(google_ads) {
				 WH.wikihowAds.setAds(google_ads);
			}
		}
	}
} 

//-->
</script>
