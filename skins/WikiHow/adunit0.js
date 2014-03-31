google_max_num_ads = '3';
if (gHideAds) {
	google_max_num_ads = '0';
}
	
function google_ad_request_done(google_ads) {
        var i;
        if (google_ads.length == 0) { return; }
      	s = '<div class="adunit adunitp0"><div id="adunit1"><p style="margin:0 0 5px 0; padding:0; font-size:1em;"><a href="' 
			+ google_info.feedback_url + '" style="color:#B0B0B0;">Ads by Google</a></p>'; 
      	for(i = 0; i < google_ads.length; ++i) {
			s += '<div class="ad1">'+ '<h4><a href="' + google_ads[i].url + '">' 
			+ google_ads[i].line1 + '</a></h4> ' 
            + google_ads[i].line2 + ' ' + google_ads[i].line3 + '<br />' +
            '<a href="' + google_ads[i].url + '">' + google_ads[i].visible_url + '</a></div>';
					
        }
		s += "</div></div>";
        document.write(s);
        return;
}

google_ad_channel = wh_custom_channel;
google_ad_client = "pub-9543332082073187";
google_ad_output = 'js';
google_ad_type = 'text';
google_feedback = 'on';
