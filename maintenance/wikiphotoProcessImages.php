<?php
/**
 * Runs every hour to process any newly uploaded images.  Adds images to the
 * articles identified by the article ID.
 *
 * Note: this script should only be run by wikiphoto-process-images-hourly.sh.
 *   It needs to have the correct setuid user so that /var/www/images_en
 *   files are created with the correct permissions.
 * 
 * Usage: php wikiphotoProcessImages.php
 */

/*
 * data schema:
 *
CREATE TABLE wikiphoto_article_status (
  article_id INT UNSIGNED PRIMARY KEY,
  creator VARCHAR(32) NOT NULL default '',
  processed VARCHAR(14) NOT NULL default '',
  reviewed TINYINT UNSIGNED NOT NULL default 0,
  retry TINYINT UNSIGNED NOT NULL default 0,
  error TEXT NOT NULL,
  warning TEXT NOT NULL,
  url VARCHAR(255) NOT NULL default '',
  images INT UNSIGNED NOT NULL default 0,
  replaced INT UNSIGNED NOT NULL default 0,
  steps INT UNSIGNED NOT NULL default 0,
);

CREATE TABLE wikiphoto_image_names (
  filename VARCHAR(255) NOT NULL,
  wikiname VARCHAR(255) NOT NULL
);

CREATE TABLE images_sha1 (
    is_sha1 varchar(255) not null,
    is_page_id int unsigned not null,
    is_page_title varchar(255) not null,
    is_updated varchar(14) not null,
    PRIMARY KEY(is_sha1),
    INDEX(is_page_id)
);
 *
 */

require_once('commandLine.inc');

global $IP;
require_once("$IP/extensions/wikihow/common/S3.php");
require_once("$IP/extensions/wikihow/DatabaseHelper.class.php");

class WikiPhotoProcess {

	const PHOTO_LICENSE = 'cc-by-sa-nc-3.0-self';
	const SCREENSHOT_LICENSE = 'Screenshot';
	const PHOTO_USER = 'Wikiphoto';
	const IMAGES_DIR = '/usr/local/pfn/images';
	const AWS_BUCKET = 'wikiphoto';
	//const AWS_BUCKET = 'wikiphoto-test';
	const AWS_BACKUP_BUCKET = 'wikiphoto-backup';
	const DEFAULT_STAGING_DIR = '/usr/local/wikihow/wikiphoto';
	const IMAGE_PORTRAIT_WIDTH = '220px';
	const IMAGE_LANDSCAPE_WIDTH = '300px';
	const WARNING_MIN_WIDTH = 3200;
	const REPROCESS_EPOCH = 1359757162; // Fri Feb  1 14:19:26 PST 2013

	static $debugArticleID = '',
		$stepsMsg,
		$imageExts = array('png', 'jpg', 'jpeg'),
		$excludeUsers = array('old', 'backup'),
		$enlargePhotoUsers = array(),
		$stagingDir = '',
		$excludeArticles = array(
			57203, 1251223, 354106,
		);
	
