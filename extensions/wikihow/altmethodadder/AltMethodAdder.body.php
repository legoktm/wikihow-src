<?
/*
* 
*/
class AltMethodAdder extends UnlistedSpecialPage {

	const DEFAULT_METHOD = "Name your method";
	const DEFAULT_STEPS = "Add your steps using an ordered list. For example:\n# Step one\n# Step two\n# Step three";
	
	function __construct() {
		parent::__construct('AltMethodAdder');
	}

	function execute($par) {
		global $wgRequest, $wgOut, $wgUser;
		$articleId = intval($wgRequest->getVal('aid'));
		$altMethod = $wgRequest->getVal('altMethod');
		$altSteps = $wgRequest->getVal('altSteps');
		if($articleId != 0 && $altMethod != "" && $altSteps != "") {
			$wgOut->setArticleBodyOnly(true);
			$result = $this->addMethod($articleId, $altMethod, $altSteps);
			print_r(json_encode($result));
			return;
		}
		
		$userGroups = $wgUser->getGroups();
		if ($wgUser->isBlocked() || !in_array('staff', $userGroups))
		{
			$wgOut->setRobotpolicy('noindex,nofollow');
			$wgOut->showErrorPage('nosuchspecialpage', 'nospecialpagetext');
			return;
		}
		
		$llr = new NewAltMethods();
    	$llr->getList();

		return;
		
	}
	
	private function addMethod($articleId, $altMethod, $altSteps) {
		global $wgParser, $wgUser;
		$title = Title::newFromID($articleId);
		$result = array();
		if($title) {
			$isValid = MethodGuardian::checkContent($title, $altMethod, $altSteps, false);
			//only add it to the db if it's actually a valid method as defined
			//by the MethodGuardian::checkContent
			if($isValid) {
				$dbw = wfGetDB(DB_MASTER);
				$dbw->insert('altmethodadder', array('ama_page' => $articleId, 'ama_method' => $altMethod, 'ama_steps' => $altSteps, 'ama_user' => $wgUser->getID(), 'ama_timestamp' => wfTimestampNow()));
			}
			$result['success'] = true;
			
			//Parse the wikiText that they gave us.
			//Need to add in a steps header so that mungeSteps
			//actually knows that it's a steps section
			$newMethod = $wgParser->parse("== Steps ==\n=== " . $altMethod . " ===\n" . $altSteps, $title, new ParserOptions())->getText();
			$result['html'] = WikihowArticleHTML::postProcess($newMethod, array('no-ads' => true));
		}
		else
			$result['success'] = false;
		
		return $result;
	}
	
	/***
	 * This will get display on article pages
	 */
	public static function getCTA(&$t) {
		if (self::isActivePage() 
			&& !self::isReferredFromArticleCreator()
			&& self::isValidTitle($t)) {
			
			$tmpl = new EasyTemplate( dirname(__FILE__) );
			$tmpl->set_vars(array('title' => $t->getText()));
		
			return $tmpl->execute('AltMethodAdder.tmpl.php');
		}
	}
	
	public static function isValidTitle(&$t) {
		return $t && $t->exists() && $t->getNamespace() == NS_MAIN && !$t->isProtected();
	}

	public static function isActivePage() {
		//now showing on ALL pages
		return true;
	}

	public static function isReferredFromArticleCreator() {
		return strpos($_SERVER['HTTP_REFERER'], '/Special:ArticleCreator') !== false;
	}
	
	function getSQL() {
		return "SELECT ama_timestamp as value, altmethodadder.* from altmethodadder";
	}
}

class NewAltMethods extends QueryPage {
	function __construct() {
		parent::__construct('AltMethodAdder');
	}

	function getList() {
		list( $limit, $offset ) = wfCheckLimits();
		$this->limit = $limit;
		$this->offset = $offset;
		
		parent::execute('');
	}
	
	function getPageHeader() {
		global $wgOut;
		$wgOut->setPageTitle("New Alternate Methods");
		return;
	}

	function getName() {
		return "Alternate Methods";
	}

	function isExpensive() {
		return false;
	}
	function isSyndicated() { return false; }

	function getSQL() {
		return AltMethodAdder::getSql();
	}

	function formatResult( $skin, $result ) {
		global $wgLang, $wgContLang;
		
		$title = Title::newFromID( $result->ama_page );

        	if($title) {
                $html = "";
                if($result->ama_patrolled)
                    $html .= "<span style='color:#229917'>&#10004</span> &nbsp;&nbsp;";

                $html .= "<a href='" . $title->escapeFullURL() . "'>". $title->getText() . "</a><br />" . $result->ama_method . "<br />" . $result->ama_steps;

                return $html;

            }
	}

}
