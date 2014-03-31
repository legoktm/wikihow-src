<?php

class EditRedirect2 extends UnlistedSpecialPage
{
	const OUR_SESSION_NAME = 'hydra_editredirect';
	const EXPERIMENT_NAME = 'after_edit_greenhouse_edit_redirect';
	
	public function __construct() {
		parent::__construct("EditRedirect2");
	}

	/*
	 * Hook from hydra when experiment is actived
	 */
	static function onHydraRunExperiment($experiment) 
	{
		global $wgTitle;
		if($experiment == self::EXPERIMENT_NAME && $wgTitle != NULL && $wgTitle->getNamespace() == NS_MAIN) {
			$_SESSION[self::OUR_SESSION_NAME] = true;	
		}
		return true;
	}

	/*
	 * Process request to add category, and redirect to greenhouse
	 */
	function execute() {
		global $wgUser, $wgRequest, $wgOut;
	
		if($wgUser->isAnon()) {
      $wgOut->blockedPage();
		  return;
		}
		$cat = $wgRequest->getval("cat",NULL);
		if($cat) {
			CategoryInterests::addCategoryInterest($cat);
			$wgOut->redirect('/Special:EditFinder/Topic');
		}
			
	}
	static function beforeHeaderDisplay($isMobile) {
		global $whEditRedirectSave, $wgOut, $wgRequest, $wgUser, $wgTitle;
		/*
		 * We only want to display the edit redirect page on desktop after the first edit on a main namespace page. We check a bunch of criteria to ensure this is he case.
		 * criteria to ensure this is indeed the first edit
		 */
		if((!$isMobile && isset($_SESSION[self::OUR_SESSION_NAME]) && $_SESSION[self::OUR_SESSION_NAME] && Hydra::isEnabled(self::EXPERIMENT_NAME) ) || $wgRequest->getVal("abtest_test2")=="1") {
			// Turn off cache because this is a onetime thing
			$wgOut->enableClientCache(false);
			unset($_SESSION[self::OUR_SESSION_NAME]);
			
			$cats = $wgTitle->getParentCategories();	
			if(sizeof($cats) == 0) {
				return true;	
			}
			$catkeys = array_keys($cats);
			$cat = false;
			foreach($catkeys as $k) {
				if(preg_match('@Category:(.+)@i', $k, $matches) && $matches[1] != 'Featured-Articles') {
					$cat = $matches[1];	
					break;
				}
			}

			// We only display the edit redirect dialog for main namespace edits
			if($wgTitle->getNamespace() == NS_MAIN && $wgRequest->getText( 'action', 'view' ) =='view' && $cat ) {
				EasyTemplate::set_path( dirname(__FILE__) );
				$catText = str_replace('-',' ', $cat);
				$vars = array('cat' => $cat, 'catText' => $catText );
				$tmpl = EasyTemplate::html("CatRedirect.tmpl.php", $vars);

				$wgOut->addScript($tmpl);
	}
		}
		return true;
	}
}
