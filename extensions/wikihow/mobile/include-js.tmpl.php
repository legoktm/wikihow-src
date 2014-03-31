<?= $mwResources ?>

<? if (!empty($scriptsCombine2)): ?>
	<? if ($deviceOpts['name'] != 'iphoneapp'): ?>
		<script type="text/javascript" src="/extensions/min/g/<?= join(',', $scriptsCombine2) ?><?= isset($GLOBALS['wgRequest']) && $GLOBALS['wgRequest']->getVal('c') == 't' ? '&c=t' : '' ?>&r=<?= WH_SITEREV ?>&e=.js"></script>
	<? else: ?>
		<? // HACK FOR OLD iPhone APP ?>
		<script type="text/javascript" src="<?= wfGetPad() ?>/extensions/min/?g=<?= join(',', $scriptsCombine2) ?>&rev=<?= WH_SITEREV ?>"></script>
	<? endif; ?>
<? endif; ?>

<? foreach ($scripts as $script): ?>
	<? if ($deviceOpts['name'] != 'iphoneapp'): ?>
		<script type="text/javascript" src="/extensions/min/g/<?= $script ?><?= isset($GLOBALS['wgRequest']) && $GLOBALS['wgRequest']->getVal('c') == 't' ? '&c=t' : '' ?>&r=<?= WH_SITEREV ?>&e=.js"></script>
	<? else: ?>
		<? // HACK FOR OLD iPhone APP ?>
		<script type="text/javascript" src="<?= wfGetPad() ?>/extensions/min/?g=<?= $script ?>&rev=<?= WH_SITEREV ?>"></script>
	<? endif; ?>
<? endforeach; ?>
