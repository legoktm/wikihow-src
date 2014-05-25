<?php

global $IP;
require_once("$IP/extensions/wikihow/RisingStar.php");

abstract class ArticleViewer extends ContextSource {
	var $articles, $articles_start_char;

	function __construct(IContextSource $context) {
		$this->setContext($context);
		$this->clearState();
	}

	function clearState() {
		$this->articles = array();
		$this->articles_start_char = array();
	}

	abstract function doQuery();
}

class FaViewer extends ArticleViewer {
	function doQuery() {
		$fas = FeaturedArticles::getTitles(45);
		foreach ($fas as $fa) {
			$this->articles[] = Linker::link($fa['title']);
		}
	}
}

class RsViewer extends ArticleViewer {
	var $maxNum;

	function __construct(IContextSource $context, $maxNum = 16) {
		parent::__construct($context);
		$this->maxNum = $maxNum;
	}

	function doQuery() {
		$rs = RisingStar::getRS();

		if ($rs) {
			$i = 0;
			foreach ($rs as $titleString => $star) {
				$title = Title::newFromText($titleString);
				if($title) {
					$this->articles[] = Linker::link($title);
				}
				if (++$i >= $this->maxNum) {
					break;
				}
			}
		}
	}
}

class WikihowCategoryViewer extends ArticleViewer {
	var $title, $limit, $from, $until,
		$children, $children_start_char,
		$showGallery, $gallery,
		$articles_fa, $article_info,
		$article_info_fa, $articles_start_char_fa;

	function __construct($title, IContextSource $context, $from = '', $until = '') {
		global $wgCategoryPagingLimit;

		parent::__construct($context);

		$this->title = $title;
		$this->from = $from;
		$this->until = $until;
		$this->limit = $wgCategoryPagingLimit;
	}

	/**
	 * Format the category data list.
	 *
	 * @param string $from -- return only sort keys from this item on
	 * @param string $until -- don't return keys after this point.
	 * @return string HTML output
	 * @private
	 */
	function getHTML() {
		global $wgOut, $wgCategoryMagicGallery;

		wfProfileIn(__METHOD__);

		$skin = $this->getSkin();
		$this->showGallery = $wgCategoryMagicGallery && !$wgOut->mNoGallery;

		$this->clearCategoryState();
		$this->doQuery();
		$this->finaliseCategoryState();

		$sections = array();
		if (count($this->articles) > 0) {
			$sections = $this->columnListRD($this->articles, $this->articles_start_char, $this->article_info);
		}

		$r = $this->getCategoryTop() .
		$r = "<br style='clear:both;'/>" .
			$sections['featured'] .
			$this->getSubcategorySection() .
			$sections['pages'] .
			$this->getImageSection() .
			$this->getCategoryBottom();

		// Give a proper message if category is empty
		if ($r == '') {
			$r = wfMessage('category-empty')->parse();
		}

		wfProfileOut(__METHOD__);
		return $r;
	}

	function finaliseCategoryState() {
		if ($this->flip) {
			$this->children = array_reverse($this->children);
			$this->children_start_char = array_reverse($this->children_start_char);
			$this->articles = array_reverse($this->articles);
			$this->articles_start_char = array_reverse($this->articles_start_char);
		}
	}

