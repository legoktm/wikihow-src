<?php if ( !empty( $tweets ) && is_array( $tweets ) ): ?>
		<?php global $wgRequest; ?>
		<?php $endClass = ($wgRequest->getVal('returnType') == 'json' ? 'new_bottom' : 'last'); ?>
		<?php $totalTweets = count( $tweets ); ?>
		<?php $i = 1; ?>
		<?php foreach ( $tweets as $tweet ): ?>
			
			<?php $response = json_decode( base64_decode( $tweet['response_object'] ) ) ?>
			<li id="twitter_<?= $tweet['tweet_id'] ?>" class="<?= $i == $totalTweets ? $endClass : '' ?>">
				<?php $vars = array(	'fromUser' => $response->from_user, 
										'profileImage' => $response->profile_image_url, 
										'tweet' => $tweet['tweet'], 
										'createdOn' => $tweet['twitter_created_on'] ); ?>
				
				<?= TwitterReplierTemplate::html('tweet', $vars ); ?>
				<?php $i++; ?>
			</li>
		<?php endforeach ?>
	<?php else: ?>
		<li>No tweets</li>
<?php endif ?>
