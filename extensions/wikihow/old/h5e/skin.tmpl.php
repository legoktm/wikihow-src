<script>
	var wgArticleExists = <?= $articleExists ? 'true' : 'false' ?>;
</script>
<link rel="stylesheet" href="/extensions/min/f/extensions/wikihow/h5e/skin.css,/extensions/wikihow/eiu/easyimageupload.css&rev=<?= WH_SITEREV ?>" />
<?
	$startScripts = "/extensions/min/f/extensions/wikihow/h5e/jquery.textNodes.js,/extensions/wikihow/common/jquery-ui-slider-dialog-custom/jquery.ui-1.8.13.sortable.min.js,/skins/common/ac.js,/extensions/wikihow/eiu/easyimageupload.js,/extensions/wikihow/common/json2.js";
	$jsModules = array(
		'browser.js', 'cursor.js', 'drafts.js', 'images.js', 
		'inline-links.js', 'key-input.js', 'new-articles.js', 
		'references.js', 'related-wikihows.js', 'sections.js', 
		'toolbar.js', 'utilities.js', 'html5editor.js'
	);

	if (!H5E_DEBUG) { // for production
		foreach ($jsModules as &$js) {
			$js = '/extensions/wikihow/h5e/' . $js;
		}
		print '<script type="text/javascript" src="' . $startScripts . ',' . join(',', $jsModules) . '&rev=' . WH_SITEREV . '"></script>';
	} else { // debugging
		print '<script type="text/javascript" src="' . $startScripts . '&rev=' . WH_SITEREV . '"></script>';
		foreach ($jsModules as &$js) {
			print '<script type="text/javascript" src="/extensions/wikihow/h5e/' . $js . '"></script>' . "\n";
		}
	}

	$langKeys = array('new-link','howto','h5e-ref','h5e-new-section','h5e-new-alternate-method','h5e-new-method','h5e-references-removed','h5e-references-removed','h5e-loading','h5e-more-results','Ingredients','Steps','Video','Tips','Warnings','relatedwikihows','sourcescitations','thingsyoullneed', 'h5e-edit-summary-examples', 'h5e-changes-to-be-discarded', 'import-content', 'import-content-url', 'h5e-add-reference', 'h5e-edit-reference', 'h5e-add', 'h5e-search', 'h5e-edit-link', 'h5e-edit-link-external', 'h5e-add-link', 'h5e-done', 'h5e-change', 'h5e-edit-ref', 'h5e-hidden-template', 'h5e-hidden-video', 'h5e-rel-wh-edit', 'h5e-enter-edit-summary', 'h5e-first-step', 'h5e-external-link-editing-disabled', 'h5e-external-links-warning', 'h5e-remove-section', 'warning', 'congrats-article-published', 'h5e-switch-advanced', 'h5e-publish-timeout', 'h5e-error', 'h5e-editing-title', 'h5e-creating-title', 'h5e-create-new-article', 'h5e-server-connection-error', 'h5e-savedraft', 'h5e-loaddraft', 'h5e-draftsaved', 'h5e-saving', 'h5e-saving-lc', 'h5e-saving-draft', 'h5e-new-warning', 'h5e-new-tip', 'h5e-new-source', 'h5e-no-cursor-error', 'h5e-canceling-edit', 'h5e-loading-advanced-editor', 'h5e-loading-draft');
	echo Wikihow_i18n::genJSMsgs($langKeys);
?>

<div class="h5e-edit-link-options-over rounded_corners">
	<a href="#" id="h5e-editlink-cancel" title="<?= wfMsg('h5e-cancel') ?>" class="h5e-x"></a>
	<span class="h5e-edit-link-inner">
		<?= wfMSg('h5e-goto-link') ?>
		<a id="h5e-editlink-display" href="#"></a><br />
		<a id="h5e-editlink-change" href="#"><?= wfMsg('h5e-change') ?></a> - 
		<a id="h5e-editlink-remove" href="#"><?= wfMsg('h5e-remove') ?></a>
	</span>
</div>

