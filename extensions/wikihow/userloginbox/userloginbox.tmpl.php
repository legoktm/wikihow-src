<form name="userlogin" class="userlogin" method="post" action="<?=$action_url?>">
	
	<h3><?= wfMessage('log_in_via')->text() ?></h3>
	<?=$social_buttons?>
		
	<div class="userlogin_inputs">
		<h3><?= wfMessage('login')->text() ?></h3>
		<input type='text' class='loginText input_med' name="wpName" id="wpName1<?=$suffix?>" value="Username" size='20' />
		<input type="hidden" id="wpName1_showhide<?=$suffix?>" /><br />
		
		<input type='password' class='loginPassword input_med' name="wpPassword" id="wpPassword1<?=$suffix?>" value="" size='20' />
		<input type="hidden" id="wpPassword1_showhide<?=$suffix?>" />
	</div>

	<input type='submit' class="button primary login_button" name="wpLoginattempt" id="wpLoginattempt" value="<?= wfMessage('login')->text() ?>" />

	<div class="userlogin_remember">
		<input type='checkbox' name="wpRemember" value="1" id="wpRemember<?=$suffix?>" checked="checked" /> 
		<label for="wpRemember<?=$suffix?>"><?= wfMessage('remember_me')->text() ?></label>
	</div>
	
	<div class="userlogin_links">
		<a href="/Special:LoginReminder" id="forgot_pwd<?=$suffix?>"><?= wfMessage('forgot_pwd')->text()?></a>
		<a href="<?php if(SSL_LOGIN_DOMAIN) print "https://" . SSL_LOGIN_DOMAIN; ?>/Special:Userlogin?type=signup"><?= wfMessage('nologinlink')->text()?></a>
	</div>
</form>
