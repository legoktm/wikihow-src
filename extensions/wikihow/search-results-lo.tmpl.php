<div id='lo_search'>
	<form id='search_site' action='<?=$me?>' method='get' >
		<div id='search_head' class='lo_search_head'>
		<input type='text' id='keywords' class='lo_q' name='search' maxlength='75' value="<?= $enc_q ?>" />
		<input type='hidden' name='lo' value='1'/>
		<? if (count($results) > 0): ?>
			<span class='result_count lo_count'><?= wfMsg('lsearch_num_results', number_format($total)) ?></span>
		<? endif; ?>
		<input type='submit' class='button button100 input_button lo_search_button' value='<?= wfMsg('search') ?>' />
		</div></form>

	<?
		// refactor: set vars if $q == empty
		if ($q == null):
			return;
		endif;
	?>

<div id='lo_searchresults_list'>
	<? if (count($results) > 0): ?>
		<div class="sr_for lo_for">
			<?= wfMsgForContent('lsearch_results_for', $enc_q) ?>
		</div>
		<?= wfMsg('Adunit_search_top', $enc_q); ?>
		<?= wfMsg('Adunit_search_right'); ?>
	<? endif; ?>

	<? if ($suggestionLink): ?>
		<div class="sr_suggest"><?= wfMsg('lsearch_suggestion', $suggestionLink) ?></div>
	<? endif; ?>

	<? if (count($results) == 0): ?>
		</div> <!--lo_searchresults_footer -->
		<div class="sr_noresults"><?= wfMsg('lsearch_noresults', $enc_q) ?></div>
		<div id='searchresults_footer' class="lo_footer"><br /></div>
		<? return; ?>
	<? endif; ?>
	<div id='searchresults_list' class='lo_list'>
	<? foreach($results as $i => $result): ?>
		<div class="result lo_result">
			<? if (!$result['is_category']): ?>
				<? if (!empty($result['img_thumb_100'])): ?>
					<div class='result_thumb'><img src="<?= $result['img_thumb_100'] ?>" /></div>
				<? endif; ?>
			<? else: ?>
				<div class='result_thumb cat_thumb'><img src="<?= $result['img_thumb_100'] ? $result['img_thumb_100'] : '/skins/WikiHow/images/Book_75.png' ?>" /></div>
			<? endif; ?>

	<?
		$url = $result['url'];
		if (!preg_match('@^http:@', $url)) {
			$url = $BASE_URL . '/' . $url;
		}
	?>
				<a href="<?= $url ?>" class="result_link lo_result_link"><?= $result['title_match'] ?></a>
				<div class="lo_abstract"><?=$result['abstract']?></div>
				<div class="lo_url"><?=$result['dispurl']?></div>

			<div class="clearall"></div>
		</div>
	<? endforeach; ?>
	</div>
	</div> <!-- lo_searchresults_footer -->
	<div style="clear:both"> </div>
	<?
	if (($total > $start + $max_results
		  && $last == $start + $max_results)
		|| $start >= $max_results): ?>

	<div id='searchresults_footer' class='lo_footer'>

	<div class="sr_next">
	<? // "Next >" link ?>
	<? if ($total > $start + $max_results && $last == $start + $max_results): ?>
		<a href="<?= "$me?search=" . urlencode($q) . "&start=" . ($start + $max_results) . "&lo=1";?>"><?=wfMsg('lsearch_next')?></a>
	<? else: ?>
		<?= wfMsg("lsearch_next") ?>
	<? endif; ?>
	</div>

	<div class='sr_prev'>
	<? // "< Prev" link ?>
	<? if ($start - $max_results >= 0): ?>
		<a href="<?= "$me?search=" . urlencode($q) . ($start - $max_results !== 0 ? "&start=" . ($start - $max_results) : '') . "&lo=1"?>"><?=wfMsg('lsearch_previous')?></a>
	<? else: ?>
		<?= wfMsg("lsearch_previous") ?>
	<? endif; ?>
	&nbsp;
	</div>

	<?= wfMsg('lsearch_results_range', $first, $last, $total) ?>


	</div>

	<? endif; ?>
</div>
