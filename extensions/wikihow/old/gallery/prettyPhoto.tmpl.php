
<div id="pp_right_arrow" class="pp_arrows" style="background-image:url(<?= wfGetPad('/extensions/wikihow/gallery/arrows.png') ?>)"></div>
<div id="pp_left_arrow" class="pp_arrows" style="background-image:url(<?= wfGetPad('/extensions/wikihow/gallery/arrows.png') ?>)"></div>
<div class="pp_slideshow_wrapper">
	<div class="pp_slideshow">
		<div class="pp_slides">
	<?for ($i = 0; $i < $numImages; $i++) { ?>
			<p><a href="<?=wfGetPad('/Special:GallerySlide'.$fileUrl[$i].',,'.$articleID.',,'.$revid.'?ajax=true')?>" rel="prettyPhoto[wikiHow]" style="background-image:url(<?=wfGetPad($thumbUrl[$i])?>)"></a></p>
	<?}?>
			<p class="pp_endslide"><a href="<?=wfGetPad('/Special:GallerySlide/end,,'.$articleID.',,?ajax=true')?>" rel="prettyPhoto[wikiHow]"></a></p>
		</div>
	</div>

</div>