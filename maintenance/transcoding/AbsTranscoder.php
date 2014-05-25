<?php

global $IP;
require_once("$IP/extensions/wikihow/common/S3.php"); 
require_once("$IP/extensions/wikihow/DatabaseHelper.class.php");

interface Transcodable {
    public function processTranscodingArticle($articleId, $creator); //after transcoding is done.
    public function processMedia($articleID, $creator, $imageList, $warning, $isHybridMedia); //transcode or schedule transcode
}

abstract class AbsTranscoder implements Transcodable {
	const BRTAG = "<br><br>";
	const BRTAG_TO_VID = true;
	const BRTAG_TO_IMG = false;
	const REMOVE_BRTAG_FROM_END_OF_STEP = true;

	private $stepsMsg;
	
	function __construct() {
		$this->stepsMsg = wfMsg('steps');
	}
	
	public static function d($msg) {
	    WikiVisualTranscoder::d($msg);	
	}

	public static function i($msg) {
	    WikiVisualTranscoder::i($msg);	
	}

	/**
	 * Load wikitext and get article URL
	 */
	public function getArticleDetails($id) {
		$dbr = WikiVisualTranscoder::getDB('read');
		$rev = Revision::loadFromPageId($dbr, $id);
		if ($rev) {
			$text = $rev->getText();
			$title = $rev->getTitle();
			$url = WikiVisualTranscoder::makeWikihowURL($title);
			return array($text, $url, $title);
		} else {
			return array('', '', null);
		}
	}
	
	/**
	 * Remove the Steps section from an article, leaving a placeholder
	 */
	public function cutStepsSection($articleText) {
		$out = array();
		$token = Misc::genRandomString();
		$steps = '';
		$found = false;
	
		$former_recursion_limit = ini_set( "pcre.recursion_limit", 90000 ); 
																			//to any media
	
		// look for the steps section, cut it
		$newText = preg_replace_callback(
				'@^(\s*==\s*' . $this->stepsMsg . '\s*==\s*)$((.|\n)*)^(\s*==[^=])@mU',
				function ($m) use ($token, &$steps, &$found) {
					$steps = $m[2];
					$newText = $m[1] . $token . $m[4];
					$found = true;
					return $newText;
				},
				$articleText
		);
		if (!$found) {
			$newText = preg_replace_callback(
					'@^(\s*==\s*' . $this->stepsMsg . '\s*==\s*)$((.|\n)*)(?!^\s*==[^=])@m',
					function ($m) use ($token, &$steps, &$found) {
						$steps = $m[2];
						$newText = $m[1] . $token;
						$found = true;
						return $newText;
					},
					$articleText
			);
		}
		ini_set( "pcre.recursion_limit", $former_recursion_limit ); 
	
		if (!$found) $token = '';
		return array($newText, $steps, $token);
	}
	
	/**
	 * Removes all of the specified templates from the start of the intro of the
	 * wikitext.
	 *
	 * @param $wikitext a string of wikitext
	 * @param $templates an array of strings identifying the templates, like
	 *   array('pictures', 'illustrations')
	 */
	 protected function removeTemplates($wikitext, $templates) {
		global $wgParser;
		$intro = $wgParser->getSection($wikitext, 0);
		$replaced = false;
		foreach ($templates as &$template) {
			$template = strtolower($template);
		}
		$intro = preg_replace_callback(
				'@({{([^}|]+)(\|[^}]*)?}})@',
				function ($m) use ($templates, &$replaced) {
					$name = trim(strtolower($m[2]));
					foreach ($templates as $template) {
						if ($name == $template) {
							$replaced = true;
							return '';
						}
					}
					return $m[1];
				},
				$intro
		);
	
		if ($replaced) {
			$wikitext = $wgParser->replaceSection($wikitext, 0, $intro);
		}
		return $wikitext;
	}

	/**
	 * Save wikitext for an article
	 */
	protected function saveArticleText($id, $wikitext) {
		$saved = false;
		$title = Title::newFromID($id);
		if ($title) {
			$article = new Article($title);
			$saved = $article->doEdit($wikitext, 'Saving new step-by-step photos');
		}
		if (!$saved) {
			return 'Unable to save wikitext for article ID: ' . $id;
		} else {
			return '';
		}
	}

