<div id="authorizationContainer" title="Posting to Twitter Account">
	<p class="description"><?= wfMsg('authentication-description') ?></p>
	<p class="images"><img src="<?= wfGetPad('/extensions/wikihow/TwitterReplier/images/logo_wikihow.gif') ?>" alt="" /><img src="<?= wfGetPad('/extensions/wikihow/TwitterReplier/images/right_arrow.png') ?>" alt="" /><img src="<?= wfGetPad('/extensions/wikihow/TwitterReplier/images/logo_twitter.gif') ?>" alt="" /></p>
	<hr />
	<p class="button"><a href="#" id="authorizeMe"><img src="<?= wfGetPad('/extensions/wikihow/TwitterReplier/images/btn_authorize_me.png') ?>" alt="Authorize Me" /></a></p>
</div>

<div id="reply_container" >
	<div id="instructions">
		<p><?php echo wfMsg( 'right-rail-instructions' ) ?></p>
	</div>
	<div id="reply_content" style="display:none;">
		<p id="close"><a class="reply_close" href="#"><img src="<?= wfGetPad('/extensions/wikihow/TwitterReplier/images/icon_close.gif') ?>" alt="close" /></a> <a class="reply_close" href="#">close</a></p>

		<p class="bold"><?php echo wfMsg( 'search-article-header' ) ?></p>
		<form method="post" action="" id="trSearchForm">
			<input class="default_search_input" type="text" name="trSearch" value="<?= wfMsg('default-search-title') ?>"/>
		</form>

		<p class="bold suggest_article_header"><?php echo wfMsg( 'suggest-article-header' ) ?></p>

		<table id="suggestedTitles">
			<thead>
				<tr>
					<th></th>
					<th>Article Title</th>
				</tr>
			</thead>
			<tbody>

			</tbody>
		</table>

		<img src="<?= wfGetPad('/extensions/wikihow/TwitterReplier/images/line_dotted.gif') ?>" alt=""/>
		<p class="customize_header grey bold"><?php echo wfMsg( 'customize-tweet-header' ) ?></p>

		<p id="respond_to" class="grey bold"><?php echo wfMsg( 'respond-to', '<span class="reply_to_user"></span>' ) ?></p>

		<form method="post">
			<textarea id="reply_tweet" class="light_grey">

			</textarea>
			<input type="image" src="<?= wfGetPad('/extensions/wikihow/TwitterReplier/images/btn_TweetItForward.gif') ?>" name="tweet" value="Tweet Suggestion" id="reply"/>
			<img id="reply_spinner" src="<?= wfGetPad('/skins/common/images/spinner-circles.gif') ?>" />
			<div id="char_limit"></div>
			<div class="reply_as light_grey" id="reply_as" <?php echo empty( $twitterHandle ) ? "style='display:none'" : '' ?>>
				Replying as <a id="twitter_handle" href="http://twitter.com/<?= htmlspecialchars($twitterHandle) ?>" rel="nofollow" target="_blank">@<?= htmlspecialchars($twitterHandle) ?></a> &mdash; <a class="unlink_action" href="#">unlink from wikiHow</a>
			</div>
		</form>
		<span id="reply_status_id" style="display:none"></span>
	</div>
</div>

<div class="clearall"></div>
</div>
