<h3><div class='altblock'></div><?=wfMsg('createpage_new_head')?></h3><br />
<div class='wh_block'>
	<?=wfMsg('createpage_new_details')?>
	<form id='gatCreateForm' method='GET' onsubmit='return checkform()' name='createform'>
	<?=wfMsg('howto','')?> <input autocomplete='off' maxLength='256' size='49' name='target' value='' class='search_input' type='text' />
	<input type='submit' value='<?= wfMsg('submit') ?>' class='button primary createpage_button' />
	</form>
</div>

<?php global $wgLanguageCode; if($wgLanguageCode == 'en') { ?>
<h3><div class='altblock'></div><?=wfMsg('createpage_topic_sugg_head')?></h3><br />
<div class='wh_block'>
	<?=wfMsg('createpage_topic_sugg_details')?>
	<form id='gatCreateFormTopics' method='POST' onsubmit='return checkform()' name='createform_topics'>
	<input type='text' name='q' size='50' class="search_input" />
	<input type='submit' value='Submit' class='button primary createpage_button' />
	</form>
</div>
<?php } ?>
<h3><div class='altblock'></div><?=wfMsg('createpage_other_head')?></h3><br />
<div class='wh_block'>
	<?=wfMsg('createpage_other_details')?>
</div>