	public function isCreatorKnownScreenShotter($creator) {
		$userIsScreenshotter = false;
		$screenshotters = explode("\n", ConfigStorage::dbGetConfig('wikiphoto-exclude-from-image-warning'));
		foreach ($screenshotters as $ssUser) {
			$ssUser = trim($ssUser);
			if (!$ssUser) continue;
			if (strtolower($ssUser) == strtolower($creator)) {
				$userIsScreenshotter = true;
				break;
			}
		}
		return $userIsScreenshotter;
	}
	
	public function processHybridMedia($articleID, $creator, $videoList, $photoList) {
		$err = '';
		$numSteps = 0;
		$replaced = 0;
		
		$vidBrTag = self::BRTAG_TO_VID ? self::BRTAG : '';
		$imgBrTag = self::BRTAG_TO_IMG ? self::BRTAG : '';
		
        self::d("processHybridMedia parse out steps section replacing it with a token, leaving the above and below wikitext intact");
		// parse out steps section replacing it with a token, leaving
		// the above and below wikitext intact
		list($text, $url, $title) = $this->getArticleDetails($articleID);
		if (!$text || !$title) $err = 'Could not find article ID ' . $articleID;
        self::d("getArticleDetails: err:". $err);
		if (!$err) {
			list($text, $steps, $stepsToken) = $this->cutStepsSection($text);
			if (!$stepsToken) {
				if (preg_match('@^(\s|\n)*#redirect@i', $text)) {
					$err = 'Could not parse Steps section out of article -- article text is #REDIRECT';
				} else {
					$err = 'Could not parse Steps section out of article';
				}
			}
		}
		$hybridMediaList = null;
		// try to place videos into wikitext, using tokens as placeholders.
		if (!$err) {
			$userIsScreenshotter = $this->isCreatorKnownScreenShotter($creator);
			list($err, $hybridMediaList) = 
				$this->placeHybridMediaInSteps($articleID, $title, $videoList, $photoList, $text, $steps, $numSteps, $replaced, $userIsScreenshotter);
		}
	
		// detect if no photos and videos were to be processed
		if (!$err) {
			if (count($videoList) == 0 && count($photoList) == 0) {
				$err = 'No photos and videos to process';
			}
		}
	
		// replace the tokens within the video or image tag
		if (!$err && $hybridMediaList && count($hybridMediaList) > 0) {
			$isAllLandscape = true;
			$hadColourProblems = false; 
			$hadSizeProblems = false; 
			$userIsScreenshotter = false;
			$isAllPhotoLandscape = count(photoList) > 0 ? true : false;
	
			$text = str_replace($stepsToken, $steps, $text);
			
            foreach ($hybridMediaList as &$media) {
				$video = $media['video'];
				
				if ($video) { //video related validation
					if (!empty($video['width']) && !empty($video['height'])
					&& $video['width'] > $video['height'])
					{
						$sizeParam = WikiVisualTranscoder::VIDEO_LANDSCAPE_WIDTH;
					} else {
						$sizeParam = WikiVisualTranscoder::VIDEO_PORTRAIT_WIDTH;
						// Log first portrait video
						if (!$isAllLandscape) {
							$warning .= "portrait:{$video['name']}\n";
						}
						$isAllLandscape = false;
					}
		
					// Log pixel width issues
					if (!$userIsScreenshotter
					&& !$hadSizeProblems
					&& !empty($video['width'])
					&& $video['width'] < WikiVisualTranscoder::VIDEO_WARNING_MIN_WIDTH)
					{
						$warning .= "size:{$video['width']}px:{$video['name']}\n";
						$hadSizeProblems = true;
					}
				}
				
				$image = $media['photo'];
				if ($image) {
					if (!empty($image['width']) && !empty($image['height'])
					&& $image['width'] > $image['height']) {
						$sizeParam = WikiVisualTranscoder::IMAGE_LANDSCAPE_WIDTH;
					} else {
						$sizeParam = WikiVisualTranscoder::IMAGE_PORTRAIT_WIDTH;
						// Log first portrait image
						if (!$isAllPhotoLandscape) {
							$warning .= "portrait:{$image['name']}\n";
						}
						$isAllPhotoLandscape = false;
					}
					
					// Detect colour profile issues
					if (!$hadColourProblems && !empty($image['filename'])) {
						$exifProfile = WikiPhoto::getExifColourProfile($image['filename']);
						if ($exifProfile && WikiPhoto::isBadWebColourProfile($exifProfile)) {
							$warning .= "colour:$exifProfile:{$image['name']}\n";
							$hadColourProblems = true;
						}
					}
					
					// Log pixel width issues
					if (!$userIsScreenshotter
					&& !$hadSizeProblems
					&& !empty($image['width'])
					&& $image['width'] < WikiVisualTranscoder::WARNING_MIN_WIDTH)
					{
						$warning .= "size:{$image['width']}px:{$image['name']}\n";
						$hadSizeProblems = true;
					}

                    // Log pixel width issues
                    if (!$userIsScreenshotter
                    && !$hadSizeProblems
                    && !empty($image['width'])) {
                        if ($image['width'] < WikiVisualTranscoder::WARNING_MIN_WIDTH) {
                            $warning .= "size:{$image['width']}px:{$image['name']}\n";
                            $hadSizeProblems = true;
                        } else {
                            $maxImgDimen = $image['width'] > $image['height'] ? $image['width'] : $image['height'];
                            if ($maxImgDimen > WikiVisualTranscoder::ERROR_MAX_IMG_DIMEN) {
                                $err .= "size:{$image['width']}px > max size ". WikiVisualTranscoder::ERROR_MAX_IMG_DIMEN ."px:{$image['name']}\n";
                                $hadSizeProblems = true;
                            }
                        }
                    }
				}
			
                self::d("video=$video, image=$image");
				$mediaTag = null;
				if ($video && !$image) { //video only
					$mediaTag = $vidBrTag.'{{whvid|' . $video['mediawikiName'] . '|' . $video['previewMediawikiName'] . '}}';
					$text = str_replace($video['token'], $mediaTag, $text);
				} elseif (!$video && $image) { //image only
					$mediaTag = $imgBrTag.'[[Image:' . $image['mediawikiName'] . '|center|' . $sizeParam . ']]';
					$text = str_replace($image['token'], $mediaTag, $text);
				} elseif ($video && $image) { //hybrid
					$mediaTag = $vidBrTag.'{{whvid|' . $video['mediawikiName'] . '|' . $video['previewMediawikiName'] . 
																	   '|' . $image['mediawikiName'] . '}}';
					$text = str_replace($video['token'], $mediaTag, $text);
				}
			}
		}
	
		// remove certain templates from start of wikitext
		if (!$err) {
			$templates = array('illustrations', 'pictures', 'screenshots', 'stub');
			$text = $this->removeTemplates($text, $templates);
		}
	
		// write wikitext and add/update wikivideo row
		if (!$err) {
			$err = $this->saveArticleText($articleID, $text);
		}

		// try to enlarge the uploaded photos of certain users
		if (!$err) {
			// now we want to ALWAYS enlarge the images for articles with ALL Landscape
			if ($isAllPhotoLandscape) {
				Wikitext::enlargeImages($title, true, AdminEnlargeImages::DEFAULT_CENTER_PIXELS);
			}
		}

// 		if ($err) {
// 			self::dbSetArticleProcessed($articleID, $creator, $err, $warning, $url, 0, $numSteps, 0, self::STATUS_ERROR);
// 		} else {
// 			self::dbSetArticleProcessed($articleID, $creator, '', $warning, $url, count($videoList), $numSteps, $replaced, self::STATUS_COMPLETE);
// 		}
	
		// remove transcoding job db entries and s3 URIs
		//self::removeOldTranscodingJobs($articleID);
	
		$numPhotos = $photoList ? count($photoList) : 0;
		$numVideos = $photoList ? count($videoList) : 0;
		
		self::i("processed wikitext: $creator $articleID $url ". 
		"photos=" . $numPhotos . ", ".
		"videos=" . $numVideos . " $err");
		
		return array($err, $warning, $url, $numSteps, $replaced);
// 		if ($err) {
// 			self::dbSetArticleProcessed($articleID, $creator, $err, $warning, $url, 0, $numSteps, 0, self::STATUS_ERROR);
// 		} else {
// 			self::dbSetArticleProcessed($articleID, $creator, '', $warning, $url, count($videoList), $numSteps, $replaced, self::STATUS_COMPLETE);
// 		}
	}

