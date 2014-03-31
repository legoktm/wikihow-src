<?=$ar_css?>
<style>
	#ar_followup {
		display: none;
		font-size: 1.2em;
		margin: 0px 0 5px 100px;
		width: 360px;
	}

	#ar_inner {
		width: 150px;
		height: 25px;
		margin-left: 130px;
		margin-right: auto;
	}

	.ar_button {
		display: inline-block;
		color: rgb(1, 142, 171); 
		background-position: 0% 0px;
		font-size: 11px;
	}

	.ar_button:hover {
		background-position: 0% -26px;
	}

	#ar_comment {
		width: 300px;
		border: 1px solid #A59983;
		height: 65px;
	}

	#ar_email {
		width: 150px;
		border: 1px solid #A59983;
		height: 17px;
	}

	#ar_ok {
		margin: 5px -10px 0 0;
	}
</style>
<?=$ar_js?>
<div id="ar_inner">
	<a href="#" id="ar_yes" class="button white_button ar_button">Yes</a> <a href="#" id="ar_no" class="button white_button ar_button">No</a>
</div>
<div id='ar_followup'>
	<div style='margin-top: -3px'> <label for ='ar_comment'>What else should be in the article?</label><textarea name='ar_comment'  id='ar_comment'></textarea></div>
	<div style='margin-top: 5px'>
		<span style='float:right'><a href="#" id="ar_ok" class="button white_button ar_button">OK</a></span>
		<label for='ar_email'>Email me here when it's added (optional)</label> <input type='text' name='ar_email' id='ar_email'></input>
	</div>
</div>
<script type='text/javascript'>
	(function($) {
		var followupPrompt = function() {
			$('#ar_inner,div.tta_text_05').hide();
			$('#ar_followup').show();
			$('#slider_thanks_02').animate({'height': '140px'}, 'fast', function() {
			});
		};
		
		var hidePrompt = function() {
			slider.closeSlider();
			slider.test_on = false;
			$.get('/Special:ArticleRating', {'rating': 1, 'aid': wgArticleId});
		};

		$('#ar_followup').on('click', '#ar_ok', function(e) {
			e.preventDefault();
			if ($('#ar_comment').val().length) {
				var params = {
					'rating': 0,
					'aid': wgArticleId,
					'comment': $('#ar_comment').val(),
					'email': $('#ar_email').val()
				};

				$.get('/Special:ArticleRating', params);
				slider.closeSlider();
				slider.test_on = false;
			}
		});

		$('#ar_inner').on('click', '#ar_yes', function(e) {
			e.preventDefault();
			hidePrompt();
		});

		$('#ar_inner').on('click', '#ar_no', function(e) {
			e.preventDefault();
			followupPrompt();
		});
	}(jQuery));
</script>
