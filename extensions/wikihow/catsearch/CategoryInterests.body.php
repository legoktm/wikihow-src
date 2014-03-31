<?

/*
* A utility class that assists in storing and retreiving category interests for users.
* See the CategorySearchUI for the accompanying interface that uses this class.
* DB Schema:
*
*	CREATE TABLE `category_interests` (
*	  `ci_user_id` mediumint(8) unsigned NOT NULL default '0',
*	  `ci_category` varchar(255) NOT NULL default '',
*	  PRIMARY KEY  (`ci_user_id`,`ci_category`),
*	  KEY `ci_category` (`ci_category`)
*	) ENGINE=InnoDB DEFAULT CHARSET=latin1
*/
class CategoryInterests extends UnlistedSpecialPage {

	function __construct() { 
		parent::__construct( 'CategoryInterests' );
	}
	

	function execute($par) {
		global $wgOut, $wgRequest, $wgUser;

		$fname = 'CategoryInterests::execute';
		wfProfileIn( $fname );

		$wgOut->setRobotpolicy( 'noindex,nofollow' );
		if ($wgUser->getId() == 0) {
			$wgOut->showErrorPage( 'nosuchspecialpage', 'nospecialpagetext' );
		}

		$retVal = false;
		$action = $wgRequest->getVal('a');
		$category = $wgRequest->getVal('cat');
		switch ($action) {
			case 'hier':
				$retVal = $this->getCatPath($category);
				break;
			case 'trunc':
				$retVal = array('related' => $this->getSubCategoriesTruncated($category));
				break;
			case 'sugg':
				$retVal = array('suggestions' => $this->suggestCategoryInterests());
				break;
			case 'get':
				$retVal = array('interests' => $this->getCategoryInterests());
				break;
			case 'sub':
				$arr = array($category);
				$retVal = $this->getSubCategoryInterests($arr);
				break;
			case 'add':
				$retVal = $this->addCategoryInterest($category);
				break;
			case 'remove':
				$retVal = $this->removeCategoryInterest($category);
				break;
			default: 
				// Oops. Didn't understad the action
				$retVal = false;
		}

		$wgOut->setArticleBodyOnly(true);
		$wgOut->addHtml(json_encode($retVal));
		return;

		wfProfileOut( $fname );
	}

	/*
	*	Returns a list of categories in the form of the title name. Return the top-level categories if no categories have been selected
	*/
	public static function getCategoryInterests() {
		global $wgUser, $wgCategoryNames;
		
		$catInterests = array();

		$dbr = wfGetDB(DB_SLAVE);
		$res = $dbr->select('category_interests', array('ci_category'), array('ci_user_id' => $wgUser->getId()));
		while ($row = $dbr->fetchObject($res)) {
			$catInterests[] = stripslashes($row->ci_category);
		}

		return $catInterests;
	}

	/*
	* given an array of category titles, return all the subcategories below these categories
	*/
	public static function getSubCategoryInterests(&$categories) {
		global $wgMemc;
		$cats = array();
		foreach ($categories as $category) {
			$key = wfMemcKey('catint_subcats', $category);
			$subcats = $wgMemc->get($key);
			if (!$subcats) {
				$t = Title::newFromText($category, NS_CATEGORY);
				$subcats = self::getSubCategories($t);
				$wgMemc->set($key, $subcats, 60 * 60 * 24);
			}
			$cats =	array_merge($cats,$subcats);
		}

		return array_values(array_unique($cats));
	}

	/*
	* Given a NS_CATEGORY-namespaced title object, return all the subcategories in a one dimensional array
	*/
	private function getSubCategories(&$t) {
		$flattened = array();
		if ($t && $t->exists()) {
			$tree = self::getSubCategoryTree($t);
			// only flatten a tree if there is a tree. Handles the case where the category is a leaf node
			if (is_array($tree)) {
				self::flattenTree($flattened, $tree);
				$flattened = str_replace(' ', '-', $flattened);
			}
		}
		return $flattened;
	}


	/*
	* Given a NS_CATEGORY-namespaced title object, return a subcategory associative array tree
	*/
	public static function getSubCategoryTree(&$t) {
		global $wgContLang;

		$flattened = array();
		$parentTree = $t->getParentCategoryTree();
		if (is_array($parentTree)) {
			self::flattenTree($flattened, $parentTree);
		}

		$flattened = array_reverse($flattened);
		// Don't forget to add the actual category
		$flattened[] = $t->getPartialURL();
		// Convert it to a format that matches the result of Categoryhelper::getCategoryTreeArray(); 
		$catNsText = $wgContLang->getNSText (NS_CATEGORY);
		$flattened = str_replace($catNsText . ':', '', $flattened);
		$flattened = str_replace('-', ' ', $flattened);

		$ch = new Categoryhelper();
		$tree = $ch->getCategoryTreeArray();
		foreach ($flattened as $cat) {
			$tree = $tree[$cat];
		}

		return $tree;
	}

	private function getPeerCategories(&$t) {
		global $wgCategoryNames;

		$flattened = array();
		if ($t && $t->exists()) {
			$cat = CatSearch::getParentCats($t);
			// Top level category
			if(empty($cat)) {
				$topCats = array();
				$catNames = array_values($wgCategoryNames);
				foreach ($catNames as $name) {
					$topCats[] = str_replace(" ", "-", $name);
				}
				return $topCats;
			}

			$parentTitle = Title::newFromText($cat[0], NS_CATEGORY);
			$tree = self::getSubCategoryTree($parentTitle);
			if (!is_array($tree)) {
				$tree = array($tree);
			}
			$flattened = array_keys($tree);

			// Remove category of the parameter from the array 
			$pos = array_search($t->getText(), $flattened);
			if (false !== $pos) {
				unset($flattened[$pos]);
			}
		}
		return $flattened;
	}

