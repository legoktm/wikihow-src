<?

if ( !defined('MEDIAWIKI') ) die();

class Categoryhelper extends UnlistedSpecialPage {

	function __construct() {
		parent::__construct( 'Categoryhelper' );
	}

	static function getCategoryDropDownTree() {
		//global $wgMemc;

		//$key = wfMemcKey('category', 'dropdowntree', 'wikihow');
		//$result = $wgMemc->get( $key );
		//if (!is_array($result)) {
			$t = Title::makeTitle(NS_PROJECT, wfMessage('categories')->text());
			$r = Revision::newFromTitle($t);
			if (!$r) return array();
			$text = $r->getText();

			$lines = split("\n", $text);
			$bucket = array();
			$result = array();
			$bucketname = '';
			foreach ($lines as $line) {
				if (strlen($line) > 1
					&& strpos($line, "*") == 0
					&& strpos($line, "*", 1) === false) {
					$result[$bucketname] = $bucket;
					$bucket = array();
					$bucketname = trim(str_replace("*", "", $line));
				} else if (trim($line) != "") {
					$bucket[] = trim($line);
				}
			}

		//	$wgMemc->set($key, $result, time() + 3600);
		//}
		return $result;
	}

	static function makeCategoryArray($current_lvl, &$lines) {
		$pattern = '/^(\*+)/';
		$bucket2 = array();

		while (count($lines)>0) {
			$line = array_shift($lines);
			preg_match($pattern, $line, $matches);
			$lvl = strlen($matches[0]);
			$prevcat = $cat;
			$cat = trim(str_replace("*", "", $line));

			if ($current_lvl == $lvl) {
				//array_push($bucket2,$cat);
				$bucket2[$cat] = $cat;
			} else if ($lvl > $current_lvl) {
				array_unshift($lines, $line);
				$bucket2[$prevcat] = self::makeCategoryArray($current_lvl + 1, $lines);
			} else {
				array_unshift($lines, $line);
				return $bucket2;
			}
		}
		return $bucket2;
	}

	static function getCategoryTreeArray() {
		//global $wgMemc;

		//$key = wfMemcKey('category', 'arraytree', 'wikihow');
		//$result = $wgMemc->get( $key );
		//if (!$result) {
			$t = Title::makeTitle(NS_PROJECT, wfMessage('categories')->text());
			$r = Revision::newFromTitle($t);
			if (!$r) return array();
			$text = $r->getText();
			$text = preg_replace('/^\n/m', '', $text);

			$lines = split("\n", $text);
			$result = self::makeCategoryArray(1, $lines);

		//	$wgMemc->set($key, $result, time() + 3600);
		//}
		return $result;
	}

	static function getCurrentParentCategories() {
		global $wgTitle, $wgMemc;

		$cachekey = wfMemcKey('parentcats', $wgTitle->getArticleId());
		$cats = $wgMemc->get($cachekey);
		if ($cats) return $cats;

		$cats = $wgTitle->getParentCategories();

		$wgMemc->set($cachekey, $cats);
		return $cats;
	}

	static function getCurrentParentCategoryTree() {
		global $wgTitle, $wgMemc;

		$cachekey = wfMemcKey('parentcattree', $wgTitle->getArticleId());
		$cats = $wgMemc->get($cachekey);
		if ($cats) return $cats;

		$cats = $wgTitle->getParentCategoryTree();

		$wgMemc->set($cachekey, $cats);
		return $cats;
	}

	static function cleanUpCategoryTree($tree) {
		$results = array();
		if (!is_array($tree)) return $results;
		foreach ($tree as $cat) {
			$t = Title::newFromText($cat);
			if ($t)
				$results[]= $t->getText();
		}
		return $results;
	}

	static function flattenCategoryTree($tree) {
		if (is_array($tree)) {
			$results = array();
			foreach ($tree as $key => $value) {
				$results[] = $key;
				$x = self::flattenCategoryTree($value);
				if (is_array($x))
					return array_merge($results, $x);
				else
					return $results;
			}
		} else {
			$results = array();
			$results[] = $tree;
			return $results;
		}
	}