	/**
	 * Format a list of articles into two columns REDESIGN
	 * list, ordered vertically.
	 *
	 * @param array $articles
	 * @param array $articles_start_char
	 * @return string
	 * @private
	 */
	function columnListRD($articles, $articles_start_char, $article_info) {
		// divide list into three equal chunks
		$chunk = (int)(count($articles) / 2);

		$featured = 0;
		$articles_with_templates = array();
		$articles_with_templates_info = array();
		$ti = htmlspecialchars($this->title->getText());

		$r = '<div id="mw-pages"><h2>' . wfMessage('category_header', $ti)->text() . "</h2>\n";
		$r .= '<p>' . wfMessage('Category_articlecount', 'ARTICLECOUNT')->text() . '</p>';
		$rf = '<div id="mw_featured"><h2>' . wfMessage('featured_articles')->text() . '</h2>';
		$rf .= "<div class='featured_articles_inner' id='featuredArticles'><table class='featuredArticle_Table'><tr>";

		$index = 0;
		$index2 = 0;
		$rf_break = 0;
		$rf_show = false;
		$rf_count = 0;
		$r_count = 0;

		if (count($articles) > 0) {
			$r .= '<ul class="category_column column_first">' . "\n";
			for ($index = 0; $index < count($articles); $index++) {

				$rtmp = '';
				if (($index2 == $chunk) && ($r_count > 0)) {
					$r .= '</ul> <ul class="category_column">' . "\n";
				}

				if (is_array($article_info) && $article_info[$index]['page_is_featured']) {
					if (preg_match('/title="(.*?)"/', $articles[$index], $matches)) {
						if ($rf_count < 30) {
							$f = Title::newFromText($matches[1]);
							$rf .= $this->getSkin()->featuredArticlesLineWide($f);
							$rf_break++;
							$rf_count++;
							$rf_show = true;
						}
					}
					if ($rf_break == 5) {
						$rf .= "</tr>\n<tr>";
						$rf_break = 0;
					}
					$r .= "<li>{$articles[$index]}</li>\n";
				} else {
					$rtmp = "<li>{$articles[$index]}</li>\n";
				}

				if (is_array($article_info) && isset($article_info[$index])) {
					$page_len = $article_info[$index]['page_len'];
					// save articles with certain templates to put at the end
					//TODO: internationalize the shit out of this
					if ($article_info[$index]['page_further_editing'] == 1 || $page_len < 750) {
						if (strpos($articles[$index], ":") === false) {
							$articles_with_templates[] = $articles[$index];
							$articles_with_templates_info[] = $article_info[$index];
							continue;
						} else {
							$r .= $rtmp;
							$r_count++;
						}
					} else {
						$r .= $rtmp;
						$r_count++;
					}
				}

				$index2++;
			}
			if ($r_count > 0) {
				$r = str_replace('ARTICLECOUNT', $r_count, $r);
				$r .= '</ul></div> <div class="clearall"></div>';
			} else {
				$r = '';
			}

			// Add more FAs from subcategories
			if ($rf_count < 10) {
				$randomFAs = array();
				$randomFAs = $this->getSubCatFAs();
				for ($i = 0; $rf_count < 10 && $i < count($randomFAs); $i++) {
					if (isset($randomFAs[$i])) {
						if ($rf_count == 5)
							$rf .= "</tr>\n<tr>";

						if (preg_match('/title="([^"]*)"/', $randomFAs[$i], $matches)) {
							$f = Title::newFromText($matches[1]);
							$rf .= $this->getSkin()->featuredArticlesLineWide($f);
							$rf_show = true;
							$rf_count++;
						}
					} else {
						if ($rf_count < 5)
							$rf .= "<td></td>\n";
					}

				}
			}

			$rf .= "\n</tr></table></div></div>";
		}

		if (sizeof($articles_with_templates) > 0) {
			$chunk = (int)(count($articles) / 2);
			$r .= "<div id=\"mw-help\"> <h2>" . wfMessage('articles_that_require_attention')->text() . "</h2>\n";

			$r .= "<p>There are " . count($articles_with_templates) . " articles in this category that require attention.</p>\n";
			$index = 0;
			$r .= '<ul class="category_column column_first">' . "\n";
			for ($index = 0; $index < sizeof($articles_with_templates); $index++) {
				if (($index == $chunk) && (sizeof($articles_with_templates) > 5)) {
					$r .= '</ul> <ul class="category_column">' . "\n";
				}
				$r .= "<li>{$articles_with_templates[$index]} </li>\n";
			}
			$r .= "</ul></div><div class=\"clearall\"></div>";
		}

		$ret = array();
		$ret['pages'] = $r;
		if ($rf_show) {
			$ret['featured'] = $rf;
		} else {
			$ret['featured'] = "";
		}
		return $ret;
	}

