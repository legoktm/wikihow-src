<span>
	<? if( $profileImage ): ?>
	<span class="avatar">
		<img src="<?= $profileImage ?>" alt="@<?= $fromUser ?> avatar" width="48" height="48"/>
	</span>
	<? endif; ?>

	<span class="twitter_handle" style="font-weight:bold;color:#444" >
		<?= $fromUser ?>
	</span>

	<br /> 

	<span class="tweet">
		<?= $tweet ?>
	</span> 

	<br />
	<? if( $createdOn ): ?>
	<span class="time reltime">
		<?php $timeStr = strtotime( $createdOn ); ?>
		<?= TwitterReplierTemplate::formatTime( $timeStr ) ?>
		<input type="hidden" name="ts" value="<?= $timeStr ?>" />
	</span>
	<? endif; ?>
</span>
