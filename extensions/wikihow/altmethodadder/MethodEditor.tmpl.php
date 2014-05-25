<div id="method" class="tool">
	<div id="method_header" class="tool_header">
		<a href="#" id="method_keys">Get Shortcuts</a>
		<?php if($showList): ?>
			<a href="/Special:MethodEditor?allarticles=true" class="button secondary" target="_blank" id="method_list">Get all titles</a>
		<?php endif; ?>
		<h1>Improve and publish this new method,<br /> or delete it.</h1>
        <div class='altmethod_bubble_outer'>
            <div class='altmethod_bubble'><a class='altmethod_x'>x</a><div class='altmethod_txt'>Before publishing this alternate method, edit it to make it helpful, clear, and grammatically correct.</div></div>
        </div>
		<a href="#" id="method_skip" class="button secondary">Skip</a><a href="#" id="method_keep" class="button primary">Publish</a> <a href="#" id="method_delete" class="button secondary">Delete</a><div id="method_edit"></div>
		<div class="clearall"></div>
		<div id="method_method_container">
			<div id="method_icon_container"><h3 id="method_icon" class="altblock"><span>&nbsp;&nbsp;&nbsp;</span></h3></div>
			<input id="method_method" class="tool_input" />
		</div>
		<textarea id="method_steps"></textarea> <br />
	</div>
	<div id='method_editor' class="tool_header">
		<div id='article_contents'></div>
	</div>
	<div id='method_waiting'><img src='<?= wfGetPad('/extensions/wikihow/rotate.gif') ?>' alt='' /></div>
	<div id="method_article"></div>
</div>
<div id="method_error" style="display:none;" class='tool_header'>
	There are no additional alternate methods to approve at this time. Please check again later. In the interim, please visit our <a href="/Special:CommunityDashboard">community dashboard</a> to find another way to help out.
</div>
<div id="method_info" style="display:none;">
    <?= wfMessage('methodeditor_keys')->text(); ?>
</div>
