<?php

global $IP;
require_once("$IP/extensions/wikihow/common/S3.php"); 
require_once("$IP/extensions/wikihow/DatabaseHelper.class.php");

class ImageTranscoder extends AbsTranscoder {
	public function processTranscodingArticle($articleId, $creator) {
		
	}

	
// 	/*
// 	 * Call this when there are ONLY images in the uploaded zip
// 	 */
// 	public function processMedia($articleID, $creator, $imageList, $warning, $isHybridMedia) {
// 		$err = '';
// 		$numSteps = 0;
// 		$replaced = 0;

// 		// load article
// 		list($text, $url, $title) = $this->getArticleDetails($articleID);
// 		if (!$text || !$title) $err = 'Could not find article ID ' . $articleID;
    
// 		// parse out steps section replacing it with a token, leaving
// 		// the above and below wikitext intact
// 		if (!$err) {
// 			list($text, $steps, $stepsToken) = $this->cutStepsSection($text);
// 			if (!$stepsToken) {
// 				if (preg_match('@^(\s|\n)*#redirect@i', $text)) {
// 					$err = 'Could not parse Steps section out of article -- article text is #REDIRECT';
// 				} else {
// 					$err = 'Could not parse Steps section out of article';
// 				}
// 			}
// 		}
	
// 		// check if user is a known screenshot uploader
// 		if (!$err) {
// 			$userIsScreenshotter = $this->isCreatorKnownScreenShotter($creator);
// 		}
	
// 		// try to place images into wikitext, using tokens as placeholders.
// 		if (!$err) {
// 			$err = $this->placeImagesInSteps($articleID, $title, $imageList, $text, $steps, $numSteps, $replaced, $userIsScreenshotter);
// 		}
	
// 		// detect if no photos were to be processed
// 		if (!$err) {
// 			if (count($imageList) == 0) {
// 				$err = 'No photos to process';
// 			}
// 		}
	
// 		// replace the tokens within the image tag
// 		if (!$err) {
// 			$isAllLandscape = true;
// 			$hadColourProblems = false;
// 			$hadSizeProblems = false;
	
// 			$text = str_replace($stepsToken, $steps, $text);
// 			foreach ($imageList as $image) {
// 				if (!empty($image['width']) && !empty($image['height'])
// 				&& $image['width'] > $image['height'])
// 				{
// 					$sizeParam = WikiVisualTranscoder::IMAGE_LANDSCAPE_WIDTH;
// 				} else {
// 					$sizeParam = WikiVisualTranscoder::IMAGE_PORTRAIT_WIDTH;
// 					// Log first portrait image
// 					if (!$isAllLandscape) {
// 						$warning .= "portrait:{$image['name']}\n";
// 					}
// 					$isAllLandscape = false;
// 				}
	
// 				// Detect colour profile issues
// 				if (!$hadColourProblems && !empty($image['filename'])) {
// 					$exifProfile = WikiPhoto::getExifColourProfile($image['filename']);
// 					if ($exifProfile && WikiPhoto::isBadWebColourProfile($exifProfile)) {
// 						$warning .= "colour:$exifProfile:{$image['name']}\n";
// 						$hadColourProblems = true;
// 					}
// 				}
	
// 				// Log pixel width issues
// 				if (!$userIsScreenshotter
// 				&& !$hadSizeProblems
// 				&& !empty($image['width'])) {
//                     if ($image['width'] < WikiVisualTranscoder::WARNING_MIN_WIDTH) {
//                         $warning .= "size:{$image['width']}px:{$image['name']}\n";
//                         $hadSizeProblems = true;
//                     } else {
//                         $maxImgDimen = $image['width'] > $image['height'] ? $image['width'] : $image['height'];
//                         if ($maxImgDimen > WikiVisualTranscoder::ERROR_MAX_IMG_DIMEN) {
//                             $err .= "size:{$image['width']}px > max size ". WikiVisualTranscoder::ERROR_MAX_IMG_DIMEN ."px:{$image['name']}\n";
//                             $hadSizeProblems = true;
//                         }
//                     }
//                 }
	
// 				$imageTag = '[[Image:' . $image['mediawikiName'] . '|center|' . $sizeParam . ']]';
// 				$text = str_replace($image['token'], $imageTag, $text);
// 			}
// 		}
	
// 		// remove certain templates from start of wikitext
// 		if (!$err) {
// 			$templates = array('illustrations', 'pictures', 'screenshots', 'stub');
// 			$text = $this->removeTemplates($text, $templates);
// 		}
	
// 		// write wikitext and add/update wikiphoto row
// 		if (!$err) {
// 			$err = $this->saveArticleText($articleID, $text);
// 		}
	
// 		// try to enlarge the uploaded photos of certain users
// 		if (!$err) {
// 			// now we want to ALWAYS enlarge the images for articles with ALL Landscape
// 			if ($isAllLandscape) {
// 				Wikitext::enlargeImages($title, true, AdminEnlargeImages::DEFAULT_CENTER_PIXELS);
// 			}
// 		}
	
// 		if ($err) {
// 			return array($err, $title, $warning, $url, 0, 0);
// 		} else {
// 			return array($err, $title, $warning, $url, count($imageList), $replaced);
// 		}
// 	}


