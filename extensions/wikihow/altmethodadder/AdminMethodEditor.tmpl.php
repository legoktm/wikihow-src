<?=$css?>
<form class="ame_ts" method="GET">
<label for="days">Recent Method Editor Edits </label><input type="text" name="days" value="<?=$days?>"/> days
<input type="submit" maxlength="2" name="submit" value="Go"/>
</form>
<div id="ame_csv" class="button secondary"><a href="/Special:AdminMethodEditor?csv=1">Download CSV</a></div>
<table class="ame_scores">
	<tr>
		<th>User</th>
		<th>Page</th>
		<th>Date</th>
	</tr>
	<?
	$i = 0;
	foreach ($results as $result):
		$class = $i % 2 == 0 ? 'even' : 'odd';
		$i++
	?>
		<tr class="<?=$class?>">
			<td><a href='#' class='ame_detail' id='rct_<?=$result['log_user']?>'><?=$result['log_user_name']?></a></td>
			<td><a href='<?=$result['log_title']?>' class='ame_detail'><?=$result['title_url']?></a></td>
			<td><?=$result['date']?></td>
		</tr>
	<? endforeach; ?>
</table>