	static function getIconMap() {
		$catmap = array(
			wfMessage("arts-and-entertainment")->text() => "Image:Category_arts.jpg",
			wfMessage("health")->text() => "Image:Category_health.jpg",
			wfMessage("relationships")->text() => "Image:Category_relationships.jpg",
			wfMessage("cars-&-other-vehicles")->text() => "Image:Category_cars.jpg",
			wfMessage("hobbies-and-crafts")->text() => "Image:Category_hobbies.jpg",
			wfMessage("sports-and-fitness")->text() => "Image:Category_sports.jpg",
			wfMessage("computers-and-electronics")->text() => "Image:Category_computers.jpg",
			wfMessage("holidays-and-traditions")->text() => "Image:Category_holidays.jpg",
			wfMessage("travel")->text() => "Image:Category_travel.jpg",
			wfMessage("education-and-communications")->text() => "Image:Category_education.jpg",
			wfMessage("home-and-garden")->text() => "Image:Category_home.jpg",
			wfMessage("work-world")->text() => "Image:Category_work.jpg",
			wfMessage("family-life")->text() => "Image:Category_family.jpg",
			wfMessage("personal-care-and-style")->text() => "Image:Category_personal.jpg",
			wfMessage("youth")->text() => "Image:Category_youth.jpg",
			wfMessage("finance-and-legal")->text() => "Image:Category_finance.jpg",
			wfMessage("finance-and-business")->text() => "Image:Category_finance.jpg",
			wfMessage("pets-and-animals")->text() => "Image:Category_pets.jpg",
			wfMessage("food-and-entertaining")->text() => "Image:Category_food.jpg",
			wfMessage("philosophy-and-religion")->text() => "Image:Category_philosophy.jpg",
		);
		return $catmap;
	}

	static function getTopCategory($title = null) {
		global $wgContLang;
		if (!$title) {
			// an optimization because memcache is hit
			$parenttree = Categoryhelper::getCurrentParentCategoryTree();
		} else {
			$parenttree = $title->getParentCategoryTree();
		}
		$catNamespace = $wgContLang->getNSText(NS_CATEGORY) . ":";
		$parenttree_tier1 = $parenttree;

		$result = null;
		while ((!$result || $result == "WikiHow") && is_array($parenttree)) {
			$a = array_shift($parenttree);
			if (!$a) {
				$keys = array_keys($parenttree_tier1);
				$result = str_replace($catNamespace, "", $keys[0]);
				break;
			}
			$last = $a;
			while (sizeof($a) > 0 && $a = array_shift($a) ) {
				$last = $a;
			}
			$keys = array_keys($last);
			$result = str_replace($catNamespace, "", $keys[0]);
		}
		return $result;
	}

	static function displayCategoryArray($lvl, $catary, &$display, $toplevel) {
		$indent = "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";

		if (is_array($catary)) {
			foreach(array_keys($catary) as $cat) {
				if ($lvl == 0) { $toplevel = $cat; }

				$fmt = "";
				for($i=0;$i<$lvl;$i++) {
					$fmt .= $indent;
				}
				$display .= "<a name=\"".urlencode(strtoupper($cat))."\" id=\"".urlencode(strtoupper($cat))."\" ></a>\n";
				$display .= $fmt;
				if (is_array($catary[$cat])) {
					$display .= "<img id=\"img_".urlencode($cat)."\" src=\"/skins/WikiHow/topics-arrow-off.gif\" height=\"10\" width=\"10\" border=\"0\" onClick=\"toggleImg(this);Effect.toggle('toggle_".urlencode(strtoupper($cat))."', 'slide', {delay:0.0,duration:0.0}); return false;\" /> ";
				} else {
				$display .= "<img src=\"/skins/WikiHow/blank.gif\" height=\"10\" width=\"10\" border=\"0\"  /> ";
				}

				if ($lvl == 0) {
					$display .= "$cat <br />\n";
				}else {
					$display .= "<INPUT TYPE=CHECKBOX NAME=\"".$toplevel.",".$cat."\" >  " . $cat . "<br />\n";
				}

				$display .= "<div id=\"toggle_".urlencode(strtoupper($cat)) ."\" style=\"display:none\">\n";
				$display .= "   <div>\n";
				if ($lvl > 0) {

				}
				self::displayCategoryArray($lvl + 1, $catary[$cat], $display, $toplevel);

				$display .= "   </div>\n</div>\n";
			}
		}
	}

	static function flattenary(&$bucket, $lines) {
		foreach (array_keys($lines) as $line) {
			if (is_array($lines[$line])) {
				array_push($bucket, $line);
				self::flattenary($bucket, $lines[$line]);
			} else {
				array_push($bucket, $lines[$line]);
			}
		}
	}

