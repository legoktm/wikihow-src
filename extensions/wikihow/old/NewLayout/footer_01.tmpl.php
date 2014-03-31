<br class="clearall" />

<div id="footer_shell">
    <div id="footer">

        <div id="footer_side">
			<div class="footer_logo footer_sprite"></div>
			 <p id="footer_tag"><?=wfMsg('main_logo_title')?></p>
			 <?=$footer_links?>
			        </div><!--end footer_side-->

        <div id="footer_main">
			<?=$search?>
				
			<h3><?= wfMsg('explore_categories') ?></h3>
			<?=$cat_list?>

	    	<div id="sub_footer">
			<?=$sub_foot?>
			</div>

        </div><!--end footer_main-->
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
		});
	})(jQuery);
</script>

</html>

