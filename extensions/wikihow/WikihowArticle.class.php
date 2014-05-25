<?
global $IP;

require_once("$IP/extensions/wikihow/common/composer/vendor/electrolinux/phpquery/phpQuery/phpQuery.php");
require_once("$IP/skins/WikiHowSkin.php");

/**
 * A class that represents a wikiHow article. Used to add special processing
 * on top of the Article class (but without adding any explicit database
 * access methods).
 *
 * NOTE: this desperately needs to be refactored. It should inherit from Article
 */
class WikihowArticleEditor {

	/*private*/
	 var $mSteps, $mTitle, $mLoadText;
	 var $section_array;
	 var $section_ids;
	 var $mSummary, $mCategories, $mLangLinks;

	/*private */
	 var $mTitleObj;

	/*private*/
	 var $mIsWikiHow, $mIsNew;

	 var $mWikiPage;

	/*static */
	public static $imageArray;

	private function __construct() {
		$this->mSteps = "";
		$this->mTitle = "";
		$this->mSummary = "";
		$this->mIsWikiHow = true;
		$this->mIsNew = true;
		$this->mCategories = array();
		$this->section_array = array();
		$this->section_ids = array();
		$this->mLangLinks = "";
		$this->mLoadText = "";
	}

	private static $instance = null;

	// singleton constructor
	static function singletonFromWikiPage($wikiPage) {
		if (self::$instance == null) {
			self::$instance = new WikihowArticleEditor();

			self::$instance->mWikiPage = $wikiPage;
			self::$instance->mTitleObj = $wikiPage->getTitle();
			self::$instance->mTitle = $wikiPage->getTitle()->getText();
			$text = ContentHandler::getContentText($wikiPage->getContent(Revision::RAW));
			self::$instance->loadFromText($text);
		}
		return self::$instance;
	}

	// this function is here to support getting the wikitext from an article by title,
	// but will not initialize a new wikihowArticle editor.. it exists to support functions
	// that want the wikitext only if it matches the currently loaded one.
	static function wikiHowArticleIfMatchingTitle($title) {
		if (self::$instance) {
			if (self::$instance->mTitle == $title) {
				return self::$instance;
			}
		}
		return null;
	}

	// will get an instance of this class with the given title..
	// will return the shared instance if it has the same title but will not overwrite the shared instance
	static function newFromTitle($title) {
		// see if we already have this as our main instance...
		if (self::$instance && self::$instance->mTitle == $title) {
			return self::$instance;
		}

		$wikiPage = new WikiPage($title);
		if (!$wikiPage) return null;
		$whow = new WikihowArticleEditor();
		$whow->mTitleObj = $wikiPage->getTitle();
		$whow->mWikiPage = $wikiPage;
		$whow->mTitle = $wikiPage->getTitle()->getText();
		$text = ContentHandler::getContentText($wikiPage->getContent(Revision::RAW));
		$whow->loadFromText($text);

		return $whow;
	}

	function newFromText($text) {
		$whow = new WikihowArticleEditor();
		$whow->loadFromText($text);
		return $whow;
	}

	private function loadFromText($text) {
		global $wgContLang, $wgParser;
		
		$this->mLoadText = $text;
		// extract the category if there is one
		// TODO: make this an array

		$this->mCategories = array();

		// just extract 1 category for now
		//while ($index !== false && $index >= 0) { // fix for multiple categories
		preg_match_all("/\[\[" .  $wgContLang->getNSText(NS_CATEGORY) . ":[^\]]*\]\]/im", $text, $matches);
		foreach($matches[0] as $cat) {
			$cat = str_replace("[[" . $wgContLang->getNSText(NS_CATEGORY) . ":", "", $cat);
			$cat = trim(str_replace("]]", "", $cat));
			$this->mCategories[] = $cat;
			$text = str_replace("[[" . $wgContLang->getNSText(NS_CATEGORY) . ":" . $cat . "]]", "", $text);
		}

		// extract interlanguage links
		$matches = array();
		if ( preg_match_all('/\[\[[a-z][a-z]:.*\]\]/', $text, $matches) ) {
			foreach ($matches[0] as $match) {
				$text = str_replace($match, "", $text);
				$this->mLangLinks .= "\n" . $match;
			}
		}
		$this->mLangLinks = trim($this->mLangLinks);

		// get the number of sections

		$sectionCount = self::getSectionCount($text);

		$found_summary = false;
		for ($i = 0; $i < $sectionCount; $i++) {
			$section = $wgParser->getSection($text, $i);
			$title = self::getSectionTitle($section);
			$section = trim(preg_replace("@^==.*==@", "", $section));
			$title = strtolower($title);
			$title = trim($title);
			if ($title == "" && !$found_summary) {
				$this->section_array["summary"] = $section;
				$this->section_ids["summary"] = $i;
				$found_summary = true;
			} else {
				$orig = $title;
				$counter = 0;
				while (isset($section_array[$title])) {
					$title = $orig + $counter;
				}
				$title = trim($title);
				$this->section_array[$title] = $section;
				$this->section_ids[$title] = $i;
			}
		}

		// set the steps
		// AA $index = strpos($text, "== Steps ==");
		// AA if (!$index) {
		if ($this->hasSection("steps") == false) {
			$this->mIsWikiHow = false;
			return;
		}

		$this->mSummary = $this->getSection("summary");
		$this->mSteps = $this->getSection(wfMessage('steps')->text());

		// TODO: get we get tips and warnings from getSection?
		$this->mIsNew = false;

	}

	// used by formatWikiText below
	private static function formatBulletList($text) {
		$result = "";
		if ($text == null || $text == "") return $result;
		$lines = split("\n", $text);
		if (!is_array($lines)) return $result;
		foreach($lines as $line) {
			if (strpos($line, "*") === 0) {
				$line = substr($line, 1);
			}
			$line = trim($line);
			if ($line != "") {
				$result .= "*$line\n";
			}
		}
		return $result;
	}

	/**
	 * Returns the index of the given section
	 * returns -1 if not known
	 */
	function getSectionNumber($section) {
		$section = strtolower($section);
		if ( !empty($this->section_ids[$section]) )
			return $this->section_ids[$section];
		else
			return -1;
	}

	private function setSteps($steps) {
		$this->mSteps = $steps;
	}

	private function setTitle($title) {
		$this->mTitle = $title;
	}

	private function setSummary($summary) {
		$this->mSummary = $summary;
	}

	private function setCategoryString($categories) {
		$this->mCategories = split(",", $categories);
	}

	function getLangLinks() {
		return $this->mLangLinks;
	}

	private function setLangLinks($links) {
		$this->mLangLinks = $links;
	}

	function getSteps($forEditing = false) {
		return str_replace("\n\n", "\n", $this->mSteps);
	}

	function getTitle() {
		return $this->mTitle;
	}

	function getSummary() {
		return $this->mSummary;
	}

	/**
	 * This function is used in places where the intro is shown to help
	 * in various backend tools (Intro Image Adder, Video Adder, etc)
	 * This removes all images for these tools.
	 */
	static function removeWikitext($text) {
		global $wgParser, $wgTitle;

		//remove all images
		$text = preg_replace('@\[\[Image:[^\]]*\]\]@im', '', $text);

		//then turn wikitext into html
		$options = new ParserOptions();
		$text = $wgParser->parse($text, $wgTitle, $options)->getText();

		//need to remove all <pre></pre> tags (not sure why they sometimes get added
		$text = preg_replace('/\<pre\>/i', '', $text);
		$text = preg_replace('/\<\/pre\>/i', '', $text);

		return $text;
	}

	// DEPRECATED -- used only in EditPageWrapper.php
	function getCategoryString() {
		$s = "";
		foreach ($this->mCategories as $cat) {
			$s .= $cat . "|";
		}
		return $s;
	}

