<script type="text/javascript">
<!--
$('.cp_search_input').keypress(function (evt) {
  if (evt.which == 13) {
	 $('.cp_search_articles').click();
	 return false;
  }
});
//-->
</script>

<form action='/Special:CreatePage'  method='POST'>
<div class="wh_block">
	<input type='hidden' name='create_redirects' value='1'/>
	<h3>Enter Article Title:</h3>
	<div>
		<br />
		<?=wfMsg('howto','')?> <input type='text' id='createpage_title' value="<?=$step1_title?>" name='createpage_title' class='search_input' />
		<input type='button' value='Search Again' onclick='document.getElementById("cp_next").disabled = true; searchTopics();' class='button createpage_button secondary' />
	</div>

	<div id='createpage_search_results'>
		<?=$related_block?>
	</div>

	<div id="createpage_buttons">
		<input type='submit' value='Next' id='cp_next' class='button primary' />
		<input type='button' onclick='window.location="/Special:CreatePage";' value='Cancel' class='button secondary' />
	</div>
	<br class="clearall" />
</div>
</form>
