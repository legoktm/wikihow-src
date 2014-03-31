<? if (!empty($articles)) { ?>
	<? foreach ($articles as $a) { ?>
	<tr>
		<td><?=$a->getPageId()?></td>
		<td><?=$a->getLangCode()?></td>
		<td class='urlcol'><?=$linker->linkWikiHowUrl($a->getUrl())?></td>
		<td><?=$a->getPrice()?></td>
		<? $displayUser = $currentUser->isAdmin() ? $currentUser : $u ?>
		<td class='tagcol'><?=implode(", ", $linker->linkTags($a->getViewableTags($displayUser)))?></td>
		<td><?=implode(", ", $a->getTopLevelCategories())?></td>
		<td class='datecol'><?=$a->getReservedDate()?></td>
		<td class='actioncol'><a href='#' class='release' langcode='<?=$a->getLangCode()?>' aid='<?=$a->getPageId()?>'>remove from my list</a></td>
	</tr>
	<? } ?>
<? } ?>
