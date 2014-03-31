<div class='wh_ad_inner adunit<?=$adId?>'>
	<script async src="http://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js"></script>
	<script type="text/javascript">
		<!--
		var adEligible = false;
		if(<?= ($adId === "intro"?"true":"false") ?> && fromsearch) {
			adEligible = true;
		}
		else if( <?= ($adId === "intro"?"true":"false") ?> && !fromsearch) {
			document.write('<d' + 'iv class="no_ad"></d' + 'iv>');
		}
		else {
			adEligible = true;
		}

		if(adEligible) {
			document.write('<i' + 'ns class="adsbygoogle" style="display:inline-block;width:<?= $params['width'] ?>px;height:<?= $params['height'] ?>px" data-ad-client="ca-pub-9543332082073187" data-ad-slot="<?= $params['slot'] ?>" data-font-size="large"> </i' + 'ns>');

			(adsbygoogle = window.adsbygoogle || []).push({
				params: {google_max_num_ads: <?= $params['max_ads'] ?>,
					google_override_format: true}
			});
		}
		//-->
	</script>
</div>