<div id="h5e-link-dialog" class="h5e-dialog" title="">
	<p><?= wfMsg('h5e-text') ?><br /><input id="h5e-link-text" autocomplete="off" /></p>
	<p style="float:right;"><a id="h5e-link-preview" target="_blank" href="#"><?= wfMsg('view-article') ?></a></p>
	<form id="h5e-ac-link" name="h5e-ac-link">
	<p><?= wfMsg('h5e-article') ?></p>
	<p class="h5e-howto-input">
		<span class="h5e-link-how-to-link"><?= wfMsg('howto', '') ?></span>
		<input id="h5e-link-article" autocomplete="off" />
		<div class="h5e-external-link-editing-disabled"><span><?= wfMsg('h5e-external-link-editing-disabled') ?></span></div>
	</p>
	</form>
	<div class="h5e-bottom-buttons">
		<div class="h5e-link-external-help">
			<a href="#"><img src="<?= wfGetPad('/skins/WikiHow/images/icon_help.jpg') ?>"/></a>
			<a class="h5e-external-link-why" href="#"><?= wfMsg('h5e-how-to-add-external-link') ?></a>
		</div>
		<a id="h5e-link-cancel" href="#"><?= wfMsg('h5e-cancel') ?></a>
		<input class="h5e-button button64 h5e-input-button" id="h5e-link-change" value="<?= wfMsg('h5e-done') ?>" contenteditable="false" />
	</div>
</div>

<div id="h5e-external-url-msg-dialog" title="<?= wfMsg('h5e-external-links') ?>">
	<div>
		<?= wfMsg('h5e-external-link-disallowed') ?>
	</div>
	<div class="h5e-bottom-buttons">
		<input class="h5e-button button64 h5e-input-button" id="h5e-link-change" value="<?= wfMsg('h5e-ok') ?>" contenteditable="false" />
	</div>
</div>

<div id="h5e-sections-dialog" class="h5e-dialog" title="<?= wfMsg('h5e-sections') ?>">
	<div id="h5e-sections">
	</div>
	<br/>
	<div class="h5e-bottom-buttons">
		<a id="h5e-sections-cancel" href="#"><?= wfMsg('h5e-cancel') ?></a>
		<input class="h5e-button button64 h5e-input-button" id="h5e-sections-change" value="<?= wfMsg('h5e-change') ?>" contenteditable="false" />
	</div>
</div>

<div id="h5e-am-dialog" class="h5e-dialog" title="<?= wfMsg('h5e-new-method') ?>">
	<p><input id="h5e-am-name" class="h5e-input" type="text" autocomplete="off" size="25" /><br /></p>
	<br/>
	<div class="h5e-bottom-buttons">
		<a id="h5e-am-cancel" href="#"><?= wfMsg('h5e-cancel') ?></a>
		<input class="h5e-button button64 h5e-input-button" id="h5e-am-add" value="<?= wfMsg('h5e-add') ?>" contenteditable="false" />
	</div>
</div>