	// USE OF THIS METHOD IS DEPRECATED
	// it munges the wikitext too much and can't handle alt methods
	// EditPageWrapper.php is the only file that should use it
	function formatWikiText() {
		global $wgContLang;

		$text = $this->mSummary . "\n";

		// move all categories to the end of the intro
		$text = trim($text);
		foreach ($this->mCategories as $cat) {
			$cat = trim($cat);
			if ($cat != "") {
				$text .= "\n[[" . $wgContLang->getNSText(NS_CATEGORY) . ":$cat]]";
			}
		}

		$ingredients = $this->getSection("ingredients");
		if ($ingredients != null && $ingredients != "") {
			$tmp = self::formatBulletList($ingredients);
			if ($tmp != "") {
				$text .= "\n== "  . wfMessage('ingredients')->text() .  " ==\n" . $tmp;
			}
		}

		$step = split("\n", $this->mSteps);
		$steps = "";
		foreach ($step as $s) {
			$s = ereg_replace("^[0-9]*", "", $s);
			$index = strpos($s, ".");
			if ($index !== false &&  $index == 0) {
				$s = substr($s, 1);
			}
			if (trim($s) == "") continue;
			$s = trim($s);
			if (strpos($s, "#") === 0) {
				$steps .= $s . "\n";
			} else {
				$steps .= "#" . $s . "\n";
			}
		}
		$this->mSteps = $steps;

		$text .= "\n== "  . wfMessage('steps')->text() .  " ==\n" . $this->mSteps;

		$tmp = $this->getSection("video");
	   	if ($tmp != "")
		  $text .= "\n== "  . wfMessage('video')->text() .  " ==\n" . trim($tmp) . "\n";

		// do the bullet sections
		$bullet_lists = array("tips", "warnings", "thingsyoullneed", "related", "sources");
		foreach ($bullet_lists as $b) {
			$tmp = self::formatBulletList($this->getSection($b));
			if ($tmp != "") {
				$text .= "\n== "  . wfMessage($b)->text() .  " ==\n" . $tmp;
			}
		}

		$text .= $this->mLangLinks;

		/// add the references div if necessary
		if (strpos($text, "<ref>") !== false) {
			$rdiv = '{{reflist}}';
			$headline = "== "  . wfMessage('sources')->text() .  " ==";
			if (strpos($text, $headline) !== false) {
				$text = trim($text) . "\n$rdiv\n";
				//str_replace($headline . "\n", $headline . "\n" . $rdiv . "\n", $text);
			} else {
				$text .=  "\n== "  . wfMessage('sources')->text() .  " ==\n" . $rdiv . "\n";
			}
		}

		return $text;
	}

	function getFullURL() {
	 	return $this->mTitleObj->getFullURL();
	}

	function getDBKey() {
	 	return $this->mTitleObj->getDBKey();
	}

	function isWikiHow() {
	 	return $this->mIsWikiHow;
	}

	/*
	 * We might want to update this function later to be more comprehensive.
	 * For now, if it has == Steps == in it, it's a wikiHow article.
	 */
	static function articleIsWikiHow($article) {
		if (!$article instanceof Article) return false;
		if (!$article->mTitle instanceof Title) return false;
		if ($article->getTitle()->getNamespace() != NS_MAIN) return false;
		$text = $article->getContent();
		$count = preg_match('/^==[ ]*' . wfMessage('steps')->text() . '[ ]*==/mi', $text);
		return $count > 0;
	}

	/**
	 * Returns true if the guided editor can be used on this article.
	 * Iterates over the article's sections and makes sure it contains
	 * all the normal sections.
	 *
	 * DEPRECATED -- used only in includes/Wiki.php to determine if we
	 * can load the article in the guided editor.
	 */
	static function useWrapperForEdit($article) {
		global $wgWikiHowSections;

	 	$index = 0;
	 	$foundSteps = 0;
	 	$text = $article->getContent(true);

		$mw = MagicWord::get( 'forceadv' );
		if ($mw->match( $text ) ) {
			return false;
		}
		$count = self::getSectionCount($text);

		// these are the good titles, if we have a section title
		// with a title in this list, the guided editor can't handle it
		$sections = array();
		foreach($wgWikiHowSections as $s) {
			$sections[] = wfMessage($s)->text();
		}

	 	while ($index < $count) {	
	 		$section = $article->getSection($text, $index); 
	 		$title = self::getSectionTitle($section);

			if ($title == wfMessage('steps')->text()) {
	 			$foundSteps = true;
	 		} elseif ($title == "" && $index == 0) {
				// summary
	 		} elseif (!in_array($title, $sections)) {
	 			return false;
	 		}
	 		if (!$section) {
	 			break;
	 		}
	 		$index++;
	 	}

	 	if ($index <= 8) {
	 		return $foundSteps;
	 	} else {
	 		return false;
	 	}
	}

	private static function getSectionCount($text) {
		$matches = array();
		# Reuben note: I separate the ? character from the > characters below because 
		# there are some syntax highlighters that interpret that as END OF PHP
		preg_match_all( '/^(=+).+?=+|^<h([1-6]).*?' . '>.*?<\/h[1-6].*?' . '>(?!\S)/mi', $text, $matches);
		return count($matches[0]) + 1;
	}

	 /**
	  *   Given a MediaWiki section, such as
	  *   == Steps ===
	  *   1. This is the first step.
	  *   2. This is the second step.
	  *
	  *   This function returns 'Steps'.
	  */
	private static function getSectionTitle($section) {
	 	$title = "";
	 	$index = strpos(trim($section), "==");
	 	if ($index !== false && $index == 0) {
	 		$index2 = strpos($section, "==", $index+2);
	 		if ($index2 !== false && $index2 > $index) {
	 			$index += 2;
	 			$title = substr($section, $index, $index2-$index);
	 			$title = trim($title);
	 		}
	 	}
	 	return $title;
	}

	function hasSection($title) {
		$ret = isset($this->section_array[strtolower(wfMessage($title)->text())]);
	 	if (!$ret) $ret = isset($this->section_array[$title]);
	 	return $ret;
	}

	function getSection($title) {
		$title = strtolower($title);
	 	if ($this->hasSection($title)) {
			$sectionName = strtolower(wfMessage($title)->text());
			$ret = isset($this->section_array[$sectionName]) ? $this->section_array[$sectionName] : null;
			$ret = empty($ret) ? $this->section_array[$title] : $ret;
			return $ret;
	 	} else {
	 		return "";
	 	}
	}

	private function setSection($title, $section) {
		$this->section_array[$title] = $section;
	}

	private function setRelatedString($related) {
		 $r_array = split("\|", $related);
		 $result = "";
		 foreach ($r_array as $r) {
			 $r = trim($r);
			 if ($r == "") continue;
			 $result .= "*  [[" . $r . "|" . wfMessage('howto', $r)->text() . "]]\n";
		 }
		 $this->setSection("related", $result);
	}

	// DEPRECATED -- used only in EditPageWrapper.php
	static function newFromRequest($request) {
		$whow = new WikihowArticleEditor();
		$steps = $request->getText("steps");
		$tips  = $request->getText("tips");
		$warnings = $request->getText("warnings");
		$summary =  $request->getText("summary");

		$category = "";
		$categories = "";
		for ($i = 0; $i < 2; $i++) {
			if ($request->getVal("category" . $i, null) != null) {
				if ($categories != "") $categories .= ", ";
				$categories .= $request->getVal("category" . $i);
			} else if ($request->getVal('topcategory' . $i, null) != null && $request->getVal('TopLevelCategoryOk') == 'true') {
				if ($categories != "") $categories .= ", ";
				$categories .= $request->getVal("topcategory" . $i);
			}
		}

		$hidden_cats = $request->getText("categories22");
		if ($categories == "" && $hidden_cats != "")
			$categories = $hidden_cats;

		$ingredients = $request->getText("ingredients");

		$whow->setSection("ingredients", $ingredients);
		$whow->setSteps($steps);
		$whow->setSection('tips', $tips);
		$whow->setSection('warnings', $warnings);
		$whow->setSummary($summary);
		$whow->setSection("thingsyoullneed", $request->getVal("thingsyoullneed"));
		$whow->setLangLinks($request->getVal('langlinks'));

		$related_no_js = $request->getVal('related_no_js');
		$no_js = $request->getVal('no_js');

		if ($no_js != null && $no_js == true) {
			$whow->setSection("related", $related_no_js);

		} else {
			// user has javascript
			$whow->setRelatedString($request->getVal("related_list"));
		}
		$whow->setSection("sources", $request->getVal("sources"));
		$whow->setSection("video", $request->getVal("video"));
		$whow->setCategoryString($categories);
		return $whow;
	}

