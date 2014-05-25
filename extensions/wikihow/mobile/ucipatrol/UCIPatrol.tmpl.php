<div id="uci" class="tool">
	<div id="uci_header" class="tool_header">
		<a href="#" id="uci_keys">Get Shortcuts</a>
		<h1>Would seeing this image help the reader of this article?</h1>
		<div id="uci_img_wrap">
			<img id="uci_img"></img>
		</div>
		<div id="uci_patrol_buttons" class="uci_buttons">
			<a href="#" id="uci_resetskip" title="resetskip" class="button secondary" tabindex="2">ResetSkip</a>
			<a href="#" id="uci_errortest" title="errortest" class="button secondary" tabindex="2">ErrorTest</a>
			<a href="#" id="uci_bad" title="bad" class="button secondary uci_button_right" tabindex="3">No</a>
			<a href="#" id="uci_skip" title="skip" class="button primary uci_button uci_button_right" tabindex="4">I'm Not Sure</a>
			<a href="#" id="uci_good" title="good" class="button secondary uci_button_right" tabindex="5">Yes</a>
		</div>
		<div id="uci_votecomplete">
			<div id="uci_complete_buttons" class="uci_buttons">
				<a href="#" id="uci_confirm" title="confirm" class="button primary uci_button uci_button_right">Next</a>
				<a href="#" id="uci_undo" title="undo" class="button secondary uci_button uci_button_left">Undo</a>
			</div>
			<div class="clearall"></div>
			<div id="uci_voters">
				<h2 id="uci_complete_message"></h2>
				<div class="clearall"></div>
				<div id="uci_complete_sub"></div>
				<div class="clearall"></div>
				<div id="uci_voter_wrapper">
					<div id="user_voter" class="uci_voter">
						<div class="clearall"></div>
						<div id="uci_user_vote"></div>
					</div>
				</div>
			</div>
		</div>
		<div class="clearall"></div>
		<div id='uci_waiting'>
			<div id='uci_waiting_spinner'>
				<img src='<?= wfGetPad('/extensions/wikihow/rotate.gif') ?>' alt='' />
			</div>
		</div>
	</div>
	<div id="uci_article"></div>
</div>
<div id="uci_error" style="display:none;">
	There are no images to approve. Please check again later. In the interim, please visit our <a href="/Special:CommunityDashboard">community dashboard</a> to find another way to help out.
</div>
<div id="uci_info" style="display:none;">
    <?= wfMessage('ucipatrol_keys')->text(); ?>
</div>
