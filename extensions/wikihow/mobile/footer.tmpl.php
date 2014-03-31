
	<? if ($deviceOpts['show-header-footer']): ?>
		<? if ($showAds): ?>
			<script type="text/javascript"><!--
			window.googleAfmcRequest = {
				client: 'ca-mb-pub-9543332082073187',
				ad_type: 'text_image',
				output: 'html',
				channel: '2856335553',
				format: '320x50_mb',
				oe: 'utf8',
				color_border: 'ece9e3',
				color_bg: 'ece9e3',
				color_link: '23198c',
				color_text: '000000',
				color_url: '3a6435'
			};
			//--></script>
			<script type="text/javascript" src="http://pagead2.googlesyndication.com/pagead/show_afmc_ads.js"></script>
		<? endif; ?>
		<div id="footer">
			<? if (!$isMainPage && $showSharing) { ?>
			<div id="sharing">
				<div id="sharing_inner">
					<div class="g-plusone" data-size="medium" data-href="<?=$pageUrl?>"></div>
				</div>
			</div>
			<? } ?>
			<?= EasyTemplate::html('search-box.tmpl.php') ?>
			<ul id="footer_links">
				<li class="nodot"><a href="<?= $redirMainUrl ?>" rel="nofollow"><?= wfMsg('full-site') ?></a></li>
				<? if (!empty($editUrl)): ?>
					<li><a href="<?= $editUrl ?>"><?= wfMsg('edit') ?></a></li>
				<? endif; ?>
				<li><a href="/Special:Randomizer"><?=wfMsg('randompage'); ?></a></li>
			</ul><? //end footer_links ?>
		</div>
		<div id="footbar">
			<ul>
				<li id="<?= $isEnglish ? 'footbar_logo' : 'footbar_logo_intl' ?>"><? if (!$isMainPage): ?><a href="/<?= wfMsg('mainpageurl') ?>"></a><? endif; ?></li>
				<li><? print wfMsg("CommunityDashboardLink"); ?></li>
				<? if ($isEnglish): ?>
				<li><a href="/Special:ArticleQualityGuardian">HELP US</a></li>
				<? endif; ?>
				<li><a href="<?= $redirMainUrl ?>" rel="nofollow"><? print wfMsg('full_site'); ?></a></li>
				<li id="footbar_random"><a href="/Special:Randomizer"><img src="data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0idXRmLTgiPz4NCjwhLS0gR2VuZXJhdG9yOiBBZG9iZSBJbGx1c3RyYXRvciAxNi4wLjAsIFNWRyBFeHBvcnQgUGx1Zy1JbiAuIFNWRyBWZXJzaW9uOiA2LjAwIEJ1aWxkIDApICAtLT4NCjwhRE9DVFlQRSBzdmcgUFVCTElDICItLy9XM0MvL0RURCBTVkcgMS4xLy9FTiIgImh0dHA6Ly93d3cudzMub3JnL0dyYXBoaWNzL1NWRy8xLjEvRFREL3N2ZzExLmR0ZCI+DQo8c3ZnIHZlcnNpb249IjEuMSIgaWQ9IkxheWVyXzEiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyIgeG1sbnM6eGxpbms9Imh0dHA6Ly93d3cudzMub3JnLzE5OTkveGxpbmsiIHg9IjBweCIgeT0iMHB4Ig0KCSB3aWR0aD0iMTI4cHgiIGhlaWdodD0iOTguNDI5cHgiIHZpZXdCb3g9IjAgMCAxMjggOTguNDI5IiBlbmFibGUtYmFja2dyb3VuZD0ibmV3IDAgMCAxMjggOTguNDI5IiB4bWw6c3BhY2U9InByZXNlcnZlIj4NCjxnPg0KCTxwYXRoIGZpbGwtcnVsZT0iZXZlbm9kZCIgY2xpcC1ydWxlPSJldmVub2RkIiBmaWxsPSIjNTc3MTQyIiBkPSJNMCw2Ni4wNjljMTEuNjE4LDAuMDAzLDIzLjI0MS0wLjE3OSwzNC44NTMsMC4xDQoJCWM0Ljc3OCwwLjExNCw4LjIxNS0xLjcxNCwxMS4yMDUtNS4wNzJjNS4yMjctNS44NzIsOS4wMTctMTIuNzA4LDEzLjA0OC0xOS4zODFjMy42ODktNi4xMSw3LjM4NS0xMi4yMDgsMTIuMDQtMTcuNjYzDQoJCWM1Ljg3OC02Ljg4NywxMi45NzYtMTAuNDIsMjIuMzY2LTEwLjQyYzkuNzIyLDAtMC4wODcsMC4wNTQsOS43MTEsMC4wNTRjMS40ODUsMCwxLjk1My0wLjQ2MSwxLjk1My0xLjk1OQ0KCQljMC0xMS41Ni0wLjAyOCwwLjE1NC0wLjAyOC0xMS43MjdjMy4yMDUsMi45MTksNS45MTcsNS4zNzQsOC42MTEsNy44NDhjNC4xMzYsMy43OTcsOC4yNTQsNy42MTQsMTIuMzk3LDExLjQwNA0KCQljMC41MjYsMC40ODItMC4wMTktMC4wMDIsMS44MzgsMS42NzZjMCwwLDAsMCwwLTAuMDAxYy03LjQ0MSw2Ljc2NS0xNC44ODQsMTQuMTAxLTIyLjg0NiwyMS4zMzkNCgkJYzAtMTEuMDUzLDAuMDUyLTAuMzg3LDAuMDUyLTExLjEzNWMwLTIuMjE1LTAuNjU0LTIuNjY5LTIuNzM5LTIuNjY5Yy05Ljc5OCwwLTAuMDg0LDAuMDA3LTkuOTgxLDAuMDA3DQoJCWMtMy40MTEsMC02LjA1NywxLjE0OS04LjM4NiwzLjQ3N2MtNC4yMSw0LjIwOC03LjI3NCw5LjI0NC0xMC4zNTgsMTQuMjYxYy00LjYyMiw3LjUyNC04LjgyLDE1LjMyOS0xNC40MTEsMjIuMjE2DQoJCWMtNC43NTIsNS44NTQtMTAuMTY3LDEwLjgxOS0xNy45ODcsMTIuMTUzYy0xLjIwOCwwLjIwNy0yLjQ1MywwLjI3Ny0zLjY4MSwwLjI4MUMyNS4xMDQsODAuODk2LDEyLjU1Miw4MC45MDcsMCw4MC45MjUNCgkJQzAsNzUuOTczLDAsNzEuMDIxLDAsNjYuMDY5eiIvPg0KCTxwYXRoIGZpbGwtcnVsZT0iZXZlbm9kZCIgY2xpcC1ydWxlPSJldmVub2RkIiBmaWxsPSIjNTc3MTQyIiBkPSJNMTA1LjIwMyw5OC40MjljMC0zLjIwNSwwLTYuNzE5LDAtMTAuNTMxDQoJCWMwLTIuODgzLTAuNTM1LTMuOTA2LTMuNjM1LTMuNzk5Yy00LjkyMiwwLjE3LTkuODkzLDAuNTMzLTE0Ljc5OC0wLjM0NWMtNy4zMzEtMS4zMTMtMTIuNzQ2LTUuNTY0LTE3LjE3Ni0xMS4zMTYNCgkJYy0wLjY1Ni0wLjg1My0wLjQ2NC0xLjUzMiwwLjA1My0yLjMxNWMyLjYwNi0zLjk1MSw1LjE5My03LjkxNSw3Ljk5My0xMi4xODhjMS43NTUsMi41NTgsMy4xOSw0Ljk4Myw1LjA3Niw3LjA3Mw0KCQljMi42MjIsMi45MDUsNS42ODcsNC4zNzcsOS43NTcsNC4zNzdjMTAuMzMyLDAsMC4wOTQsMCwxMC4yNjksMGMxLjc5NiwwLDIuNDA2LTAuNDA4LDIuNDA2LTIuMzcxYzAtNy43MjksMC03LjE3OSwwLTExLjQxNQ0KCQljNy45MDUsNy4xODMsMTUuMzc2LDEzLjM5NywyMi44NDYsMjAuMTg0YzAuMDEzLDAsMC0wLjAxMiwwLTAuMDAxTDEwNS4yMDMsOTguNDI5eiIvPg0KCTxwYXRoIGZpbGwtcnVsZT0iZXZlbm9kZCIgY2xpcC1ydWxlPSJldmVub2RkIiBmaWxsPSIjNTc3MTQyIiBkPSJNMCwxNi45MjhjMTIuNTY2LDAuMDE4LDI1LjEzMiwwLjAzOCwzNy42OTksMC4wNTUNCgkJYzYuODE1LDAuMDEsMTIuNTM5LDIuNjk1LDE3LjQ5NSw3LjE5NmMwLjc4NywwLjcxNSwxLjc5MiwxLjQwNiwwLjg2OCwyLjg1OWMtMi41OSw0LjA3Ni01LjA1NCw4LjIzMS03LjU4MywxMi4zODINCgkJYy0xLjM2LTEuNTMtMi40NzMtMi45NjMtMy43NzEtNC4yMDRjLTIuNDE4LTIuMzExLTUuMjExLTMuNTM1LTguNzIyLTMuNDk0QzIzLjk5MiwzMS44NiwxMS45OTYsMzEuNzgxLDAsMzEuNzg1DQoJCUMwLDI2LjgzMywwLDIxLjg4LDAsMTYuOTI4eiIvPg0KPC9nPg0KPC9zdmc+DQo=" width="26" height="20" /> <? print wfMsg('random') ?></a></li>
				<? if (!$isMainPage) { ?>
				<li class="foot_edit"><a href="<?= $editUrl ?>"><img src="data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0idXRmLTgiPz4KPCFET0NUWVBFIHN2ZyBQVUJMSUMgIi0vL1czQy8vRFREIFNWRyAxLjEvL0VOIiAiaHR0cDovL3d3dy53My5vcmcvR3JhcGhpY3MvU1ZHLzEuMS9EVEQvc3ZnMTEuZHRkIj4KPHN2ZyB2ZXJzaW9uPSIxLjEiIGlkPSJMYXllcl8xIiB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHhtbG5zOnhsaW5rPSJodHRwOi8vd3d3LnczLm9yZy8xOTk5L3hsaW5rIiB4PSIwcHgiIHk9IjBweCIKCSB3aWR0aD0iMTAwcHgiIGhlaWdodD0iMTAwLjAwMXB4IiB2aWV3Qm94PSIwIDAgMTAwIDEwMC4wMDEiIGVuYWJsZS1iYWNrZ3JvdW5kPSJuZXcgMCAwIDEwMCAxMDAuMDAxIiB4bWw6c3BhY2U9InByZXNlcnZlIj4KPHBhdGggZmlsbD0iI0ZGRkZGRiIgZD0iTTg3LjAwMSwzMy41MDZsMTEuNjg3LTExLjY4NWMxLjgwMy0xLjgwNCwxLjc0MS00LjgxNy0wLjE0LTYuNjk3TDg0Ljg3NiwxLjQ1MQoJYy0xLjg3OS0xLjg3OS00Ljg5My0xLjk0MS02LjY5Ny0wLjEzOEw2Ni40OTQsMTIuOTk5TDg3LjAwMSwzMy41MDZ6Ii8+CjxyZWN0IHg9IjE2LjQyOSIgeT0iMzUuNTM5IiB0cmFuc2Zvcm09Im1hdHJpeCgtMC43MDcxIDAuNzA3MSAtMC43MDcxIC0wLjcwNzEgMTIwLjY3MjcgNTAuMDk1OCkiIGZpbGw9IiNGRkZGRkYiIHdpZHRoPSI2Ny4wNjUiIGhlaWdodD0iMjkuMDAyIi8+CjxnPgoJPHBvbHlnb24gZmlsbD0iI0ZGRkZGRiIgcG9pbnRzPSIxNy43MzUsOTMuMTQ2IDMzLjQyNyw4Ny4wOCAyMy4xNzQsNzYuODI2IDEyLjkyLDY2LjU3MiA2Ljg1NSw4Mi4yNjYgCSIvPgoJPHBvbHlnb24gZmlsbD0iI0ZGRkZGRiIgcG9pbnRzPSI1LjE0LDg2LjcwMiAwLDEwMC4wMDEgMTMuMjk4LDk0Ljg2IAkiLz4KPC9nPgo8L3N2Zz4K" width="20" height="20" /> <? print wfMsg('edit_caps') ?></a></li>
				<? } ?>
			</ul>
		</div>
	<? endif; ?>

	<? // before deferred js ?>
	<?= MobileHtmlBuilder::showDeferredJS($deviceOpts) ?>
	<? // after deferred js ?>

	<?= MobileHtmlBuilder::showBootStrapScript() ?>
	<?= Wikihow_i18n::genJSMsgs(array('facebook_locale')) ?>

	<? if ($deviceOpts['show-analytics']): ?>
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
	<? endif; ?>
	<? if ($showOptimizely && class_exists('OptimizelyPageSelector')): ?>
		<?php print OptimizelyPageSelector::getOptimizelyTag() ?>
	<? endif; ?>
	<? if ($showClickIgniter): ?>
	<script type="text/javascript">
	(function() {
		var ci = document.createElement('script'); ci.type = 'text/javascript'; ci.async = true;
		ci.src = 'http://cdn.clickigniter.io/ci.js';
		var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(ci, s);
	})();
	</script>
	<? endif; ?>
	<? if ($showGoSquared): ?>
	<script type="text/javascript">
		var GoSquared = {};
		GoSquared.acct = "GSN-491441-Y";
		(function(w){
			function gs(){
				w._gstc_lt = +new Date;
				var d = document, g = d.createElement("script");
				g.type = "text/javascript";
				g.src = "//d1l6p2sc9645hc.cloudfront.net/tracker.js";
				g.async = true;
				var s = d.getElementsByTagName("script")[0];
				s.parentNode.insertBefore(g, s);
			}
			w.addEventListener ?
			w.addEventListener("load", gs, false) :
			w.attachEvent("onload", gs);
		})(window);
	</script>
	<? endif; ?>
	<? if ($showRUM): ?>
	<script>
		(function(){
			var a=document.createElement('script'); a.type='text/javascript'; a.async=true;
			a.src='//yxjj4c.rumanalytics.com/sampler/basic2';
			var b=document.getElementsByTagName('script')[0]; b.parentNode.insertBefore(a,b);
		})();
	</script>
	<? endif; ?>
	<script type="text/javascript">
		var ua = navigator.userAgent;
		if ((ua) && (ua.indexOf('Firefox') > 0) && (ua.indexOf('Mobile; rv') > 0)) {
			//Firefox? NO SEARCH FOR YOU!
			$('.search').css('display','none');
		}
	</script>
	<?= wfReportTime() ?>
</body>
</html>