	function getSubCatFAs() {
		global $wgOut, $wgCategoryMagicGallery, $wgCategoryPagingLimit, $wgTitle;

		$children = $this->children;
		$fas = count($this->articles_fa);
		$fas_needed = (10 - count($this->articles_fa));

		$randomFAs = array();
		if ($fas < 10) {
			$allSubCats = $this->shortListRD($this->children, $this->children_start_char, true);
			$used = array();
			$fas2 = array();
			$count = 0;
			while (count($used) < count($allSubCats)) {
				$j = rand(0, count($allSubCats));
				if (!in_array($j, $used)) {
					$t = Title::newFromText("Category:" . $allSubCats[$j]);
					if (isset($t) && $t->getArticleID() > 0) {
						$cat = new self($t);
						$fas2 = $cat->getFAs();
						$randomFAs = array_merge((array)$fas2, (array)$randomFAs);
					}
					if (count($randomFAs) >= $fas_needed) {
						return ($randomFAs);
					}

					$used[] = $j;
				}
				$count++;
				if ($count >= 30) {
					return $randomFAs;
				}

			}
		}
		return ($randomFAs);
	}

	/**
	 * Format a list of articles chunked by letter in a bullet list.
	 * @param array $articles
	 * @param array $articles_start_char
	 * @return string
	 * @private
	 */
	function shortListRD($articles, $articles_start_char, $flatten = false) {
		if (count($articles) == 0) {
			return "";
		}
		$chunk = (int)((count($articles) / 2) + 2);

		$sk = $this->getSkin();
		$r = '<ul class="category_column column_first">' . "\n";
		$allSubCats = array();
		for ($index = 0; $index < count($articles); $index++) {

			if ($index == $chunk) {
				$r .= "\n" . '</ul> <ul class="category_column">' . "\n";
			}
			if (is_array($articles[$index])) {
				$query= self::getViewModeArray($this->getContext());
				$r .= "<li>{$articles[$index][0]}</li>";
				$links = array();
				foreach ($articles[$index][1] as $t) {
					$allSubCats[] = $t->getText();
					$links[] = Linker::link($t, $t->getText(), array(), $query);
				}
				$r .= "\n<ul><li>" . implode(" <strong>&bull;</strong> ", $links) . "</li></ul>\n";
			} elseif ($articles[$index] instanceof Title) {
				$t = $articles[$index];
				$allSubCats[] = $t->getText();
				$link = Linker::link($t, $t->getText());
				$r .= "<li>{$link}</li>";
			} else {
				if (is_string($articles[$index])) {
					if (preg_match('/title="Category:(.*?)"/', $articles[$index], $matches)) {
						$allSubCats[] = $matches[1];
					}
					$r .= "<li>{$articles[$index]}</li>";
				} else {
					print_r($articles[$index]);
				}
			}
		}

		if ($flatten) {
			return $allSubCats;
		}

		$r .= "</ul>\n";
		return $r;
	}

	function getFAs() {
		global $wgOut, $wgCategoryMagicGallery, $wgCategoryPagingLimit, $wgTitle;

		$this->clearCategoryState();
		$this->doQuery();
		return $this->articles_fa;
	}

	function clearCategoryState() {
		$this->articles = array();
		$this->articles_start_char = array();
		$this->children = array();
		$this->children_start_char = array();
		if ($this->showGallery) {
			$this->gallery = new ImageGallery();
			$this->gallery->setHideBadImages();
		}
		$this->articles_fa = array();
		$this->article_info = array();
		$this->article_info_fa = array();
		$this->articles_start_char_fa = array();
	}

