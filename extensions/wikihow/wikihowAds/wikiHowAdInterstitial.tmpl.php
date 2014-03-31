<link rel="stylesheet" type="text/css" href="<?=wfGetPad('/extensions/wikihow/wikihowAds/interstitialStyle.css')?>" />

<div id="interstitialBackground" onclick="closeInterstitial();"></div>
<div id="interstitialAdUnit">
<div id="interstitialTitle">
    <center>Advertisement</center>
</div>
    <div id="interstitialLeft">
        <script type="text/javascript">
            var interstitialCookie=getCookie("adSenseInterstitial");
            
            // Test to see if cookies are enabled
            var cookiesEnabled = false;
            setCookie("testing", "testValue", 1);
            
            if (getCookie("testing") != null) {
                cookiesEnabled = true;
                setCookie("testing", "testValue", -1);
            }


            if (interstitialCookie=="adSenseInterstitialValue" || cookiesEnabled == false){
                closeInterstitial();
            }else{
				//cookie now set in quizzes.js
                //setCookie("adSenseInterstitial","adSenseInterstitialValue",1);
                google_ad_client = "ca-pub-9543332082073187";
                google_ad_slot = <?=$slot?>;
                google_ad_width = 336;
                google_ad_height = 280;
				google_ad_type = "image";
                google_ad_region = "adSenseInterstitial";
                document.write('<scr'+'ipt type="text/javascript" src="http://pagead2.googlesyndication.com/pagead/show_ads.js"></scr' +'ipt>');
            }
        </script>
    </div>
    <div id="interstitialRight">
        <div id="interstitialRightText" onclick="closeInterstitial();">
            <div id="closeAdText"><center>Close Ad</center></div><div id="closeAdX"><center>X</center></div>
        </div>
    </div>
</div>
<!--script>
	//moved to quizzes.js
    //window.setTimeout(closeInterstitial,30000);
</script-->