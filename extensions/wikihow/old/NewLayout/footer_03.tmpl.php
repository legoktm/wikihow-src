<br class="clearall" />

<div id="footer_shell">
    <div id="footer">

		<div id="sub_footer">
		<!-- MediaWiki:Sub_footer_new_anon -->
			<div id="creative_commons">
				<a class="imglink sub_footer_link footer_creative_commons footer_sprite" href="/wikiHow:Creative-Commons"></a>
				<p style="clear:left;">All text shared under a <a href="http://www.wikihow.com/wikiHow:Creative-Commons">Creative Commons License</a></p>.
			</div>

			<div id="mediawiki_p">
				<a class="imglink sub_footer_link footer_mediawiki footer_sprite" href="/Powered-and-Inspired-by-Mediawiki"></a>
				<p style="clear:left;"><a href="/Powered-and-Inspired-by-Mediawiki">Powered by Mediawiki.</a></p>
			</div>			
		</div>
		<div id="footer_side">
			<img src="<?= wfGetPad('/skins/WikiHow/images/redesign/logo_footer.png') ?>" />
			 <?=$footer_links?>
			<div id="footer_main">
				<form action="/Special:GoogSearch" id="cse-search-box-footer">
				  <div>
					<input type="hidden" name="cx" value="008953293426798287586:mr-gwotjmbs" />
					<input type="hidden" name="cof" value="FORID:10" />
					<input type="hidden" name="ie" value="UTF-8" />
					<input type="text" id="cse_q" name="q" size="30" value="" class="search_box" />
					<input type="submit" id="cse_sa" value="Search" class="search_button" onclick='gatTrack("Search","Search","Custom_search");'/>
				  </div>
				</form>

				<script type="text/javascript">
				loadGoogleCSESearchBox('en');
				</script>

				<br />
			</div><!--end footer_main-->
		</div><!--end footer_side-->
        <br class="clearall" />
    </div><!--end footer-->
</div><!--end footer_shell-->
<div id="dialog-box" title=""></div>
<div id="fb-root" ></div>
<?=$footertail?>
</body>
<script type="text/javascript">
	(function ($) {
		// fired on DOM ready event
		$(document).ready(function() {
			WH.addScrollEffectToTOC();
		});

		$(window).load(function() {
			if ($('.twitter-share-button').length) {

				// Load twitter script
				$.getScript("http://platform.twitter.com/widgets.js", function() {
					twttr.events.bind('tweet', function(event) {
						if (event) {
							var targetUrl;
							if (event.target && event.target.nodeName == 'IFRAME') {
							  targetUrl = extractParamFromUri(event.target.src, 'url');
							}
							_gaq.push(['_trackSocial', 'twitter', 'tweet', targetUrl]);
						}
					});

				});
			}

			if ($('#fb_sidebar_content').length) {
				$('#fb_sidebar_content').html('<iframe src="http://www.facebook.com/plugins/like.php?href=http%3A%2F%2Fwww.facebook.com%2Fwikihow&amp;layout=standard&amp;show_faces=false&amp;width=215&amp;action=like&amp;colorscheme=light&amp;height=35" scrolling="no" frameborder="0" style="border:none; overflow:hidden; width:215px; height:40px;" allowTransparency="true"></iframe>');
			}
			if( isiPhone < 0 && isiPad < 0 && $('.gplus1_button').length){
				WH.setGooglePlusOneLangCode();
				var node2 = document.createElement('script');
				node2.type = 'text/javascript';
				node2.src = 'http://apis.google.com/js/plusone.js';
				$('body').append(node2);
			}
			// Init Facebook components
			WH.FB.init('new');
			
			if($('#pinterest').length) {
				var node3 = document.createElement('script');
				node3.type = 'text/javascript';
				node3.src = 'http://assets.pinterest.com/js/pinit.js';
				$('body').append(node3);
			}
		});
		
		$(window).scroll(function () {
			$("#rolling_logo").css({ opacity: $(window).scrollTop()/32 - 1 });
			/*if($(window).scrollTop() > 64)
				$("#rolling_logo").show();
			else
				$("#rolling_logo").hide();*/

		});
		
		$(".check").click(function(){
			$(this).find("div").show();
		});

	})(jQuery);
</script>

</html>