	/**
	 *
	 * Convert wikitext to plain text
	 *
	 * @param    text  The wikitext
	 * @param    options An array of options that you would like to keep in the text
	 *				"category": Keep category tags
	 *				"image": Keep image tags
	 *				"internallinks": Keep internal links the way they are
	 *				"externallinks": Keep external links the way they are
	 *				"headings": Keep the headings tags
	 *				"templates": Keep templates
	 *				"bullets": Keep bullets
	 * @return     text
	 *
	 */
	function textify($text, $options = array()) {
		// take out category and image links
		$tags = array();
		if (!isset($options["category"])) {
			$tags[] = "Category";
		}
		if (!isset($options["image"])) {
			$tags[] = "Image";
		}
		$text = preg_replace("@^#[ ]*@m", "", $text);
		foreach ($tags as $tag) {
			$text = preg_replace("@\[\[{$tag}:[^\]]*\]\]@", "", $text);
		}

		// take out internal links
		if (!isset($options["internallinks"])) {
			preg_match_all("@\[\[[^\]]*\|[^\]]*\]\]@", $text, $matches);
			foreach ($matches[0] as $m) {
				$n = preg_replace("@.*\|@", "", $m);
				$n = preg_replace("@\]\]@", "", $n);
				$text = str_replace($m, $n, $text);
			}

			// internal links with no alternate text
			$text = preg_replace("@\]\]|\[\[@", "", $text);
		}

		// external links
		if (isset($options["remove_ext_links"])) {
			// for [http://google.com proper links]
			$text = preg_replace("@\[[^\]]*\]@", "", $text);
			// for http://www.inlinedlinks.com
			$text = preg_replace("@http://[^ |\n]*@", "", $text);
		} else if (!isset($options["externallinks"])) {
			// take out internal links
			preg_match_all("@\[[^\]]*\]@", $text, $matches);
			foreach ($matches[0] as $m) {
				$n = preg_replace("@^[^ ]*@", "", $m);
				$n = preg_replace("@\]@", "", $n);
				$text = str_replace($m, $n, $text);
			}
		}

		// headings tags
		if (!isset($options["headings"])) {
			$text = preg_replace("@^[=]+@m", "", $text);
			$text = preg_replace("@[=]+$@m", "", $text);
		}

		// templates
		if (!isset($options["templates"])) {
			$text = preg_replace("@\{\{[^\}]*\}\}@", "", $text);
		}

		// bullets
		if (!isset($options["bullets"])) {
			$text = preg_replace("@^[\*|#]*@m", "", $text);
		}

		// leading space
		$text = preg_replace("@^[ ]*@m", "", $text);

		// kill html
		$text = strip_tags($text);

		return trim($text);
	}

	// Removes method prefix in alt method names, and returns types of alt methods for display to user
	static function removeMethodNamePrefix(&$name) {
		global $wgLanguageCode;
		$ret = array('has_parts' => false, 'has_methods' => true);
		$count = 0;
	
		// For English we use the partRegex. For international, we allow multiple words for part line-seperated in the parts message
		$partRegex = '@^Part [^:.-]+[:.-]@';
		if($wgLanguageCode != 'en') {
			$parts = preg_split('@[\r\n]+@', wfMessage('parts')->text());
			if($parts) {
				$partRegex = array();
				foreach($parts as $part) {
					$partRegex[] = '@^' . preg_quote($part, '@') . '[^:]*(:|$)@i';
				}
			}
		}

		$name = preg_replace($partRegex, '', $name, -1, $count);
		if ($count > 0) {
			$name = trim($name);
			$ret['has_parts'] = true;
			$ret['has_methods'] = false;
			return $ret;
		}
	
		// For English we use the methodRegex and respective matchRegex
		// For international, we allow multiple words for method line-seperated in the methods message, but only method names of a single regex form are allowed
		$methodRegex = array('@^Method [^:.-]+([:.-]|$)@', '@^Option [^:-]+[:-]@', '@^Project [^:-]+[:-]@', '@^Methods$@', '@^Method \d+ of \d+@', '@^(First|Second|Third|[A-Z][a-z]+th) Method([:-]|$)@', '@^Method[ -]\d+\s*\(([^)]+)\)$@');
		$matchRegex = array('', '', '', '', '', '', '$1');
		if($wgLanguageCode != 'en') {
			$methods = preg_split("@[\r\n]+@", wfMessage('methods')->text());
			if($methods) {
				$methodRegex = array();
				$matchRegex = array();	
				foreach($methods as $method) {
					$methodRegex[] = '@^' . preg_quote($method) . ' [^:.-]+([:.-])@i';
					$matchRegex[] = '';
				}
				foreach($methods as $method) {
					$methodRegex[] = '@^' . preg_quote($method) . '@i';
					$matchRegex[] = '';

				}
			}
		}
		$name = preg_replace(
			$methodRegex,
			$matchRegex,
			$name, -1, $count);
		if ($count > 0) {
			$name = trim($name);
		}
		return $ret;
	}

	static function grabArticleEditLinks($isGuided) {
		global $wgTitle, $wgUser, $wgLanguageCode, $wgRequest;

		$article = new Article($wgTitle);

		$relHTML = '';
		$relBtn = '';
		$editLink = '';
		$edithelp = '';
		if (self::articleIsWikiHow($article)
			|| ($wgTitle->getArticleID() == 0
				&& $wgTitle->getNamespace() == NS_MAIN) )
		{
			$sk = $article->getContext()->getSkin();

			if (class_exists('Html5editor') && isHtml5Editable(true)) {
				$relQuery = array('h5e' => 'true');
				// if article doesn't exist, enter into article creation mode
				if ($wgTitle->getArticleID() <= 0) {
					$relQuery['create-new-article'] = 'true';
				}
				$editLink = Linker::linkKnown($wgTitle, wfMessage('html5_editor')->text(), array("id"=>"othereditlink"), $relQuery) . wfMessage('h5e_save_first')->text();

			} else {
				$oldparameters = "";
				if ($wgRequest->getVal("oldid") != "") {
					$oldparameters = "&oldid=" . $wgRequest->getVal("oldid");
				}
				if ($isGuided) {
					$editLink = $sk->makeKnownLinkObj($wgTitle, wfMessage('advanced_editing_link')->text(), 'action=edit&advanced=true' . $oldparameters, '','','');
					//weave links button
					$relBtn = $wgLanguageCode == 'en' ? PopBox::getGuidedEditorButton() : '';
					$relHTML = PopBox::getPopBoxJSGuided() . PopBox::getPopBoxDiv() . PopBox::getPopBoxCSS();
				}
				else {
					$editLink = $sk->makeKnownLinkObj($wgTitle, wfMessage('guided_editing_link')->text(), 'action=edit&override=yes' . $oldparameters, '','','');
				}
			}

			$edithelpurl = $sk->makeInternalOrExternalUrl( wfMsgForContent( 'edithelppage' ));
			$edithelp = '<a target="helpwindow" href="'.$edithelpurl.'">'.
				htmlspecialchars( wfMessage( 'edithelp_link' )->text() ).'</a>';
		}
		// Take out switch to guided editing and editing help on edit page for logged out users on international
		if ($wgLanguageCode == "en" || $wgTitle->userCan('edit')) {
			$editlinks = $relHTML . '<div class="editpage_links">' . $editLink . ' ' . $relBtn . ' ' . $edithelp . '</div>';
		}
		else {
			$editlinks = '';	
		}

		return $editlinks;
	}