	static function json2Array() {
		global $wgRequest;
		$val = array();

		$wgary = $wgRequest->getValues();
		if (is_array($wgary)) {
			foreach (array_keys($wgary) as $wgarykeys) {
				$jsonstring = preg_replace('/_/m', ' ', stripslashes($wgarykeys));
				$val = json_decode($jsonstring, true);

				if ($val['json'] == "true") { return $val; }
			}
		}
		return $val;
	}

	function execute($par) {
		global $wgOut, $wgRequest;
		$wgOut->setArticleBodyOnly(true);
		if ($wgRequest->getVal('cat')) {
			$category = $wgRequest->getVal('cat');
			$options = self::getCategoryDropDownTree();
			foreach($options[$category] as $sub) {
				echo self::getHTMLForCategoryOption($sub, '', true);
			}
		}

		if ($wgRequest->getVal('type') == "categorypopup") {
			$options2 = self::getCategoryTreeArray();
			echo self::getHTMLForPopup($options2);
		}

		$jsonAry = self::json2Array();
		if ($jsonAry['type'] == "supSubmit") {
			$jsonAry['ctitle'] = preg_replace('/-whPERIOD-/m',".",$jsonAry['ctitle']);
			$jsonAry['ctitle'] = preg_replace('/-whDOUBLEQUOTE-/m',"\"",$jsonAry['ctitle']);
			echo self::getHTMLsupSubmit($jsonAry);
		}

		return;
	}

	static function getTopLevelCategoriesForDropDown() {
		$results = array();
		$options = self::getCategoryDropDownTree();
		foreach ($options as $key=>$value) {
			$results[] = $key;
		}
		return $results;
	}

	static function modifiedParentCategoryTree($parents = array(), $children = array() ) {
		if($parents != '') {
			foreach($parents as $parent => $current) {
				if ( array_key_exists( $parent, $children ) ) {
					# Circular reference
					$stack[$parent] = array();
				} else {
					$nt = Title::newFromText($parent);
					if ( $nt ) {
						$stack[$parent] = $nt->getParentCategoryTree( $children + array($parent => 1) );
					}
				}
			}
			return $stack;
		} else {
			return array();
		}
	}