	function doQuery() {
		$dbr = wfGetDB(DB_SLAVE);
		if ($this->from != '') {
			$pageCondition = 'cl1.cl_sortkey >= ' . $dbr->addQuotes($this->from);
			$this->flip = false;
		} elseif ($this->until != '') {
			$pageCondition = 'cl1.cl_sortkey < ' . $dbr->addQuotes($this->until);
			$this->flip = true;
		} else {
			$pageCondition = '1 = 1';
			$this->flip = false;
		}

		$sql = "SELECT page_title, page_namespace, page_len, page_further_editing,
				cl1.cl_sortkey, page_counter, page_is_featured
			FROM (page, categorylinks cl1)
			WHERE $pageCondition
				AND cl1.cl_from = page_id
				AND cl1.cl_to = " . $dbr->addQuotes($this->title->getDBKey()) . "
				AND page_namespace != 14
			GROUP BY page_id
			ORDER BY " . ($this->flip ? 'cl1.cl_sortkey DESC' : 'cl1.cl_sortkey') . "
			LIMIT " . ($this->limit + 1);
		$res = $dbr->query($sql, __METHOD__);

		$count = 0;
		$this->nextPage = null;
		foreach ($res as $x) {
			if (!$this->processRow($x, $count)) {
				break;
			}
		}

		// get all of the subcategories this time
		$sql = "SELECT page_title, page_namespace, page_len, page_further_editing,
				cl1.cl_sortkey, page_counter, page_is_featured
			FROM (page, categorylinks cl1)
			WHERE cl1.cl_from = page_id
				AND cl1.cl_to = " . $dbr->addQuotes($this->title->getDBKey()) . "
				AND page_namespace = " . NS_CATEGORY . "
			GROUP BY page_id
			ORDER BY " . ($this->flip ? 'cl1.cl_sortkey DESC' : 'cl1.cl_sortkey');
		$res = $dbr->query($sql, __METHOD__);
		$count = 0;
		foreach ($res as $x) {
			$this->processRow($x, $count);
		}
	}

	function processRow($x, &$count) {
		if (++$count > $this->limit) {
			// We've reached the one extra which shows that there are
			// additional pages to be had. Stop here...
			$this->nextPage = $x->cl_sortkey;
			return false;
		}

		$title = Title::makeTitle($x->page_namespace, $x->page_title);

		if ($title->getNamespace() == NS_CATEGORY) {
			// check for subcategories
			$subcats = $this->getSubcategories($title);
			if (sizeof($subcats) == 0) {
				$this->addSubcategory($title, $x->cl_sortkey, $x->page_len);
			} else {
				$this->addSubcategory($title, '', 0, $subcats);
			}
		} elseif ($this->showGallery && $title->getNamespace() == NS_IMAGE) {
			$this->addImage($title, $x->cl_sortkey, $x->page_len, $x->page_is_redirect);
		} else {
			// page in this category
			$info_entry = array();
			$info_entry['page_counter'] = $x->page_counter;
			$info_entry['page_len'] = $x->page_len;
			$info_entry['page_further_editing'] = $x->page_further_editing;
			$isFeatured = !empty($x->page_is_featured);
			$info_entry['page_is_featured'] = intval($isFeatured);
			$info_entry['number_of_edits'] = isset($x->edits) ? $x->edits : 0;
			$info_entry['template'] = isset($x->tl_title) ? $x->tl_title : '';
			if (!$info_entry['page_is_featured']) {
				$pageIsRedirect = isset($x->page_is_redirect) ? $x->page_is_redirect : false;
				$this->addPage($title, $x->cl_sortkey, $x->page_len, $pageIsRedirect, $info_entry);
			} else {
				$this->addFA($title, $x->cl_sortkey, $x->page_len, $info_entry);
			}
		}
		return true;
	}

	function getSubcategories($title) {
		$dbr = wfGetDB(DB_SLAVE);
		$res = $dbr->select(
			array('categorylinks', 'page'),
			array('page_title', 'page_namespace'),
			array('page_id=cl_from',
				'cl_to' => $title->getDBKey(),
				'page_namespace=' . NS_CATEGORY
			),
			__METHOD__
		);
		$results = array();
		foreach ($res as $row) {
			$results[] = Title::makeTitle($row->page_namespace, $row->page_title);
		}
		return $results;
	}

	/*
	*  Returns a query associative array if the viewMode is text, blank otheriwise (for image mode)
	*/
	static function getViewModeArray($context) {
		return $context->getRequest()->getVal('viewMode', 0) ? array('viewMode'=>'text'): array();
	}

