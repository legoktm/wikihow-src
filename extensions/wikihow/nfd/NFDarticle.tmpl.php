
<div id='nfd_options'></div>

<div id='nfd_head' class='tool_header'>
	<a href='#' class='button secondary' id='nfd_skip'><?= wfMsg('nfd_skip_article') ?></a>
	<a href='#' class='button secondary' id='nfd_delete'><?= wfMsg("nfd_button_delete"); ?></a>
	<a href='#' class='button secondary' id='nfd_keep'><?= wfMsg("nfd_button_keep"); ?></a>
	<a href='#' class='button primary' id='nfd_save'><?= wfMsg("nfd_button_save"); ?></a>

	<?= $articleInfo ?>
</div>
<input type='hidden' id='qcrule_choices' value='' />
<div id="article_tabs">
	<ul id="tabs">
		<li><a href="#" id="tab_article" title="Article" class="on" onmousedown="button_click(this);">Article</a></li>
		<li><a href="#" title="Edit" id="tab_edit"><?= wfMsg('edit'); ?></a></li>
		<li><span id="gatDiscussionTab"><a href="#" id="tab_discuss" title="<?= wfMsg('discuss') ?>" onmousedown="button_click(this);"><?= wfMsg('discuss') ?></a></span></li>
		<li><a href="#" id="tab_history"  title="<?= wfMsg('history') ?>" onmousedown="button_click(this);"><?= wfMsg('history') ?></a></li>
	</ul><!--end tabs-->
</div><!--end article_tabs-->
<div id="articleBody" class="nfd_tabs_content">
	<?= $articleHtml ?>
</div>
<div id="articleEdit" class="nfd_tabs_content"></div>
<div id="articleDiscussion" class="nfd_tabs_content"></div>
<div id="articleHistory" class="nfd_tabs_content"></div>
<input type='hidden' name='nfd_id' value='<?= $nfdId ?>'/>