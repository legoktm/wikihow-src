<?php if( is_array( $results ) && count( $results ) > 0 ): ?>
	<?php $counter = 0; ?>
	<?php foreach( $results as $result ): ?>
		<tr class="<?php echo ($counter % 2 === 0) ? 'even' : 'odd' ?>">
			<td><input type="radio" class="suggested_article" name="article" value='<?php echo json_encode( array( 'title' => htmlentities( $result->mTextform, ENT_QUOTES ), 'url' => 'http://www.wikihow.com/' . $result->mUrlform ) ) ?>' /></td>
			<td><a href="http://www.wikihow.com/<?php echo $result->mUrlform ?>"><?php echo $result->mTextform ?></a></td>
		</tr>
		<?php $counter++ ?>
	<?php endforeach ?>
<?php else: ?>
	<tr>
		<td colspan="2" class="no_wh_articles"><?php echo wfMsg( 'no-wh-articles-found', $tweet ) ?></td>
	</tr>
<?php endif ?>
	<tr style="display:none"><td id="searchTerms"><?php echo $tweet ?></td></tr>
