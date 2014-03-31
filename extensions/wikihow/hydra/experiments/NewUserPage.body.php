<?php

class NewUserPage extends UnlistedSpecialPage  {

	const EXPERIMENT_NAME = 'new_user_page';
	const PAGE_NAME = 'NewUserPage';

	public function __construct() {
		parent::__construct(self::PAGE_NAME);	
	}
		

	public static function onNewUserRedirect(&$redirectURL) {
		if(Hydra::isEnabled(self::EXPERIMENT_NAME)) {
			$t = Title::makeTitle(NS_SPECIAL, self::PAGE_NAME); 
			// Remove leading slash because redirect functionality is messed up to add slash
			$redirectURL = preg_replace("@^/@","",$t->getLocalURL()); 
		}
		return(true);
	}

	public function execute() {
		global $wgOut;
		EasyTemplate::set_path(dirname(__FILE__));
		$wgOut->setPageTitle("Welcome to wikiHow!");
		$wgOut->addHTML(EasyTemplate::html('NewUserPage.tmpl.php'));
		return true;
	}
}
