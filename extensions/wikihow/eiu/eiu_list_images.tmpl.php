<? // template no longer in use ?>

<table width='100%' align='center'>
	<tr>
		<td class='recently_uploaded_title' colspan='2'><?= $msg ?></td>
		<td colspan='2' class='recently_uploaded_next'>
			<? if ($page > 1): ?>
				<a href="#" onclick="easyImageUpload.loadImages('<?= $src ?>', -1, <?= $page - 1 ?>); return false;">&laquo;&nbsp;Prev&nbsp;<?= $RESULTS_PER_PAGE ?></a>
			<? endif; ?>
			<? if ($page > 1 && $next_available > 0): ?> | <? endif; ?>
			<? if ($next_available > 0): ?>
				<a href="#" onclick="easyImageUpload.loadImages('<?= $src ?>', -1, <?= $page + 1 ?>); return false;">Next&nbsp;<?= $next_available ?>&nbsp;&raquo;</a>
			<? endif; ?>
		</td>
	</tr><tr>
	<? foreach ($photos as $i => $photo): ?>
		<? if ($i % 4 == 0 && $i > 0): ?></tr><tr><? endif; ?>
		<? if ($photo['found']): ?>
			<td class='rresult'>
				<img src='<?= $photo['thumb_url'] ?>' id='thumb-eiu-flickr-img-<?= $i ?>' /><br/>
				<? if ($isLoggedIn): ?>
					<a href="#" onclick="return easyImageUpload.insertImage('<?= $src ?>', 'eiu-flickr-img-<?= $i ?>');"><?= wfMsg('eiu-insert') ?></a> | <a href="#" onclick="return easyImageUpload.previewImage('<?= $src ?>', 'eiu-flickr-img-<?= $i ?>', 'return easyImageUpload.insertImage(\'<?= $src ?>\', \'eiu-flickr-img-<?= $i ?>\');', '<?= wfMsg('eiu-insert') ?>');"><?= wfMsg('eiu-preview') ?></a>
				<? else: ?>
					<?= wfMsg('eiu-insert3') ?>
				<? endif; ?>
				<div style="display: none;" id="eiu-flickr-img-<?= $i ?>"><?= $photo['details'] ?></div>
			</td>
		<? else: ?>
			<td class='rresult'>Can't find file for <?= $photo['name'] ?></td>
		<? endif; ?>
	<? endforeach; ?>
	</tr>
</table>
