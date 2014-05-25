<?php

class ImageHelper extends UnlistedSpecialPage {

	const IMAGES_ON = true;

	/***************************
	 **
	 **
	 ***************************/
	function __construct() {
		parent::__construct( 'ImageHelper' );
	}

	public static function heightPreference($desiredWidth, $desiredHeight, &$file) {
		$heightPreference = false;
		if ($file) {
			$sourceWidth = $file->getWidth();
			$sourceHeight = $file->getHeight();
			if ($desiredWidth/$desiredHeight < $sourceWidth/$sourceHeight) {
				//desired image is portrait
				$heightPreference = true;
			}
		}
		return $heightPreference;
	}
	
	function getRelatedWikiHows($title) {
		global $wgOut;
		wfLoadExtensionMessages('ImageHelper');
		
		$articles = ImageHelper::getLinkedArticles($title);
		$relatedArticles = array();
		foreach ($articles as $t) {
			$related = ImageHelper::setRelatedWikiHows($t);
			foreach ($related as $titleString) {
				$relatedArticles[$titleString] = $titleString;
			}
		}

		$section = '';

		$count = 0;
		$images = '';
		foreach ($relatedArticles as $titleString) {
			$t = Title::newFromText($titleString);
			if ($t && $t->exists()) {
				$result = SkinWikihowskin::getArticleThumb($t, 127, 140);
				$images .= $result;
				
				if (++$count == 4) break;
			}
		}

		if ($count > 0) {
			$section .= "<div class='other_articles minor_section'>
						<h2>" . wfMsg('ih_relatedArticles') . "</h2>
						$images
						<div class='clearall'></div>
						</div>";
		}

		$wgOut->addHTML($section);
	}

	function setRelatedWikiHows($title) {
		global $wgTitle, $wgParser, $wgMemc;

		$key = wfMemcKey("ImageHelper_related", $title->getArticleID());
		$result = $wgMemc->get($key);
		if ($result) {
			return $result;
		}

		$templates = wfMsgForContent('ih_categories_ignore');
		$templates = explode("\n", $templates);
		$templates = str_replace("http://www.wikihow.com/Category:", "", $templates);
		$templates = array_flip($templates); // make the array associative.

		$r = Revision::newFromTitle($title);
		$relatedTitles = array();
		if ($r) {
			$text = $r->getText();
			$whow = WikihowArticleEditor::newFromText($text);
			$related = preg_replace("@^==.*@m", "", $whow->getSection('related wikihows'));

			if ($related != "") {
				$preg = "/\\|[^\\]]*/";
				$related = preg_replace($preg, "", $related);
				$rarray = explode("\n", $related);
				foreach ($rarray as $related) {
					preg_match("/\[\[(.*)\]\]/", $related, $rmatch);

					//check to make sure this article isn't in a category
					//that we don't want to show
					$title = Title::MakeTitle( NS_MAIN, $rmatch[1] );
					$cats = ($title->getParentCategories());
					if (is_array($cats) && sizeof($cats) > 0) {
						$keys = array_keys($cats);
						$found = false;
						for ($i = 0; $i < sizeof($keys) && !$found; $i++) {
							$t = Title::newFromText($keys[$i]);
							if (isset($templates[urldecode($t->getPartialURL())]) ) {
								//this article is in a category we don't want to show
								$found = true;
								break;
							}
						}
						if ($found) continue;
					}

					$relatedTitles[] = $rmatch[1];
				}
				
			} else {
				$cats = $title->getParentCategories();
				$cat1 = '';
				if (is_array($cats) && sizeof($cats) > 0) {
					$keys = array_keys($cats);
					$cat1 = '';
					$found = false;
					$templates = wfMsgForContent('ih_categories_ignore');
					$templates = explode("\n", $templates);
					$templates = str_replace("http://www.wikihow.com/Category:", "", $templates);
					$templates = array_flip($templates); // make the array associative.
					for ($i = 0; $i < sizeof($keys) && !$found; $i++) {
						$t = Title::newFromText($keys[$i]);
						if (isset($templates[urldecode($t->getPartialURL())]) ) {
							continue;
						}
						$cat1 = $t->getDBKey();
						$found = true;
						break;
					}
				}
				if ($cat1 != '') {
					$dbr = wfGetDB( DB_SLAVE );
					$num = intval(wfMsgForContent('num_related_articles_to_display'));
					$res = $dbr->select('categorylinks', 'cl_from', array ('cl_to' => $cat1),
						"WikiHowSkin:getRelatedArticlesBox",
						array ('ORDER BY' => 'rand()', 'LIMIT' => $num*2));
					
					$count = 0;
					while (($row = $dbr->fetchObject($res)) && $count < $num) {
						if ($row->cl_from == $title->getArticleID()) {
							continue;
						}
						$t = Title::newFromID($row->cl_from);
						if (!$t) {
							continue;
						}
						if ($t->getNamespace() != NS_MAIN) {
							continue;
						}
						$relatedTitles[] = $t->getText();
						$count++;
					}

				}
			}
		}

		$wgMemc->set($key, $relatedTitles);
		
		return $relatedTitles;
		
	}

