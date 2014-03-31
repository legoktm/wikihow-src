<? if (($dv_dl_file_pdf != '') && ($dv_dl_file_xls == '')) { ?>
<p class="dv_dl_pdf_2"><a href="<?=$dv_dl_file_pdf?>" id="gatSamplePdf3" target="_blank" rel="nofollow"><?=$dv_download?> <?=$dv_dl_text_pdf?></a></p>
<? } ?>
<? if ($dv_dl_file_doc != '') { ?>
<p class="dv_dl_doc_2"><a href="<?=$dv_dl_file_doc?>" id="gatSampleWord3" target="_blank" rel="nofollow"><?=$dv_download?> <?=$dv_dl_text_doc?></a></p>
<? } ?>
<? if ($dv_dl_file_xls != '') { ?>
<p class="dv_dl_xls_2"><a href="<?=$dv_dl_file_xls?>" id="gatSampleXls3" target="_blank" rel="nofollow"><?=$dv_download?> <?=$dv_dl_text_xls?></a></p>
<? } ?>
<? if ($dv_dl_file_txt != '') { ?>
<p class="dv_dl_txt_2"><a href="<?=$dv_dl_file_txt?>" id="gatSampleTxt3" target="_blank" rel="nofollow"><?=$dv_download?> <?=$dv_dl_text_txt?></a></p>
<? } ?>
<? if ($dv_dl_file_gdoc != '') { ?>
<p class="dv_dl_gdoc_2"><a href="http://docs.google.com/viewer?url=<?=$dv_dl_file_gdoc?>" target="_blank" id="gatSampleGdoc3" rel="nofollow"><?=$dv_open_in?> <?=$dv_dl_text_gdoc?></a></p>
<? } ?>