	static function getCategoryOptionsForm($default, $cats = null) {
		global $wgUser, $wgMaxCategories, $wgRequest;

		if (!$wgUser->isLoggedIn())
			return "";

		// get the top and bottom categories
		$valid_cats = array();
		if (is_array($cats)) {
			$valid_cats = array_flip($cats);
		}

		if ($wgRequest->getVal('oldid') != null && $default != "") {
			$fakeparent = array();
			$fakeparent[Title::makeTitle(NS_CATEGORY, $default)->getFullText()] = array();
			$tree = self::modifiedParentCategoryTree($fakeparent);
		} else {
			$tree = Categoryhelper::getCurrentParentCategoryTree();
		}
		if (!$tree) $tree = array();
		$toplevel = array();
		$bottomlevel = array();

		if ($wgRequest->getVal('topcategory0', null) != null) {
			// user has already submitted form, could be a preview, just set it to what they posted
			for ($i = 0; $i < $wgMaxCategories; $i++) {
				if ($wgRequest->getVal('topcategory' . $i, null) != null) {
					$toplevel[] = $wgRequest->getVal('topcategory' . $i);
					$bottomlevel[] = $wgRequest->getVal('category' . $i);
				}
			}
		} else {
			// fresh new form from existing article
			foreach ($tree as $k=>$v) {
				$keys = array_keys($tree);
				$bottomleveltext = $k;
				$child = $v;
				$topleveltext = $k;
				while (is_array($child) && sizeof($child) > 0) {
					$keys = array_keys($child);
					$topleveltext = $keys[0];
					$child = $child[$topleveltext];
				}
				$tl_title = Title::newFromText($topleveltext);
				$bl_title = Title::newFromText($bottomleveltext);
				if (isset($valid_cats[$bl_title->getText()])) {
					if ($tl_title != null) {
						$toplevel[] = $tl_title->getText();
						$bottomlevel[] =  $bl_title->getText();
					} else {
						$toplevel[] = $bl_title->getText();
					}
				} else {
					#print_r($tree);
					#echo "shit! <b>{$bl_title->getText()}</b><br/><br/>"; print_r($bl_title); print_r($valid_cats);
				}
			}
		}

		$helper = Title::makeTitle(NS_SPECIAL, "Categoryhelper");

		$toplevels = self::getTopLevelCategoriesForDropDown();
		$options = self::getCategoryDropDownTree();

		$html = "<script type='text/javascript' src='/extensions/wikihow/categories.js'></script>";
		$html .= '<style type="text/css" media="all">/*<![CDATA[*/ @import "/extensions/wikihow/categories.css"; /*]]>*/</style>';
		$html .= " <script type='text/javascript'>
					var gCatHelperUrl = \"{$helper->getFullURL()}\";
					var gCatHelperSMsg = \"" .wfMessage('selectsubcategory')->text() . "\";
					var gMaxCats = {$wgMaxCategories};
					var gCatMsg = '" . wfMessage('categoryhelper_summarymsg')->text() . "';
				</script>
					<input type='hidden' name='TopLevelCategoryOk' value='" . (sizeof($toplevel) == sizeof($bottomlevel) ? "false" : "true") . "'/>
				<noscript>" . wfMessage('categoryhelper_javascript')->text() . "<br/></noscript>
				";
		$i = 0;

		$max = 1;
		if (sizeof($toplevel) > 0) $max = sizeof($toplevel);
		for ($i = 0; $i < $max || $i < $wgMaxCategories; $i++) {
			$top = $bot = '';
			$style = ' style="display:none;" ';
			if ($i < sizeof($toplevel) || $i == 0) {
				$top = $toplevel[$i];
				$bot = $bottomlevel[$i];
				$style = '';
			}

			if ($i > 0) $html .= "<br/>";

			$html .= "<SELECT class='topcategory_dropdown' name='topcategory{$i}' id='topcategory{$i}' onchange='updateCategories({$i});' $style>
					<OPTION VALUE=''>".wfMessage('selectcategory')->text()."</OPTION>";

			foreach ($toplevels as $c) {
				$c = trim($c);
				if ($c== "") continue;
				$html .= "<OPTION VALUE=\"$c\" " . ($c == $top ? "SELECTED": "") ." >$c</OPTION>\n";
			}
			$html .= "</SELECT>   <span id='category_div{$i}'><SELECT onchange='catHelperUpdateSummary();' class='subcategory_dropdown' name='category{$i}' id='category{$i}'  $style>";
				if (is_array($options[$top])) {
					if ($bot == "") {
						 $html .= "<OPTION VALUE=''>".wfMessage('selectcategory')->text()."</OPTION>";
					}
					foreach($options[$top] as $sub) {
						$html .= self::getHTMLForCategoryOption($sub, $bot);
					}
				}
			$html .= "</SELECT> </span> ";
		}
		if ($i >= sizeof($toplevel)) {
			$html .= "<a onclick='javascript:showanother();' id='showmorecats'>" . wfMessage('addanothercategory')->text() . "</a>";
		}

		return $html;
	}

