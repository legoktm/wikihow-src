<?php

class OptimizelyPageSelector extends UnlistedSpecialPage {
	public function  __construct() {
		parent::__construct("OptimizelyPageSelector");	
	}
	// We will hash, and allow about this percent of articles
	const ARTICLE_PCT = 1.0;

	public static function getOptimizelyTag() {
		$tag = "<script type=\"text/javascript\">if(window['optimizely'] == undefined) { window['optimizely'] = [] } window['optimizely'].push(['addToSegment', 'wikihow_user_name', wgUserName]);</script>"; 
		if(IS_PROD_EN_SITE) {
			$tag .= "<script src=\"//cdn.optimizely.com/js/526710254.js\"></script>";
		}
		else {
			$tag .= "<script src=\"//cdn.optimizely.com/js/539020690.js\"></script>";
		}
		return($tag);
	}

	/*
	 * Check if we enable optimizely for a user. We disable 
	 * old users.
	 */
	public static function isUserEnabled($user) {
		// We enable Optimizely for anons	
		$user->load();
		if($user->getId() ==0) {
			return(true);	
		}
		// Get registration date
		$registration = $user->getRegistration();
		// Users registered before registration was kept are old
		if(!$registration) {
			return(false);	
		}
		$registration = wfTimestamp(TS_UNIX, $registration);
		$oldDate = wfTimestamp(TS_UNIX, "20131209000000");
		return($registration > $oldDate);
	}

	/* 
	 * Determine if we should show optimizely on this page
	 * @param articleName Name of the article we want to determine whether to show
	 */
	public static function isArticleEnabled($title) {
		global $wgLanguageCode;

		// Turn off optimizely if we didn't get an article name, or we aren't in English
		if(!$title) {
			return(false);	
		}
		if($wgLanguageCode != "en") {
			return(false);	
		}
		
		$articleName = $title->getText();
		if(!$articleName) {
			return(false);	
		}
		
		//Put Optimizely on all non-mamespace pages
		if($title->getNamespace() != NS_MAIN) {
			return(true);	
		}
		$hash = crc32($articleName);
		if( ((($hash % 1000)) / 999.0) <= self::ARTICLE_PCT) {
			return(true);	
		}
	}

	/*
	 * Provide a tool to see which URLs are enabled for Optimizely
	 */
	public function execute() {
		global $wgRequest, $wgUser, $wgOut;

		$userGroups = $wgUser->getGroups();
		if (!in_array('staff', $userGroups)) {                                             
			$wgOut->setRobotpolicy('noindex,nofollow');
			$wgOut->showErrorPage('nosuchspecialpage', 'nospecialpagetext');
			return;
		}

		$urls = $wgRequest->getVal('urls');

		if($urls) {
			header("Content-Type: text/tsv");
			header('Content-Disposition: attachment; filename="output.xls"');

			$urls = preg_split("@[\r\n]@",urldecode($urls)); 
			foreach($urls as $url) {
				print($url . "\t");
				if(preg_match("@http://www.wikihow.com/([^?]+)(\?|$)@",$url,$matches)) {
					$t = Title::newFromText($matches[1]);
					if(!$t || !$t->exists()) {
						$t = Title::newFromText(urldecode($matches[1]));
					}
					if(!$t || !$t->exists()) {
						print("Not found");	
					}
					else {
						print(self::isArticleEnabled($t) ? "1" : "0");	
					}
				}
				else {
					print("Not found");	
				}
				print("\n");
			}
			exit(0);
		}
		else {
			$wgOut->addScript(HtmlSnips::makeUrlTags('js', array('download.jQuery.js'), 'extensions/wikihow/common', false));
			$wgOut->addScript(HtmlSnips::makeUrlTags('js', array('jquery.sqlbuilder-0.06.js'), 'extensions/wikihow/titus', false));
			EasyTemplate::set_path(dirname(__FILE__).'/');                                                                                                                
			$wgOut->addHTML(EasyTemplate::html('optimizelytool.tmpl.php'));
		}
	}
}