	/*
	*  Returns a query string parameter if the viewMode is text, blank otheriwise (for image mode)
	*/
	static function getViewModeParam() {
		global $wgRequest;
		return $wgRequest->getVal('viewMode', 0) ? "viewMode=text" : '';
	}
	/**
	 * Add a subcategory to the internal lists
	 */
	function addSubcategory($title, $sortkey, $pageLength, $subcats = null) {
		global $wgContLang;

		// Subcategory; strip the 'Category' namespace from the link text.
		$query = self::getViewModeArray($this->getContext());
		$link = Linker::linkKnown($title, $wgContLang->convertHtml($title->getText()), array(), $query);
		if ($subcats == null) {
			$this->children[] = $link;
		} else {
			$rx = array();
			$rx[] = $link;
			$rx[] = $subcats;
			$this->children[] = $rx;
		}

		$this->children_start_char[] = $this->getSubcategorySortChar($title, $sortkey);
	}

	/**
	 * Get the character to be used for sorting subcategories.
	 * If there's a link from Category:A to Category:B, the sortkey of the resulting
	 * entry in the categorylinks table is Category:A, not A, which it SHOULD be.
	 * Workaround: If sortkey == "Category:".$title, than use $title for sorting,
	 * else use sortkey...
	 */
	function getSubcategorySortChar($title, $sortkey) {
		global $wgContLang;

		if ($title->getPrefixedText() == $sortkey) {
			$firstChar = $wgContLang->firstChar($title->getDBkey());
		} else {
			$firstChar = $wgContLang->firstChar($sortkey);
		}

		return $wgContLang->convert($firstChar);
	}

	/**
	 * Add a page in the image namespace
	 */
	function addImage(Title $title, $sortkey, $pageLength, $isRedirect = false) {
		if ($this->showGallery) {
			$image = new Image($title);
			if ($this->flip) {
				$this->gallery->insert($image);
			} else {
				$this->gallery->add($image);
			}
		} else {
			$this->addPage($title, $sortkey, $pageLength, $isRedirect);
		}
	}

	/**
	 * Add a miscellaneous page
	 */
	function addPage($title, $sortkey, $pageLength, $isRedirect = false, $info_entry = null) {
		global $wgContLang;

		// AG - the makeSizeLinkObj is deprecated and Linker::link takes care of size/color of the link now
		$this->articles[] = $isRedirect
			? '<span class="redirect-in-category">' . Linker::linkKnown($title) . '</span>'
			: Linker::link($title);
		if (is_array($info_entry))
			$this->article_info[] = $info_entry;
		$this->articles_start_char[] = $wgContLang->convert($wgContLang->firstChar($sortkey));
	}

	function addFA($title, $sortkey, $pageLength, $info_entry = null) {
		global $wgContLang;

		// Removed because it's adding duplicate content in some cat pages (sc 1/2/2014)
		// $this->articles_fa[] = Linker::link(
			// $pageLength, $title, $wgContLang->convert($title->getPrefixedText())
		// );
		// AG - the makeSizeLinkObj is deprecated and Linker::link takes care of size/color of the link now
		$this->articles_fa[] = Linker::link( $title, $wgContLang->convert($title->getPrefixedText()) );
		$this->articles_start_char_fa[] = $wgContLang->convert($wgContLang->firstChar($sortkey));
		if (is_array($info_entry)) {
			$this->article_info_fa[] = $info_entry;
		}
	}

	function getSubcategorySection() {
		global $wgTitle;

		# Don't show subcategories section if there are none.
		$r = '';
		$c = count($this->children);
		if ($c > 0) {
			# Showing subcategories
			$r .= "<div id=\"mw-subcategories\">\n";
			$r .= '<h2>' . wfMessage('subcategories', $wgTitle->getText())->text() . "</h2>\n";
			$r .= $this->shortListRD($this->children, $this->children_start_char);
			$r .= "\n</div>";
		}
		return $r;
	}

	function getImageSection() {
		if ($this->showGallery && !$this->gallery->isEmpty()) {
			$this->gallery->setPerRow(3);
			return "<div id=\"mw-category-media\">\n" .
			'<h2>' . wfMessage('category-media-header', htmlspecialchars($this->title->getText()))->text() . "</h2>\n" .
			wfMessage('category-media-count', $this->gallery->count())->parse() .
			$this->gallery->toHTML() . "\n</div>";
		} else {
			return '';
		}
	}