	/*
	 * Call this when there are ONLY images in the uploaded zip
	*/
	public function processMedia($articleID, $creator, $imageList, $warning, $isHybridMedia) {
		$videoList = array();
		list($err, $newWarning, $url, $numSteps, $replaced) = 
			$this->processHybridMedia($articleID, $creator, $videoList, $imageList);
		
		if ($newWarning) $warning .= $newWarning;
			
		// load article
		list($text, $url, $title) = $this->getArticleDetails($articleID);
		if (!$text || !$title) $err .= 'Could not find article ID ' . $articleID;
	
		if ($err) {
			return array($err, $title, $warning, $url, 0, 0);
		} else {
			return array($err, $title, $warning, $url, count($imageList), $replaced);
		}
	}
	
	
	
	
	
// 	/**
// 	 * Place a set of images into an article's wikitext.
// 	 */
// 	private function placeImagesInSteps($articleID, $title, &$images, &$text, &$stepsText, &$numSteps, &$replaced, $userIsScreenshotter) {
// 		$errs = '';
	
// 		// Count all images that will be replaced
// 		$countReplaced = preg_match_all('@(\{\{whvid\|)@im', $stepsText, $throwAway);
// 		$countReplaced += preg_match_all('@(\[\[Image:|\{\{largeimage)@im', $text, $throwAway);
// 		$countReplaced += preg_match_all('@(\[\[Image:|\{\{largeimage)@im', $stepsText, $throwAway);
	
// 		//start by deleting all images that already exist in the article.
// 		//first in the main text
// 		$text = preg_replace('@\[\[Image:[^\]]*\]\]@im', '', $text);
// 		$text = preg_replace('@\{\{largeimage\|[^\}]*\}\}@im', '', $text);
// 		//now in the steps too
// 		$stepsText = preg_replace('@\{\{whvid\|[^\}]*\}\}@im', '', $stepsText);
// 		$stepsText = preg_replace('@\[\[Image:[^\]]*\]\]@im', '', $stepsText);
// 		$stepsText = preg_replace('@\{\{largeimage\|[^\}]*\}\}@im', "", $stepsText);

// 		// process the list of images to make sure we can understand all filenames
// 		$hasFinalStep = false;
// 		list($err, $hasFinalStep) = $this->addExtraPhotoInfo($title, $images);

// 		// split steps based on ^# then add the '#' character back on
// 		$steps = preg_split('@^\s*#@m', $stepsText);
// 		for ($i = 1; $i < count($steps); $i++) {
// 			$steps[$i] = "#" . $steps[$i];
// 		}
	
// 		// place images in steps
// 		$stepNum = 1;
// 		for ($i = 1; $i < count($steps); $i++) {
// 			if (preg_match('@^(([#*]|\s)+)((.|\n)*)@m', $steps[$i], $m)) {
// 				$stripped = preg_replace('@\s+@', '', $m[1]);
// 				$levels = strlen($stripped);
// 				if ($levels == 1) {
// 					$subNum = 0;
// 					$stepIdx = false;
// 					foreach ($images as $j => $image) {
// 						if ($image['step'] == $stepNum && $image['sub'] == null) {
// 							$stepIdx = $j;
// 							break;
// 						}
// 					}
// 					if ($stepIdx !== false) {
// 						$imgToken = 'IMG_' . Misc::genRandomString() . '_' . $stepNum;
	
// 						$steps[$i] = $m[1] . $imgToken . $m[3];
// 						$images[$stepIdx]['token'] = $imgToken;
//                         self::d('$images[$stepIdx][\'token\']='. $images[$stepIdx]['token'] );
// 					} else 
//                         self::d('$stepIdx='. $stepIdx);
// 					$stepNum++;
// 				}
// 				elseif ($levels == 2) {
// 					//we're in a bullet, check to see if we have a
// 					//image for this substep
// 					$subNum++;
// 					$stepIdx = false;
// 					foreach ($images as $j => $image) {
// 						if ($image['step'] == ($stepNum - 1) ) {
// 							if ($image['sub'] != null && $image['sub'] == $subNum) {
// 								$stepIdx = $j;
// 								break;
// 							}
// 						}
// 					}
// 					if ($stepIdx !== false) {
// 						$imgToken = 'IMG_' . Misc::genRandomString() . '_' . ($stepNum - 1) . "_" . $subNum;
	
// 						$steps[$i] = $m[1] . $imgToken . $m[3];
// 						$images[$stepIdx]['token'] = $imgToken;
//                         self::d('$images[$stepIdx][\'token\']='. $images[$stepIdx]['token'] );
// 					} else
//                         self::d('$stepIdx='. $stepIdx);
// 				}
// 			} 
// 		}
// 		$numSteps = $stepNum - 1;
	
// 		// were we able to place all images in the article?
// 		$notPlaced = array();
// 		foreach ($images as $image) {
// 			if (!isset($image['token'])) {
// 				$notPlaced[] = $image['name'];
// 			}
// 		}
// 		if ($notPlaced) {
// 			$err = 'Unable to place images in the wikitext: ' . join(', ', $notPlaced);
// 		}
	
// 		// add all these images to the wikihow mediawiki repos
// 		if (!$err) {
// 			$err = self::addAllMediaWikiImages($articleID, $images, $userIsScreenshotter);
			
// 			if (!$err) {
// 				$stepsText = join('', $steps);
// 				if (count($steps) && trim($steps[0]) == '') {
// 					$stepsText = "\n" . $stepsText;
// 				}
// 			}
// 		}
	
// 		$replaced = !$err ? $countReplaced : 0;
	
// 		return $err;
// 	}
	