	static function getCategoryOptionsForm2($default, $cats = null) {
		global $wgUser, $wgMaxCategories, $wgRequest, $wgTitle;

		if (!$wgUser->isLoggedIn())
			return "";

		// get the top and bottom categories
		$valid_cats = array();
		if (is_array($cats)) {
			$valid_cats = array_flip($cats);
		}

		if ($wgRequest->getVal('oldid') != null && $default != "") {
			$fakeparent = array();
			$fakeparent[Title::makeTitle(NS_CATEGORY, $default)->getFullText()] = array();
			$tree = self::modifiedParentCategoryTree($fakeparent);
		} else {
			//don't use caching for this
			$tree = $wgTitle->getParentCategoryTree();
		}
		if (!$tree) $tree = array();
		$toplevel = array();
		$bottomlevel = array();

		if ($wgRequest->getVal('topcategory0', null) != null) {
			// user has already submitted form, could be a preview, just set it to what they posted
			for ($i = 0; $i < $wgMaxCategories; $i++) {
				if ($wgRequest->getVal('topcategory' . $i, null) != null) {
					$toplevel[] = $wgRequest->getVal('topcategory' . $i);
					$bottomlevel[] = $wgRequest->getVal('category' . $i);
				}
			}
		} else {
			// fresh new form from existing article
			foreach ($tree as $k=>$v) {
				$keys = array_keys($tree);
				$bottomleveltext = $k;
				$child = $v;
				$topleveltext = $k;
				while (is_array($child) && sizeof($child) > 0) {
					$keys = array_keys($child);
					$topleveltext = $keys[0];
					$child = $child[$topleveltext];
				}
				$tl_title = Title::newFromText($topleveltext);
				$bl_title = Title::newFromText($bottomleveltext);
				if (isset($valid_cats[$bl_title->getText()])) {
					if ($tl_title != null) {
						$toplevel[] = $tl_title->getText();
						$bottomlevel[] =  $bl_title->getText();
					} else {
						$toplevel[] = $bl_title->getText();
					}
				} else {
					#print_r($tree);
					#echo "shit! <b>{$bl_title->getText()}</b><br/><br/>"; print_r($bl_title); print_r($valid_cats);
				}
			}
		}

		$html = "\n";
		$catlist = "";

		for ($i = 0; $i < $wgMaxCategories; $i++) {
			if ($toplevel[$i] != "") {
				//$html .= "<a href=\"/Category:".$bottomlevel[$i]."\">".$toplevel[$i].":".$bottomlevel[$i]."</a><br>\n";
				$html .= "<input type=hidden readonly size=40 name=\"topcategory".$i."\" value=\"".$toplevel[$i]."\" />";
				$html .= "<input type=hidden readonly size=60 name=\"category".$i."\" value=\"".$bottomlevel[$i]."\" />\n";
				if ($i == 0) {
					$catlist = $bottomlevel[$i];
				} else {
					$catlist .= ", ".$bottomlevel[$i];
				}
			} else {
				$html .= "<input type=hidden readonly size=40 name=\"topcategory".$i."\" value=\"\" />";
				$html .= "<input type=hidden readonly size=60 name=\"category".$i."\" value=\"\" />\n";
			}
		}

		if (!$catlist) {
			$html .= "<div id=\"catdiv\">" . wfMessage('ep_not_categorized')->text() . "</div>\n";
		} else {
			$html .= "<div id=\"catdiv\">$catlist</div>\n";
		}

		return $html;
	}

	static function getHTMLForCategoryOption($sub, $default, $for_js = false) {
		$style = "";
		if (strpos($sub, "**") !== false && strpos($sub, "***") === false)
			$style = 'style="font-weight: bold;"';
		$sub = substr($sub, 2);
		$value = trim(str_replace("*", "", $sub));
		$display = str_replace("*", "&nbsp;&nbsp;&nbsp;&nbsp;", $sub);
		return "<OPTION VALUE=\"{$value}\" " . ($default == $value ? "SELECTED" : "") . " $style>$display</OPTION>\n";
	}

