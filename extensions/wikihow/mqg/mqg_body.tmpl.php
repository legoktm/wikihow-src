<?
if ($mqg_pic) {
	$mqg_width = $mqg_pic->width;
	$mqg_height = $mqg_pic->height;
?>
<div id='mqg_prompt' >
		<div id='mqg_display'>
			<div class="mqg_outerwrap">
				<div class="mqg_container">
					<div id="mqg_trans_response" class="mqg_center mqg_subheading">Is this a good image for this article?</div>
				</div>
			</div>
			<? if ($mqg_device['intro-image-format'] == 'conditional') {
				$className = ($mqg_width <= $mqg_device['screen-width'] / 2 ? 'vertical' : 'horizontal');
			} else if ($mqg_device['intro-image-format'] == 'right') {
				$className = 'floatright';
			} ?>
			<!--
				<div id='mqg_pic' class="rounders grey" style="width:<?=$mqg_width?>px; height:<?=$mqg_height?>px;">
					<?=$mqg_pic->toHtml()?>
					<div class="corner top_left"></div>
					<div class="corner top_right"></div>
					<div class="corner bottom_right"></div>
					<div class="corner bottom_left"></div>
				</div>
			-->
				<div id="mqg_pic" style="width:<?=$mqg_width?>px;height:<?=$mqg_height?>px;">
					<?=$mqg_pic->toHtml()?>
				</div>
			</div>
			<div id="mqg_buttons">
				<a id="mqg_yes" href="#" class="button button52">Yes</a> <a id="mqg_no" href="#" class="button white_button">No</a> 
				<div id="mqg_skip_div">
					<div id="mqg_skip_arrow"></div>
					<a href="#" id="mqg_skip">Skip</a>
				</div>
			</div>
		</div>
</div>
<?
}
?>
<div id='mgq_article'>
<?=$mqg_article?>
</div>