	function getCategoryBottom() {
		if ($this->until != '') {
			return $this->pagingLinks($this->title, $this->nextPage, $this->until, $this->limit);
		} elseif ($this->nextPage != '' || $this->from != '') {
			return $this->pagingLinks($this->title, $this->from, $this->nextPage, $this->limit);
		} else {
			return '';
		}
	}

	/**
	 * @param Title $title
	 * @param string $first
	 * @param string $last
	 * @param int $limit
	 * @param array $query - additional query options to pass
	 * @return string
	 * @private
	 */
	function pagingLinks($title, $first, $last, $limit, $query = array()) {
		global $wgLang;

		$sk = $this->getSkin();
		$limitText = $wgLang->formatNum($limit);

		$prevLink = htmlspecialchars(wfMessage('prevn', $limitText)->text());
		if ($first != '') {
			$prevLink = Linker::link($title, $prevLink, array(), array_merge($query, array('until' => $first)));
		}
		$nextLink = htmlspecialchars(wfMessage('nextn', $limitText)->text());
		if ($last != '') {
			$nextLink = Linker::link($title, $nextLink, array(), array_merge($query, array('from' => $last)));
		}

		return "($prevLink) ($nextLink)";
	}

	function getCategoryTop() {
		$r = '';
		if ($this->until != '') {
			$r .= $this->pagingLinks($this->title, $this->nextPage, $this->until, $this->limit);
		} elseif ($this->nextPage != '' || $this->from != '') {
			$r .= $this->pagingLinks($this->title, $this->from, $this->nextPage, $this->limit);
		}
		return $r == ''
			? $r
			: "<br style=\"clear:both;\"/>\n" . $r;
	}

	function getPagesSection() {
		$ti = htmlspecialchars($this->title->getText());
		// Don't show articles section if there are none.
		$r = array();
		$c = count($this->articles);
		if ($c > 0) {
			$r = $this->columnListRD($this->articles, $this->articles_start_char, $this->article_info);
		}
		return $r;
	}

	/**
	 * Format a list of articles chunked by letter, either as a
	 * bullet list or a columnar format, depending on the length.
	 *
	 * @param array $articles
	 * @param array $articles_start_char
	 * @param int $cutoff
	 * @return string
	 * @private
	 */
	function formatList($articles, $articles_start_char, $cutoff = 6, $article_info = null) {
		if (count($articles) > $cutoff) {
			return $this->columnList($articles, $articles_start_char, article_info);
		} elseif (count($articles) > 0) {
			// for short lists of articles in categories.
			return $this->shortList($articles, $articles_start_char);
		}
		return '';
	}

