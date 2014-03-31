<div id="tweetit_container"> <!-- end of div in templates/reply.tmpl.php file -->
	<div style="height:1px;"></div>
	<div id="new_tweets">5 more tweets have arrived ... Click to view</div>
	<ul id="tweets_ticker">
		<?php echo TwitterReplierTemplate::html( 'tweets', array( 'tweets' => $tweets ) ) ?>
	</ul>
	
	<span id="screenName"><?php echo $screenName ? $screenName : '' ?></span>
	<span id="profileImage"><?php echo $profileImage ? $profileImage : '' ?></span>