<div class='nfd_modal'>
	<p><?= wfMsg('nfd_conf_question', $titleUrl, $title); ?> </p>
	<div style='clear:both'></div>
	<span style='float:right'>
		<input class='button' type='button' value='No' onclick='closeConfirmation(false);return false;' >
		<input class='button primary' type='button' value='Yes' onclick='closeConfirmation(true);return false;' >
	</span>
</div>