	/**
	 *
	 * Returns an array of titles that have links to the given
	 * title (presumably an image). All returned articles will be in the
	 * NS_MAIN namespace and will also not be in a excluded category.
	 * 
	 */
	function getLinkedArticles($title) {
		global $wgMemc;
		$cachekey = wfMemcKey("ImageHelper_linked", $title->getArticleID());

		$result = $wgMemc->get($cachekey);
		if ($result) {
			return $result;
		}

		$imageTitle = $title->getDBkey();
		$dbr = wfGetDB( DB_SLAVE );
		$page = $dbr->tableName( 'page' );
		$imagelinks = $dbr->tableName( 'imagelinks' );

		$sql = "SELECT page_namespace,page_title,page_id FROM $imagelinks,$page WHERE il_to=" .
		  $dbr->addQuotes( $imageTitle ) . " AND il_from=page_id";
		$sql = $dbr->limitResult($sql, 500, 0);
		$res = $dbr->query( $sql, __METHOD__ );

		$articles = array();

		$templates = wfMsgForContent('ih_categories_ignore');
		$templates = explode("\n", $templates);
		$templates = str_replace("http://www.wikihow.com/Category:", "", $templates);
		$templates = array_flip($templates); // make the array associative.

		foreach ($res as $s) {
			//check if in main namespace
			if ($s->page_namespace != NS_MAIN) {
				continue;
			}

			//check if in category exclusion list
			$title = Title::MakeTitle( $s->page_namespace, $s->page_title );
			$cats = ($title->getParentCategories());
			if (is_array($cats) && sizeof($cats) > 0) {
				$keys = array_keys($cats);
				$found = false;
				for ($i = 0; $i < sizeof($keys) && !$found; $i++) {
					$t = Title::newFromText($keys[$i]);
					if (isset($templates[urldecode($t->getPartialURL())]) ) {
						//this article is in a category we don't want to show
						$found = true;
						break;
					}
				}
				if ($found)
					continue;
			}
			if ($s->page_title != $imageTitle) {
				$articles[] = $title;
			}

		}

		$wgMemc->set($cachekey, $articles);
		return $articles;
	}

	function getSummaryInfo($image) {
		global $wgOut, $wgTitle;

		$sk = $this->getSkin();

		$sizes = ImageHelper::getDisplaySize($image);

		$tmpl = new EasyTemplate( dirname(__FILE__) );
		$tmpl->set_vars(array(
			'preview' => $sizes['width'] . "x" . $sizes['height'] . "px",
			'full' => ($sizes['full'] == 0 ? "<a href='" . $image->getFullUrl() . "'>" . $image->getWidth() . "x" . $image->getHeight() . " px </a>" : wfMsg( 'file-nohires')),
			'file' => $sk->formatSize($image->getSize()),
			'mime' => $image->getMimeType(),
			'imageCode' => "[[" . $wgTitle->getFullText() . "|thumb|description]]"
		));
		
		$wgOut->addHTML($tmpl->execute('fileInfo.tmpl.php'));

	}

