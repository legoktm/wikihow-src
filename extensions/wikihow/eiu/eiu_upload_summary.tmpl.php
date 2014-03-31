<? // Final screen / text of easy image upload process ?>
<?
	$thumbHtml = $file->getThumbnail($width, $height)->toHtml();
	$details['html'] = $thumbHtml;
	$details['filename'] = $imageFilename;
	$details['tag'] = $tag;
?>
<h2 style="text-align: center;"><?= wfMsg('eiu-success') ?></h2>
<div style="text-align: center;">
	<p id="imagethumb"><?= $thumbHtml ?></p>
	<p><?= wfMsg('eiu-wiki-text-placed') ?></p>
	<div id="ImageUploadDisplayedTag"><?= $tag ?></div>
	<input onclick="easyImageUpload.closeUploadDialog();" type="button" value="<?= wfMsg('eiu-return') ?>" class="button primary" />
	<input type="hidden" id="ImageUploadTag" value="<?= htmlspecialchars( $tag ) ?>" />
	<input type="hidden" id="ImageUploadFilename" value="<?= htmlspecialchars($imageFilename) ?>" />
	<input type="hidden" id="ImageUploadImageDetails" value="<?= htmlspecialchars( json_encode($details) ) ?>" />
</div>
