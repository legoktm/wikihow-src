<? if (!empty($assigned)) {?>
<table class='wap'>
	<thead>
		<th>Id</th>
		<th>Lang</th>
		<th>Url</th>
		<th>Price</th>
		<th>Tags</th>
		<th>Categories</th>
		<th>Reserved Date</th>
		<th>Process</th>
	</thead>
	<tbody>
		<?=$assigned?>
	</tbody>
</table>
<div>
	<a href='#' id='assigned_list_more_rows' cid='<?=$u->getId()?>' offset='<?=$offset + $numrows?>' numrows='<?=$numrows?>'>[More Articles]</a>
</div>
<? } ?>
