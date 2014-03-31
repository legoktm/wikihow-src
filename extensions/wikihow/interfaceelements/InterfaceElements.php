<?php

Class InterfaceElements {
	public function addBubbleTipToElement($element, $cookiePrefix, $text) {
		global $wgOut;

		$wgOut->addJSCode('jqck'); //jQuery Cookie. Add as JS code so we don't have duplicate includes
		$wgOut->addCSSCode('tbc'); // Tips Bubble CSS

		InterfaceElements::addJSVars(array('bubble_target_id' => $element, 'cookieName' => $cookiePrefix.'_b'));
		$wgOut->addHTML(HtmlSnips::makeUrlTags('js', array('interfaceelements/tipsbubble.js'), 'extensions/wikihow', false));

		$tmpl = new EasyTemplate(dirname(__FILE__));

		$tmpl->set_vars(array('text' => $text));
		$wgOut->addHTML($tmpl->execute('TipsBubble.tmpl.php'));
	}

	public function addJSVars($data) {
		global $wgOut;
		$text = "";
		foreach($data as $key => $val) {
			$text = $text."var ".$key." = ".json_encode($val).";";
		}
		$wgOut->addHTML(Html::inlineScript("\n$text\n") . "\n");
	}
}
