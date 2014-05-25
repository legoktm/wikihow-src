<?
/*
* A visual tool to create new wikiHow articles
*/
class ArticleCreator extends SpecialPage {
	// You can set this to false for debugging purposes
	// but it should be set true in production
	var $onlyEditNewArticles = true;
	
	function __construct() {
		global $wgHooks;
		parent::__construct('ArticleCreator');
		$wgHooks['getToolStatus'][] = array('Misc::defineAsTool');
	}

	function execute($par) {
		$context = $this->getContext();
		$request = $context->getRequest();
		$out = $context->getOutput();
		
		if ($request->getVal('ac_created_dialog', 0)) {
			$out->setArticleBodyOnly(true);
			$out->addHtml($this->getCreatedDialogHtml());
			return; 
		}
		
 		$out->addCSSCode('acc');
 		//$out->addJSCode('ac');
 		$out->addJSCode('jqs');
 		$out->addJSCode('tmc');
 		
 		$out->addModules( 'ext.guidedTour' );  // Used for showing validation responses
		$out->addModules('ext.wikihow.articlecreator'); // Module to enable mw messages in javascript
			
		$t = Title::newFromText($request->getVal('t'));
		$out->setHTMLTitle(wfMessage('ac-html-title', $t->getText()));
			
		if ($request->wasPosted()) {
			$out->setArticleBodyOnly(true);
			$response = array();
			$token = $context->getUser()->getEditToken();
			if ($request->getVal('ac_token') != $token) {
				$response['error'] = wfMessage('ac-invalid-edit-token');
			} else if ($this->onlyEditNewArticles && $t->exists()) {
				$response['error'] = wfMessage('ac-title-exists', $t->getEditUrl());
			} else if (!$t->userCan( 'create', $context->getUser(), false)) {
				$response['error'] = wfMessage('ac-cannot-create', $t->getEditUrl());
			} else {
				$a = new Article($t);
				$a->doEdit($request->getVal('wikitext'), wfMessage('ac-edit-summary'));
				// Add an author email notification
				$aen = new AuthorEmailNotification();
				$aen->addNotification($t->getText()); 
				
				$response['success'] = wfMessage('ac-successful-publish');
				$response['url'] = $t->getFullUrl();
			}
			
			$out->addHtml(json_encode($response));	
		} else {
			if ($this->onlyEditNewArticles && $t->exists()) {
				$out->redirect($t->getEditURL());
			} else {
				$this->outputStartupHtml();
			}
		}
	}
	
	private function outputStartupHtml() {
		$out = $this->getContext()->getOutput();
		$request = $this->getContext()->getRequest();
		$t = Title::newFromText($request->getVal('t'));
		$advancedEditLink = $this->getContext()->getSkin()->makeKnownLinkObj($t, wfMessage('advanced_editing_link')->text(), 'action=edit&advanced=true', '','','class="ac_advanced_link"');
		
		$out->addHtml($this->getTemplatesHtml($t));
	
		$sections = array(
			array('name' => wfMessage('ac-section-intro-name')->text(), 
					'token' => $this->getContext()->getUser()->getEditToken(),
					'advancedEditLink' => $advancedEditLink,
					'pageTitle' => $t->getText(),
					'desc' => wfMessage('ac-section-intro-desc')->text(), 
					'buttonTxt' => wfMessage('ac-section-intro-button-txt')->text(),
					'placeholder' => wfMessage('ac-section-intro-placeholder')->text(),
			),
			array('name' => wfMessage('ac-section-steps-name')->text(),  
					'pageTitle' => $t->getText(),
					'methodSelectorText' => wfMessage('ac-method-selector-txt')->text(),
					'addMethodButtonTxt' => wfMessage('ac-section-steps-add-method-button-txt')->text(),
			),
			array('name' => wfMessage('ac-section-tips-name')->text(), 
					'desc' => wfMessage('ac-section-tips-desc')->text(), 
					'buttonTxt' => wfMessage('ac-section-tips-button-txt')->text(),
					'placeholder' => wfMessage('ac-section-tips-placeholder')->text(),
					
			),
			array('name' => wfMessage('ac-section-warnings-name')->text(), 
					'desc' => wfMessage('ac-section-warnings-desc')->text(), 
					'buttonTxt' => wfMessage('ac-section-warnings-button-txt')->text(),
					'placeholder' => wfMessage('ac-section-warnings-placeholder')->text(),
			),
			array('name' => wfMessage('ac-section-sources-name')->text(),
					'desc' => wfMessage('ac-section-sources-desc')->text(),
					'buttonTxt' => wfMessage('ac-section-sources-button-txt')->text(),
					'placeholder' => wfMessage('ac-section-sources-placeholder')->text(),
			),
		);

		foreach ($sections as $section) {
			$section['idname'] = preg_replace('@\ @', '', strtolower($section['name']));
			switch ($section['name']) {
				case 'Steps':
					$out->addHTML($this->getStepsSectionHtml($section));
					break;
				case 'Introduction':
					$out->addHTML($this->getIntroSectionHtml($section));
					break;
				default:
					$out->addHTML($this->getOtherSectionHtml($section));
			}
		}
		$out->addHtml($this->getFooterHtml());		
	}
	
