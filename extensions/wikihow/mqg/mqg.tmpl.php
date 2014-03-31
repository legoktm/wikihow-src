<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<meta name="viewport" content="width=device-width" /> 
	<meta name="apple-mobile-web-app-capable" content="yes" />
	<meta name="google-site-verification" content="Jb3uMWyKPQ3B9lzp5hZvJjITDKG8xI8mnEpWifGXUb0" />
	<title><?=$mqg_title?></title>
	<link href="<?=wfGetPad('/extensions/min/?g=mwhc,mwha')?>&rev=<?=WH_SITEREV ?>" rel="stylesheet" type="text/css" /> 
	<?=$mqg_css?>
	<script type="text/javascript">
		var WH_SITEREV = <?= '"' . WH_SITEREV , '"'?>;
	</script>
	<script type="text/javascript" src="<?= wfGetPad('/extensions/min/?g=mjq,mwh,mga,mah') ?>&rev=<?= WH_SITEREV ?>"></script>
	<?=$mqg_js?>
	<link rel="apple-touch-icon" href="<?= wfGetPad('/skins/WikiHow/safari-large-icon.png') ?>" />
	<link rel="apple-touch-icon" sizes="114x114" href="<?= wfGetPad('/skins/WikiHow/safari-large-icon.png') ?>" />
	<meta name="robots" content="noindex,nofollow" />
</head>
<body>
	<div id="mqg_preload">
		<div class='mqg_yes'></div>
		<div class='mqg_no'></div>
		<div class='mqg_skip'></div>
	</div>
	<div id="header">
		<div id="header_logo">
			<a href="/<?= wfMsg('mainpageurl') ?>" class="logo">
				<img src="<?= wfGetPad('/skins/WikiHow/images/wikihow.png') ?>" alt="WikiHow" />
			</a>
			<a href="<?= $randomUrl ?>" class="surprise"><?=wfMsg('surprise-me') ?></a>
		</div><!--end header_logo-->
		<? if (@$showTagline): ?>
		<div id="tagline">
		  <blockquote><p><?= wfMsg('mobile-tagline') ?></p></blockquote>
		</div><!--end tagline-->
		<? endif; ?>
	</div>
	<div id='mqg_eml_box' class='mqg_rounded'>
		<div class = "mqg_eml_txt">
			<img src="<?=wfGetPad('/skins/WikiHow/images/mqg_eml.png')?>"></img>
			<?=wfMsg('mqg_eml_txt')?></div>
		<div class="mqg_eml_input">Email Address: <input type='text' id='mqg_eml'></input></div>
		<div class="mqg_eml_buttons"><a id="mqg_dismiss" href="#">No, thanks</a> <a id="mqg_ok" href="#" class="button button52">OK</a></div>
	</div>
	<div id="mqg_spinner">
		<img src="<?=wfGetPad('/extensions/wikihow/rotate.gif')?>" alt=" "></img>
	</div>
	<div id='mqg_body'>
	</div>
	<script type="text/javascript">
		var _gaq = _gaq || [];
		_gaq.push(['_setAccount', 'UA-2375655-1']);
		_gaq.push(['_setDomainName', '.wikihow.com']);
		_gaq.push(['_trackPageview']);
		(function() {
			var ga = document.createElement('script'); ga.type = 'text/javascript'; ga.async = true;
			ga.src = ('https:' == document.location.protocol ? 'https://ssl' : 'http://www') + '.google-analytics.com/ga.js';
			var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(ga, s);
		})();
	</script>
</body>
</html>
