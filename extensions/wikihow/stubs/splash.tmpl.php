<!DOCTYPE html>
<html>
<head>
<title>Rate our Works-in-Progress!</title>
<meta name="viewport" content="width=device-width" /> 
<meta name="apple-mobile-web-app-capable" content="yes" />
<?= $GLOBALS['wgOut']->getHeadScripts() ?>
<?= Skin::makeGlobalVariablesScript(array('skinname' => 'mobile')) ?>
<script src="<?= wfGetPad('/extensions/min/g/mjq,mwh,mga,stb&rev=') . WH_SITEREV ?>"></script>
<link type="text/css" rel="stylesheet" href="<?= wfGetPad('/extensions/min/g/mwhc,mwhf,mwhh&rev=') . WH_SITEREV ?>" />
<style>
.overlay {
	position: fixed;
	z-index: 1;
	background-color: #F4F4F4;
}
.inner {
	z-index: 2;
	background-color: #F4F4F4;
	height: 300px;
	width: 250px;
	margin: 25px auto;
	text-align: center;
}
.title {
	text-align: center;
}
#hill-can-meter {
	background: url(/extensions/wikihow/stubs/images/hillary_meter.png) no-repeat -69px -80px;
	background-size: 300px;
	width: 163px;
	height: 86px;
	position: absolute;
	margin: 24px 0 0 43px;
}
#hill-can {
	position: relative;
	z-index: 1;
	width: 163px;
	top: 22px;
}

.hillary-head {
	background-color: #93b874;
	height: 48px;
	text-align: center;
	color: #FFF;
	font-size: .75em;
	font-weight: bold;
}
.hillary-wikihow {
	padding-top: 16px;
	width: 83px;
	height: 15px;
}
.main {
	padding: 12px;
}
.main p {
	padding: 10px 0;
}
.button-p { 
	text-align: center;
	margin: 10px 0; 
}
.hil-start {
	background-color: #93b874;
	color: #FFF;
	border-radius: 5px;
	padding: 8px;
	
}
.yes, .no {
	font-weight: bold;
}
.ww {
	font-style: italic;
}
.list {
	margin-left: 20px;
</style>

</head>
<body>
<div class="hillary-head">
	<img src="<?=wfGetPad('/extensions/wikihow/stubs/images/hillary_wikihow.png')?>" class="hillary-wikihow" />
</div>

<div class="overlay" style="display:none">
	<div class="inner">
		<div class="title">Article Quality Guardian</div>
		<div id="hill-can-meter"></div>
		<canvas id="hill-can"></canvas>
	</div>
</div>

<div class="main">
	<p>We need your help determining if articles on wikiHow are helpful or not. So how does it work?</p>

	<ul class="list">
	<li>If you think an article is helpful, press <span class="yes">YES</span>.</li>
	<li>If you think an article is not helpful, press <span class="yes">NO</span>.</li>
	<li>If you are not sure, press <span class="yes">SKIP</span>.</li>
	</ul>

	<p>We'll add up the results, and will decide what articles are worthy of sticking around on wikiHow. Thanks for your help!</p>

	<p class="button-p"><a href="<?= $firstURL ?>" class="hil-start">Get Started</a></p>
</div>
<script>
$(document).ready( function () {

	// get the screen height and width
	var maskHeight = $(document).height();
	var maskWidth = $(window).width();

	// set height and width of overlay to fill up the whole screen
	$('.overlay')
		.css({'width':maskWidth,'height':maskHeight})
		.show();
		
	// get the window height and width
	var winH = $(window).height();
	var winW = $(window).width();
		  
	// set the overlay dialog to center
	var id = $('.inner');
	id.css('top',  winH/2-id.height()/2);
	id.css('left', winW/2-id.width()/2);

	WH.Stubs.absSetTick(-2);
	WH.Stubs.initGauge();
	
	// do a little needle animation before we fade
	// away this overlay
	setTimeout(function () {
		WH.Stubs.absSetTick(3);
		WH.Stubs.setGaugeTick();
		setTimeout(function () {
			WH.Stubs.absSetTick(1);
			WH.Stubs.setGaugeTick();
			setTimeout(function () {
				$('.overlay').fadeOut();
			}, 1000);
		}, 1000);
	}, 1000);
});
</script>
</body>
</html>
