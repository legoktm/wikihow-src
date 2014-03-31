<?=$ar_css?>
<style>
	#ar_outer {
		font-size: 14px;
		margin-top: 5px;
		margin-left: auto;
		margin-right: auto;
		width: 268px;
	}
	#ar_inner {
		padding: 5px;
		width: 110px;
		height: 25px;
		margin-left: auto;
		margin-right: auto;
	}
</style>
<?=$ar_js?>
<div id='ar_outer'>
		Help us improve wikiHow! Rate this article.
		<div id="ar_inner">
			<input name="ar_star" type="radio" class="ar_star required" value="1"/>
			<input name="ar_star" type="radio" class="ar_star" value="2"/>
			<input name="ar_star" type="radio" class="ar_star" value="3"/>
			<input name="ar_star" type="radio" class="ar_star" value="4"/>
			<input name="ar_star" type="radio" class="ar_star" value="5"/>
		</div>
</div>
<script type='text/javascript'>
	(function($) {
		var sent = false;
		$('input.ar_star').rating({
			callback: function(value, link) {
				if (!sent) {
					$.get('/Special:ArticleRating', {'rating':value, 'aid':wgArticleId });
					sent = true;
				}
			}
		});
	}(jQuery));

</script>
