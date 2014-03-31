<form id="ConflictImageForm" method="post" name="ConflictImageForm">
<input id="eiu-image-details" type="hidden" name="image-details" value="" />
<input id="ImageUploadUseExisting" type="hidden" name="ImageUploadUseExisting" value="" />
<input id="ImageUploadRename" type="hidden" name="ImageUploadRename" value="" />
<input type="hidden" name="type" value="<?= isset($file) ? htmlspecialchars($file->getMediaType()) : '' ?>" />
<input type="hidden" name="ImageIsConflict" value="1" />
<input id="ImageAttribution" type="hidden" name="ImageAttribution" value="<?= htmlspecialchars($image_comment) ?>" />
<?php
	$file_temp = new LocalFile(Title::newFromText($mwname, NS_IMAGE), RepoGroup::singleton()->getLocalRepo());
	$existing_title = Title::newFromText($name, NS_IMAGE);
	$file_existing = new LocalFile($existing_title, RepoGroup::singleton()->getLocalRepo());
	print wfMsg('eiu-conflict-inf', $file_existing->getName());
?>
<input name="mwname" type="hidden" value="<?= htmlspecialchars($mwname) ?>" />
<br/><br/>

<table cellspacing="0" class="ImageUploadConflictTable wh_block" width='100%'>
	<tr>
		<td>
			<h2><?= wfMsg('eiu-rename') ?></h2>
		</td>
		<td>
			<h2><?= wfMsg('eiu-existing') ?></h2>
		</td>
	</tr>
	<tr id="ImageUploadCompare">
		<td>
			<?= $file_temp->getThumbnail(265, 205)->toHtml() ?>
		</td>
		<td>
			<input type="hidden" name="ImageUploadExistingName" value="<?= htmlspecialchars($file_existing->getName()) ?>" />
			<?= $file_existing->getThumbnail(265, 205)->toHtml() ?>
		</td>
	</tr>
	<tr>
		<td>
			<input type="text" name="ImageUploadRenameName" value="<?= htmlspecialchars($suggestedFirstPart) ?>" />
			<label for="ImageUploadRenameName">.<?= htmlspecialchars($extension) ?></label><br />
	        <input name="ImageUploadRenameExtension" type="hidden" value="<?= htmlspecialchars($extension) ?>" />
			<input type="button" value="<?= wfMsg('eiu-insert') ?>" onclick="easyImageUpload.addImageDetailsToForm(); easyImageUpload.resolveImageConflictRename(); return false;" class="button primary" />
		</td>
		<td>
			<a onclick="easyImageUpload.addImageDetailsToForm(); easyImageUpload.resolveImageConflictExisting(); return false;" href="#"><?= wfMsg('eiu-use-existing-instead') ?></a>
		</td>
	</tr>
</table>

</form>