	private function createStepToken(&$mediaList, &$steps, $tokenPrefix, $stepNum, $i, $m, $substToken) {
		$stepIdx = false;
		if ($mediaList) {
			foreach ($mediaList as $j => $media) {
				if ($media['step'] == $stepNum && $media['sub'] == null) {
					$stepIdx = $j;
					break;
				}
			}
			if ($stepIdx !== false && $substToken !== false) {
				$mediaToken = $tokenPrefix . Misc::genRandomString() . '_' . $stepNum;
		
				if (preg_match('@[\n]+=@m', $m[3])) {
					$steps[$i] = trim($m[1]) . trim(preg_replace('@([\n]+=)@m', $mediaToken . '$1', $m[3])) . "\n";
				} else {
					$steps[$i] = trim($m[1]) . trim($m[3]) . $mediaToken . "\n";
				}
				$mediaList[$stepIdx]['token'] = $mediaToken;
                self::d("createStepToken: mediaList[$stepIdx]['token'] : ". $mediaList[$stepIdx]['token']);
                self::d("createStepToken: steps[$i] : ". $steps[$i]);
			}
		}
		return $stepIdx;
	}
	
	private function createSubStepToken(&$mediaList, &$steps, $tokenPrefix, $stepNum, $subNum, $i, $m, $substToken) {
		$stepIdx = false;
		if ($mediaList) {
			foreach ($mediaList as $j => $media) {
				if ($media['step'] == ($stepNum - 1) ) {
					if ($media['sub'] != null && $media['sub'] == $subNum) {
						$stepIdx = $j;
						break;
					}
				}
			}
			if ($stepIdx !== false && $substToken !== false) {
				$mediaToken = $tokenPrefix . Misc::genRandomString() . '_' . ($stepNum - 1) . "_" . $subNum;
		
				if (preg_match('@[\n]+=@m', $m[3])) {
					$steps[$i] = trim($m[1]) . trim(preg_replace('@([\n]+=)@m', $mediaToken . '$1', $m[3])) . "\n";
				} else {
					$steps[$i] = trim($m[1]) . trim($m[3]) . $mediaToken . "\n";
				}
				$mediaList[$stepIdx]['token'] = $mediaToken;
                self::d("createSubStepToken: mediaList[$stepIdx]['token'] : ". $mediaList[$stepIdx]['token']);
                self::d("createSubStepToken: steps[$i] : ". $steps[$i]);
			}
		}
		return $stepIdx;
	}
		
