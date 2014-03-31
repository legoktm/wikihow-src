<div class='textscroller_outer' id="<?=$id?>">
	<div class='textscroller_inner'>
		<div class='greytext'><?=$grayText?></div>
		<div class='scrolltext'><?=$scrollText?></div>
	</div>
	<div class='arrow_outer'>
	<? if (!empty($arrowText)) { ?>
		<div class='arrow_right'></div>
		<div class='arrow_text'><?=$arrowText?></div>
	<? } else { ?>
		&nbsp;
	<? } ?>
	</div>
</div>
