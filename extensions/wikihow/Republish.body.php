<?php
class Republish extends SpecialPage {

    function __construct() {
        parent::__construct( 'Republish' );
    }

	function getRepublishText($t){
		global $wgRequest, $wgContLang, $wgOut, $wgServer;

		if (!$t) return "";
		$r = Revision::newFromTitle($t);
		$mp = Title::newMainPage();
		$title = "<h1 style='margin-bottom: 0px;'><a href=\"" . $t->getFullURL() ."\">"  . wfMsg('howto', $t->getText()) . "</a></h1>\n"  . wfMsg('republish_taglinelink', $mp->getFullURL()) . "<br/>";
		if (!$r) return "";
		$text = $r->getText();
		if ($wgRequest->getVal('striptags', 'true') != 'false')	
			$text = preg_replace("/\[\[" .  $wgContLang->getNSText ( NS_IMAGE )  ."[^\]]*\]\]/", "", $text);
		$output = $wgOut->parse($text);
	
		//$output = str_replace("<div class='SecL'></div><div class='SecR'></div>", "", $output);
		//$output = preg_replace("/<div id=\"[a-z]*\"/", "<div ", $output);
		$output = str_replace('href="/', 'href="' . $wgServer . '/', $output);
		if ($wgRequest->getVal('striptags', 'true') != 'false')	{
			$output = strip_tags($output, '<b><i><h1><h2><ol><ul><li><a>');	
		} else {
			$output = str_replace('<img src="/', '<img src="http://www.wikihow.com/', $output);
		}
		$output = preg_replace("@href=(['\"])/@", "href=$1{$wgServer}/", $output);
		$output = $title . "\n" . $output . wfMsg('republish_footer', $t->getText(), $t->getFullURL());
		$output = preg_replace("/\n\n[\n]*/", "\n", $output);
		return $output;
	}

	function execute ($par) {
	    global $wgRequest, $wgSitename, $wgLanguageCode;
	    global $wgDeferredUpdateList, $wgOut, $wgUser, $wgServer, $wgContLang;
	    $fname = "wfRepublish";

		$this->setHeaders();	
		$sk = $wgUser->getSkin();
	
		$target= $par != '' ? $par : $wgRequest->getVal('target');
	
		if ($target =='') {
			$wgOut->addHTML(wfMsg('articlestats_notitle'));
			return;
		}
	
		$t = Title::newFromText($target);
		$id = $t->getArticleID();
		if ($id == 0) {
			$wgOut->addHTML(wfMsg("checkquality_titlenonexistant"));
			return;
		}
	
		$output = Republish::getRepublishText($t);
	
		$wgOut->addHTML(
				"
				<script type='text/javascript' src='" . wfGetPad('/extensions/min/f/skins/WikiHow/sharetab.js?') . WH_SITEREV . "'></script>
				<center>
				<textarea id='output' style='font-size: 1.0em; width:500px;' rows='8' cols='10'>$output</textarea>
				</center>"
				. wfMsg('republish_quicklinks',  urlencode($t->getText()), urlencode($t->getFullURL()) ) . "<br/>"
				. wfMsg('republish_instructions') 
			);
				
		$wgOut->addHTML("<br/>
			<style type='text/css'>
				#preview h2 {
					background: none;
					color: #000;
					border: none;
					padding: 0px;
					margin-left: 0px;
					font-size: 100%;	
				}
			</style>" 
			//. wfMsg('republish_preview') . 
			//"<br/><br/><div style='border: 1px solid #eee; padding: 10px;' id='preview'>$output</div>"
			. "<script type='text/javascript'>
				var txtarea = document.getElementById('output');
				txtarea.focus();
				txtarea.select();	
			</script>
			");
	
	}

	function getRepublishFooter($title) {
		if (!$title) return "";
		$output = Republish::getRepublishText($title);
		$result = wfMsg('republish_box', $title->getPrefixedUrl(), $title->getFullUrl(), $output);
		$result = preg_replace('/\<[\/]?pre\>/', '', $result);
		return $result;
	}
}