	/**
	 * Format a list of articles chunked by letter in a three-column
	 * list, ordered vertically.
	 *
	 * @param array $articles
	 * @param array $articles_start_char
	 * @return string
	 * @private
	 */
	function columnList($articles, $articles_start_char, $article_info) {
		// divide list into three equal chunks
		$chunk = (int)(count($articles) / 3);

		// get and display header
		$r = '<table width="100%"><tr valign="top">';

		$prev_start_char = 'none';

		// loop through the chunks
		$featured = 0;
		$articles_with_templates = array();
		$articles_with_templates_info = array();

		// loop through the chunks
		for ($startChunk = 0, $endChunk = $chunk, $chunkIndex = 0;
			 $chunkIndex < 3;
			 $chunkIndex++, $startChunk = $endChunk, $endChunk += $chunk + 1) {
			$atColumnTop = true;

			// output all articles in category
			for ($index = $startChunk;
				 $index < $endChunk && $index < count($articles);
				 $index++) {
				// check for change of starting letter or begining of chunk
				if (($index == $startChunk) ||
					($articles_start_char[$index] != $articles_start_char[$index - 1])
				) {
					if ($atColumnTop) {
						$atColumnTop = false;
					} else {
						$r .= "</ul>\n";
					}
					$cont_msg = "";
					if ($articles_start_char[$index] == $prev_start_char)
						$cont_msg = ' ' . wfMessage('listingcontinuesabbrev')->escaped();
					$prev_start_char = $articles_start_char[$index];
				}
				if (is_array($article_info) && $article_info[$index]['page_is_featured'] && $featured == 0) {
					$r .= "<div id='category_featured_entries'><img src='/skins/common/images/star.png' style='margin-right:5px;'><b>" . wfMessage('featured_articles_category')->text() . "</b>";
					$featured = 1;
				} elseif (is_array($article_info) && !$article_info[$index]['page_is_featured'] && $featured == 1) {
					$r .= "</div>";
				}
				if (is_array($article_info) && isset($article_info[$index])) {
					$page_len = $article_info[$index]['page_len'];
					$page_further_editing = $article_info[$index]['page_further_editing'];
					// save articles with certain templates to put at the end
					if ($page_further_editing || $page_len < 750) {
						$articles_with_templates[] = $articles[$index];
						$articles_with_templates_info[] = $article_info[$index];
						continue;
					}
				}

				$r .= "<div id='category_entry'>{$articles[$index]}</div>";
			}
		}

		if (sizeof($articles_with_templates) > 0) {
			$r .= "<div style='margin-top: 10px;'><b>" . wfMessage('articles_that_require_attention')->text() . "</b>";
			$index = 0;
			for ($index = 0; $index < sizeof($articles_with_templates); $index++) {
				$r .= "<div id='category_entry'>{$articles_with_templates[$index]} </div>";
			}
			$r .= "</div>";
		}
		$r .= '</tr></table>';
		return $r;
	}

	/**
	 * Format a list of articles chunked by letter in a bullet list.
	 * @param array $articles
	 * @param array $articles_start_char
	 * @return string
	 * @private
	 */
	function shortList($articles, $articles_start_char) {
		$r = "<div id=subcategories_list>";
		$r .= '<ul>';
		$sk = $this->getSkin();
		for ($index = 0; $index < count($articles); $index++) {
			if (is_array($articles[$index])) {
				$r .= "<li>{$articles[$index][0]}</li>";
				$links = array();
				foreach ($articles[$index][1] as $t) {
					$links[] = Linker::link($t, $t->getText());
				}
				$r .= "<div id=subcategories_list2><ul><li>" . implode(" <b>&bull;</b> ", $links) . "</li></ul></div>";
			} elseif ($articles[$index] instanceof Title) {
				$t = $articles[$index];
				$link = Linker::link($t, $t->getText());
				$r .= "<li>{$link}</li>";
			} else {
				if (is_string($articles[$index])) {
					$r .= "<li>{$articles[$index]}</li>";
				} else {
					print_r($articles[$index]);
				}
			}
		}
		$r .= '</div>';
		return $r;
	}

	function getArticlesFurtherEditing($articles, $article_info) {
		$articles_with_templates = array();
		$articles_with_templates_info = array();

		for ($index = 0; $index < count($articles); $index++) {
			if (is_array($article_info) && isset($article_info[$index])) {
				$page_len = $article_info[$index]['page_len'];
				// save articles with certain templates to put at the end
				//TODO: internationalize the shit out of this
				if ($article_info[$index]['page_further_editing'] == 1 || $page_len < 750) {
					if (strpos($articles[$index], ":") === false) {
						$articles_with_templates[] = $articles[$index];
						$articles_with_templates_info[] = $article_info[$index];
						continue;
					}
				}
			}
		}

		if (sizeof($articles_with_templates) > 0) {
			$chunk = (int)(count($articles) / 2);
			$html = "";

			$html .= "<h3>" . wfMessage('articles_that_require_attention')->text() . "</h3>\n";

			$html .= "<p>There are " . count($articles_with_templates) . " articles in this category that require attention.</p>\n";

			$html .= '<ul>' . "\n";
			for ($index = 0; $index < sizeof($articles_with_templates); $index++) {
				if (($index == $chunk) && (sizeof($articles_with_templates) > 5)) {
					$html .= '</ul> <ul class="category_column">' . "\n";
				}
				$html .= "<li>{$articles_with_templates[$index]} </li>\n";
			}
			$html .= "</ul><div class=\"clearall\"></div>";
		}

		return $html;
	}
}
