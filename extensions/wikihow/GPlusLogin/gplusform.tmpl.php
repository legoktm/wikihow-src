<div id='gpl'>
	<div id='gpl_header'>
		<div><?= wfMsg('gplus-savetime') ?></div>
	</div>
	<div id='gpl_error'><?=$error?></div>
	<form method='POST' id='gpl_form' action='/Special:GPlusLogin'>
		<input name='proposed_username' class='gpl_readonly gpl_username' type='hidden' value='<?=$username?>'/>
		<input name='original_username' class='gpl_readonly gpl_username' type='hidden' value='<?=$origname?>'/>
		<input name='avatar_url' class='gpl_readonly gpl_username' type='hidden' value='<?=$avatar?>'/>
		<input name='gplus_id' class='gpl_readonly gpl_username' type='hidden' value='<?=$id?>'/>
		<table>
			<tr>
				<td class='gpl_label'><?= wfMsg('gplus-username') ?></td>
				<td>
<? if ($username) { ?>
					<div id='gpl_faux_username' class='gpl_readonly'>
						<div id='gpl_x'>X</div>
						<img class='gpl_user_avatar' src='<?=$avatar?>' />
						<div id='gpl_user_text'><div id='gpl_user_text_username'><?=$username?></div></div>
					</div> 
					<input type='text' name='requested_username' id='gpl_requested_username' style='display:none'/>
<? } else { ?>
					<input type='text' name='requested_username' id='gpl_requested_username'/>
<? } ?>
				</td>
			</tr>
			<tr>
				<td class='gpl_label'><?= wfMsg('gplus-email') ?></td>
				<td><input name='email' class='gpl_readonly' type='text' value='<?=$email?>' readonly='readonly'/></td>
			</tr>
			<!--tr>
				<td colspan="2" class='gpl_check'><label for='show_authorship'>Link your Google profile to the content you post. <input name='show_authorship' id='show_authorship' type='checkbox' checked='checked' /></td>
			</tr-->
			<tr>
				<td></td>
				<td><input type='submit' id='gpl_submit' class='gpl_submit' value='Register'/></td>
			</tr>
		</table>
	</form>
</div>
