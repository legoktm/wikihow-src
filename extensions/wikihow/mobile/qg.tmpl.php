<div id='qg_test'>
	<div style="background:#FFFFCC;border: 0px;padding: 10px 5px 5px 5px;">
			<div id='qg_complete'> Thanks for your vote! Help us decide on <a id='qg_complete_link' href="<?=$randomUrl . '?tool=QG'?>">another article</a>?</div>
			<div id='qg_display'>
			<div style="float: right;"><a class='m_qg_dismiss' href="<?=$completeUrl?>&btn=s">[close]</a> </div> 				
			<div class="m_qg_heading">Hey! We need your help</div>
			<div class="m_qg_subheading">Is this a good image for this article?</div>
			<? if ($deviceOpts['intro-image-format'] == 'conditional') {
				$className = ($QGWidth <= $deviceOpts['screen-width'] / 2 ? 'vertical' : 'horizontal');
			} else if ($deviceOpts['intro-image-format'] == 'right') {
				$className = 'floatright';
			} ?>
			<?
				if (is_object($thumb)) {
					$QGWidth = floor($width * (.75));
					$QGHeight = floor($height * (.75));
					$QGThumb = clone $thumb;
					$QGThumb->width = $QGWidth;
					$QGThumb->height = $QGHeight;
				}
			?>
			<div style="margin-right: auto; margin-left: auto;width:<?=$QGWidth?>px; height:<?=$QGHeight?>px;">
				<div class="rounders grey" style="width:<?=$QGWidth?>px; height:<?=$QGHeight?>px;">
					<? if (is_object($QGThumb)) {
							echo $QGThumb->toHtml();
						}
					?>
					<div class="corner top_left"></div>
					<div class="corner top_right"></div>
					<div class="corner bottom_right"></div>
					<div class="corner bottom_left"></div>
				</div>
			</div>
			<div style="margin-top: 5px;margin-right: auto; margin-left: auto;width: 107px">
				<a href="<?=$completeUrl?>&btn=y" class="button button52">Yes</a> <a href="<?=$completeUrl?>&btn=n" class="button white_button">No</a>
			</div>
			</div>
	</div>
</div>