	private function getStepSubStepIdx($stepNum, $subNum) {
		return $stepNum . '_' . $subNum;
	}
	
	//abstract public function addWikiHowVideo($articleID, &$video);
    /**
     * Add a new video file into the mediawiki infrastructure so that it can
     * be accessed as {{whvid|filename.mp4|Preview.jpg}}
     */
    public function addWikiHowVideo($articleId, &$video) {
    
        // find name for video; change filename to Filename 1.jpg if
        // Filename.jpg already existed
        $regexp = '/[^' . Title::legalChars() . ']+/';
        $first = preg_replace($regexp, '', $video['first']);
        // Let's also remove " and ' since s3 doesn't seem to like
        $first = preg_replace('/["\']+/', '', $first);
        $ext = $video['ext'];
        $newName = $first . '.' . $ext;
        $i = 1;
        do {
            if (!WikiVideo::fileExists($newName)) break;
            $newName = $first . ' Version ' . ++$i . '.' . $ext;
        } while ($i <= 1000);
    
        // Move the file from one s3 bucket to another
        $ret = WikiVideo::copyFileToProd(WikiVisualTranscoder::AWS_TRANSCODING_OUT_BUCKET, $video['aws_uri_out'], $newName);
        if ($ret['error']) return $ret['error'];
    
        // instruct later processing about which mediawiki name was used
        $video['mediawikiName'] = $newName;
    
        // Add preview image
        $img = $video;
        $img['ext'] = 'jpg';
        $err = Mp4Transcoder::addMediawikiImage($articleId, $img);
        if ($err) {
            return 'Unable to add preview image: ' . $err;
        } else {
            $video['previewMediawikiName'] = $img['mediawikiName'];
            // Cleanup temporary preview image
            if (!empty($img['filename'])) {
                $rmCmd = "rm " . $img['filename'];
                system($rmCmd);
            }
        }
    
        self::d(">>> addWikiHowVideo: video['mediawikiName']=". $video['mediawikiName'] .", video['previewMediawikiName']=". $video['previewMediawikiName']);
        // Keep a log of where videos were uploaded in wikivideo_video_names table
        $dbw = WikiVisualTranscoder::getDB('write');
        $vidname = $articleID . '/' . $video['name'];
        $sql = 'INSERT INTO wikivisual_vid_names SET filename=' . $dbw->addQuotes($vidname) . ', wikiname=' . $dbw->addQuotes($video['mediawikiName']);
        $dbw->query($sql, __METHOD__);
    
        return '';
    }

	
	/**
	 * Place a set of videos into an article's wikitext.
	 */
	private function placeHybridMediaInSteps($articleID, $title, &$videos, &$images, &$text, &$stepsText, &$numSteps, &$replaced, $userIsScreenshotter) {

        $err = '';
		$hybridMediaList = array();

		// Count all images + videos that will be replaced
		$countReplaced = preg_match_all('@(\{\{whvid\|)@im', $stepsText, $throwAway);
		$countReplaced += preg_match_all('@(\[\[Image:|\{\{largeimage)@im', $text, $throwAway);
		$countReplaced += preg_match_all('@(\[\[Image:|\{\{largeimage)@im', $stepsText, $throwAway);
		//$countReplaced += preg_match_all('@(\{\{Video:|\{\{largevideo)@im', $stepsText, $throwAway);
	
		//start by deleting all videos that already exist in the article.
		//first in the main text
		$text = preg_replace('@\[\[Image:[^\]]*\]\]@im', '', $text);
		$text = preg_replace('@\{\{largeimage\|[^\}]*\}\}@im', '', $text);
		//now in the steps too
		$stepsText = preg_replace('@'. self::BRTAG .'\{\{whvid\|[^\}]*\}\}@im', '', $stepsText);
		$stepsText = preg_replace('@'. self::BRTAG .'\[\[Image:[^\]]*\]\]@im', '', $stepsText);
		$stepsText = preg_replace('@'. self::BRTAG .'\{\{largeimage\|[^\}]*\}\}@im', "", $stepsText);
		$stepsText = preg_replace('@\{\{whvid\|[^\}]*\}\}@im', '', $stepsText);
		$stepsText = preg_replace('@\[\[Image:[^\]]*\]\]@im', '', $stepsText);
		$stepsText = preg_replace('@\{\{largeimage\|[^\}]*\}\}@im', "", $stepsText);
		
		// process the list of media to make sure we can understand all filenames
		list($err, $hasFinalStepVid) = $this->addExtraVideoInfo($title, $videos);
		if (!$err && $images != null && count($images > 0)) {
			list($err, $hasFinalStepImg) = $this->addExtraPhotoInfo($title, $images);
		}
		self::d("Final step Vid=". (int)$hasFinalStepVid .", Img=". (int)$hasFinalStepImg);
		$hasFinalStep = $hasFinalStepImg === true || $hasFinalStepVid === true;
		
		if ($err) {
			self::d("Got error from addExtraPhotoInfo: [$err]");
			return array($err, null);
		}
	
		// split steps based on ^# then add the '#' character back on
		$steps = preg_split('@^\s*#@m', rtrim($stepsText) ."\n");
		for ($i = 1; $i < count($steps); $i++) {
			$steps[$i] = "#" . $steps[$i];
			if (self::REMOVE_BRTAG_FROM_END_OF_STEP === true) {
				$steps[$i] = preg_replace('@(<br> *)+$@im', '', $steps[$i]);
			}
		}

		if ($hasFinalStep === true) {
			//also remove last step if it contains only 'Finished'
			$tstep = array_pop($steps);
			if (strtolower(WikiVisualTranscoder::FINISHED) !=  
				strtolower(preg_replace("/[^a-zA-Z0-9]+/", "", strip_tags(html_entity_decode($tstep))))) {
				$steps[] = $tstep; 
			}
		}
				
		// place media in steps

		$stepNum = 1;
		for ($i = 1; $i < count($steps); $i++) {
			if (preg_match('@^(([#*]|\s)+)((.|\n)*)@m', $steps[$i], $m)) {
				$stepIdxVid = false;
				$stepIdxPhoto = false;
				$stripped = preg_replace('@\s+@', '', $m[1]);
				$m[1] = trim($m[1]);
				$levels = strlen($stripped);
				if ($levels == 1) {
					$subNum = 0;

					$stepIdxVid = $this->createStepToken($videos, $steps, 'VID_', $stepNum, $i, $m, true);
					$stepIdxPhoto = $this->createStepToken($images, $steps, 'IMG_', $stepNum, $i, $m, $stepIdxVid === false ? true : false);
					$stepNum++;
				} elseif ($levels == 2) {
					//we're in a bullet, check to see if we have a
					//video for this substep
					$subNum++;

					$stepIdxVid = $this->createSubStepToken($videos, $steps, 'VID_', $stepNum, $subNum, $i, $m, true);
					$stepIdxPhoto = $this->createSubStepToken($images, $steps, 'IMG_', $stepNum, $subNum, $i, $m, $stepIdxVid === false ? true : false);
				}
                self::d("videos[$stepIdxVid]=". $videos[$stepIdxVid] .", images[$stepIdxPhoto]=". $images[$stepIdxPhoto]);
				$hybridMedia = array();
				if ($stepIdxVid !== false) $hybridMedia['video'] = &$videos[$stepIdxVid];
				if ($stepIdxPhoto !== false) $hybridMedia['photo'] = &$images[$stepIdxPhoto];
				$hybridMediaList[] = $hybridMedia;
			} else
                self::d("No match preg_match('@^(([#*]|\s)+)((.|\n)*)@m' with step [". $steps[$i]."]");
		}
		$numSteps = $stepNum - 1;
	
		//take care of -final step if any
		if (!$err && $hasFinalStep !== false) {
			$finalStepText = "#" . WikiVisualTranscoder::FINISHED .'.';
			$steps[] = $finalStepText;
			$finalStepIdx = array_search($finalStepText, $steps);
			
			if (preg_match('@^(([#*]|\s)+)((.|\n)*)@m', $finalStepText, $m)) {
				$stepIdxVid = false;
				$stepIdxPhoto = false;
				$stepIdxVid = $this->createStepToken($videos, $steps, 'VID_', WikiVisualTranscoder::FINALSTR, $finalStepIdx, $m, true);
				$stepIdxPhoto = $this->createStepToken($images, $steps, 'IMG_', WikiVisualTranscoder::FINALSTR, $finalStepIdx, $m, $stepIdxVid === false ? true : false);
				$hybridMedia = array();
				if ($stepIdxVid !== false) $hybridMedia['video'] = &$videos[$stepIdxVid];
				if ($stepIdxPhoto !== false) $hybridMedia['photo'] = &$images[$stepIdxPhoto];
				$hybridMediaList[] = $hybridMedia;
			}
		}
		
		// were we able to place all videos in the article?
		$notPlaced = array();
		$placed = array(); //when step contains hybrid then only vid gets places. 
						   //use this to avoid failure while checking images
		foreach ($videos as $video) {
			if (!isset($video['token'])) {
				$notPlaced[] = $video['name'];
			} else {
				$fnames = explode('.',$video['name']);
				$placed[$fnames[0]] = $video['name']; //get filename before 1st '.' as key
			}
		}

		// were we able to place all images in the article?
		foreach ($images as $image) {
			$fnames = explode('.', $image['name']);
			if (!isset($image['token']) && 
				!array_key_exists($fnames[0], $placed)) {
				$notPlaced[] = $image['name'];
			}
		}
		
		if ($notPlaced) {
			$err = 'Unable to place media in the wikitext: ' . join(', ', $notPlaced);
		}
	
		// add all these videos to the wikihow mediawiki repos
		if (!$err) {
			foreach ($videos as &$vid) {
				$error = $this->addWikiHowVideo($articleID, $vid);
				if (strlen($error)) {
					$err = 'Unable to add new video file ' . $vid['name'] . ' to wikiHow: ' . $error;
				} else {
					$vid['width'] = WikiVisualTranscoder::DEFAULT_VIDEO_WIDTH;
					$vid['height'] = WikiVisualTranscoder::DEFAULT_VIDEO_HEIGHT;
				}
			}
		
            self::d(">>>>>>>> count(\$images)". count($images));
			if (!$err && $images && count($images) > 0) {
				$err = ImageTranscoder::addAllMediaWikiImages($articleID, $images, $userIsScreenshotter);
			}
	
			if (!$err) {
				$stepsText = join('', $steps);
				if (count($steps) && trim($steps[0]) == '') {
					$stepsText = "\n" . $stepsText;
				}
			}
		}
	
		$replaced = !$err ? $countReplaced : 0;
	
		return array($err, &$hybridMediaList);
	}
	
