<?= SSearch::searchBox($q) ?>
<?= SSearch::searchResultsJS() ?>

<? if ($error): ?>
	<div id="search-error-warning" style="font-style: italic; margin: 2em 0;">
		<?= $error ?>
	</div>
<? else: ?>
	<div id="search-results-line" style="text-align: right; margin-top: 10px;">
		Results <b><?= ($page - 1) * $page_size + 1 ?></b> - <b><?= ($page - 1) * $page_size + count($results['matches']) ?></b> 
		  of about <b><?= $results['total_found'] ?></b>
		  for <b><?= $q ?></b>.
		  (<b><?= sprintf('%.2f', round($results['time'], 2)) ?></b> seconds)
	</div>

	<? if ($warning): ?>
		<div id="search-error-warning" style="font-style: italic;">
			<?= $warning ?>
		</div>
	<? endif; ?>

	<? if ($results['spelling']): ?>
		<div id="search-results-spelling" style="padding-top: 10px; padding-bottom: 7px;">
			<span style="color: #CC0000; font-size: 15px;">Did you mean:</span> <a href="/Special:SSearch?q=<?= urlencode($results['spelling']) ?>" style="font-weight: bold; font-style: italic; font-size: 15px;"><?= $results['spelling'] ?></a>
		</div>
	<? endif; ?>

	<? foreach ($results['matches'] as $i=>$doc): ?>
		<div class="search-match" style="height: 100px; margin: 1em 0; clear:both;<? if ($i%2 == 0): ?> background-color:#F4F2E9;<? endif; ?>">
			<? if ($doc['attrs']['wst_img_thumb_100']): ?>
				<div style="float:right;">
					<a href="/<?= $doc['attrs']['wst_url_title'] ?>"><img border="0" src="<?= wfGetPad($doc['attrs']['wst_img_thumb_100']) ?>" /></a>
				</div>
			<? endif; ?>
			<span class="search-results-title" style="font-size: 16px; font-weight: normal;">
				<a href="/<?= $doc['attrs']['wst_url_title'] ?>"><?= wfMsg('howto', $doc['attrs']['excerpt']) ?></a>
			</span>
			<? if ($doc['attrs']['wst_is_featured']): ?>
				<span class="search-results-featured-article" style="color: #617561; margin-left: 20px;"><img src="<?= wfGetPad('/skins/WikiHow/images/star.gif') ?>" width="15" height="14" alt="*" /> Featured Article</span>
			<? endif; ?>
			<br/>
			<span class="search-results-last-touched" style="display: none;"><?= $doc['attrs']['wst_timestamp'] ?></span><br/>
			<br/>
			Popularity: <b><?= $doc['attrs']['wst_popularity'] ?>/5</b>
		</div>
	<? endforeach; ?>

	<div id="search-results-paging" style="text-align: center; margin-bottom: 10px;">
		<? foreach ($paging as $pager): ?>
			<?
			$lt = ''; $rt = '';
			switch ($pager) {
			case '...':
				$p = 0;
				$txt = '...';
				break;
			case 'prev':
				$p = $page - 1;
				$txt = wfMsg('prev-page');
				$rt = '&nbsp;&nbsp;';
				break;
			case 'next':
				$p = $page + 1;
				$txt = wfMsg('next-page');
				$lt = '&nbsp;&nbsp;';
				break;
			default: 
				if ($pager !== $page) {
					$p = $pager;
					$txt = $pager;
				} else {
					$p = 0;
					$txt = '<b>' . $page . '</b>';
				}
				break;
			}
			?>
			<? if ($p > 0): ?>
				<?= $lt ?><a href="/Special:SSearch?q=<?= urlencode($q) ?><?= ($p > 1 ? '&p=' . $p : '') ?>"><?= $txt ?></a><?= $rt ?>
			<? else: ?>
				<?= $txt ?>
			<? endif; ?>
		<? endforeach; ?>
	</div>
	
	<?= SSearch::searchBox($q) ?>
<? endif; ?>

