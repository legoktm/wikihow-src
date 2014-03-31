	<div id="main_page">		
		<p id="main_tag"><? print wfMsg('main_tag')?></p>
		<div id="spotlight_article">
			<div id="spotlight_image">
				<a href="<?= $spotlight['url'] ?>"><img src="<?= $spotlight['img'] ?>" alt="" id="intro_img" /></a>
				<? if(!$nonEng) { ?><div class="home_label"></div><? } ?><?= $width ?>
			</div>
			<h1><a href="<?= $spotlight['url'] ?>"><?=  $spotlight['name'] ?></a></h1>
			<p><?= $spotlight['intro'] ?> <a href="<?= $spotlight['url'] ?>"><?= wfMsg('read-more')?></a></p>
		</div><!--end article_top-->
		<? if (!empty($featured)) { ?>
		<h3 class="fa_head"><?= wfMsg('featured-articles') ?></h3>
		<div id="featured_articles">
			<div class="fa_row">
			<? for ($i = 0; $i < 6; $i++): ?>
				<? if (($i > 0) && ($i % 2 == 0)): ?></div><div class="fa_row"><? endif; ?>
				<? if ($i < count($featured)): $fa = $featured[$i]; ?>
					<a href="<?= $fa['url'] ?>" class="related_box" style="<?= $fa['bgimg'] ?>">
						<p><?=  $fa['name'] ?></p>
					</a>
				<? endif; ?>
			<? endfor; ?>
			</div>
	<!-- rs: disable other langs until they're deployed
			<a href="<?= $languagesUrl ?>" class="wikihow_world"><img src="<?= wfGetPad('/extensions/wikihow/mobile/images/globe.gif') ?>" alt="" /> <?= wfMsg('wikihow-other-languages') ?></a>
	-->
		</div><!--end featured_articles-->
		<? } ?>
		<p id="surprise_p"><a href="<?= $randomUrl ?>"><?= wfMsg('surprise-me') ?></a></p>
	</div>
