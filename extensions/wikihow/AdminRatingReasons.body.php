<?

if (!defined('MEDIAWIKI')) die();

class AdminRemoveRatingReason extends UnlistedSpecialPage {
	public function __construct() {
		parent::__construct('AdminRemoveRatingReason');
	}

	public function getAllowedUsers(){
		return AdminRatingReasons::getAllowedUsers();
	}
	public function userAllowed() {
		return AdminRatingReasons::userAllowed();
	}

	public function execute($par) {
		global $wgRequest, $wgOut, $wgUser;

		if (!$this->userAllowed()) {
			return;
		}

		$ratr_item = isset( $par ) ? $par : $wgRequest->getVal('target');
		$ratr_id = $wgRequest->getVal('id');

		$dbw = wfGetDB( DB_MASTER );

		if (!$ratr_id) {
			$dbw->delete("rating_reason", array("ratr_item" => $ratr_item));
			$wgOut->addHTML("<h3>All Rating Reasons for {$ratr_item} have been deleted.</h3><br><hr size='1'><br>");
		} else {
			$dbw->delete("rating_reason", array("ratr_id" => $ratr_id));
			$wgOut->addHTML("<h3>Rating Reason for {$ratr_item} has been deleted.</h3><br><hr size='1'><br>");
		}

		$arr = Title::makeTitle(NS_SPECIAL, "AdminRatingReasons");

		$skin = $wgUser->getSkin();
		$orig = Title::newFromText("sample/".$ratr_item);

		$wgOut->addHTML("Return to ". $skin->makeLinkObj($arr, "AdminRatingReasons"."<br>"));
		$wgOut->addHTML("Go to ".$skin->makeLinkObj($orig, "{$ratr_item}"));
	}
}

class AdminRatingReasons extends UnlistedSpecialPage {

	// TODO We probably want to consider having the clear on the page that shows the ratings clear it too. 
	
	public function __construct() {
		parent::__construct('AdminRatingReasons');
	}

	public function getAllowedUsers() {
		return  array(
		);
	}

	public function userAllowed() {
		global $wgUser;

		$user = $wgUser->getName();
		$allowedUsers = $this->getAllowedUsers();

		$userGroups = $wgUser->getGroups();
		if ($wgUser->isBlocked() || (!in_array($user, $allowedUsers) && !in_array('staff', $userGroups)))
		{
			return False;
		}

		return True;

	}

	public function execute() {
		global $wgRequest, $wgOut, $wgUser;

		if (!$this->userAllowed()) {
			$wgOut->setRobotpolicy('noindex,nofollow');
			$wgOut->showErrorPage('nosuchspecialpage', 'nospecialpagetext');
			return;
		}

		$wgOut->setHTMLTitle('Admin - Rating Reasons - wikiHow');
		$wgOut->setPageTitle('List Of Rating Reasons');

		$ArrLink = SpecialPage::getTitleFor('AdminRatingReasons');
		$filterThis = $wgUser->getSkin()->makeLinkObj($ArrLink, 'Show All Rating Reasons');

		// TODO what does this checklimits line do..
		list( $limit, $offset) = wfCheckLimits();

		$item = $wgRequest->getVal('item');
		$pqp = new RatingReasonsQueryPage($item);
		$pqp->getList();
		$wgOut->addHTML("<hr><br><p>{$filterThis}</p>");
	}
}

class RatingReasonsQueryPage extends PageQueryPage {

	public function __construct($item=NULL) {
		$this->ratingsCache = array();
		if ($item) {
			$this->itemId = $item;
		}

		parent::__construct('RatingReasonsQueryPage');
	}

	function getList() {
		list( $limit, $offset ) = wfCheckLimits();
		$this->limit = $limit;
		$this->offset = $offset;
		parent::execute('');
	}

	function getName() {
		return 'AdminRatingReasons';
	}

	function getRatingAccuracy($itemId) {
		$rd = $this->ratingsCache[$itemId];

		if (!$rd) {
			$dbr = wfGetDB(DB_SLAVE);
			$rd = Pagestats::getRatingData($itemId, 'ratesample', 'rats', $dbr);
			$this->ratingsCache[$itemId] = $rd;
		} 

		return $rd;
	}

	function isExpensive( ) { return false; }

	function isSyndicated() { return false; }

	function getOrder() {
		return ' ORDER BY ratr_item ' . ($this->sortDescending() ? 'DESC' : ''); 
	}

	function getSQL() {
		$query = "SELECT ratr_type as type, 0 as namespace, ratr_item as title, ratr_id as value, ratr_text from rating_reason";

		if ($this->itemId) {
			$query = $query." WHERE ratr_item = '{$this->itemId}'";
		}

		return $query;
	}

	function formatResult($skin, $result) {
		$t = Title::newFromText("$result->type/$result->title");

		$clear = SpecialPage::getTitleFor( 'AdminRemoveRatingReason', $result->title );
		$idv = "id={$result->value}";

		$ArrLink = SpecialPage::getTitleFor('AdminRatingReasons');
		$filterThis = $skin->makeLinkObj($ArrLink, 'filter', 'item='.$result->title);
		$clearThis = $skin->makeLinkObj($clear, 'clear', $idv);
		$clearAll = $skin->makeLinkObj($clear, 'clear all');


		$acc = $this->getRatingAccuracy($result->title);
		$accText .= "{$acc->percentage}% of {$acc->total} votes";

		return $skin->makeLinkobj($t, $result->title ) . ": {$result->ratr_text} - ({$filterThis}, {$clearThis}, {$clearAll}) - Accuracy: ".$accText;
	}
}
