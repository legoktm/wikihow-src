<div id="pp_right_arrow" class="pp_arrows" style="background-image:url(<?= wfGetPad('/extensions/wikihow/gallery/arrows.png') ?>)"></div>
<div id="pp_left_arrow" class="pp_arrows" style="background-image:url(<?= wfGetPad('/extensions/wikihow/gallery/arrows.png') ?>)"></div>
<div class="pp_slideshow_wrapper">
	<div class="pp_slideshow">
		<div class="pp_slides">
	<?for ($i = 0; $i < $numImages; $i++) { ?>
			<p><a href="<?=wfGetPad('/Special:GallerySlide'.$fileUrl[$i].',,'.$articleID.',,'.$revid.',,redesign02')?>" class="gallery_link" style="background-image:url(<?=wfGetPad($thumbUrl[$i])?>)"></a></p>
	<?}?>
		</div>
	</div>

</div>