	function getImages($articleId) {
		global $wgMemc;

		$key = wfMemcKey("ImageHelper_getImages", $articleId);
		$result = $wgMemc->get($key);
		if ($result) {
			return $result;
		}

		$dbr = wfGetDB( DB_SLAVE );
		$results = array();
		$res = $dbr->select(array('imagelinks'), '*', array('il_from' => $articleId));
		foreach ($res as $row) {
			$results[] = (array)$row;
		}

		$wgMemc->set($key, $results);

		return $results;
	}
	
	function getLinksTo($articles) {
		global $wgOut;

		wfLoadExtensionMessages('ImageHelper');

		$section = '';

		$count = 0;
		$images = '';
		foreach ($articles as $t) {
			if ($t && $t->exists()) {
				$images .= SkinWikihowskin::getArticleThumb($t, 150, 120);
				
				if (++$count == 5) break;
			}
		}

		if ($count > 0) {
			$section .= "<h4>" . wfMsg('Linkstoimage') . "</h4>";
			$section .= "<table class='featuredArticle_Table'>";
			$section .= $images;
			$section .= "</table>";
		}

		$wgOut->addHTML($section);
	}


	/**
	 *
	 * This function takes an array of titles and finds other images
	 * that are in those articles.
	 */
	function getConnectedImages($articles, $title) {
		global $wgOut, $wgMemc;

		wfLoadExtensionMessages('ImageHelper');

		$exceptions = wfMsg('ih_exceptions');
		$imageExceptions = explode("\n", $exceptions);

		$sk = $this->getSkin();

		$key = wfMemcKey("ImageHelper_getConnectedImages", $title->getText());
		$result = $wgMemc->get($key);
		if ($result) {
			$wgOut->addHTML($result);
			return;
		}

		$imageName = $title->getDBkey();
		if (in_array($imageName, $imageExceptions)) {
			$wgMemc->set($key, "");
			return;
		}
		
		$html = '';

		$noImageArray = array();
		foreach ($articles as $title) {
			$imageUrl = array();
			$thumbUrl = array();
			$imageTitle = array();
			$imageWidth = array();
			$imageHeight = array();
			
			$results = ImageHelper::getImages($title->getArticleID());

			$count = 0;
			if (count($results) <= 1) {
				$noImageArray[] = $title;
				continue;
			}
			
			$titleLink = $sk->makeKnownLinkObj( $title, "" );
			$found = false;
			foreach ($results as $row) {
				if ($count >= 4) break;

				if ($row['il_to'] != $imageName && !in_array($row['il_to'], $imageExceptions)) {
					$image = Title::newFromText("Image:" . $row['il_to']);
					if ($image && $image->getArticleID() > 0) {

						$file = wfFindFile($image);
						if ($file && isset($file)) {
							$heightPreference = ImageHelper::heightPreference(127, 140, $file);
							$thumb = $file->getThumbnail(127, 140, true, true, $heightPreference);
							$imageUrl[] = $image->getFullURL();
							$thumbUrl[] = $thumb->getUrl();
							$imageTitle[] = $row['il_to'];
							$imageWidth[] = $thumb->getWidth();
							$imageHeight[] = $thumb->getHeight();
							$count++;
							$found = true;
						}
					}
				}
			}
			if ($count > 0) {
				$tmpl = new EasyTemplate( dirname(__FILE__) );
				$tmpl->set_vars(array(
					'imageUrl' => $imageUrl,
					'thumbUrl' => $thumbUrl,
					'imageTitle' => $imageTitle,
					'title' => $titleLink,
					'numImages' => count($imageUrl),
					'imageWidth' => $imageWidth,
					'imageHeight' => $imageHeight,
					'imgStrip' => false
				));

				$html .= $tmpl->execute('connectedImages.tmpl.php');
			} else {
				$noImageArray[] = $title;
			}
		}

		if (sizeof($noImageArray) > 0) {
			$html .= "<div class='minor_section'>
						<h2>" . wfMsg('ih_otherlinks') . "</h2><ul class='im-images'>";
			foreach ($noImageArray as $title) {
				$link = $sk->makeKnownLinkObj( $title, "" );
				$html .= "<li>{$link}</li>\n";
			}
			$html .= "</ul></div>";
		}
		
		$wgMemc->set($key, $html);

		$wgOut->addHTML($html);
	}