<div id="h5e-editing-toolbar">
	<div class="h5e-tb-function-wrapper">
		<div class="h5e-tb-left-edge"></div>
		<div class="h5e-tb-left-wrapper">
			<a id="h5e-toolbar-img" class="h5e-button h5e-button-img" title="<?= wfMsg('h5e-add-image') ?>" href=""><?= wfMsg('h5e-image') ?></a>
			<a id="h5e-toolbar-a" class="h5e-button h5e-button-a" title="<?= wfMsg('h5e-add-link') ?>" href=""><?= wfMsg('h5e-link') ?></a>

			<img src="<?= wfGetPad('/skins/WikiHow/images/separator.gif') ?>" class="h5e-separator" />
			
			<a id="h5e-toolbar-italics" class="h5e-button h5e-button-italics" title="<?= wfMsg('h5e-italics') ?>" href=""></a>
			<a id="h5e-toolbar-indent" class="h5e-button h5e-button-indent" title="<?= wfMsg('h5e-add-bullet') ?>" href=""></a>
			<a id="h5e-toolbar-outdent" class="h5e-button h5e-button-outdent h5e-disabled" title="<?= wfMsg('h5e-remove-bullet') ?>" href=""></a>

			<img src="<?= wfGetPad('/skins/WikiHow/images/separator.gif') ?>" class="h5e-separator" />
			
			<a id="h5e-toolbar-ref" class="h5e-button h5e-button-ref" title="<?= wfMsg('h5e-ref') ?>" href=""></a>
			<a id="h5e-toolbar-section" class="h5e-button h5e-button-section" title="<?= wfMsg('h5e-sections') ?>" href=""></a>
			<a id="h5e-toolbar-related" class="h5e-button h5e-button-related" title="<?= wfMsg('h5e-edit-related') ?>" href=""></a>

			<img src="<?= wfGetPad('/skins/WikiHow/images/separator.gif') ?>" class="h5e-separator" />

			<a id="h5e-discard-changes" class="h5e-button h5e-discard-changes h5e-toolbar-cancel" title="<?= wfMsg('h5e-discard-changes') ?>" href=""></a>

			<img src="<?= wfGetPad('/skins/WikiHow/images/separator.gif') ?>" class="h5e-separator" />

			<input type="text" id="h5e-edit-summary-pre" class="h5e-input h5e-example-text" value="" />
			<a id="h5e-toolbar-publish" class="h5e-button button106" href=""><?= wfMsg('h5e-publish') ?></a>
			<span id="h5e-toolbar-savedraft"></span>
			<? //<a id="h5e-toolbar-loaddraft" class="h5e-button button-gray-73" href="#"><?= wfMsg('h5e-loaddraft')? ></a>?>
		</div>
		<div class="h5e-tb-right-edge"></div>
		<div class="h5e-tb-right-wrapper">
			<a href="#" title="<?= wfMsg('h5e-cancel') ?>" class="h5e-x h5e-toolbar-cancel"></a>
			<a href="#" class="h5e-toolbar-cancel"><?= wfMsg('h5e-cancel') ?></a>
		</div>
	</div>
	<div class="h5e-tb-save-wrapper">
		<div class="h5e-tb-left-edge"></div>
		<div class="h5e-tb-left-wrapper">
			<div class="h5e-describe-edits"><?= wfMsg('h5e-describe-edits') ?></div>
			<div>
				<input type="text" id="h5e-edit-summary-post" class="h5e-input h5e-example-text" size="70" />
				<a id="h5e-edit-summary-save" class="h5e-button button106" href=""><?= wfMsg('h5e-save') ?></a>
			</div>
		</div>
		<div class="h5e-tb-right-edge"></div>
		<div class="h5e-tb-right-wrapper">
			<a href="#" title="<?= wfMsg('h5e-close') ?>" class="h5e-x h5e-toolbar-cancel"></a>
			<a href="#" class="h5e-toolbar-cancel"><?= wfMsg('h5e-close') ?></a>
		</div>
	</div>
</div>

<div class="h5e-saving-notice">
	<img src="<?= wfGetPad('/extensions/wikihow/rotate_white.gif') ?>"/><br/>
	<br/>
	<span class="saving-message"><?= wfMsg('h5e-saving') ?></span>
</div>

<div id="h5e-mwimg-mouseover">
	<div id="h5e-mwimg-mouseover-bg"></div>
	<ul id="h5e-mwimg-mouseover-confirm">
		<li class="h5e-mwimg-mouseover-confirm_top"></li>
		<li class="h5e-mwimg-mouseover-confirm-main"><?= wfMsg('h5e-confirm-delete-image') ?></li>
		<li class="h5e-mwimg-mouseover-confirm-main"><span class="h5e-mwimg-confirm-yes"><?= wfMsg('h5e-yes') ?></span> | <span class="h5e-mwimg-confirm-no"><?= wfMsg('h5e-no') ?></span></li>
		<li class="h5e-mwimg-mouseover-confirm_bottom"></li>
	</ul>
	<a class="edit-remove-image" title="<?= wfMsg('h5e-remove-image') ?>" href=""><?= wfMsg('h5e-remove') ?></a>
</div>

<div id="edit-ref-dialog" class="h5e-dialog" title="<?= wfMsg('h5e-edit-reference') ?>">
	<p><?= wfMsg('h5e-add-reference-text') ?><br />
	<input class="h5e-input" id="ref-edit" type="text" size="50" autocomplete="off" /></p>
	<div class="h5e-bottom-buttons">
		<a id="ref-edit-cancel" href="#"><?= wfMsg('h5e-cancel') ?></a>
		<input class="h5e-button button64 h5e-input-button" id="ref-edit-change" value="<?= wfMsg('h5e-change') ?>" contenteditable="false" />
	</div>
