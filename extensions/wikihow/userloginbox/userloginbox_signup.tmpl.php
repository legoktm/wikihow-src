<div name="userlogin" class="userlogin">
	
	<h3><?= wfMessage('sign_up_using')->text() ?></h3>
	<?=$social_buttons?>

	<br />
	<h3><?= wfMessage('or')->text() ?> <a href="<?php if(SSL_LOGIN_DOMAIN) print "https://" . SSL_LOGIN_DOMAIN; ?>/Special:Userlogin?type=signup&returnto=<?=$returnTo?>"><?= wfMessage('create_wh_acct')->text()?></a></h3>
	
	<div><br /><?= wfMessage('got_wh_acct')->text() ?> <a href="<?php if(SSL_LOGIN_DOMAIN) print "https://" . SSL_LOGIN_DOMAIN; ?>/Special:Userlogin?returnto=<?=$returnTo?>"><?= wfMessage('login')->text() ?></a></div>
</div>
