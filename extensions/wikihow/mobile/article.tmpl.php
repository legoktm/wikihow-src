	<?= wfMsg('test_setup_mobile') ?>
	<div id='cta'></div>
	<div id="article" class="<?=$articleClasses?>"> 
		<?=$checkmarks?>
		<div id="image-preview">
<?
	// image: /extensions/wikihow/winpop_x.gif
	$img_data = 'R0lGODlhFQAVAMQAAOHh4cTExOnp6bKyssjIyPr6+rCwsPT09NPT0729veTk5MPDw9nZ2d7e3s7OzsXFxe/v77i4uK6urv///62trQAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAACH5BAAAAAAALAAAAAAVABUAAAVw4AQ8VGmepURJASAaKmrGpwEEtKyrwar/wCAqJ/zREgKBo6QQMIoUxqSQQEwmCejgMIFcEUbZ4joRQE+CK+FcIpAPg/BpUJg06o1hCpWGuydrQlYTC0xTEWxAEouJilA8ckQlLTA+J4uSNiI4Zy0TIQA7';
?>
			<img src="data:image/gif;base64,<?= $img_data ?>" width="21" height="21" alt="close window" id="mobile_x" onclick="closeImagePreview();" />
				<img id="image-src" />
			<a id="image-src-credits" rel='nofollow'><?= wfMsg('creditsLink') ?></a>
		</div><!--end image-preview-->
		<div id="article_top">
			<h1<? if ($title_class) { ?> class="<?= $title_class ?>"<? } ?>><?= wfMsg('howto', $title) ?></h1>
			<? if ($thumb): ?>
				<?
					if ($deviceOpts['intro-image-format'] == 'conditional') {
						$className = ($width <= $deviceOpts['screen-width'] / 2 ? 'vertical' : 'horizontal');
					} else if ($deviceOpts['intro-image-format'] == 'right') {
						$className = 'floatright';
					}
				?>
				<div id='intro_img' class="<?= $className ?>">
					<? if (!$nonEng) { ?>
					<!--div class="home_label"></div-->
					<? } ?>
					<img alt="" src="<?= wfGetPad( $thumb->getUrl() ) ?>" width="<?= $width ?>" height="<?= $height ?>" srcset="<?= wfGetPad( $thumb_ss ) ?>" border="0" class="mwimage101" id="<?=$thumb_id?>" />
				</div>
			<? endif; ?>
			<p><?= $intro ?></p>
			<? global $wgLanguageCode; ?>
			<? if (@$deviceOpts['show-ads'] && $wgLanguageCode == 'en'): ?>
				<?= wfMsg('adunitmobile_setup'); ?>
			<? endif; ?>
			<? if (@$deviceOpts['show-ads']): ?>
			<div>
			<div class="wh_ad" style="display:inline-block">
				<?
				$adLabel = wfMessage('ad_label')->text();
				echo wfMsg('adunitmobile1', $adLabel);
				?>
			</div>
			</div>
			<? endif; ?>
			<div class="clear"></div>
			<?=$swap_script?>
			<div id="article_tabs">
				<? $tabs = 0; ?>
				<? if (isset($sections['steps'])): $tabs++; ?>
					<? $tab_name = $isGerman ? wfMsg('m_step') : $sections['steps']['name'] ?>
					<div id="tab-steps" class="tab<?= $tabs == 1 ? ' active' : '' ?>"><a href="#"><?= $tab_name ?></a></div>
				<? endif; ?>
				<? if (isset($sections['ingredients'])): $tabs++; ?>
					<div id="tab-ingredients" class="tab"><a href="#"><?= wfMsg('ingredients') ?></a></div>
				<? endif; ?>
				<? if (!isset($sections['ingredients']) && isset($sections['thingsyoullneed'])): $tabs++; ?>
					<div id="tab-thingsyoullneed" class="tab"><a href="#"><?= wfMsg('thingsyoullneedtab') ?></a></div>
				<? endif; ?>
				<? if (isset($sections['tips']) || isset($sections['warnings'])): $tabs++; 
					$section_name = (isset($sections['tips'])) ? 'tab-tips' : 'tab-warnings';
				?>
					<div id="<?=$section_name?>" class="tab"><a href="#"><?= wfMsg('tips-and-warnings') ?></a></div>
				<? endif; ?>
				<? if ($tabs < 3 && isset($sections['video'])): $tabs++; ?>
					<div id="tab-video" class="tab"><a href="#"><?= $sections['video']['name'] ?></a></div>
				<? endif; ?>
			</div><!--end article_tabs-->
			<div id="article_tabs_line"></div>
		</div><!--end article_top-->
		
<? $gotFirst = false; foreach ($sections as $id => $section): ?>
	<? $expandSection = $id == 'steps' || $id == 'ingredients' || $id == 'relatedwikihows' || $id == 'tips'; ?>
		<? if ($id == 'steps' && !$gotFirst) { ?>
		<? } else { echo $sections[$id][0] ?>
		<div id="drop-heading-<?= $id ?>" class="drop-heading">
			<div class="drop-heading-expander<?= $expandSection ? ' d-h-show' : '' ?>"></div>
			<h2><a href="#"><?= $section['name'] ?></a></h2>
		</div>
		<? } ?>
		<div id="drop-content-<?= $id ?>" class="content <?= $expandSection ? 'content-show' : '' ?>">
		<? if ($id == 'relatedwikihows' && !empty($sections['relatedwikihows']['boxes'])) { ?>
			<div class="related_boxes">
				<div class="related_row">
			<? foreach ($sections['relatedwikihows']['boxes'] as $key=>$rel) { ?>
				<a href="<?= $rel['url'] ?>" class="related_box" style="<?= $rel['bgimg'] ?>">
					<p><? if(!$nonEng) {?> <span>How to</span><? } ?><?= $rel['name'] ?></p>
				</a>
				<? if ($key == 2 && count($sections['relatedwikihows']['boxes']) != 3) { ?>
					</div><div class="related_row">
				<? } ?>
			<? } ?>
				</div>
			</div>
		<? } ?>
			<?= $section['html'] ?>
		<? if ($id != 'steps') { ?>
			<div class='backtotop'><a href="#">&uarr; <? print wfMsg('back_to_top')?></a></div>
		<? } ?>
		</div>
<? $gotFirst = true; endforeach; ?>

<?= MobileHtmlBuilder::showDeferredJS($deviceOpts) ?>
<?= MobileHtmlBuilder::showBootStrapScript() ?>
		
		<div id="final_section_cap"></div>
		<div id="final_section">
			<?=$final_share?>
			<?=$page_rating?>
			<div id="final_links">
				<a href="<?= $redirMainUrl ?>Special:CreatePage"><? print wfMsg('write_an_article') ?></a>
			</div>
		</div>
	</div>
	<?=$articleRating?>
	<!--end article-->