	function displayBottomAds() {
		global $wgOut, $wgUser;
		
		if ($wgUser->getID() == 0) {
			$channels = wikihowAds::getCustomGoogleChannels('imagead2', false);
			$embed_ads = wfMsg('imagead2', $channels[0], $channels[1] );
			$embed_ads = preg_replace('/\<[\/]?pre\>/', '', $embed_ads);
			$wgOut->addHTML($embed_ads);
		}
	}

	/*
	 * All this code is taken from ImagePage.php in includes
	 */
	function getDisplaySize($img) {
		global $wgOut, $wgUser, $wgImageLimits, $wgRequest, $wgLang, $wgContLang;

		$sizeSel = intval( $wgUser->getOption( 'imagesize') );
		if ( !isset( $wgImageLimits[$sizeSel] ) ) {
			$sizeSel = User::getDefaultOption( 'imagesize' );

			// The user offset might still be incorrect, specially if
			// $wgImageLimits got changed (see bug #8858).
			if ( !isset( $wgImageLimits[$sizeSel] ) ) {
				// Default to the first offset in $wgImageLimits
				$sizeSel = 0;
			}
		}
		$max = $wgImageLimits[$sizeSel];
		$maxWidth = $max[0];
		//XXMOD for fixed width new layout.  eventhough 800x600 is default 679 is max article width
		if ($maxWidth > 679)
			$maxWidth = 629;
		$maxHeight = $max[1];

		if ( $img->exists() ) {
			# image
			$page = $wgRequest->getIntOrNull( 'page' );
			if ( is_null( $page ) ) {
				$params = array();
				$page = 1;
			} else {
				$params = array( 'page' => $page );
			}
			$width_orig = $img->getWidth();
			$width = $width_orig;
			$height_orig = $img->getHeight();
			$height = $height_orig;

			if ( $img->allowInlineDisplay() ) {
				# image

				# "Download high res version" link below the image
				#$msgsize = wfMsgHtml('file-info-size', $width_orig, $height_orig, $sk->formatSize( $this->img->getSize() ), $mime );
				# We'll show a thumbnail of this image
				if ( $width > $maxWidth || $height > $maxHeight ) {
					# Calculate the thumbnail size.
					# First case, the limiting factor is the width, not the height.
					if ( $width / $height >= $maxWidth / $maxHeight ) {
						$height = round( $height * $maxWidth / $width);
						$width = $maxWidth;
						# Note that $height <= $maxHeight now.
					} else {
						$newwidth = floor( $width * $maxHeight / $height);
						$height = round( $height * $newwidth / $width );
						$width = $newwidth;
						# Note that $height <= $maxHeight now, but might not be identical
						# because of rounding.
					}
					$size['width'] = $width;
					$size['height'] = $height;
					$size['full'] = 0;
					return $size;
				} else {
					# Image is small enough to show full size on image page
					$size['width'] = $width;
					$size['height'] = $height;
					$size['full'] = 1;
					return $size;
				}

			} else {
				#if direct link is allowed but it's not a renderable image, show an icon.
				if ($img->isSafeFile()) {
					$icon= $img->iconThumb();

					$wgOut->addHTML( '<div class="fullImageLink minor_section" id="file">' .
					$icon->toHtml( array( 'desc-link' => true ) ) .
					'</div>' );
				}

				$showLink = true;
			}


			

			if (!$this->img->isLocal()) {
				$this->printSharedImageText();
			}
		} else {
			# Image does not exist
			$size['width'] = -1;
			$size['height'] = -1;
			return $size;
		}
	}