	private function getFooterHtml() {
		EasyTemplate::set_path(dirname(__FILE__).'/');
		return EasyTemplate::html('ac-footer');
	}
		
	private function getTemplatesHtml($t) {
		$vars = array(
			'desc' => wfMessage('ac-section-steps-desc')->text(), 
			'pageTitle' => $t->getText(),
			'doneButtonTxt' => wfMessage('ac-section-steps-method-done-button-txt')->text(),
			'addMethodButtonTxt' => wfMessage('ac-section-steps-add-method-button-txt')->text(),
			'buttonTxt' => wfMessage('ac-section-steps-button-txt'),
			'nameMethodPlaceholder' => wfMessage('ac-section-steps-name-method-placeholder')->text(),
			'addStepPlaceholder' => wfMessage('ac-section-steps-addstep-placeholder')->text(),
			'copyWikitextMsg' => wfMessage('ac-copy-wikitext')->text(),
		);
		EasyTemplate::set_path(dirname(__FILE__).'/');
		return EasyTemplate::html('ac-html-templates', $vars);
	}
	
	private function getMethodSelectorHtml() {
		EasyTemplate::set_path(dirname(__FILE__).'/');
		return EasyTemplate::html('ac-method-selector');
	}
	
	private function getStepsSectionHtml(&$section) {
		EasyTemplate::set_path(dirname(__FILE__).'/');
		return EasyTemplate::html('ac-steps-section', $section);
	}
	
	private function getIntroSectionHtml(&$section) {
		EasyTemplate::set_path(dirname(__FILE__).'/');
		
		return EasyTemplate::html('ac-intro', $section);
	}
	
	private function getOtherSectionHtml(&$section) {
		EasyTemplate::set_path(dirname(__FILE__).'/');
		return EasyTemplate::html('ac-section', $section);
	}
	
	private function getCreatedDialogHtml() {
		EasyTemplate::set_path(dirname(__FILE__).'/');
		$vars['dialogStyle'] = "<link type='text/css' rel='stylesheet' href='" . 
			wfGetPad('/extensions/wikihow/common/jquery-ui-themes/jquery-ui.css?rev=' . WH_SITEREV) . "' />\n";
		return EasyTemplate::html('ac-created-dialog', $vars);
	}
	
	public static function printArticleCreatedScript($t) {
		global $wgUser;
		$aid = $t->getArticleId();
	
 		setcookie('aen_dialog_check', $aid, time()+3600);
		 echo '
		  <script type="text/javascript">
		  var whNewLoadFunc = function() {
		  	if ( getCookie("aen_dialog_check") != "" ) {
		  		var url = "/extensions/wikihow/common/jquery-ui-1.9.2.custom/js/jquery-ui-1.9.2.custom.min.js";
		  		$.getScript(url, function() {
		  			$("#dialog-box").load("/Special:ArticleCreator?ac_created_dialog=1");
		  			$("#dialog-box").dialog({
		  				modal: true,
		  				closeText: "Close",
		 				width: 600,
		 				height: 200,
		 				position: "center",
		  				title: "' . wfMessage('createpage_congratulations')->text() . '"
		  			});
		  			deleteCookie("aen_dialog_check");
		  		});
		  	}
		  };

		  $(window).load(whNewLoadFunc);

		  </script>
		';	  
	}
	
	public static function onEditFormPreloadText( &$text, &$title ) {
		global $wgRequest;
		if ($wikitext = $wgRequest->getVal('ac_wikitext')) {
			$text = $wikitext;
		}
		return true;
	}
}
