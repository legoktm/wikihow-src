<h1><?=$doc_title?></h1>

<img src="<?=$dv_fallback_img?>" id="sample_img" />

<div id="docviewer_choices" class="dv_mobile">
	<h4><?=$header_get?></h4>
	<ul id="dv_dls">
		<? if (($dv_dl_file_pdf != '') && ($dv_dl_file_xls == '')) { ?>
		<li class="dv_dl_pdf">
			<a href="<?=$dv_dl_file_pdf?>" class="dv_dl_block" id="gatSamplePdf1" target="_blank" rel="nofollow"></a>
			<p><a href="<?=$dv_dl_file_pdf?>" id="gatSamplePdf2" target="_blank" rel="nofollow"><?=$dv_download?><br /><span><?=$dv_dl_text_pdf?></span></a></p>
		</li>
		<? } ?>
		<? if ($dv_dl_file_doc != '') { ?>
		<li class="dv_dl_doc">
			<a href="<?=$dv_dl_file_doc?>" class="dv_dl_block" id="gatSampleWord1" target="_blank" rel="nofollow"></a>
			<p><a href="<?=$dv_dl_file_doc?>" id="gatSampleWord2" target="_blank" rel="nofollow"><?=$dv_download?><br /><span><?=$dv_dl_text_doc?></span></a></p>
		</li>
		<? } ?>
		<? if ($dv_dl_file_xls != '') { ?>
		<li class="dv_dl_xls">
			<a href="<?=$dv_dl_file_xls?>" class="dv_dl_block" id="gatSampleXls1" target="_blank" rel="nofollow"></a>
			<p><a href="<?=$dv_dl_file_xls?>" id="gatSampleXls2" target="_blank" rel="nofollow"><?=$dv_download?><br /><span><?=$dv_dl_text_xls?></span></a></p>
		</li>
		<? } ?>
		<? if ($dv_dl_file_txt != '') { ?>
		<li class="dv_dl_txt">
			<a href="<?=$dv_dl_file_txt?>" class="dv_dl_block" id="gatSampleTxt1" target="_blank" rel="nofollow"></a>
			<p><a href="<?=$dv_dl_file_txt?>" id="gatSampleTxt2" target="_blank" rel="nofollow"><?=$dv_download?><br /><span><?=$dv_dl_text_txt?></span></a></p>
		</li>
		<? } ?>
		<? if ($dv_dl_file_gdoc != '') { ?>
		<li class="dv_dl_gdoc">
			<a href="http://docs.google.com/viewer?url=<?=$dv_dl_file_gdoc?>" target="_blank" class="dv_dl_block" id="gatSampleGdoc1" rel="nofollow"></a>
			<p><a href="http://docs.google.com/viewer?url=<?=$dv_dl_file_gdoc?>" id="gatSampleGdoc2" rel="nofollow"><?=$dv_open_in?><br /><span><?=$dv_dl_text_gdoc?></span></a></p>
		</li>
		<? } ?>
	</ul>
	<? if ($dv_related != '') { ?>
	<h4><?=$header_found?></h4>
	<table id="dv_found">
	<?=$dv_found?>
	</table>
	<? } ?>
</div>