	/**
	 * Remove the Steps section from an article, leaving a placeholder
	 */
	private static function cutStepsSection($articleText) {
		$out = array();
		$token = Misc::genRandomString();
		$steps = '';
		$found = false;

		// look for the steps section, cut it
		$newText = preg_replace_callback(
			'@^(\s*==\s*' . self::$stepsMsg . '\s*==\s*)$((.|\n)*)^(\s*==[^=])@mU',
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
				'@^(\s*==\s*' . self::$stepsMsg . '\s*==\s*)$((.|\n)*)(?!^\s*==[^=])@m',
				function ($m) use ($token, &$steps, &$found) {
					$steps = $m[2];
					$newText = $m[1] . $token;
					$found = true;
					return $newText;
				},
				$articleText
			);
		}

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
	private static function removeTemplates($wikitext, $templates) {
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
	 * Check the database about whether an article needs processing
	 */
	/*
	private static function dbImagesNeedProcessing($articleID) {
		$dbr = self::getDB('read');
		$sql = 'SELECT processed, retry FROM wikiphoto_article_status WHERE article_id=' . $dbr->addQuotes($articleID);
		$res = $dbr->query($sql, __METHOD__);
		$row = $dbr->fetchRow($res);
		if (!$row || !$row['processed'] || $row['retry']) {
			return true;
		} else {
			return false;
		}
	}
	*/

	/**
	 * Set an article as processed in the database
	 */
	private static function dbSetArticleProcessed($articleID, $creator, $error, $warning, $url, $numImages, $numSteps, $replaced) {
		$dbw = self::getDB('write');
		if (!$warning) $warning = '';
		$sql = 'REPLACE INTO wikiphoto_article_status SET 
			article_id=' . $dbw->addQuotes($articleID) . ', 
			processed=' . $dbw->addQuotes(wfTimestampNow(TS_MW)) . ', 
			replaced=' . $dbw->addQuotes($replaced) . ', 
			retry=0, 
			error=' . $dbw->addQuotes($error) . ', 
			warning=' . $dbw->addQuotes($warning) . ', 
			url=' . $dbw->addQuotes($url) . ', 
			images=' . $dbw->addQuotes($numImages) . ', 
			creator=' . $dbw->addQuotes($creator) . ', 
			steps=' . $dbw->addQuotes($numSteps);
		$dbw->query($sql, __METHOD__);
	}

	/**
	 * Place a set of images into an article's wikitext.
	 */
	private static function placeImagesInSteps($articleID, $title, &$images, &$text, &$stepsText, &$numSteps, &$replaced, $userIsScreenshotter) {
		$errs = '';
		
		// Count all images that will be replaced
		$countReplaced = preg_match_all('@(\[\[Image:|\{\{largeimage)@im', $text, $throwAway);
		$countReplaced += preg_match_all('@(\[\[Image:|\{\{largeimage)@im', $stepsText, $throwAway);

		//start by deleting all images that already exist in the article.
		//first in the main text
		$text = preg_replace('@\[\[Image:[^\]]*\]\]@im', '', $text);
		$text = preg_replace('@\{\{largeimage\|[^\}]*\}\}@im', '', $text);
		//now in the steps too
		$stepsText = preg_replace('@\[\[Image:[^\]]*\]\]@im', '', $stepsText);
		$stepsText = preg_replace('@\{\{largeimage\|[^\}]*\}\}@im', "", $stepsText);
		
		// process the list of images to make sure we can understand all filenames
		foreach ($images as &$img) {
			if (!preg_match('@^((.*)-\s*)?([0-9b]+|intro)\.(' . join('|', self::$imageExts) . ')$@i', $img['name'], $m)) {
				$errs .= 'Filename not in format Name-1.jpg: ' . $img['name'] . '. ';
			} else {
				// new: just discard $m[2]
				$img['first'] = $title->getText();	//future image title
				$img['sub'] = null;					//bullet number
				$img['ext'] = strtolower($m[4]);	//image extension
				
				$bulletpos = strrpos($m[3], "b");
				if($bulletpos !== false) {
					$img['step'] = substr($m[3], 0, $bulletpos); //step number
					$img['sub'] = substr($m[3], $bulletpos + 1); 
				}
				else
					$img['step'] = strtolower($m[3]);  //step number
				
				                

				if ($img['step'] == 'intro') {
					$img['first'] .= ' Intro';
				} else {
					$img['first'] .= ' Step ' . $img['step'];
					if($img['sub'] !== null) {
						$img['first'] .= "Bullet" . $img['sub'];
					}
				}
			}
		}
		
		// split steps based on ^# then add the '#' character back on
		$steps = preg_split('@^\s*#@m', $stepsText);
		for ($i = 1; $i < count($steps); $i++) {
			$steps[$i] = "#" . $steps[$i];
		}

		// place images in steps
		$stepNum = 1;
		for ($i = 1; $i < count($steps); $i++) {
			if (preg_match('@^(([#*]|\s)+)((.|\n)*)@m', $steps[$i], $m)) {
				$stripped = preg_replace('@\s+@', '', $m[1]);
				$levels = strlen($stripped);
				if ($levels == 1) {
					$subNum = 0;
					$stepIdx = false;
					foreach ($images as $j => $image) {
						if ($image['step'] == $stepNum && $image['sub'] == null) {
							$stepIdx = $j;
							break;
						}
					}
					if ($stepIdx !== false) {
						$imgToken = 'IMG_' . Misc::genRandomString() . '_' . $stepNum;
						
						$steps[$i] = $m[1] . $imgToken . $m[3];
						$images[$stepIdx]['token'] = $imgToken;
					}
					$stepNum++;
				}
				elseif ($levels == 2) {
					//we're in a bullet, check to see if we have a
					//image for this substep
					$subNum++;
					$stepIdx = false;
					foreach ($images as $j => $image) {
						if ($image['step'] == ($stepNum - 1) ) {
							if($image['sub'] != null && $image['sub'] == $subNum) {
								$stepIdx = $j;
								break;
							}
						}
					}
					if ($stepIdx !== false) {
						$imgToken = 'IMG_' . Misc::genRandomString() . '_' . ($stepNum - 1) . "_" . $subNum;
						
						$steps[$i] = $m[1] . $imgToken . $m[3];
						$images[$stepIdx]['token'] = $imgToken;
					}
				}
			}
		}
		$numSteps = $stepNum - 1;

		// try to place intro image in article, if there is one
		if (!$err) {
			$introIdx = false;
			foreach ($images as $i => $image) {
				if ($image['step'] == 'intro') {
					$introIdx = $i;
				}
			}

			// we have an intro image to place ...
			if ($introIdx !== false) {
				// We have problems with Segmentation faults in this regular
				// expression if this setting is at the default 100k
				$former_lim = ini_set('pcre.recursion_limit', 15000);

				// remove existing image and place new image after templates
				if (preg_match('@^((\s|\n)*({{[^}]*}})?(\s|\n)*)(\[\[Image:[^\]]*\]\])?((.|\n)*)$@m', $text, $m)) {
					$start = $m[1];
					$end = $m[6];
					$token = 'IMG_' . Misc::genRandomString() . '_intro';
					$newText = $start . $token . $end;
					$images[$introIdx]['token'] = $token;
				} else {
					$err = 'Unable to insert intro image';
				}

				// reset value
				ini_set('pcre.recursion_limit', $former_lim);
			} else {
				$newText = $text;
			}
		}

		// were we able to place all images in the article?
		$notPlaced = array();
		foreach ($images as $image) {
			if (!isset($image['token'])) {
				$notPlaced[] = $image['name'];
			}
		}
		if ($notPlaced) {
			$err = 'Unable to place in the wikitext: ' . join(', ', $notPlaced);
		}

		// add all these images to the wikihow mediawiki repos
		if (!$err) {
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

			if (!$err) {
				$stepsText = join('', $steps);
				if (count($steps) && trim($steps[0]) == '') {
					$stepsText = "\n" . $stepsText;
				}
				$text = $newText;
			}
		}

		$replaced = !$err ? $countReplaced : 0;

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
			$comment = '{{' . self::PHOTO_LICENSE . '}}';
		} else {
			$comment = '{{' . self::SCREENSHOT_LICENSE . '}}';
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
		$dbw = self::getDB('write');
		$imgname = $articleID . '/' . $image['name'];
		$sql = 'INSERT INTO wikiphoto_image_names SET filename=' . $dbw->addQuotes($imgname) . ', wikiname=' . $dbw->addQuotes($image['mediawikiName']);
		$dbw->query($sql, __METHOD__);

		return true;
	}

	/**
	 * Process all images for an article from the wikiphoto upload dir
	 */
	private static function processImages($articleID, $creator, $imageList, $warning) {
		$err = '';
		$numSteps = 0;
		$replaced = 0;
		
		// load article
		list($text, $url, $title) = self::getArticleDetails($articleID);
		if (!$text || !$title) $err = 'Could not find article ID ' . $articleID;

		// parse out steps section replacing it with a token, leaving 
		// the above and below wikitext intact
		if (!$err) {
			list($text, $steps, $stepsToken) = self::cutStepsSection($text);
			if (!$stepsToken) {
				if (preg_match('@^(\s|\n)*#redirect@i', $text)) {
					$err = 'Could not parse Steps section out of article -- article text is #REDIRECT';
				} else {
					$err = 'Could not parse Steps section out of article';
				}
			}
		}

		// check if user is a known screenshot uploader
		if (!$err) {
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
		}

		// try to place images into wikitext, using tokens as placeholders.
		if (!$err) {
			$err = self::placeImagesInSteps($articleID, $title, $imageList, $text, $steps, $numSteps, $replaced, $userIsScreenshotter);
		}

		// detect if no photos were to be processed
		if (!$err) {
			if (count($imageList) == 0) {
				$err = 'No photos to process';
			}
		}

		// replace the tokens within the image tag
		if (!$err) {
			$isAllLandscape = true;
			$hadColourProblems = false;
			$hadSizeProblems = false;

			$text = str_replace($stepsToken, $steps, $text);
			foreach ($imageList as $image) {
				if (!empty($image['width']) && !empty($image['height']) 
					&& $image['width'] > $image['height'])
				{
					$sizeParam = self::IMAGE_LANDSCAPE_WIDTH;
				} else {
					$sizeParam = self::IMAGE_PORTRAIT_WIDTH;
					// Log first portrait image
					if (!$isAllLandscape) {
						$warning .= "portrait:{$image['name']}\n";
					}
					$isAllLandscape = false;
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
					&& $image['width'] < self::WARNING_MIN_WIDTH)
				{
					$warning .= "size:{$image['width']}px:{$image['name']}\n";
					$hadSizeProblems = true;
				}

				$imageTag = '[[Image:' . $image['mediawikiName'] . '|right|' . $sizeParam . ']]';
				$text = str_replace($image['token'], $imageTag, $text);
			}
		}

		// remove certain templates from start of wikitext
		if (!$err) {
			$templates = array('illustrations', 'pictures', 'screenshots', 'stub');
			$text = self::removeTemplates($text, $templates);
		}

		// write wikitext and add/update wikiphoto row
		if (!$err) {
			$err = self::saveArticleText($articleID, $text);
		}

		// try to enlarge the uploaded photos of certain users
		if (!$err) {
			// now we want to ALWAYS enlarge the images for articles with ALL Landscape
			if ($isAllLandscape) {
				Wikitext::enlargeImages($title, true, AdminEnlargeImages::DEFAULT_CENTER_PIXELS);
			}
		}

		if ($err) {
			self::dbSetArticleProcessed($articleID, $creator, $err, $warning, $url, 0, $numSteps, 0);
		} else {
			self::dbSetArticleProcessed($articleID, $creator, '', $warning, $url, count($imageList), $numSteps, $replaced);
		}

		return array($err, $title);
	}

	/**
	 * Grab the status of all articles processed.
	 */
	private static function dbGetArticlesUpdatedAll() {
		$articles = array();
		$dbr = self::getDB('read');

		$res = DatabaseHelper::batchSelect('wikiphoto_article_status',
			array('article_id', 'processed', 'error', 'retry'),
			'',
			__METHOD__,
			array(),
			DatabaseHelper::DEFAULT_BATCH_SIZE,
			$dbr);

		foreach ($res as $row) {
			// convert MW timestamp to unix timestamp
			$row->processed = wfTimestamp(TS_UNIX, $row->processed);
			$articles[ $row->article_id ] = (array)$row;
		}
		return $articles;
	}

	/**
	 * Flag article as needing retry
	 */
	/*private static function dbFlagNeedsRetry($id) {
		$dbw = self::getDB('write');
		$dbw->update('wikiphoto_article_status', array('needs_retry' => 1), array('article_id' => $id), __METHOD__);
	}*/

	private static function timeDiffStr($t1, $t2) {
		$diff = abs($t1 - $t2);
		if ($diff >= 2 * 24 * 60 * 60) {
			$days = floor($diff / (24 * 60 * 60));
			return "$days days";
		} else {
			$hours = $diff / (60 * 60);
			return sprintf('%.2f hours', $hours);
		}
	}

	/**
	 * List articles on S3
	 */
	private static function getS3Articles(&$s3, $bucket) {
		$list = $s3->getBucket($bucket);

		// compile all the articles into a list of files/zips from s3
		$articles = array();
		foreach ($list as $path => $details) {
			// match string: username/1234.zip
			if (!preg_match('@^([a-z][-._0-9a-z]{0,30})/([0-9]+)\.zip$@i', $path, $m))
			{
				continue;
			}

			list(, $user, $id) = $m;
			$id = intval($id);
			if (!$id) continue;

			if (in_array($user, self::$excludeUsers) // don't process anything in excluded people
				|| preg_match('@^[0-9]+$@', $user)) // don't allow usernames that are all digits
			{
				continue;
			}

			// process the list of images files into a list of articles
			if (!isset($articles[$id])) {
				$articles[$id] = array(
					'user' => $user,
					'time' => $details['time'],
					'files' => array(),
					'zip' => 1,
				);
			}

			if ($user != $articles[$id]['user']) {
				$diffStr = self::timeDiffStr($articles[$id]['time'], $details['time']);
				if ($articles[$id]['time'] < $details['time']) {
					$warnUser = $articles[$id]['user'];
					$articles[$id]['time'] = $details['time'];
					$articles[$id]['user'] = $user;
				} else {
					$warnUser = $user;
				}
				$articles[$id]['warning'] = "Reprocessing since user $warnUser uploaded $diffStr earlier\n";
			}

		}

		return $articles;
	}

	/**
 	 * Cleanup and remove all old copies of photos.  If there's a zip file and
	 * a folder, delete the folder.
	 */
	private static function doS3Cleanup() {
		$s3 = new S3(WH_AWS_WIKIPHOTO_ACCESS_KEY, WH_AWS_WIKIPHOTO_SECRET_KEY);
		$src = self::getS3Articles($s3, self::AWS_BUCKET);
		foreach ($src as $id => $details) {
			if ($details['zip'] && $details['files']) {
				$uri = $details['user'] . '/' . $id . '.zip';
				$count = count($details['files']);
				if ($count <= 1) {
					$files = join(',', $details['files']);
					print "not enough files ($count) to delete $uri: $files\n";
				} else {
					print "deleting $uri\n";
					$s3->deleteObject(self::AWS_BUCKET, $uri);
				}
			}
		}
	}

	/**
	 * Copy all our S3
	 */
	private static function doS3Backup() {
		$s3 = new S3(WH_AWS_WIKIPHOTO_ACCESS_KEY, WH_AWS_WIKIPHOTO_SECRET_KEY);
		$s3bkup = new S3(WH_AWS_BACKUP_ACCESS_KEY, WH_AWS_BACKUP_SECRET_KEY);

// for debugging to make it faster to re-run!
//$file = '/tmp/dbg';
//if (!file_exists($file)) {
		$src = self::getS3Articles($s3, self::AWS_BUCKET);
		$dest = self::getS3Articles($s3bkup, self::AWS_BACKUP_BUCKET);
//$out = serialize(array($src, $dest));
//file_put_contents($file, $out);
//} else {
//list($src, $dest) = unserialize(file_get_contents($file));
//}

		foreach ($src as $id => $srcDetails) {
			$destDetails = @$dest[$id];
			$zipFile = $id . '.zip';
			$destZip = $srcDetails['user'] . '/' . $zipFile;

			// if the dest file exists and the source file is older, we don't
			// need to backup again
			if (@$destDetails['time']
				&& $srcDetails['time'] < $destDetails['time']) 
			{
				continue;
			}

			// if we can't read the source for some reason (ie, multiple
			// versions of the same article from different users), we don't
			// try to sync it
			if ($srcDetails['err']) {
				continue;
			}

			// skip empty directories
			if (!$srcDetails['zip'] && !$srcDetails['files']) {
				continue;
			}

			if ($srcDetails['zip']) {
				$prefix = $srcDetails['user'] . '/';
				$files = array($zipFile);
				list($err, $stageDir) = self::pullFiles($id, $s3, $prefix, $files);
			}
			elseif ($srcDetails['files']) { // pull files into staging area
				$prefix = $srcDetails['user'] . '/' . $id . '/';
				list($err, $stageDir) = self::pullFiles($id, $s3, $prefix, $srcDetails['files']);
				if (!$err) {
					echo "zipping $zipFile...";
					$err = self::zip($stageDir, $zipFile);
					if (!$err && filesize($stageDir . '/' . $zipFile) <= 0) {
						$err = "could not create zip $zipFile";
					}
				}
			}

			if (!$err) {
				$err = self::postFile($s3bkup, $stageDir . '/' . $zipFile, $destZip);
				echo "uploaded $stageDir/$zipFile to $destZip\n";
			} else {
				echo "error uploading $destZip: $err\n";
			}

			if ($stageDir) {
				self::safeCleanupDir($stageDir);
			}
		}
	}

	/**
	 * Process images on S3 instead of from the images web server dir
	 */
	private static function processS3Images() {
		$s3 = new S3(WH_AWS_WIKIPHOTO_ACCESS_KEY, WH_AWS_WIKIPHOTO_SECRET_KEY);

//$file = '/tmp/whp';
//if (!file_exists($file)) {
		$articles = self::getS3Articles($s3, self::AWS_BUCKET);
		$processed = self::dbGetArticlesUpdatedAll();
//$out = yaml_emit(array($articles, $processed));
//file_put_contents($file, $out);
//} else {
//list($articles, $processed) = yaml_parse(file_get_contents($file));
//}

		// process all articles
		foreach ($articles as $id => $details) {
			$debug = self::$debugArticleID;
			if ($debug && $debug != $id) continue;

			if (@$details['err']) {
				if (!$processed[$id]) {
					self::dbSetArticleProcessed($id, $details['user'], $details['err'], '', '', 0, 0, 0);
				}
				continue;
			}

			// if article needs to be processed again because new files were
			// uploaded, but article has already been processed, we should
			// just flag as a retry attempt
			if (!$debug
				&& isset($processed[$id])
				&& !$processed[$id]['retry']
				&& $processed[$id]['processed'] < $details['time'])
			{
				if ($details['time'] >= self::REPROCESS_EPOCH) {
					$processed[$id]['retry'] = 1;
					$processed[$id]['error'] = '';
				} else {
					// don't reprocess stuff from before a certain point in time
					continue;
				}
			}
			
			// if this article was already processed, and nothing about its
			// images has changes, and it's not set to be retried, don't
			// process it again
			if (!$debug
				&& isset($processed[$id])
				&& !$processed[$id]['retry']
				&& $processed[$id]['processed'] > $details['time'])
			{
				continue;
			}

			// if article is not on Wikiphoto article exclude list
			if (WikiPhoto::checkExcludeList($id)) {
				$err = 'Article was found on Wikiphoto EXCLUDE list';
				self::dbSetArticleProcessed($id, $details['user'], $err, '', '', 0, 0, 0);
				continue;
			}

			// pull zip file into staging area
			$stageDir = '';
			$imageList = array();
			if ($details['zip']) {
				$prefix = $details['user'] . '/';
				$zipFile = $id . '.zip';
				$files = array($zipFile);
				list($err, $stageDir) = self::pullFiles($id, $s3, $prefix, $files);
				if (!$err) {
					list($err, $files) = self::unzip($stageDir, $zipFile);
				}
				if (!$err) {
					foreach ($files as $file) {
						$imageList[] = array('name' => basename($file), 'filename' => $file);
					}
				}
			} else { // no zip -- ignore
				continue;
			}

			if (!$err && in_array($id, self::$excludeArticles)) {
				$err = 'Forced skipping this article because there was an repeated error when processing it';
			}

			if (!$err) {
				$warning = @$details['warning'];
				list($err, $title) = self::processImages($id, $details['user'], $imageList, $warning);
			} else {
				self::dbSetArticleProcessed($id, $details['user'], $err, '', '', 0, 0, 0);
			}

			if ($stageDir) {
				self::safeCleanupDir($stageDir);
			}

			$titleStr = ($title ? ' (' . $title->getText() . ')' : '');
			$errStr = $err ? ' err=' . $err : '';
			$imageCount = count($imageList);
			print date('Y/M/d H:i') . " processed: {$details['user']}/$id$titleStr images=$imageCount$errStr\n";
		}
	}

	/**
	 * Upload a file to S3
	 */
	private static function postFile(&$s3, $file, $uri) {
		$err = '';
		$ret = $s3->putObject(array('file' => $file), self::AWS_BACKUP_BUCKET, $uri);
		if (!$ret) {
			$err = "unable to upload $file to S3";
		}
		return $err;
	}

	/**
	 * Download files from S3
	 */
	private static function pullFiles($id, &$s3, $prefix, &$files) {
		$err = '';
		$dir = self::$stagingDir . '/' . $id . '-' . mt_rand();
		$ret = mkdir($dir);
		if (!$ret) {
			$err = 'unable to create dir: ' . $dir;
			return array($err, '');
		}

		foreach ($files as &$file) {
			$aws_file = $prefix . $file;
			$file = preg_replace('@/@', '-', $file);
			$local_file = $dir . '/' . $file;
			$ret = $s3->getObject(self::AWS_BUCKET, $aws_file, $local_file);
			if (!$ret || $ret->error) {
				$err = "problem retrieving file from S3: s3://" . self::AWS_BUCKET . "/$aws_file";
				break;
			}
		}
		return array($err, $dir);
	}

	/**
	 * Unzip a file into a directory.
	 */
	private static function unzip($dir, $zip) {
		$err = '';
		$files = array();
		system("unzip -j -o -qq $dir/$zip -d $dir", $ret);
		if ($ret != 0) {
			$err = "error in unzipping $dir/$zip";
		}
		if (!$err) {
			if (!unlink($dir . '/' . $zip)) {
				$err = "error removing zip file $dir/$zip";
			}
		}
		if (!$err) {
			$upcase = array_map('strtoupper', self::$imageExts);
			$exts = array_merge($upcase, self::$imageExts);
			$ret = glob($dir . '/*.{' . join(',', $exts) . '}', GLOB_BRACE);
			if (false === $ret) {
				$err = 'no files unzipped';
			} else {
				$files = $ret;
			}
		}
		return array($err, $files);
	}

	private static function zip($dir, $zip) {
		$err = '';
		system("(cd $dir; zip -9 -q $zip *)", $ret);
		if ($ret != 0) {
			$err = "problems while executing zip command to create $zip";
		}
		return $err;
	}

	/**
	 * Remove tmp directory.
	 */
	private static function safeCleanupDir($dir) {
		$staging_dir = self::$stagingDir;
		if ($dir && $staging_dir && strpos($dir, $staging_dir) === 0) {
			system("rm -rf $dir");
		}
	}

	/**
	 * Load wikitext and get article URL
	 */
	private static function getArticleDetails($id) {
		$dbr = self::getDB('read');
		$rev = Revision::loadFromPageId($dbr, $id);
		if ($rev) {
			$text = $rev->getText();
			$title = $rev->getTitle();
			$url = self::makeWikihowURL($title);
			return array($text, $url, $title);
		} else {
			return array('', '', null);
		}
	}

	private static function makeWikihowURL($title) {
		return 'http://www.wikihow.com/' . $title->getDBkey(); //using this form so the links look right in fred for eliz.
	}

	/**
	 * Save wikitext for an article
	 */
	private static function saveArticleText($id, $wikitext) {
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

	/**
	 * Get database handle for reading or writing
	 */
	private static function getDB($type) {
		static $dbw = null, $dbr = null;
		if ('read' == $type) {
			if (!$dbr) $dbr = wfGetDB(DB_SLAVE);
			return $dbr;
		} elseif ('write' == $type) {
			if (!$dbw) $dbw = wfGetDB(DB_MASTER);
			return $dbw;
		} else {
			throw new Exception('bad db type');
		}
	}

	/**
	 * Get the title of an article (used for debug)
	 */
	private static function getTitleURL($articleID) {
		$title = Title::newFromId($articleID);
		if ($title) {
			return $title->getPartialURL();
		} else {
			return '(unknown)';
		}
	}

	/**
	 * Entry point for main processing loop
	 */
	public static function main() {
		$opts = getopt('bcd:e:f:', array('backup', 'cleanup', 'staging-dir:', 'exclude-article-id:', 'force:'));
		$doBackup = isset($opts['b']) || isset($opts['backup']);
		$doCleanup = isset($opts['c']) || isset($opts['cleanup']);

		self::$stagingDir = @$opts['d'] ? @$opts['d'] : @$opts['staging-dir'];
		if (empty(self::$stagingDir)) self::$stagingDir = self::DEFAULT_STAGING_DIR;

		self::$debugArticleID = @$opts['f'] ? @$opts['f'] : @$opts['force'];

		$skipID = @$opts['e'] ? $opts['e'] : @$opts['exclude-article-id'];
		if ($skipID) self::$excludeArticles[] = $skipID;

		if ($_ENV['USER'] != 'apache') {
			print "script must be run as part of wikiphoto-process-images-hourly.sh\n";
			exit;
		}

		self::$stepsMsg = wfMsg('steps');

		Misc::loginAsUser(self::PHOTO_USER);
		if ($doBackup) {
			self::doS3Backup();
		} elseif ($doCleanup) {
			self::doS3Cleanup();
		} else {
			self::processS3Images();
		}
	}

}

WikiPhotoProcess::main();