	public static function addAllMediaWikiImages($articleId, &$images, $userIsScreenshotter) {
		foreach ($images as &$img) {
			$success = self::addMediawikiImage($articleID, $img, $userIsScreenshotter);
			if (!$success) {
				$err = 'Unable to add new image file ' . $img['name'] . ' to wikiHow';
				break;
			} else {
				$imgTitle = Title::newFromText($img['mediawikiName'], NS_IMAGE);
				if ($imgTitle) {
					$file = wfFindFile($imgTitle);
					if ($file) {
						$img['width'] = $file->getWidth();
						$img['height'] = $file->getHeight();
					}
				}
			}
		}
		return $err;
	}
	
	/**
	 * Add a new image file into the mediawiki infrastructure so that it can
	 * be accessed as [[Image:filename.jpg]]
	 */
	private static function addMediawikiImage($articleID, &$image, $userIsScreenshotter) {
		// check if we've already uploaded this image
		$dupTitle = DupImage::checkDupImage($image['filename']);
	
		// if we've already uploaded this image, just return that filename
		if ($dupTitle) {
			$image['mediawikiName'] = $dupTitle;
			return true;
		}
	
		// find name for image; change filename to Filename 1.jpg if
		// Filename.jpg already existed
		$regexp = '/[^' . Title::legalChars() . ']+/';
		$first = preg_replace($regexp, '', $image['first']);
		$ext = $image['ext'];
		$newName = $first . '.' . $ext;
		$i = 1;
		do {
			$title = Title::newFromText($newName, NS_IMAGE);
			if ($title && !$title->exists()) break;
			$newName = $first . ' Version ' . ++$i . '.' . $ext;
		} while ($i <= 1000);
	
		// insert image into wikihow mediawiki repos
		if (!$userIsScreenshotter) {
			$comment = '{{' . WikiVisualTranscoder::PHOTO_LICENSE . '}}';
		} else {
			$comment = '{{' . WikiVisualTranscoder::SCREENSHOT_LICENSE . '}}';
		}
		// next 6 lines taken and modified from
		// extensions/wikihow/eiu/Easyimageupload.body.php
		$title = Title::makeTitleSafe(NS_IMAGE, $newName);
		if (!$title) return false;
		$file = new LocalFile($title, RepoGroup::singleton()->getLocalRepo());
		if (!$file) return false;
		$ret = $file->upload($image['filename'], $comment, $comment);
		if (!$ret->ok) return false;
	
		// instruct later processing about which mediawiki name was used
		$image['mediawikiName'] = $newName;
	
		// Add our uploaded image to the dup table so it's no uploaded again
		DupImage::addDupImage($image['filename'], $image['mediawikiName']);
	
		// Keep a log of where images were uploaded in wikiphoto_image_names table
		$dbw = WikiVisualTranscoder::getDB('write');
		$imgname = $articleID . '/' . $image['name'];
		$sql = 'INSERT INTO wikivisual_photo_names SET filename=' . $dbw->addQuotes($imgname) . ', wikiname=' . $dbw->addQuotes($image['mediawikiName']);
		$dbw->query($sql, __METHOD__);
	
		return true;
	}
}