</div>

<div id="related-wh-dialog" class="h5e-dialog" title="<?= wfMsg('h5e-edit-related') ?>">
	<p class="h5e-related-p"><?= wfMsg('h5e-add-related-text') ?>:</p><br />
	<form id="h5e-ac" name="h5e-ac">
		<p class="h5e-link-how-to-related"><?= wfMsg('howto', '') ?></p>
		<input class="h5e-input" id="h5e-related-new" name="h5e-related-new" autocomplete="off" maxLength="256" value=""/> 
		<input class="h5e-button button-gray-73 h5e-input-button" id="h5e-related-add" value="<?= wfMsg('h5e-add') ?>" contenteditable="false" /><br/>
	</form>
	<br/>
	<div class="h5e-related-list">
		<ul class="h5e-related-sortable">
			<?php // list items go here ?>
		</ul>
	</div>
	<br/>
	<div class="h5e-bottom-buttons">
		<a id="h5e-related-cancel" href="#"><?= wfMsg('h5e-cancel') ?></a>
		<input class="h5e-button button64 h5e-input-button" id="h5e-related-done" value="<?= wfMsg('h5e-done') ?>" contenteditable="false" />
	</div>
	<br/>
</div>

<div class="related-wh-overlay">
</div>

<div class="related-wh-overlay-edit">
	<button id='related-wh-button'><?= wfMsg('h5e-change') ?></button>
</div>

<div id="h5e-sections-confirm" class="h5e-dialog" title="<?= wfMsg('h5e-remove-section-confirm') ?>">
	<p><?= wfMsg('h5e-remove-confirm-desc') ?></p>
	<p>&nbsp;</p>
	<p class="h5e-remove-confirm-help"><?= wfMsg('h5e-remove-confirm-help', '<span class="h5e-button-section-icon-only"></span>') ?></p>
	<br/>
	<div class="h5e-bottom-buttons">
		<input class="h5e-button button64 h5e-input-button" id="h5e-sections-confirm-remove" value="<?= wfMsg('h5e-remove') ?>" contenteditable="false" />
		<a id="h5e-sections-confirm-cancel" href="#"><?= wfMsg('h5e-cancel') ?></a>
	</div>
</div>

<div id="h5e-loaddraft-confirm" class="h5e-dialog" title="<?= wfMsg('h5e-remove-section-confirm') ?>">
	<p><?= wfMsg('h5e-loaddraft-confirm-desc') ?></p>
	<p>&nbsp;</p>
	<p class="h5e-loaddraft-confirm-help"><?= wfMsg('h5e-loaddraft-confirm-help') ?></p>
	<br/>
	<div class="h5e-bottom-buttons">
		<input class="h5e-button button64 h5e-input-button" id="h5e-loaddraft-confirm-load" value="<?= wfMsg('h5e-load') ?>" contenteditable="false" />
		<a id="h5e-loaddraft-confirm-cancel" href="#"><?= wfMsg('h5e-cancel') ?></a>
	</div>
</div>

<div class="h5e-inline-publish-template">
	<div class="h5e-inline-publish">
		<div class="h5e-publish-finished-editing">
			<?= wfMsg('h5e-finished-editing') ?>
		</div>
		<div class="h5e-publish-action">
			<div class="h5e-publish-publish"></div>
			<div class="h5e-publish-save-draft"></div>
			<a class="h5e-publish-cancel" href="#"><?= wfMsg('h5e-cancel') ?></a>
		</div>
	</div>
</div>

<div class="h5e-error-dialog" title="<?= wfMsg('h5e-error') ?>">
	<div class="error-msg"></div>
	<div class="h5e-bottom-buttons">
		<input class="h5e-error-confirm h5e-button button64 h5e-input-button" value="<?= wfMsg('h5e-ok') ?>" contenteditable="false" />
	</div>
</div>

<div id="h5e-message-console"></div>
<div class="h5e-rs-console"><br><br><br></div>