	static function setImageSections($articleText) {
		global $wgContLang;

		$sectionArray = array("summary", "steps", "video", "tips", "warnings", "things you'll need", "related wikihows", "ingredients", "sources and citations");

		self::$imageArray = array();

		$who = WikihowArticleEditor::newFromText($articleText);
		$nsTxt = "(Image|" . $wgContLang->getNsText(NS_IMAGE) . ")";

		foreach($who->section_array as $section => $sectionText) {
			if(!in_array($section, $sectionArray))
				$section = "steps";
			if(preg_match_all("@([\*]*)\[\[" . $nsTxt . ":([^\|\]]+)[^\]]*\]\]@im", $sectionText, $matches) > 0) {
				foreach($matches[3] as $index => $match) {
					$match = str_replace(" ", "-", $match);
					if($section == "steps" && $matches[1][$index] == "*")
						self::$imageArray[ucfirst($match)] = "substep";
					else
						self::$imageArray[ucfirst($match)] = $section;
				}
			}

			if(preg_match_all("@\{\{largeimage\|([^\}]+)\}\}@im", $sectionText, $matchesLarge) > 0) {
				foreach($matchesLarge[1] as $match) {
					//apparently some large images still have captions so get rid of everything else
					$parts = explode("|", $match);
					$imageName = str_replace(" ", "-", $parts[0]);
					self::$imageArray[ucfirst($imageName)] = $section;
				}
			}

		}
	}

	static function getImageSection($image) {
		$imgName = ucfirst($image);
		return isset(self::$imageArray[$imgName]) ? self::$imageArray[$imgName] : '';

	}

	static function resolveRedirects($title) {
		$res = null;
		$i = 5; // max redirects
		$dbr = wfGetDB(DB_SLAVE);

		while ($i > 0 && $title && $title->exists()) {
			$titleKey = $dbr->selectField('redirect', 
				'rd_title', 
				array('rd_from' => $title->getArticleID(),
					'rd_namespace' => $title->getNamespace()),
				__METHOD__);
			if (!$titleKey) break;
			$title = Title::newFromDBkey($titleKey);
			$i--;
		}
		if ($i > 0 && $title) {
			$res = $title;
		}
		return $res;
	}

}

class WikihowArticleHTML {

