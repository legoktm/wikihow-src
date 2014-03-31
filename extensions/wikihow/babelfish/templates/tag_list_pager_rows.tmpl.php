<? if (!empty($articles)) { ?>
<? foreach ($articles as $i => $a) { ?>
<tr>
	<? if ($u->isAdmin()) { ?>
		<td ><?=$a->getRank()?></td>
	<? } ?>
	<td ><?=$a->getPageId()?></td>
	<td class='urlcol'><?=$linker->linkWikiHowUrl($a->getUrl())?></td>
	<td><?=$a->getPrice()?></td>
	<td><?=implode(", ", $a->getTopLevelCategories())?></td>
	<td><a href='#' class='reserve' langcode='<?=$a->getLangCode()?>' aid='<?=$a->getPageId()?>'>reserve article</a></td>
</tr>
<? 
	}
}  
?>