	static function getHTMLForPopup($treearray) {
		$css = HtmlSnips::makeUrlTags('css', array('categoriespopup.css'), 'extensions/wikihow', false);
		$style = "";
		$display = "";
		$indent = "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";

		$display = '
<html>
<head>

<title>Categories</title>

<style type="text/css" media="all">/*<![CDATA[*/ @import "/skins/WikiHow/newskin.css"; /*]]>*/</style>' .  $css . '
<script language="javascript" src="/extensions/wikihow/common/prototype1.8.2/prototype.js"></script>
<script language="javascript" src="/extensions/wikihow/common/prototype1.8.2/effects.js"></script>
<script language="javascript" src="/extensions/wikihow/common/prototype1.8.2/controls.js"></script>
<script language="javascript" src="/extensions/wikihow/categoriespopup.js"></script>
<script type="text/javascript">/*<![CDATA[*/
var Category_list = [
			';

		$completeCatList = array();
		self::flattenary($completeCatList, $treearray);
		foreach ($completeCatList as $cat) {
			if ($cat != '') {
				$cat = preg_replace('/\'/', '\\\'', $cat);
				$display .= "'$cat',";
			}
		}
		$display .= "''];\n";

		$display .= '
/*]]>*/</script>
</head>
<body >

<div id="article">
<form name="catsearchform" action="#" onSubmit="return searchCategory();">
<input id="category_search" autocomplete="off" size="40" type="text" value="" onkeyup="return checkCategory();" />
<input type="button" value="'.wfMessage('Categorypopup_search')->text().'" onclick="return searchCategory();" />

<div class="autocomplete" id="cat_search" style="display:none"></div>

<script type="text/javascript">/*<![CDATA[*/
new Autocompleter.Local(\'category_search\', \'cat_search\', Category_list, {fullSearch: true});
/*]]>*/</script>
</form><br />
			';

		$display .= "<strong>".wfMessage('Categorypopup_selected')->text().": </strong><br />\n";
		$display .= "<div id=\"selectdiv\">";
		$display .= "<p>Loading...</p>";
		$display .= "</div><br />\n";

		$display .= '
<script type="text/javascript">showSelected();</script>

<strong>'.wfMessage('Categorypopup_browse')->text().':</strong>  <a href="#" onclick="return collapseAll();">['.wfMessage('Categorypopup_collapse')->text().']</a>
<a name="form_top" id="form_top" ></a>
<div id="categoriesPop" style="width:470;height:215px;overflow:auto">
<form name="category">
			';

		self::displayCategoryArray(0,$treearray,$display, "TOP");

		$display .= '
		<script type="text/javascript"> checkSelected(); </script>
			';

		$display .= '
	</div>
</div>
	<br />

	<input type="button" value="   '.wfMessage('Categorypopup_save')->text().'   " onclick="handleSAVE(this.form)" />
	<input type="button" value="'.wfMessage('Categorypopup_close')->text().'" onclick="handleCancel()" />
</form>

</body>
</html>
			';
		return $display . "\n";
	}

	/**
	 * processSupSubmit - process SpecialUncategorizedpages Submit to set category.  AJAX call.
	 */
	static function getHTMLsupSubmit($jsonAry) {
		global $wgUser;

		$category = "";
		$textnew = "";

		if ($wgUser->getID() <= 0) {
			echo "User not logged in";
			return false;
		}

		$ctitle = $jsonAry["ctitle"];
		if ($jsonAry["topcategory0"] != "") {
			$category0 = urldecode($jsonAry["category0"]);
			$category .= "[[Category:".$category0."]]\n";
			if ($jsonAry["topcategory1"] != "") {
				$category1 = urldecode($jsonAry["category1"]);
				$category .= "[[Category:".$category1."]]\n";
			}

			$title = Title::newFromURL(urldecode($ctitle));
			if ($title == null) {
				echo "ERROR: title is null for $url";
				exit;
			}

			if ($title->getArticleID() > 0) {
				// we want the most recent version, don't want to overwrite changes
				$a = new Article($title);
				$text = $a->getContent();

				$pattern = '/== .*? ==/';
				if (preg_match($pattern, $text, $matches, PREG_OFFSET_CAPTURE)) {

					$textnew = substr($text,0,$matches[0][1]) . "\n";
					$textnew .= $category ;
					$textnew .= substr($text,$matches[0][1]) . "\n";

					$summary = "categorization";
					$minoredit = "";
					$watchthis = "";
					$bot = true;

					# update the article here
					if( $a->doEdit( $textnew, $summary, $minoredit, $watchthis ) ) {
						wfRunHooks("CategoryHelperSuccess", array());
						echo "Category Successfully Saved.\n";
						return true;
					} else {
						echo "ERROR: Category could not be saved.\n";
					}
				} else {
					echo "ERROR: Category section could not be located.\n";
				}
			} else {
				echo "ERROR: Article could not be found. [$url]\n";
			}
		} else {
			echo "No Category selected\n";
		}
		return false;
	}

	static function getTitleCategoryMask($title) {
		global $wgCategoryNames, $wgContLang;
		if (!$title || $title->getNamespace() != NS_MAIN) return 0;

		$topcats = array_flip($wgCategoryNames);
		$top = $title->getParentCategoryTree();
		$flat = wfFlattenArrayCategoryKeys($top);
		$clean = array();
		foreach ($flat as $f) {
			$f = preg_replace("@^" . $wgContLang->getNsText(NS_CATEGORY) . ":@", "", $f);
			$x = Title::makeTitle(NS_CATEGORY, $f);
			$clean[] = $x->getText();
		}

		// set the usual category params
		$top = self::getTitleTopLevelCategories($title);
		$val = 0;
		foreach ($top as $c) {
			$val = $val | $topcats[$c->getText()];
		}

		return $val;
	}

	static function getTitleTopLevelCategories($title) {
		global $wgCategoryNames, $wgContLang;
		$tree = $title->getParentCategoryTree();
		$mine = array_unique( wfFlattenArrayCategoryKeys($tree) );
		$topcats = $wgCategoryNames;
		$results = array();
		foreach ($mine as $m) {
			$y = Title::makeTitle(NS_CATEGORY, str_replace($wgContLang->getNsText(NS_CATEGORY) . ":", "", $m));
			if (in_array($y->getText(), $topcats)) {
				$results[] = $y;
			}
		}
		return $results;
	}

}

