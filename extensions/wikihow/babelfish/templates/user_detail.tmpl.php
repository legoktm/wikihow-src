<?=$css?>
<?=$js?>
<?=$nav?>
Welcome to <?=$system?>, <?=$u->getName()?>! You can use this tool to browse and reserve articles on the "Article Selection Lists". 
<br>
Articles you reserve can be found under "Your Reserved Articles".
<? if ($admin || $powerUser) { ?>
	<h3>Admin Functions</h3>
	<ul>
		<? if ($admin) {?>
		<li><a href='#' id='rpt_user_articles' uname='<?=$u->getName()?>' uid='<?=$u->getId()?>'>Article Report</a></li>
		<? }?>
		<? if ($powerUser) { ?>
			<li><a href='#' id='rpt_assigned_articles'>Assigned Articles Report</a></li>
			<li><a href='#' id='rpt_completed_articles'>Completed Articles Report (past 6 weeks)</a></li>
		<? } ?>
	</ul>
<? } ?>
<h3>Article Selection Lists</h3>
<?$tags = $linker->linkTags($u->getTags())?>
<ul>
<? foreach ($tags as $tag) { ?>
<li><?=$tag?></li>
<? } ?>
</ul>
<br>
<h3><a class='c_refresh' href='/Special:<?=$system?>'>refresh</a>Your Reserved Articles</h3>
<?=$assigned?>
<br>
<h3>Your Recently Completed Articles</h3>
<? if (sizeof($completed)) {?>
<table class='wap tablesorter'>
<thead>
	<th>Id</th>
	<th>Lang</th>
	<th>Url</th>
	<th>Price</th>
	<th>Completed Date</th>
</thead>
<tbody>
	<? foreach ($completed as $a) { ?>
	<tr>
		<td><?=$a->getPageId()?></td>
		<td><?=$a->getLangCode()?></td>
		<td> <?=$linker->linkSystemUrl($a->getUrl())?> </td>
		<td> <?=$a->getPrice()?> </td>
		<td><?=$a->getCompletedDate()?></td>
	</tr>
	<? } ?>
</tbody>
</table>
<? } ?>
<br>