	function showDescription($imageTitle) {
		global $wgOut;
		
		$description = "";
		
		$t = Title::newFromText('Image:' . $imageTitle->getPartialURL() . '/description');
		if ($t && $t->getArticleId() > 0) {
			$r = Revision::newFromTitle($t);
			$description = $r->getText();
			$wgOut->addHTML("<div style='margin-top:10px;' class='im-images'>");
			$wgOut->addHTML("<strong>Description: </strong>");
			$wgOut->addHTML($description);
			$wgOut->addHTML("</div>");
		}
		
	}

	function addSideWidgets($imagePage, $title, $image) {
		global $wgLanguageCode;
		$skin = $this->getSkin();
		
		if (ImageHelper::IMAGES_ON) {
			//first add related images
			$html = ImageHelper::getRelatedImagesWidget($title);
			if ($html != "")
				$skin->addWidget($html);

		}
		//first add image info
		$html = ImageHelper::getImageInfoWidget($imagePage, $title, $image);
		if ($html != "")
			$skin->addWidget($html);
		if (ImageHelper::IMAGES_ON) {
			$html = ImageHelper::getRelatedWikiHowsWidget($title);
			if ($html != "")
				$skin->addWidget($html);
		}
	}

	function getRelatedWikiHowsWidget($title) {
		global $wgOut;

		wfLoadExtensionMessages('ImageHelper');

		$articles = ImageHelper::getLinkedArticles($title);
		$relatedArticles = array();
		foreach ($articles as $t) {
			$related = ImageHelper::setRelatedWikiHows($t);
			foreach ($related as $titleString) {
				$relatedArticles[$titleString] = $titleString;
			}
		}

		$section = '';
		$count = 0;
		$images = '';
		foreach ($relatedArticles as $titleString) {
			$t = Title::newFromText($titleString);
			if ($t && $t->exists()) {
				$images .= SkinWikihowskin::getArticleThumb($t, 127, 140);

				if (++$count == 6) break;
			}
		}
		if ($count > 0) {
			$section .= "<h3>" . wfMsg('ih_relatedArticles') . "</h3>
						<div class='other_articles_side'>
						$images
						<div class='clearall'></div>
						</div>";
		}
		

		return $section;
	}

	function getImageInfoWidget($imagePage, $title, $image) {
		global $wgOut;

		$sk = $this->getSkin();

		$t = Title::newFromText('Image-Templates', NS_CATEGORY);
		if ($t) {
			$cv = new WikihowCategoryViewer($t, $this->getContext());
			$cv->clearCategoryState();
			$cv->doQuery();

			$templates = array();
			foreach ($cv->articles as $article) {
				$start = strrpos($article, 'title="Template:');
				if ($start > 0) {
					$end = strrpos($article, '"', $start + 16 + 1);
					if ($end > 0) {
						$templates[] = strtolower(str_replace(' ', '-', substr($article, $start + 16, $end - $start - 16)));
					}
				}
				
			}

			$license = '';
			$content = preg_replace_callback(
				'@({{([^}|]+)(\|[^}]*)?}})@',
				function ($m) use ($templates, &$license) {
					$name = trim(strtolower($m[2]));
					$name = str_replace(' ', '-', $name);
					foreach ($templates as $template) {
						if ($name == $template) {
							$license .= $m[0];
							return '';
						}
					}
					return $m[1];
				},
				$imagePage->getContent()
			);
		}

		$lastUser = $image->getUser();
		$userLink = $sk->makeLinkObj(Title::makeTitle(NS_USER, $lastUser), $lastUser);

		$html = "<div id='im-info' style='word-wrap: break-word;'>";
		$html .= $wgOut->parse("=== Licensing / Attribution === \n" . $license ) . "<p>".wfMsg('image_upload', $userLink)."</p><br />";


		//now remove old licensing header
		$content = str_replace("== Licensing ==", "", $content);
		$content = str_replace("== Summary ==", "=== Summary ===", $content);
		$content = trim($content);

		if (strlen($content) > 0 && substr($content, 0, 1) != "=")
			$content = "=== Summary === \n" . $content;
		else{

		}

		$html .= $wgOut->parse($content);
		
		$html .= "</div>";

		return $html;
	}