	/*
	* Given a tree, return a flattened (one-dimensional) array of all the tree values.
	*/
	private function flattenTree(&$flattened, &$tree) {
		foreach (array_keys($tree) as $node) {
			if (is_array($tree[$node])) {
				array_push($flattened, $node);
				self::flattenTree($flattened, $tree[$node]);
			} else {
				array_push($flattened, $node);
			}
		}
	}

	/*
	* Truncate a tree structure below a certain depth.
	* Returns a 1 dimensional array of categories to the specified depth
	*/
	private function truncateTree(&$tree, $depth) {
		$trimmed = array();
		if (!is_array($tree)) {
			return $trimmed;
		}
		foreach (array_keys($tree) as $node) {
			if (is_array($tree[$node]) && $depth != 1) {
				array_push($trimmed, $node);
				$trimmed = array_merge($trimmed, self::truncateTree($tree[$node], $depth - 1));

			} else {
				array_push($trimmed, $node);
			}
		}
		return $trimmed;
	}


	/*
	* Insert a category into the category_interest table for the logged in user. Categories should be in the form of the category url title.
	* Ex: Arts-and-Entertainment or Actor-Appreciation
	*/
	public static function addCategoryInterest($category) {
		global $wgUser;

		// Don't add a category if it isn't a valid category title
		$t = Title::newFromText($category, NS_CATEGORY);
		if (!$t->exists()) {
			return false;
		}

		if ($wgUser->getId() == 0) {
			return false;
		}

		$dbw = wfGetDB(DB_MASTER);
		$category = $dbw->strencode($category);
		return $dbw->insert('category_interests', array('ci_user_id' => $wgUser->getId(), 'ci_category' => $category), 'CategoryInterests::addCategoryInterest', array('IGNORE'));
	}

	/*
	* Removes a category from the category_interest table for the logged in user. Categories should be in the form of the category url title.
	* Ex: Arts-and-Entertainment or Actor-Appreciation
	*/
	public static function removeCategoryInterest($category) {
		global $wgUser;

		if ($wgUser->getId() == 0) {
			return false;
		}

		$dbw = wfGetDB(DB_MASTER);
		$category = $dbw->strencode($category);
		return $dbw->delete('category_interests', array('ci_user_id' => $wgUser->getId(), 'ci_category' => $category));
	}

	/*
	* Suggest user interests based on the last 10 articles they've edited
	*/
	public static function suggestCategoryInterests() {
		global $wgUser;
		
		$interests = array();
		if ($wgUser->getId() == 0) {
			return $interests;
		}

		$dbr = wfGetDB(DB_SLAVE);
		$res = ProfileBox::fetchEditedData($wgUser->getName(), 10);
		while ($row = $dbr->fetchObject($res)) {
			$t = Title::newFromId($row->page_id);	
			if ($t && $t->exists()) {
				$interests = array_merge($interests, CatSearch::getParentCats($t));
			}
		}

		$interests = array_unique($interests);
		foreach ($interests as $k => $interest) {
			if(CatSearch::ignoreCategory($interest)) {
				unset($interests[$k]);
			}
		}
		// Give them some random top-level categories if they haven't done any edits yet
		if (!sizeof($interests)) {
			global $wgCategoryNames;
			$topCats = array_values($wgCategoryNames);
			$rnd = rand(1, 6);
			for ($i = 1; $i < 4; $i++) {
				$interests[] = str_replace(" ", "-", $topCats[$i * $rnd]);
			}
		}

		return array_slice(array_values($interests), 0, 3);
	}

	public static function getSubcategoriesTruncated($category, $depth = 1) {
		$t = Title::newFromText($category, NS_CATEGORY);
		if ($t && $t->exists()) {
			$tree = self::getSubCategoryTree($t);
		}
		$result = array();
		if (is_array($tree)) {
			$trimmed = array();
			$result = self::truncateTree($tree, $depth);
		} 		
		
		if (!empty($result)) {
			$result = array_values($result);
		}
		return $result;
	}

	public static function getCategoriesArray() {
		$ch = new Categoryhelper();
		$tree = $ch->getCategoryTreeArray();
		$flattened = array();
		self::flattenTree($flattened, $tree);
		return $flattened;
	}

	public static function getCatPath($cat) {
		$t = Title::newFromText($cat, NS_CATEGORY);
		$catPath = array();
		if ($t && $t->exists()) {
			$flattened = array();
			$parentTree = $t->getParentCategoryTree();
			// Extract on category path in the case where multiple super-categories are present for a subcat
			while (!empty($parentTree) && is_array($parentTree)) {
				$keys = array_keys($parentTree);
				$values = array_values($parentTree);
				$flattened[] = $keys[0];
				$parentTree = is_array($values[0]) ? $values[0] : null;
			}

			$flattened = array_reverse($flattened);
			// Don't forget to add the actual category
			$flattened[] = $t->getPartialURL();
			// Convert it to a format that matches the result of Categoryhelper::getCategoryTreeArray(); 
			$flattened = str_replace('Category:', '', $flattened);
			$flattened = str_replace('-', ' ', $flattened);
			$catPath = $flattened;
		}
		return $catPath;
	}
	
	public static function getCategoryTreeArray() {
		$ch = new Categoryhelper();
		return $ch->getCategoryTreeArray();
	}
}
