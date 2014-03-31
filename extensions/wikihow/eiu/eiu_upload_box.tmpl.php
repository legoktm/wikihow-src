<form id='eiu_uploadform' name='eiu_uploadform' enctype='multipart/form-data' method='post' action='<?= $submitUrl ?>' onsubmit="jQuery('#upload_btn').prop('disabled', true); return AIM.submit(this, { onStart: jQuery.proxy(easyImageUpload.uploadOnStart, easyImageUpload), onComplete: jQuery.proxy(easyImageUpload.uploadOnComplete, easyImageUpload) });">
    <table width='100%' class='uploadtable'>
	<tr>
		<td class='uploadinput'>
			<input type='file' id='ImageUploadFile' name='wpUploadFile' size='30' onchange="if (wgUserName) { jQuery('#upload_btn').prop('disabled', false); jQuery('#eiu_uploadform').submit(); }">
			<input type='hidden' name='uploadform1' value='1'/>
			<input type='hidden' name='src' value='upload'/>
			<input type='submit' id='upload_btn' disabled='disabled' value='Upload' style="display: none;" />
		</td>
        <td>
        	<img src="<?= wfGetPad('/extensions/wikihow/rotate.gif') ?>" alt="" class="eiu-wheel" id="eiu-wheel-upload" />
        </td>
	</tr>
    </table>
</form>