	static function processArticleHTML($body, $opts = array()) {
		global $wgUser, $wgTitle, $wgLanguageCode;

		$doc = PHPQuery::newDocument($body);

		$featurestar = pq("div#featurestar");
		if($featurestar) {
			$clearelement = pq($featurestar)->next();
			$clearelement->remove();
			$featurestar->remove();
		}

		$ads = $wgUser->isAnon() && !@$opts['no-ads'] && wikihowAds::isEligibleForAds();

		// Remove __TOC__ resulting html from all pages other than User pages
		if(@$opts['ns'] != NS_USER && pq('div#toc')->length) {
			$toc = pq('div#toc');
			//in upgrade, it's no longer preceded by an h2, so deleting the intro instead :(
			//maybe this will change so leaving in for now.
			//$toc->prev()->remove();
			$toc->remove();
		}

		$sticky = "";
		if(@$opts['sticky-headers'])
			$sticky = " sticky ";

		// Remove originators for titles that don't exist
		if($wgTitle->getArticleId() == 0) {
			pq('#originators')->remove();
		}

		//move firstHeading to inside the intro
		$firstH2 = pq("h2:first");
		if(pq($firstH2)->length() == 0) {
			try {
				pq("#bodycontents")->children(":first")->wrapAll("<div class='section wh_block'></div>");
			} catch (Exception $e) {
			}
		}
		else {
			try {
				pq($firstH2)->prevAll()->reverse()->wrapAll("<div id='intro' class='section {$sticky}'></div>");
			} catch (Exception $e) {
			}
		}

		//add a clearall to the end of the intro
		pq("#intro")->append("<div class='clearall'></div>");

		//removing any stray br tags at the start of the intro
		foreach(pq("#intro #originators")->next()->children() as $child) {
			if(pq($child)->is("br"))
				pq($child)->remove();
			else
				break;
		}

		//add the pimpedheader to our h3s!
		pq('h3, h4')->prepend('<div class="altblock"></div>');

		foreach(pq("h2") as $node) {
			$h2Parent = pq($node)->parent();
			if(@$opts['ns'] == NS_USER && pq($h2Parent)->attr("id") == "toctitle") {
				pq("#toc")->wrapAll("<div class='section'></div>");
				pq("#toc ul:first")->addClass("section_text");
				continue;
			}
			//find each section

			//first grab the name
			$sectionName = mb_strtolower( pq("span.mw-headline", $node)->html() );
			//Remove all non-letters and numbers in all languges
			if($wgLanguageCode == 'en') {
				$sectionName = preg_replace("/[^A-Za-z0-9]/u", '', $sectionName);
			}
			elseif($wgLanguageCode == 'hi') {
				$sectionName = str_replace(' ','',$sectionName);
			}
			else {
				$sectionName = preg_replace("/[^\p{L}\p{N}]/u", '', $sectionName);
			}
			//now find all the elements prior to the next h2
			$set = array();
			$h3Tags = array();
			$h3Elements = array();
			$priorToH3Set = array();
			$h3Count = 0;

			foreach(pq($node)->nextAll() as $sibling) {
				if(pq($sibling)->is("h2")) {
					break;
				}
				if(pq($sibling)->is("h3")) {
					$h3Count++;
					$h3Tags[$h3Count] = $sibling;
					$h3Elements[$h3Count] = array();
				}
				else {
					if($h3Count > 0)
						$h3Elements[$h3Count][] = $sibling;
					else {
						$priorToH3Set[] = $sibling;
					}
				}
				$set[] = $sibling;
			}
			if(mb_strtolower($sectionName) == mb_strtolower(wfMessage('steps')->text())) {
				if($h3Count > 0) {
					//has alternate methods

					$altMethodNames = array();
					$altMethodAnchors = array();

					if(count($priorToH3Set) > 0) {
						//needs to have a steps section prior to the
						//alt method
						try {
							pq($priorToH3Set)->wrapAll("<div id='{$sectionName}' class='section_text'></div>");
						} catch (Exception $e) {
						}

						$overallSet = array();
						$overallSet[] = $node;
						foreach( pq("div#{$sectionName}:first") as $temp){
							$overallSet[] = $temp;
						}

						try {
							pq($overallSet)->wrapAll("<div class='section steps {$sticky}'></div>");
						} catch (Exception $e) {
						}
					}
					else {
						//hide the h2 tag
						pq($node)->addClass("hidden");
					}

					$stepsEditUrl = pq('.editsection', $node)->attr("href");

					$displayMethodCount = $h3Count;
					$isSample = array();
					for($i = 1; $i <= $h3Count; $i++) {
                        $isSampleItem = false;
                        if(!is_array($h3Elements[$i]) || count($h3Elements[$i]) < 1) {
                            $isSampleItem = false;
                        }
                        else {
                            //the sd_container isn't always the first element, need to look through all
                            foreach($h3Elements[$i] as $node) { //not the most efficient way to do this, but couldn't get the find function to work.
                                if(pq($node)->attr("id") == "sd_container") {
                                    $isSampleItem = true;
                                    break;
                                }
                            }
                        }
						if ( $isSampleItem )
						{
							$isSample[$i] = true;
							$displayMethodCount--;
						} else {
							$isSample[$i] = false;
						}
					}

					if($ads) {
						wikihowAds::setAltMethods($displayMethodCount > 1);
					}

					//[sc] ***INTERMEDIATE STEP (remove line below)
					$wikihowArticle = WikihowArticleEditor::newFromTitle($wgTitle);
					
					$hasParts = $opts['magic-word'] == MagicWord::get( 'parts' );
						
					$displayMethod = 1;
					for($i = 1; $i <= $h3Count; $i++) {
						//change the method title
						$methodTitle = pq("span.mw-headline", $h3Tags[$i])->html();
						//[sc] ***INTERMEDIATE STEP (remove line below)
						$removeRet = WikihowArticleEditor::removeMethodNamePrefix( $methodTitle );
						$altMethodNames[] = $methodTitle;
						$altMethodAnchors[] = pq("span.mw-headline", $h3Tags[$i])->attr("id");
						//[sc] ***INTERMEDIATE STEP (swap if logic below)
						//if ($displayMethodCount > 1 && $hasParts && $opts['ns'] == NS_MAIN) {
						if ($displayMethodCount > 1 && !$isSample[$i] && ($removeRet['has_parts'] || $hasParts) && $opts['ns'] == NS_MAIN) {
							if ($methodTitle) {
								$methodTitle = wfMessage("part_2",$displayMethod,$displayMethodCount,$methodTitle)->text(); 
							} else {
								$methodTitle = wfMessage("part_1",$displayMethod, $displayMethodCount)->text(); 
							}
							$displayMethod++;
						} elseif ($displayMethodCount > 1 && !$isSample[$i] && $opts['ns'] == NS_MAIN) {
							if ($methodTitle) {
								$methodTitle = wfMessage("method_2",$displayMethod,$displayMethodCount, $methodTitle)->text(); 
							} else {
								$methodTitle = wfMessage("method_1",$displayMethod,$displayMethodCount)->text();
							}
							$displayMethod++;
						}
						pq("span.mw-headline", $h3Tags[$i])->html($methodTitle);

						//add our custom anchors
						$anchor_name = pq("span.mw-headline", $h3Tags[$i])->attr('id').'_sub';
						try {
							pq($h3Tags[$i])->before('<a name="'.$anchor_name.'" class="anchor"></a>');
						} catch (Exception $e) {
						}

						//want to change the url for the edit link to
						//edit the whole steps section, not just the
						//alternate method
						pq(".editsection", $h3Tags[$i])->attr("href", $stepsEditUrl);

						$sample = $isSample[$i] ? "sample" : "";

						//only wrap if there's stuff there to wrap.
						//This happens when people put two sub methods on top of each other without
						//any content between.
						if(count($h3Elements[$i]) > 0) {
							pq($h3Elements[$i])->wrapAll("<div id='{$sectionName}_{$i}' class='section_text'></div>");
						}
						$overallSet = array();
						$overallSet[] = $h3Tags[$i];
						foreach( pq("div#{$sectionName}_{$i}:first") as $temp){
							$overallSet[] = $temp;
						}
						try {
							pq($overallSet)->wrapAll("<div class='section steps {$sample} {$sticky}'></div>");
						} catch (Exception $e) {
						}
					}

					//BEBETH - not sure we need this anymore, but not sure yet
					//fix for Chrome -- wrap first anchor name so it detects the spacing
					try {
						pq(".section.steps:first")->prev()->children(".anchor")->after('<br class="clearall" />')->wrapAll('<div></div>');
					} catch (Exception $e) {
					}

					//now we should have all the alt methods,
					//let's create the links to them under the headline
					$charCount = 0;
					$maxCount = 80000; //temporarily turning off hidden headers
					$hiddenCount = 0;
					$anchorList = "";
					for($i = 0; $i < count($altMethodAnchors); $i++) {
						$methodName = pq('<div>' . $altMethodNames[$i] . '</div>')->text();
						// remove any reference notes
						$methodName = preg_replace("@\[\d{1,3}\]$@", "", $methodName);
						$charCount += strlen($methodName);
						$class = "";
						if($charCount > $maxCount) {
							$class = "hidden excess";
							$hiddenCount++;
						}
						if($methodName == "")
							continue;
						$anchorList .= "<a href='#{$altMethodAnchors[$i]}_sub' class='{$class}'>{$methodName}</a>";
					}

					$hiddentext = "";
					if($hiddenCount > 0) {
						$hiddenText = "<a href='#' id='method_toc_unhide'>{$hiddenCount} more method" . ($hiddenCount > 1?"s":"") . "</a>";
						$hiddenText .= "<a href='#' id='method_toc_hide' class='hidden'>show less methods</a>";
					}
					else {
						$hiddenText = '';
					}
					
					//add our little list header
					if ($hasParts) {//ucwords
						$anchorList = 	'<span>'.ucwords(Misc::numToWord(count($altMethodAnchors),10)).
										' '. wfMessage('part_3')->text().':</span>'.$anchorList;
					}
					else {
						$anchorList = 	'<span>'.ucwords(Misc::numToWord(count($altMethodAnchors),10)).
										' '. wfMessage('method_3')->text().':</span>'.$anchorList;
					}
					
					//chance to reformat the alt method_toc before output
					//using for running tests
					$bAfter = false;
					wfRunHooks('BeforeOutputAltMethodTOC', array($wgTitle, &$anchorList, &$bAfter));
					
					$bAfter = true;
					if ($bAfter) {
						pq("#originators")->after("<p id='method_toc' style='margin-top:-10px'>{$anchorList}{$hiddenText}</p>");
					}
					else {
						pq(".firstHeading")->after("<p id='method_toc'>{$anchorList}{$hiddenText}</p>");
					}

				}
				else {
					//only 1 method
					if($ads) {
						wikihowAds::setAltMethods(false);
					}
					if ($set) {
						try {
							pq($set)->wrapAll("<div id='{$sectionName}' class='section_text'></div>");
						} catch (Exception $e) {
						}
					}

					$overallSet = array();
					$overallSet[] = $node;
					foreach( pq("div#{$sectionName}:first") as $temp){
						$overallSet[] = $temp;
					}

					try {
						pq($overallSet)->wrapAll("<div class='section steps {$sticky}'></div>");
					} catch (Exception $e) {
					}
				}
			}
			else {
				//not a steps section
				if ($set) {
					$sec_id = (@$opts['list-page']) ? '' : 'id="'.$sectionName.'"';
					try {
						$new_set = pq($set)->wrapAll("<div {$sec_id} class='section_text'></div>");
					} catch (Exception $e) {
					}
				}

				$overallSet = array();
				$overallSet[] = $node;
				foreach( pq("div#{$sectionName}:first") as $temp){
					$overallSet[] = $temp;
				}
				try {
					pq($overallSet)->wrapAll("<div class='section {$sectionName} {$sticky}'></div>");
				} catch (Exception $e) {
				}

				if (@$opts['list-page']) {
					//gotta pull those dangling divs into the same space as the h2
					try {
						pq($overallSet)->parent()->append(pq($new_set));
					} catch(Exception $e) {
					}
				}
				
				// commenting this out because it's causing the following error:
				// "Couldn't add newnode as the previous sibling of refnode"
				// // format edit links for non-steps sections
				// // pq('span', $node)->prepend(pq('a.edit', $node));
				
				//remove the edit link from subheaders if we're not in the steps section
				try {
					pq(".{$sectionName} h3 .editsection")->remove();
				} catch(Exception $e) {
				}
			}
		}

		//add a clear to the end of each section_text to make sure
		//images don't bleed across the bottom
		pq(".section_text")->append("<div class='clearall'></div>");

		// Add checkboxes to Ingredients and 'Things You Need' sections, but only to the top-most li
		$lis = pq('#ingredients > ul > li, #thingsyoullneed > ul > li');
		foreach ($lis as $li) {
			$id = md5(pq($li)->html() . mt_rand(1, 100));
			pq($li)->html("<input id='item_$id' class='css-checkbox' type='checkbox'/><label for='item_$id' name='item_{$id}_lbl' class='css-checkbox-label'></label><div class='checkbox-text'>" . pq($li)->html() . '</div>');
		}
		// Move templates above article body contents and style appropriately
		foreach (pq('.template_top') as $template) {
			pq($template)->addClass('sidebox');
			if (pq($template)->parent()->hasClass('tmp_li')) {
				pq($template)->addClass('tmp_li');
			}
			if ($wgUser->isAnon()) {
				pq($template)->addClass('notice_bgcolor_lo');
			} else {
				pq($template)->addClass('notice_bgcolor_important');
			}

		}
		// put templates after the intro div
		pq('.template_top')->insertAfter('#intro');

		//now put the step numbers in
		foreach(pq("div.steps .section_text > ol") as $list) {
			pq($list)->addClass("steps_list_2");
			$stepNum = 1;
			foreach(pq($list)->children() as $step) {
				$boldStep = WikihowArticleHTML::boldFirstSentence(pq($step)->html());
				pq($step)->html($boldStep);
				pq($step)->prepend("<div class='step_num'>{$stepNum}</div>");
				pq($step)->append("<div class='clearall'></div>");
				$stepNum++;
			}
		}

        foreach(pq(".steps:last .steps_list_2")->children(":last-child") as $step) {
            pq($step)->addClass("final_li");
        }


		//move each of the large images to the top
		foreach(pq(".steps_list_2 li .mwimg.largeimage") as $image) {
			//delete any previous <br>
			foreach(pq($image)->prevAll() as $node) {
				if( pq($node)->is("br") )
					pq($node)->remove();
				else
					break;
			}

			//first handle the special case where the image
			//ends up inside the bold tag by accident
			if(pq($image)->parent()->is("b")) {
				pq($image)->insertBefore(pq($image)->parent());
			}

			if(pq($image)->parent()->parent()->is(".steps_list_2")) {
				pq($image)->parent()->prepend($image);
			}
		}

		//move each of the large images to the top
		foreach(pq(".steps_list_2 li .whvid_cont") as $vid) {
			//delete any previous <br>
			foreach(pq($vid)->prevAll() as $node) {
				if( pq($node)->is("br") )
					pq($node)->remove();
				else
					break;
			}
			if(pq($vid)->parent()->parent()->is(".steps_list_2")) {
				pq($vid)->parent()->prepend($vid);
			}
		}

		//if there's a related articles section, make it have images
		$relatedSection = pq("#relatedwikihows");
		if($relatedSection) {
			foreach(pq("li a", $relatedSection) as $related) {
				$titleText = pq($related)->attr("title");
				$title = Title::newFromText($titleText);
				if($title) {
					$image = SkinWikihowskin::getArticleThumb($title, 127, 120);
					pq($relatedSection)->prepend($image);
				}
				pq($related)->remove();
			}
			pq("ul", $relatedSection)->remove();
			pq($relatedSection)->append("<div class='clearall'></div>");
		}

		//remove all images in the intro that aren't
		//marked with the class "introimage"
		pq("#intro .mwimg:not(.introimage)")->remove();

		//let's mark all the <p> tags that aren't inside a step.
		//they need special padding
		foreach(pq(".section.steps p") as $p) {
			if(pq($p)->parents(".steps_list_2")->count() == 0 && pq($p)->children(".anchor")->count() == 0) {
				pq($p)->addClass("lone_p");
			}
		}

		// Add alt method adder cta
		if (class_exists("AltMethodAdder") && $wgTitle && $wgUser && $wgUser->isAnon()) {
			$cta = AltMethodAdder::getCTA($wgTitle);
			if (!is_null($cta)) {
				pq("div.steps:last")->after($cta);
			}
		}

		//add line breaks between the p tags
		foreach(pq("p") as $paragraph) {
			$sibling = pq($paragraph)->next();
			if(!pq($sibling)->is("p"))
				continue;
			if(pq($sibling)->children(":first")->hasClass("anchor"))
				continue;
			$id = pq($paragraph)->attr("id");
			if($id == "originators" || $id == "method_toc")
				continue;

			pq($paragraph)->after("<br />");
		}

		if($ads) {
			pq(".#intro")->append(wikihowAds::getAdUnitPlaceholder('intro'));
			pq(".steps_list_2:first li:first")->append(wikihowAds::getAdUnitPlaceholder(0));
			pq(".final_li")->append(wikihowAds::getAdUnitPlaceholder(1));

			$tipsClass = mb_strtolower(wfMessage("tips")->text()); //grabs the tips section by name, but internationalized
			pq(".{$tipsClass} .section_text")->children()->filter("ul:last")->after(wikihowAds::getAdUnitPlaceholder('2a'));
			
			//add in the Taboola ads
			if($wgLanguageCode == "en") {
				$sourcesClass = mb_strtolower(wfMessage("sources")->text()); //grabs the sources and citations section by name, but internationalized
				$sourcesClass = str_replace(' ','',$sourcesClass);
				if (pq(".section.{$sourcesClass}")->length) {
					//put above Sources & Citations if it exists
					pq(".section.{$sourcesClass}")->before(wikihowAds::getAdUnitTaboola());
				}
				else {	
					// put above Article Info
					pq("#bodycontents")->after(wikihowAds::getAdUnitTaboola());
				}
			}
		}
		$markPatrolledLink = self::getMarkPatrolledLink();
		if ($markPatrolledLink) {
			pq('#bodycontents')->append( $markPatrolledLink );
		}
		
		// //TEST - no intro
		// global $wgRequest;
		// $article_ids = explode("\n",ConfigStorage::dbGetConfig('wikihow-nointro-test'));
		// if ($wgTitle && $wgTitle->getNamespace() == NS_MAIN && $wgRequest->getVal('action') == '' && in_array($wgTitle->getArticleID(),$article_ids)) {
			// pq('#intro p:last')->remove();
		// }

		return $doc->htmlOuter();
	}

