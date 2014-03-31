<?=$css?>
<?=$js?>
<div>
	<label for="interests"><b class="whb"><?=$csui_search_label?></b></label>
	<div><input id="csui_interests"></input></div>
	<div class="csui_categories_outer">
		<b class="whb"><?=$csui_interests_label?></b> 
		<div class="csui_categories" id="categories">
			<?=$cats?>
			<div id="csui_none" class="<?=$nocats_hidden?>"><?=$csui_no_interests?></div>
		</div>
	</div>
	<div class="csui_suggestions_outer">
		<b class="whb csui_font_small"><?=$csui_suggested_label?></b> 
		<div class="csui_suggestions" id="suggestions"><?=$suggested_cats?></div>
	</div>
	<div class="csui_final_button"><a class="button" id="csui_close_popup">Done</a></div>
</div>