	function getRelatedImagesWidget($title) {
		$exceptions = wfMsg('ih_exceptions');
		$imageExceptions = explode("\n", $exceptions);
		
		$articles = ImageHelper::getLinkedArticles($title);
		$images = array();
		foreach ($articles as $t) {
			$results = ImageHelper::getImages($t->getArticleID());
			if (count($results) <= 1) {
				continue;
			}

			$titleDb = $title->getDBkey();
			foreach ($results as $row) {
				if ($row['il_to'] != $titleDb && !in_array($row['il_to'], $imageExceptions)) {
					$images[] = $row['il_to'];
				}
			}
		}

		$count = 0;
		$maxLoc = count($images);
		$maxImages = $maxLoc;
		$finalImages = array();
		while ($count < 6 && $count < $maxImages) {
			$loc = rand(0, $maxLoc);
			if (isset($images[$loc]) && $images[$loc]) {
				$image = Title::newFromText("Image:" . $images[$loc]);
				if ($image && $image->getArticleID() > 0) {
					$file = wfFindFile($image);
					if ($file) {
						$finalImages[] = array('title' => $image, 'file' => $file);
						$images[$loc] = null;
						$count++;
					} else {
						$maxImages--;
					}
				} else {
					$maxImages--;
				}
				$images[$loc] = null;
			}
		}

		if (count($finalImages) > 0) {
			$html = '<div><h3>' . wfMsg('ih_relatedimages_widget') . '</h3><table style="margin-top:10px" class="image_siderelated">';
			$count = 0;
			foreach ($finalImages as $imageObject) {
				$image = $imageObject['title'];
				$file = $imageObject['file'];
				if ($count % 2 == 0)
					$html .= "<tr>";
				
				$heightPreference = ImageHelper::heightPreference(127, 140, $file);
				$thumb = $file->getThumbnail(127, 140, true, true, $heightPreference);
				$imageUrl = $image->getFullURL();
				$thumbUrl = $thumb->getUrl();
				$imageTitle = $image->getText();

				$html .= "<td valign='top'>
							<a href='" . $imageUrl . "' title='" . htmlspecialchars($imageTitle) . "' class='image'>
							<img border='0' class='mwimage101' src='" . wfGetPad($thumbUrl) ."' alt='" . $imageTitle . "'>
							</a>
						</td>";

				if ($count % 2 == 2)
					$html .= "</tr>";

				$count++;
			}
			if ($count % 3 != 2)
				$html .= "</tr>";
			$html .= "</table></div>";
			
			return $html;
		}
		
	}

	/**
	 * As createThumb, but returns a ThumbnailImage object. This can
	 * provide access to the actual file, the real size of the thumb,
	 * and can produce a convenient <img> tag for you.
	 *
	 * For non-image formats, this may return a filetype-specific icon.
	 *
	 * @param integer $width	maximum width of the generated thumbnail
	 * @param integer $height	maximum height of the image (optional)
	 * @param boolean $render	True to render the thumbnail if it doesn't exist,
	 *                       	false to just return the URL
	 *
	 * @return ThumbnailImage or null on failure
	 *
	 * @deprecated use transform()
	 */
	// NOTE: Reuben changed this function to ignore the $render flag; we should
	// refactor the calls to this function to remove that param. Chatted with
	// Aaron and Bebeth about this $render change on May 9, 2014.
	// NOTE: Reuben deprecated the $heightPreference param -- it does nothing now
	public function getThumbnail( $width, $height=-1, $render = true, $crop = false, $heightPreference = false ) {
		$params = array( 'width' => $width );
		if ( $height != -1 ) {
			$params['height'] = $height;
		}

		if ($crop) {
			$params['crop'] = 1;
		}
		$params['heightPreference'] = $heightPreference;
		// Reuben: No longer use RENDER_NOW flag because it's unnecessary and 
		// messes up the transformVia404 stuff
		//$flags = $render ? File::RENDER_NOW : 0;
		$flags = 0;
		return $this->transform( $params, $flags );
	}

}

