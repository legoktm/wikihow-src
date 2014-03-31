<? if (!empty($rows)) { ?>
<div id='tag_row_data'>
	<table class='wap'>
	<thead>
		<? if ($u->isAdmin()) { ?>
			<th>Rank</th>
		<? } ?>
		<th>Id</th>
		<th>Url</th>
		<th>Price</th>
		<th>Categories</th>
		<th>Action</th>
	</thead>
	<?=$rows?>
	</table>
	<div id='tag_list_nav'>
		<div id='tag_list_more_rows' cid='<?=$tag?>' offset='<?=$offset + $numrows?>' numrows='<?=$numrows?>'></div>
	</div>
</div>
<? } ?>