	static function boldFirstSentence($htmlText) {
		$punct = "!\.\?\:"; # valid ways of ending a sentence for bolding

		$htmlparts = preg_split('@(<[^>]*>)@im', $htmlText,
			0, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
		$incaption = false;
		$apply_b = false;
		$closed_b = false;
		$p = '';
		while ($x = array_shift($htmlparts)) {
			# add any other "line-break" tags here.
			$is_break_tag = strpos($x, '<ul>') === 0;

			# if it's a tag, just append it and keep going
			if (!$is_break_tag && strpos($x, '<') === 0) {
				# add the tag
				$p .= $x;
				if ($x == '<span class="caption">') {
					$incaption = true;
				} elseif ($x == "</span>" && $incaption) {
					$incaption = false;
				}
				continue;
			}
			# put the closing </b> in if we hit the end of the sentence
			if (!$incaption) {
				if (!$apply_b && trim($x)) {
					$p .= '<b class="whb">';
					$apply_b = true;
				}
				if ($apply_b) {
					$x = preg_replace("@([{$punct}])@im", '$1</b>', $x, 1, $closeCount);
					$closed_b = $closeCount > 0;
				}
			}
			if (!$closed_b && $is_break_tag) {
				$x = '</b>' . $x;
				$closed_b = true;
			}

			$p .= $x;

			if ($closed_b) {
				break;
			}
		}

		# get anything left over
		$p .= implode('', $htmlparts);

		return $p;
	}

	/**
	 * This method is used to process non-article HTML
	 */
	static function processHTML($body, $action='', $opts = array()) {
		global $wgUser, $wgTitle;

		$processHTML = true;
		// $wgTitle isn't used in the hook below
		wfRunHooks('PreWikihowProcessHTML', array($wgTitle, &$processHTML));
		if (!$processHTML) {
			return $body;
		}

		$doc = PHPQuery::newDocument($body);

		//run ShowGrayContainer hook for this
		if (@$opts['show-gray-container']) pq("#bodycontents")->addClass("minor_section");	
		
		//let's mark each bodycontents section so we can target it with CSS
		if ($action) pq("#bodycontents")->addClass("bc_".$action);

		//default each mw-htmlform-submit button to a primary button
		//gotta clear too because we're floating it now
		pq(".mw-htmlform-submit")->addClass("primary button buttonright");
		pq(".mw-htmlform-submit")->after("<div class='clearall'></div>");
		
		//USER PREFERENCES//////////////////////
		pq("#mw-prefsection-echo")->append(pq("#mw-prefsection-echo-emailsettingsind"));
		
		//DISCUSSION/USER TALK//////////////////////

		//move some pieces above the main part
		pq("#bodycontents")->before(pq(".template_top")->addClass("wh_block"));
		pq("#bodycontents")->before(pq(".archive_table")->addClass("wh_block"));

		//remove those useless paragraph line breaks
		$bc = preg_replace('/<p><br><\/p>/','',pq("#bodycontents")->html());
		pq("#bodycontents")->html($bc);

		//insert postcomment form
		$pc = new PostComment();
		$pcf = $pc->getForm(false,$wgTitle,true);
		if ($pcf && $wgTitle->getFullURL() != $wgUser->getUserPage()->getTalkPage()->getFullURL()) {
			$pc_form = $pcf;
			pq("#bodycontents")->append($pc_form);
		}
		else {
			$pc_form = '<a name="postcomment"></a><a name="post"></a>';
			pq(".de:last")->prepend($pc_form);
		}
		
		//HISTORY//////////////////////
		//move top nav down a smidge
		pq("#history_form")->before(pq(".navigation:first"));

		
		//EDIT PREVIEW//////////////////////
		if (substr($action,0,6) == 'submit') {
			$name = ($action == 'submit2') ? "#editpage" : "#editform";
			
			$preview = pq("#wikiPreview");
			$changes = pq("#wikiDiff")->addClass("wh_block");
			pq("#wikiPreview")->remove();
			pq("#wikiDiff")->remove();
			
			//preview before or after based on user preference
			if ($wgUser->getOption('previewontop')) {
				pq($name)->before($preview);
				pq($name)->before($changes);
			}
			else {
				pq($name)->after($preview);
				pq($name)->after($changes);
			}
		}

		$markPatrolledLink = self::getMarkPatrolledLink();
		if ($markPatrolledLink) {
			pq('#bodycontents')->append( $markPatrolledLink );
		}
	
		return $doc->htmlOuter();
	}

	static function getMarkPatrolledLink() {
		global $wgRequest, $wgUser;

		// Append a [Mark as Patrolled] link in certain cases
		$markPatrolledLink = '';
		$rcid = $wgRequest->getInt('rcid');
		$fromRC = $wgRequest->getInt('fromrc');
		if ( $wgUser && $rcid > 0 && $fromRC && $wgUser->isAllowed( 'patrol' ) ) {
			$rc = RecentChange::newFromId($rcid);
			if ($rc) {
				$oldRevId = $rc->getAttribute('rc_last_oldid');
				$newRevId = $rc->getAttribute('rc_this_oldid');
				$diff = new DifferenceEngine(null, $oldRevId, $newRevId);
				if ( $diff->loadRevisionData() ) {
					$markPatrolledLink = $diff->markPatrolledLink();
				} else {
					throw new MWException("wikiHow internal error: we know there is an rcid ($rcid) and newrevid ($newRevId), but couldn't find the revision");
				}
			}
		}
		return $markPatrolledLink;
	}

	/**
	 * Insert ad codes, and other random bits of html, into the body of the article
	 */
	static function postProcess($body, $opts = array()) {
		global $wgWikiHowSections, $wgTitle, $wgUser;
		$ads = $wgUser->isAnon() && !@$opts['no-ads'];
		$parts = preg_split("@(<h2.*</h2>)@im", $body, 0, PREG_SPLIT_DELIM_CAPTURE);
		$reverse_msgs = array();
		$no_third_ad = false;
		$isRecipe = Microdata::showRecipeTags() && Microdata::showhRecipeTags();
		foreach ($wgWikiHowSections as $section) {
			$reverse_msgs[wfMessage($section)->text()] = $section;
		}
		$charcount = strlen($body);
		$body = "";
		for ($i = 0; $i < sizeof($parts); $i++) {
			if ($i == 0) {

				if ($body == "") {
					// if there is no alt tag for the intro image, so it to be the title of the page
					preg_match("@<img.*mwimage101[^>]*>@", $parts[$i], $matches);
					if ($wgTitle && sizeof($matches) > 0) {
						$m = $matches[0];
						$newm = str_replace('alt=""', 'alt="' . htmlspecialchars($wgTitle->getText()) . '"', $m);
						if ($m != $newm) {
							$parts[$i] = str_replace($m, $newm, $parts[$i]);
						}
						
						//add microdata
						if ($isRecipe) {
							$parts[$i] = preg_replace('/mwimage101"/','mwimage101 photo"',$parts[$i], 1);
						} else {
							$parts[$i] = preg_replace('/mwimage101"/','mwimage101" itemprop="image"',$parts[$i], 1);
						}
						$img_itemprop_done = true;
					} else {
						$img_itemprop_done = false;
					}
					
					// add microdata
					if ($isRecipe) {
						$parts[$i] = preg_replace('/\<p\>/','<p class="summary">',$parts[$i], 1);
					} else {
						$parts[$i] = preg_replace('/\<p\>/','<p itemprop="description">',$parts[$i], 1);
					}
					
					// done alt test
					$anchorPos = stripos($parts[$i], "<a name=");
					if ($anchorPos > 0 && $ads){
						$content = substr($parts[$i], 0, $anchorPos);
						$count = preg_match_all('@</p>@', $parts[$i], $matches);
						
						if ($count == 1) { // this intro only has one paragraph tag
							$class = 'low';
						} else {
							$endVar = "<p><br /></p>\n<p>";
							$end = substr($content, -1*strlen($endVar));

							if($end == $endVar) {
								$class = 'high'; //this intro has two paragraphs at the end, move ads higher
							}
							else{
								$class = 'mid'; //this intro has no extra paragraphs at the end.
							}
						}
						
						
						if (stripos($parts[$i], "mwimg") != false) {
							$body = "<div class='article_inner editable'>" . $content . "<div class='ad_image " . $class . "'>" . wikihowAds::getAdUnitPlaceholder('intro') . "</div>" . substr($parts[$i], $anchorPos) ."</div>\n";
						} else {
							$body = "<div class='article_inner editable'>" . $content . "<div class='ad_noimage " . $class . "'>" . wikihowAds::getAdUnitPlaceholder('intro') . "</div>" . substr($parts[$i], $anchorPos) ."</div>\n";
						}
					} elseif ($anchorPos == 0 && $ads) {
						$body = "<div class='article_inner editable'>{$parts[$i]}" . wikihowAds::getAdUnitPlaceholder('intro') . "</div>\n";
					}
					else
						$body = "<div class='article_inner editable'>{$parts[$i]}</div>\n";
				}
				continue;
			}
			
			if (stripos($parts[$i], "<h2") === 0 && $i < sizeof($parts) - 1) {
				// MWUP aaron changed this from <span> to <span because we now no longer wrap
				// the section name in a normal span, but a span with class and id
				preg_match("@<span.*</span>@", $parts[$i], $matches);
				$rev = "";
				if (sizeof($matches) > 0) {
					$h2 =  trim(strip_tags($matches[0]));
					$rev = isset($reverse_msgs[$h2]) ? $reverse_msgs[$h2] : "";
				}
				
				$body .= $parts[$i];
				
				$i++;
				if ($rev == "steps") {
					if (Microdata::showRecipeTags()) {
						if (Microdata::showhRecipeTags()) {
							$recipe_tag = " instructions'";
						} else {
							$recipe_tag = "' itemprop='recipeInstructions'";
						}
					} else {
						$recipe_tag = "'";
					}
					$body .= "\n<div id=\"steps\" class='editable{$recipe_tag}>{$parts[$i]}</div>\n";
				} elseif ($rev != "") {
					$body .= "\n<div id=\"{$rev}\" class='article_inner editable'>{$parts[$i]}</div>\n";
				} else {
					$body .= "\n<div class='article_inner editable'>{$parts[$i]}</div>\n";
				}
			} else {
				$body .= $parts[$i];
			}
		}
		
		$punct = "!\.\?\:"; # valid ways of ending a sentence for bolding
		$i = strpos($body, '<div id="steps"');
		if ($i !== false) {
			$j = strpos($body, '<div id=', $i+5); //find the position of the next div. Starting after the '<div ' (5 characters)
			$sub = "sd_"; //want to skip over the samples section if they're there
			while($j !== false && $sub == "sd_") {
				$sub = substr($body, $j+9, 3); //find the id of the next div section 9=strlen(<div id="), 3=strlen(sd_)
				$j = strpos($body, '<div id=', $j+12); //find the position of the next div. Starting after the '<div id="sd_' (12 characters)
			}
		}
		if ($j === false) $j = strlen($body);
		if ($j !== false && $i !== false) {
			$steps = substr($body, $i, $j - $i);
			$parts = preg_split("@(<[/]?ul>|<[/]?ol>|<[/]?li>)@im", $steps, 0, PREG_SPLIT_DELIM_CAPTURE  | PREG_SPLIT_NO_EMPTY);
			$numsteps = preg_match_all('/<li>/m',$steps, $matches );
			$level = 0;
			$steps = "";
			$upper_tag = "";
			// for the redesign we need some extra formatting for the OL, etc
			$levelstack = array();
			$tagstack = array();
			$current_tag = "";
			$current_li = 1;
			$donefirst = false; // used for ads to tell when we've put the ad after the first step
			$bImgFound = false;
			$the_last_picture = '';
			$final_pic = array();
			$alt_link = array();
			
			// Limit steps to 400 or it will timeout
			if ($numsteps < 400) {

				while ($p = array_shift($parts)) {
					switch (strtolower($p)) {
						case "<ol>":
							$level++;
							if ($level == 1)  {
								$p = '<ol class="steps_list_2">';
								$upper_tag = "ol";
							} else {
								$p = "&nbsp;{$p}";
							}
							if ($current_tag != "")
								$tagstack[] = $current_tag;
							$current_tag = "ol";
							$levelstack[] = $current_li;
							$current_li = 1;
							break;
						case "<ul>":
							if ($current_tag != "")
								$tagstack[] = $current_tag;
							$current_tag = "ul";
							$levelstack[] = $current_li;
							$level++;
							break;
						case "</ol>":
						case "</ul>":
							$level--;
							if ($level == 0) $upper_tag = "";
							$current_tag = array_pop($tagstack);
							$current_li = array_pop($levelstack);
							break;
						case "<li>":
							$closecount = 0;
							if ($level == 1 && $upper_tag == "ol") {
								$li_number = $current_li++;
								$p = '<li><div class="step_num">' . $li_number . '</div>';
								
								# this is where things get interesting. Want to make first sentence bold!
								# but we need to handle cases where there are tags in the first sentence
								# split based on HTML tags
								$next = array_shift($parts);
								
								$htmlparts = preg_split("@(<[^>]*>)@im", $next,
									0, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
								$dummy = 0;
								$incaption = false;
								$apply_b = false;
								$the_big_step = $next;
								while ($x = array_shift($htmlparts)) {
									# if it's a tag, just append it and keep going
									if (preg_match("@(<[^>]*>)@im", $x)) {
										//tag
										$p .= $x;
										if ($x == "<span class='caption'>") {
											$incaption = true;
										} elseif ($x == "</span>" && $incaption) {
											$incaption = false;
										}
										continue;
									}
									# put the closing </b> in if we hit the end of the sentence
									if (!$incaption) {
										if (!$apply_b && trim($x) != "") {
											$p .= "<b class='whb'>";
											$apply_b = true;
										}
										if ($apply_b) {
											$x = preg_replace("@([{$punct}])@im", "</b>$1", $x, 1, $closecount);

										}
									}
									
									$p .= $x;
										
									if ($closecount > 0) {
										break;
									}
									$dummy++;
								}
								
								# get anything left over
								$p .= implode("", $htmlparts);
								
								//microdata the final image if we haven't already tagged the intro img
								if ((!$img_itemprop_done) && ($numsteps == $li_number)) {
									$p = preg_replace('/mwimage101"/','mwimage101" itemprop="image"',$p, 1);
								}
								
								if ($closecount == 0) $p .= "</b>"; // close the bold tag if we didn't already
								if ($level == 1 && $current_li == 2 && $ads && !$donefirst) {
									$p .= wikihowAds::getAdUnitPlaceholder(0);
									$donefirst = true;
								}

							} elseif ($current_tag == "ol") {
								//$p = '<li><div class="step_num">'. $current_li++ . '</div>';
							}
							break;
						case "</li>":
							$p = "<div class='clearall'></div>{$p}"; //changed BR to DIV b/c IE doesn't work with the BR clear tag
							break;
					} // switch
					$steps .= $p;
				} // while
			} else {
				$steps = substr($body, $i, $j - $i);
				$steps = "<div id='steps_notmunged'>\n" . $steps . "\n</div>\n";
			}						
						
			// we have to put the final_li in the last OL LI step, so reverse the walk of the tokens
			$parts = preg_split("@(<[/]?ul>|<[/]?ol>|<[/]?li>)@im", $steps, 0, PREG_SPLIT_DELIM_CAPTURE);
			$parts = array_reverse($parts);
			$steps = "";
			$level = 0;
			$gotit = false;
			$donelast = false;
			$insertedAlt = false;
			foreach ($parts as $p) {
				$lp = strtolower($p);
				if ($lp == "</ol>" ) {
					$level++;
					$gotit= false;
					if (class_exists("AltMethodAdder") && $wgTitle && $wgUser && $wgUser->isAnon() && !$insertedAlt) {
						$p = $p . AltMethodAdder::getCTA($wgTitle);
						$insertedAlt = true;
					}
				} elseif ($lp == "</ul>") {
					$level++;
				} elseif (strpos($lp, "<li") !== false && $level == 1 && !$gotit) {
					/// last OL step list fucker
					$p = preg_replace("@<li[^>]*>@i", '<li class="steps_li final_li">', $p);
					$gotit = true;
				} elseif (strpos($lp, "<ul") !== false) {
					$level--;
				} elseif (strpos($lp, "<ol") !== false) {
					$level--;
				} elseif ($lp == "</li>" && !$donelast) {
					// ads after the last step
					if ($ads) {
						if(substr($body, $j) == ""){
							$p = "<script>missing_last_ads = true;</script>" . wikihowAds::getAdUnitPlaceholder(1) . $p;
							$no_third_ad = true;
						}
						else {
							$p = wikihowAds::getAdUnitPlaceholder(1) . $p;
						}
					}
					$donelast = true;
				}
				$steps = $p . $steps;
			}
			
			$body = substr($body, 0, $i) . $steps . substr($body, $j);
			
		} // if numsteps == 400?
		
		//recipe prep time test
		if (class_exists('Microdata') && $wgTitle) {
			Microdata::insertPrepTimeTest($wgTitle->getDBkey(), $body);
		}

		/// ads below tips, walk the sections and put them after the tips
		if ($ads) {
			$foundtips = false;
			$anchorTag = "";
			foreach ($wgWikiHowSections as $s) {
				$isAtEnd = false;
				if ($s == "ingredients" || $s == "steps")
					continue; // we skip these two top sections
				$i = strpos($body, '<div id="' . $s. '"');
			    if ($i !== false) {
					$j = strpos($body, '<h2>', $i + strlen($s));
				} else {
					continue; // we didnt' find this section
				}
	    		if ($j === false){
					$j = strlen($body); // go to the end
					$isAtEnd = true;
				}
	    		if ($j !== false && $i !== false) {
					$section  = substr($body, $i, $j - $i);
					if ($s == "video") {
						// special case for video
						$newsection = "<div id='video' itemprop='video'><center>{$section}</center></div>";
						$body = str_replace($section, $newsection, $body);
						continue;
					} elseif ($s == "tips") {
						//tip ad is now at the bottom of the tips section
						//need to account for the possibility of no sections below this and therefor
						//no anchor tag
						if($isAtEnd)
							$anchorTag = "<p></p>";
						$body = str_replace($section, $section . $anchorTag . wikihowAds::getAdUnitPlaceholder('2a') , $body);
						$foundtips = true;
						break;
					} else {
						$foundtips = true;
						if($isAtEnd)
							$anchorTag = "<p></p>";
						$body = str_replace($section, $section . $anchorTag . wikihowAds::getAdUnitPlaceholder(2) , $body);
						break;
					}
				}
			}
			if (!$foundtips && !$no_third_ad) { //must be the video section
				//need to put in the empty <p> tag since all the other sections have them for the anchor tags.
				$body .= "<p class='video_spacing'></p>" . wikihowAds::getAdUnitPlaceholder(2);
			}

		}	

		return $body;
	}
	
	/*
	 * look for some key magic words we'll use in processArticleHTML()
	 */
	public function grabTheMagic($wikitext) {
		
		//has parts?
		$mw = MagicWord::get('parts');
		if ($mw->match($wikitext)) return $mw;
		
		//has methods?
		$mw = MagicWord::get('methods');
		if ($mw->match($wikitext)) return $mw;
		
		//has ways?
		$mw = MagicWord::get('ways');
		if ($mw->match($wikitext)) return $mw;
		
		//has no magic?
		return '';
	}
}

class BuildWikihowArticle extends UnlistedSpecialPage {

    function __construct() {
        parent::__construct('BuildWikihowArticle');
    }

    function execute($par) {
		global $wgRequest, $wgOut;
		$wgOut->disable();
		$whow = WikihowArticleEditor::newFromRequest($wgRequest);
		if ($wgRequest->getVal('parse') == '1') {
			$body = $wgOut->parse($whow->formatWikiText());
			$magic = WikihowArticleHTML::grabTheMagic($whow->formatWikiText());
			echo WikihowArticleHTML::processArticleHTML($body, array('magic-word' => $magic));
		} else {
			echo $whow->formatWikiText();
		}
		return;
	}
}

