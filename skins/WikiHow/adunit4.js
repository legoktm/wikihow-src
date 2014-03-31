google_max_num_ads = '4';
if (gHideAds)
	google_max_num_ads = '0';
function google_ad_request_done(google_ads) {
        var i;
      	s = "<div id='showads' style='display: none;'><a href='javascript:showads()' style='float:right;'>Show Ads</a>"
			+ "<h3 style='margin-bottom:10px;'><a href='" + google_info.feedback_url + "' style='color: #443D37;'>Ads by Google</h3></div>";
        if (google_ads.length != 0) { 
	  		s += "<div id='adunit4'><div style='float: right;'><a href='javascript:hideads()'>Hide all ads</a> - <a href='/wikiHow:Why-Hide-Ads'>Why?</a></div>"
				+ "<h3 style='margin-bottom:10px;'><a href='" + google_info.feedback_url + "'  style='color: #443D37;'>Ads by Google</h3>";
	      	for(i = 0; i < google_ads.length; ++i) {
				if(i == google_ads.length - 1)
					s += '<div class="ad4 lastad">'+ '<h4><a href="' + google_ads[i].url + '" style="color: #01769F">' ;
				else
					s += '<div class="ad4">'+ '<h4><a href="' + google_ads[i].url + '" style="color: #01769F">' ;
				s += google_ads[i].line1 + '</a></h4> ' 
	            + google_ads[i].line2 + ' ' + google_ads[i].line3 + '<br />' +
	            '<a href="' + google_ads[i].url + '" style="color:#B0B0B0;">' + google_ads[i].visible_url + '</a></div>';
						
	        }
			s += "</div>";
		}
        document.write(s);
        return;
}

google_ad_channel = wh_custom_channel;
google_ad_client = "pub-9543332082073187";
google_ad_output = 'js';
google_ad_type = 'text';
google_feedback = 'on';
