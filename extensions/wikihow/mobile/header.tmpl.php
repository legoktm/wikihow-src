<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="<?= $lang ?>" lang="<?= $lang ?>">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<meta name="viewport" content="width=device-width" />
	<meta name="apple-mobile-web-app-capable" content="yes" />
	<meta name="google-site-verification" content="Jb3uMWyKPQ3B9lzp5hZvJjITDKG8xI8mnEpWifGXUb0" />
	<title><?= $htmlTitle ?></title>
	<?= $headLinks ?>
	<? if ($showRUM): ?>
	<script>
window.UVPERF = {};
UVPERF.authtoken = 'b473c3f9-a845-4dc3-9432-7ad0441e00c3';
UVPERF.start = new Date().getTime();
	</script>
	<? endif; ?>
	<? if ($deviceOpts['show-css']): ?>
		<? if ($deviceOpts['name'] != 'iphoneapp'): ?>
			<link href="/extensions/min/g/<?= join(',', $css) ?><?= isset($GLOBALS['wgRequest']) && $GLOBALS['wgRequest']->getVal('c') == 't' ? '&c=t' : '' ?>&r=<?= WH_SITEREV ?>&e=.css" rel="stylesheet" type="text/css" />
		<? else: ?>
			<? // HACK FOR OLD iPhone APP ?>
			<link href="<?= wfGetPad() ?>/extensions/min/?g=<?= join(',', $css) ?>&rev=<?= WH_SITEREV ?>" rel="stylesheet" type="text/css" />
		<? endif; ?>
	<? endif; ?>
	<? global $IP; include("$IP/extensions/wikihow/mobile/image-swap-js.tmpl.php"); ?>

	<script type="text/javascript">
		WH.exitTimerStartTime = (new Date).getTime();
	</script>
	<link rel="apple-touch-icon" href="<?= wfGetPad('/skins/WikiHow/safari-large-icon.png') ?>" />
	<link rel='canonical' href='<?= htmlentities($canonicalUrl) ?>'/>
	<? if (!$pageExists): ?>
		<meta name="robots" content="noindex,nofollow" />
	<? endif; ?>
</head>
<body>

	<? if ($deviceOpts['show-header-footer']): ?>
		<div id="header">
			<div id="header_logo">
				<? if (!$isMainPage): ?> <a href="/<?= wfMsg('mainpageurl') ?>" class="logo"></a> <? endif; ?>
			</div>
			<div id="header_search">
				<?= EasyTemplate::html('search-box.tmpl.php',array('screen_width'=>$deviceOpts['screen-width'])) ?>
			</div>
			<a href="<?=$loginlink?>" id="header_login"><?=$logintext?></a>
		</div>

		<? if (class_exists('Hillary')): ?>
			<?= Hillary::getContainer() ?>
		<? endif; ?>

		<div class="search_static"></div>
	<? endif; ?>

	<? // Firefox? NO SEARCH FOR YOU! ?>
	<script type="text/javascript">
		var ua = navigator.userAgent;
		if (ua && ua.indexOf('Firefox') > 0 && ua.indexOf('Mobile; rv') > 0) {
			var search = document.getElementsByClassName('search');
			for (var i = 0; i < search.length; i++) {
				search[i].setAttribute('style', 'display:none');
			}
		}
	</script>