	// process the list of images to make sure we can understand all filenames
	public function addExtraPhotoInfo($title, &$images) {
		$hasFinalStep = false;
		foreach ($images as &$img) {
			if (!preg_match('@^((.*)-\s*)?([0-9b]+|final)\.(' . join('|', WikiVisualTranscoder::$imgExts) . ')$@i', $img['name'], $m)) {
				$err .= 'Filename not in format Name-1.jpg: ' . $img['name'] . '. ';
                self::d("addExtraPhotoInfo: Filename not in format Name-1.jpg: " . $img['name'] . '. ');
			} else {
                self::d("addExtraPhotoInfo: preg_match m1=$m[1], m2=$m[2], m3=$m[3], m4=$m[4]");
				$hasFinalStep = $this->addExtraMediaInfo($title, $img, $m);			
			}
		}
	    return array($err, $hasFinalStep);	
	}

	// process the list of videos to make sure we can understand all filenames
	public function addExtraVideoInfo($title, &$videos) {
		$hasFinalStep = false;
		foreach ($videos as &$vid) {
			$vid['name'] = explode("/", $vid['aws_uri_out']);
			$vid['name'] = end($vid['name']);

			if (!preg_match('@^((.*)-\s*)?([0-9b]+|final)\.(360p\.' . join('|', WikiVisualTranscoder::$videoExts) . ')$@i', $vid['name'], $m)) {
				$err .= "Filename not in format Name-1.mp4: " . $vid['name'] . ". ";
			} else {
				$hasFinalStep = $this->addExtraMediaInfo($title, $vid, $m);
			}
		}
	    return array($err, $hasFinalStep);	
	}
	
	private function addExtraMediaInfo($title, &$media, $m) {
		$hasFinalStep = false;
		// new: just discard $m[2]
		$media['first'] = $title->getText();	//future video title
		$media['sub'] = null;					//bullet number
		$media['ext'] = strtolower($m[4]);	//video extension
		
		$bulletpos = strrpos($m[3], "b");
		if ($bulletpos !== false) {
			$media['step'] = substr($m[3], 0, $bulletpos); //step number
			$media['sub'] = substr($m[3], $bulletpos + 1);
		} else {
			$media['step'] = strtolower($m[3]);  //step number
		}
		
		
		if ($media['step'] == WikiVisualTranscoder::FINALSTR) {
			$media['first'] .= ' '.WikiVisualTranscoder::FINALSTRLBL;
			$hasFinalStep = true;
		} else {
			$media['first'] .= ' Step ' . $media['step'];
			if ($media['sub'] !== null) {
				$media['first'] .= "Bullet" . $media['sub'];
			}
		}
		
		return $hasFinalStep;
	}
}
