<div id="spch-container" class="tool">
	<div id="spch-head" class="tool_header">
		<div id="spch-options">
			<a href="#" class='button secondary' id="spch-skip"><?= wfMsg("spch-no"); ?></a>
			<a href="#" class="button primary spch-button-yes" id="spch-yes"><?= wfMsg('spch-yes'); ?></a>
		</div>
		<h1><?= wfMsg('spch-question'); ?></h1>
		<p id="spch-help"><?=wfMsg('spch-instructions') ?></p>
		<a href="#" id="spch-add-words"> Add all words to whitelist</a>
	</div>
	<div id='spch-error'>
		An error occurred while trying to get another article.
		Please try again later.
	</div>
	<div id='spch-preview'></div>
	<div id='spch-edit'>
		<div class='tool_box'>
			<div id='spch-content'></div>
		</div>
		<div id="spch-words" class='tool_box'>
			<p>Select the words you want to add to the whitelist.</p>
			<div class="spch-word-list">
				
			</div>
			<a href="#" class="button primary spch-add">Add words</a>
			<div class="spch-message"></div>
		</div>
		<div class='tool_box'>
			<div id='spch-summary'></div>
			<div id='spch-buttons'></div>
		</div>
	</div>
	<div id='spch-id'></div>
	<div class='spch-waiting'><img src='<?= wfGetPad('/extensions/wikihow/rotate.gif') ?>' alt='' /></div>
</div>
