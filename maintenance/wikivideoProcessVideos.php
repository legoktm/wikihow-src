<?php
/**
 * Runs every hour to process any newly uploaded videos.  Adds videos along with a preview image to the
 * articles identified by the article ID.
 *
 * Note: this script should only be run by wikivideo-process-videos-hourly.sh.
 *   It needs to have the correct setuid user so that /var/www/images_en
 *   files are created with the correct permissions.
 * 
 * Usage: php wikivideoProcessVideos.php
 */

/*
 * data schema:
 *
 CREATE TABLE `wikivideo_article_status` (
  `article_id` int(10) unsigned NOT NULL,
  `creator` varchar(32) NOT NULL default '',
  `processed` varchar(14) NOT NULL default '',
  `reviewed` tinyint(3) unsigned NOT NULL default '0',
  `retry` tinyint(3) unsigned NOT NULL default '0',
  `needs_retry` tinyint(3) unsigned NOT NULL default '0',
  `error` text NOT NULL,
  `warning` text NOT NULL,
  `url` varchar(255) NOT NULL default '',
  `videos` int(10) unsigned NOT NULL default '0',
  `replaced` int(10) unsigned NOT NULL default '0',
  `steps` int(10) unsigned NOT NULL default '0',
  PRIMARY KEY  (`article_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE `wikivideo_video_names` (
  `filename` varchar(255) NOT NULL,
  `wikiname` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE `wikivideo_transcoding_status` (
  `article_id` int(10) unsigned NOT NULL,
  `aws_job_id` varchar(32) NOT NULL default '',
  `aws_uri_in` text,
  `aws_uri_out` text,
  `processed` varchar(14) NOT NULL default '',
  `status` varchar(32) NOT NULL default '',
  `status_msg` text NOT NULL,
  PRIMARY KEY  (`aws_job_id`),
  KEY `article_id` (`article_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1


ALTER TABLE `wikivideo_article_status` ADD `photos` INT(10) unsigned NOT NULL default '0';
*
 */

require_once('commandLine.inc');

require_once("$IP/extensions/wikihow/common/S3.php");
require_once("$IP/extensions/wikihow/DatabaseHelper.class.php");
require_once("$IP/extensions/wikihow/whvid/WikiVideo.class.php");

use Aws\Common\Aws;
use Guzzle\Http\EntityBody;

class WikiVideoProcess {

	const PHOTO_LICENSE = 'cc-by-sa-nc-3.0-self';
	const VIDEO_USER = 'wikivid';

	//const AWS_UPLOAD_BUCKET = 'wikivideo-upload-test';
	const AWS_UPLOAD_BUCKET = 'wikivideo-upload';

	const AWS_TRANSCODING_IN_BUCKET = 'wikivideo-transcoding-in';
	const AWS_TRANSCODING_OUT_BUCKET = 'wikivideo-transcoding-out';

	const DEFAULT_STAGING_DIR = '/usr/local/wikihow/wikivideo';

	const DEFAULT_VIDEO_WIDTH = '500px';
	const DEFAULT_VIDEO_HEIGHT = '375px';
	const VIDEO_PORTRAIT_WIDTH = '220px';
	const VIDEO_LANDSCAPE_WIDTH = '300px';
	const VIDEO_EXTENSION = '.360p.mp4';


	const WARNING_MIN_WIDTH = 500;
	const REPROCESS_EPOCH = 1359757162; // Fri Feb  1 14:19:26 PST 2013
	const AWS_PIPELINE_ID = '1373317258162-6npnrl'; // Prod Pipeline

	const TRANSCODER_360P_16x9_PRESET = '1373409713520-t0nqq0'; // wikivideo - Generic 360p 16:9

	const STATUS_ERROR = 10;
	const STATUS_PROCESSING_UPLOADS = 20;
	const STATUS_TRANSCODING = 30;
	const STATUS_COMPLETE = 40;

	static $debugArticleID = '',
		$stepsMsg,
		$videoExts = array('mp4', 'avi', 'flv', 'aac', 'mov'),
		$imgExts = array('jpg', 'jpeg', 'gif', 'png'),
		$excludeUsers = array('old', 'backup'),
		$stagingDir = '',
		$excludeArticles = array(),
		$aws = null;
	
	/**
	 * Remove the Steps section from an article, leaving a placeholder
	 */
	private static function cutStepsSection($articleText) {
		$out = array();
		$token = Misc::genRandomString();
		$steps = '';
		$found = false;

		$former_recursion_limit = ini_set( "pcre.recursion_limit", 90000 );

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
	private static function dbVideosNeedProcessing($articleID) {
		$dbr = self::getDB('read');
		$sql = 'SELECT processed, retry FROM wikivideo_article_status WHERE article_id=' . $dbr->addQuotes($articleID);
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
	private static function dbSetArticleProcessed($articleID, $creator, $error, $warning, $url, $numVids, $numSteps, $replaced, $status, $photos = null) {
		$dbw = self::getDB('write');
		$numSteps = is_null($numSteps) ? 0 : $numSteps;
		if (!$warning) $warning = '';
		if($photos == null) {
			$dbr = self::getDB('read');
			$photos = $dbr->selectField('wikivideo_article_status', 'photos', array('article_id' => $articleID));
		}
		$sql = 'REPLACE INTO wikivideo_article_status SET 
			article_id=' . $dbw->addQuotes($articleID) . ', 
			processed=' . $dbw->addQuotes(wfTimestampNow(TS_MW)) . ', 
			replaced=' . $dbw->addQuotes($replaced) . ', 
			retry=0, 
			error=' . $dbw->addQuotes($error) . ', 
			warning=' . $dbw->addQuotes($warning) . ', 
			url=' . $dbw->addQuotes($url) . ', 
			videos=' . $dbw->addQuotes($numVids) . ', 
			creator=' . $dbw->addQuotes($creator) . ', 
			steps=' . $dbw->addQuotes($numSteps) . ',
			status=' . $dbw->addQuotes($status) . ',
			photos=' . $dbw->addQuotes($photos);
		$dbw->query($sql, __METHOD__);
	}

	/**
	 * Place a set of videos into an article's wikitext.
	 */
	private static function placeVideosInSteps($articleID, $title, &$videos, &$text, &$stepsText, &$numSteps, &$replaced) {
		$errs = '';
		
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
		$stepsText = preg_replace('@\{\{whvid\|[^\}]*\}\}@im', '', $stepsText);
		$stepsText = preg_replace('@\[\[Image:[^\]]*\]\]@im', '', $stepsText);
		$stepsText = preg_replace('@\{\{largeimage\|[^\}]*\}\}@im', "", $stepsText);
		
		// process the list of videos to make sure we can understand all filenames
		foreach ($videos as &$vid) {
			$vid['name'] = explode("/", $vid['aws_uri_out']);
			$vid['name'] = end($vid['name']);
			if (!preg_match('@^((.*)-\s*)?([0-9b]+|intro)\.(360p\.' . join('|', self::$videoExts) . ')$@i', $vid['name'], $m)) {
				$err .= "Filename not in format Name-1.mp4: " . $vid['name'] . ". ";
			} else {
				// new: just discard $m[2]
				$vid['first'] = $title->getText();	//future video title
				$vid['sub'] = null;					//bullet number
				$vid['ext'] = strtolower($m[4]);	//video extension
				
				$bulletpos = strrpos($m[3], "b");
				if($bulletpos !== false) {
					$vid['step'] = substr($m[3], 0, $bulletpos); //step number
					$vid['sub'] = substr($m[3], $bulletpos + 1); 
				}
				else
					$vid['step'] = strtolower($m[3]);  //step number
				
				                

				if ($vid['step'] == 'intro') {
					$vid['first'] .= ' Intro';
				} else {
					$vid['first'] .= ' Step ' . $vid['step'];
					if($vid['sub'] !== null) {
						$vid['first'] .= "Bullet" . $vid['sub'];
					}
				}
			}
		}

		if ($err) {
			return $err;
		}
		
		// split steps based on ^# then add the '#' character back on
		$steps = preg_split('@^\s*#@m', $stepsText);
		for ($i = 1; $i < count($steps); $i++) {
			$steps[$i] = "#" . $steps[$i];
		}

		// place videos in steps
		$stepNum = 1;
		for ($i = 1; $i < count($steps); $i++) {
			if (preg_match('@^(([#*]|\s)+)((.|\n)*)@m', $steps[$i], $m)) {
				$stripped = preg_replace('@\s+@', '', $m[1]);
				$m[1] = trim($m[1]);
				$levels = strlen($stripped);
				if ($levels == 1) {
					$subNum = 0;
					$stepIdx = false;
					foreach ($videos as $j => $video) {
						if ($video['step'] == $stepNum && $video['sub'] == null) {
							$stepIdx = $j;
							break;
						}
					}
					if ($stepIdx !== false) {
						$vidToken = 'VID_' . Misc::genRandomString() . '_' . $stepNum;
						
						if (preg_match('@[\n]+=@m', $m[3])) {
							$steps[$i] = $m[1] . preg_replace('@([\n]+=)@m', $vidToken . '$1', $m[3]) . "\n";
						} else {
							$steps[$i] = trim($m[1]) . trim($m[3]) . $vidToken . "\n";
						}
						$videos[$stepIdx]['token'] = $vidToken;
					}
					$stepNum++;
				}
				elseif ($levels == 2) {
					//we're in a bullet, check to see if we have a
					//video for this substep
					$subNum++;
					$stepIdx = false;
					foreach ($videos as $j => $video) {
						if ($video['step'] == ($stepNum - 1) ) {
							if($video['sub'] != null && $video['sub'] == $subNum) {
								$stepIdx = $j;
								break;
							}
						}
					}
					if ($stepIdx !== false) {
						$vidToken = 'VID_' . Misc::genRandomString() . '_' . ($stepNum - 1) . "_" . $subNum;
						
						if (preg_match('@[\n]+=@m', $m[3])) {
							$steps[$i] = $m[1] . preg_replace('@([\n]+=)@m', $vidToken . '$1', $m[3]) . "\n";
						} else {
							$steps[$i] = trim($m[1]) . trim($m[3]) . $vidToken . "\n";
						}
						$videos[$stepIdx]['token'] = $vidToken;
					}
				}
			}
		}
		$numSteps = $stepNum - 1;

		$newText = $text;

		// were we able to place all videos in the article?
		$notPlaced = array();
		foreach ($videos as $video) {
			if (!isset($video['token'])) {
				$notPlaced[] = $video['name'];
			}
		}
		if ($notPlaced) {
			$err = 'Unable to place in the wikitext: ' . join(', ', $notPlaced);
		}

		// add all these videos to the wikihow mediawiki repos
		if (!$err) {
			foreach ($videos as &$vid) {
				$error = self::addWikiHowVideo($articleID, $vid);
				if (strlen($error)) {
					$err = 'Unable to add new video file ' . $vid['name'] . ' to wikiHow: ' . $error;
				} else {
					$vid['width'] = self::DEFAULT_VIDEO_WIDTH; 
					$vid['height'] = self::DEFAULT_VIDEO_HEIGHT;
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

	private static function downloadImagePreview(&$image) {
		$svc = self::getS3Service();
		$downloadPath = self::$stagingDir . "/prev-" . Misc::genRandomString();
		
		$svc->getObject(array(
		  'Bucket' => self::AWS_TRANSCODING_OUT_BUCKET,
		  'Key'    => $image['aws_thumb_uri'],
		  'command.response_body' => EntityBody::factory(fopen("$downloadPath", 'w+'))));
		$image['filename'] = $downloadPath;
		
	}
	/**
	 * Add a new image file into the mediawiki infrastructure so that it can
	 * be accessed as [[Image:filename.jpg]]
	 */
	private static function addMediawikiImage($articleID, &$image) {

		// Download the preview image and set the filename to the temporarary location
		$err = self::downloadImagePreview($image);
		if ($err) {
			return $err;
		}

		// check if we've already uploaded this image
		$dupTitle = DupImage::checkDupImage($image['filename']);

		// if we've already uploaded this image, just return that filename
		if ($dupTitle) {
			//$image['dupTitle'] = true;
			$image['mediawikiName'] = $dupTitle;
			return '';
		}



		// find name for image; change filename to Filename 1.jpg if 
		// Filename.jpg already existed
		$regexp = '/[^' . Title::legalChars() . ']+/';
		$first = preg_replace($regexp, '', $image['first']);
		$ext = $image['ext'];
		$newName = $first . '-preview.' . $ext;
		$i = 1;
		do {
			$title = Title::newFromText($newName, NS_IMAGE);
			if ($title && !$title->exists()) break;
			$newName = $first . '-preview Version ' . ++$i . '.' . $ext;
		} while ($i <= 1000);

		// insert image into wikihow mediawiki repos
		$comment = '{{' . self::PHOTO_LICENSE . '}}';
		// next 6 lines taken and modified from 
		// extensions/wikihow/eiu/Easyimageupload.body.php
		$title = Title::makeTitleSafe(NS_IMAGE, $newName);
		if (!$title) return "Couln't Make a title";
		$file = new LocalFile($title, RepoGroup::singleton()->getLocalRepo());
		if (!$file) return "Couldn't make a local file";
		$ret = $file->upload($image['filename'], $comment, $comment);
		if (!$ret->ok) return "Couldn't upload file " . $image['filename'];

		// instruct later processing about which mediawiki name was used
		$image['mediawikiName'] = $newName;

		// Add our uploaded image to the dup table so it's no uploaded again
		DupImage::addDupImage($image['filename'], $image['mediawikiName']);

		return '';
	}

	/**
	 * Add a new video file into the mediawiki infrastructure so that it can
	 * be accessed as {{whvid|filename.mp4|Preview.jpg}}
	 */
	private static function addWikiHowVideo($articleID, &$video) {

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
		$ret = WikiVideo::copyFileToProd(self::AWS_TRANSCODING_OUT_BUCKET, $video['aws_uri_out'], $newName);
		if ($ret['error']) return $ret['error'];

		// instruct later processing about which mediawiki name was used
		$video['mediawikiName'] = $newName;

		// Add preview image
		$img = $video;
		$img['ext'] = 'jpg';
		$err = self::addMediawikiImage($articleId, $img);
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

		// Keep a log of where videos were uploaded in wikivideo_video_names table
		$dbw = self::getDB('write');
		$vidname = $articleID . '/' . $video['name'];
		$sql = 'INSERT INTO wikivideo_video_names SET filename=' . $dbw->addQuotes($vidname) . ', wikiname=' . $dbw->addQuotes($video['mediawikiName']);
		$dbw->query($sql, __METHOD__);

		return '';
	}

	/**
	 * Process all videos for an article from the wikivideo upload dir
	 */
	private static function processVideos($articleID, $creator, $videoList, $warning) {
		$err = '';
		$numSteps = 0;
		$replaced = 0;
		
		// parse out steps section replacing it with a token, leaving 
		// the above and below wikitext intact
		list($text, $url, $title) = self::getArticleDetails($articleID);
		if (!$text || !$title) $err = 'Could not find article ID ' . $articleID;
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

		// try to place videos into wikitext, using tokens as placeholders.
		if (!$err) {
			$err = self::placeVideosInSteps($articleID, $title, $videoList, $text, $steps, $numSteps, $replaced);
		}

		// detect if no photos were to be processed
		if (!$err) {
			if (count($videoList) == 0) {
				$err = 'No videos to process';
			}
		}

		// replace the tokens within the video tag
		if (!$err) {
			$isAllLandscape = true;
			$hadColourProblems = false;
			$hadSizeProblems = false;
			$userIsScreenshotter = false;

			$text = str_replace($stepsToken, $steps, $text);
			foreach ($videoList as $video) {
				if (!empty($video['width']) && !empty($video['height']) 
					&& $video['width'] > $video['height'])
				{
					$sizeParam = self::VIDEO_LANDSCAPE_WIDTH;
				} else {
					$sizeParam = self::VIDEO_PORTRAIT_WIDTH;
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
					&& $video['width'] < self::WARNING_MIN_WIDTH)
				{
					$warning .= "size:{$video['width']}px:{$video['name']}\n";
					$hadSizeProblems = true;
				}

				$videoTag = '{{whvid|' . $video['mediawikiName'] . '|' . $video['previewMediawikiName'] . '}}';
				$text = str_replace($video['token'], $videoTag, $text);
			}
		}

		// remove certain templates from start of wikitext
		if (!$err) {
			$templates = array('illustrations', 'pictures', 'screenshots', 'stub');
			$text = self::removeTemplates($text, $templates);
		}

		// write wikitext and add/update wikivideo row
		if (!$err) {
			$err = self::saveArticleText($articleID, $text);
		}

		if ($err) {
			self::dbSetArticleProcessed($articleID, $creator, $err, $warning, $url, 0, $numSteps, 0, self::STATUS_ERROR);
		} else {
			self::dbSetArticleProcessed($articleID, $creator, '', $warning, $url, count($videoList), $numSteps, $replaced, self::STATUS_COMPLETE);
		}

		// remove transcoding job db entries and s3 URIs
		//self::removeOldTranscodingJobs($articleID);

		print date('Y/M/d H:i') . " processed wikitext: $creator $articleID $url videos=" . count($videoList) . " $err\n";
	}

	/**
	 * Grab the status of all articles processed.
	 */
	private static function dbGetArticlesUpdatedAll() {
		$articles = array();
		$dbr = self::getDB('read');

		$res = DatabaseHelper::batchSelect('wikivideo_article_status',
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
		$dbw->update('wikivideo_article_status', array('needs_retry' => 1), array('article_id' => $id), __METHOD__);
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

			// process the list of videos files into a list of articles
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
		$s3 = new S3(WH_AWS_WIKIVIDEO_PROD_ACCESS_KEY, WH_AWS_WIKIVIDEO_PROD_SECRET_KEY);
		$src = self::getS3Articles($s3, self::AWS_UPLOAD_BUCKET);
		foreach ($src as $id => $details) {
			if ($details['zip'] && $details['files']) {
				$uri = $details['user'] . '/' . $id . '.zip';
				$count = count($details['files']);
				if ($count <= 1) {
					$files = join(',', $details['files']);
					print "not enough files ($count) to delete $uri: $files\n";
				} else {
					print "deleting $uri\n";
					$s3->deleteObject(self::AWS_UPLOAD_BUCKET, $uri);
				}
			}
		}
	}

	/**
	 * Process videos on S3 instead of from the videos web server dir
	 */
	private static function processS3Uploads() {
		$s3 = new S3(WH_AWS_WIKIVIDEO_UPLOAD_ACCESS_KEY, WH_AWS_WIKIVIDEO_UPLOAD_SECRET_KEY);

//$file = '/tmp/whp';
//if (!file_exists($file)) {
		$articles = self::getS3Articles($s3, self::AWS_UPLOAD_BUCKET);
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
			// videos has changes, and it's not set to be retried, don't
			// process it again
			if (!$debug
				&& isset($processed[$id])
				&& !$processed[$id]['retry']
				&& $processed[$id]['processed'] > $details['time'])
			{
				continue;
			}

			// if article is not on Wikiphoto article exclude list
			/*
			if (WikiPhoto::checkExcludeList($id)) {
				$err = 'Article was found on Wikiphoto EXCLUDE list';
				self::dbSetArticleProcessed($id, $details['user'], $err, '', '', 0, 0, 0);
				continue;
			}
			*/

			// pull zip file into staging area
			$stageDir = '';
			$videoList = array();
			if ($details['zip']) {
				$prefix = $details['user'] . '/';
				$zipFile = $id . '.zip';
				$files = array($zipFile);
				list($err, $stageDir) = self::pullFiles($id, $s3, $prefix, $files);
				if (!$err) {
					list($err, $files, $images) = self::unzip($stageDir, $zipFile);
				}
				if (!$err) {
					foreach ($files as $file) {
						$videoList[] = array('name' => basename($file), 'filename' => $file);
					}
				}
			} else { // no zip -- ignore
				continue;
			}

			if (!$err && in_array($id, self::$excludeArticles)) {
				$err = 'Forced skipping this article because there was an repeated error when processing it';
			}

			if (!$err) {
				// Upload to s3 transcoding input bucket
				// Create transcoding job
				// Store info on transcoding jobs
				$warning = @$details['warning'];
				// Remove existing jobs.  Do this right before transocding so we can look at the last transcoding jobs
				// for a particular article in case of error
				self::removeOldTranscodingJobs($id);
				self::transcodeVideos($id, $details['user'], $videoList, $warning, $images);
			} else {
				self::dbSetArticleProcessed($id, $details['user'], $err, '', '', 0, 0, 0, self::STATUS_ERROR, count($images));
			}

			if ($stageDir) {
				self::safeCleanupDir($stageDir);
			}


			$title = Title::newFromId($id);
			$titleStr = ($title ? ' (' . $title->getText() . ')' : '');
			$errStr = $err ? ' err=' . $err : '';
			$videoCount = count($videoList);
			print date('Y/M/d H:i') . " processed upload: {$details['user']}/$id$titleStr videos=$videoCount$errStr\n";
		}
	}

	private static function processWikitext($aid, $creator) {
		$videoList = self::dbGetTranscodingArticleJobs($aid);
		self::processVideos($aid, $creator, $videoList, '');
	}

	private static function transcodeVideos($aid, $creator, &$videoList, $warning, $images) {
		$err = '';
		$s3 = new S3(WH_AWS_WIKIVIDEO_PROD_ACCESS_KEY, WH_AWS_WIKIVIDEO_PROD_SECRET_KEY);

		$t = Title::newFromId($aid);
		if (!$t || !$t->exists()) $err = 'Could not find article ID ' . $aid;

		if (!$err) {
			$transcodeDir = $aid . "-" . mt_rand();
			foreach ($videoList as $video) {
				$transcodeInPath = $transcodeDir . "/" . $video['name'];
				$err = self::postFile($s3, $video['filename'], $transcodeInPath, self::AWS_TRANSCODING_IN_BUCKET);
				if (strlen($err)) {
					break;
				}
				$result = self::createTranscodingJob($transcodeDir, $video['name']); 
				if ($result['Status'] == 'Error') {
					$err = "Transcoding job creation error. file: $transcodeInPath, id: {$result[3]}, msg: $result[2]";
					break;
				}
				self::dbRecordTranscodingJobStatus($aid, $result);
			}
			$url = self::makeWikihowURL($t);
			if ($err) {
				self::dbSetArticleProcessed($aid, $creator, $err, $warning, $url, 0, $numSteps, 0, self::STATUS_ERROR, count($images));
				// We won't take any further actions on these jobs
				// Clean up the transcoding jobs and any S3 URIs that might have been created. 
				//self::removeOldTranscodingJobs($aid);
			} else {
				self::dbSetArticleProcessed($aid, $creator, '', $warning, $url, count($videoList), 0, 0, self::STATUS_TRANSCODING, count($images));
			}
		}
	}

	/*
	* Removes db entries and any associated S3 uris associated with the 
	* transcoding article
	*/
	private static function removeOldTranscodingJobs($aid) {
		self::dbRemoveTranscodingJobs($aid);
	}

	private function dbRecordTranscodingJobStatus($aid, &$awsJob) {
		$dbw = self::getDB('write');

		// Should only be one output file format
		$output = $awsJob["Outputs"][0];	
		$thumbUri = $output['Status'] == 'Complete' ? self::getThumbUri($awsJob) : '';
		$statusDetail = is_null($output['StatusDetail']) ? "" : $output['StatusDetail'];
		$sql = 'REPLACE INTO wikivideo_transcoding_status SET 
			article_id=' . $dbw->addQuotes($aid) . ', 
			aws_job_id=' . $dbw->addQuotes($awsJob['Id']) . ',
			aws_uri_in=' . $dbw->addQuotes($awsJob['Input']['Key']) . ',
			aws_uri_out=' . $dbw->addQuotes($output['Key']) . ',
			aws_thumb_uri=' . $dbw->addQuotes($thumbUri) . ',
			processed=' . $dbw->addQuotes(wfTimestampNow(TS_MW)) . ',
			status=' . $dbw->addQuotes($output['Status']) . ',
			status_msg=' . $dbw->addQuotes($statusDetail);
		$dbw->query($sql, __METHOD__);
	}

	private static function getThumbUri(&$awsJob) {
		$output = $awsJob["Outputs"][0];	
		$thumbPrefix = $output["Key"];
		$thumbPrefix = str_replace(self::VIDEO_EXTENSION, ".", $thumbPrefix); 

		$thumbUris = array();
		$svc = self::getS3Service();
		$lastKey = null;
		do {
			
			$inputs = array( 'Bucket' => self::AWS_TRANSCODING_OUT_BUCKET, 'Prefix' => $thumbPrefix);
			if (!is_null($lastKey)) {
				$inputs['Marker'] = $lastKey;
			}
			$result = $svc->listObjects($inputs);
			$contents = $result['Contents'];
			foreach ($contents as $key => $val) {
				$thumbUris[] = $val['Key'];
				$lastKey = $val['Key'];
			}
		} while ($result['IsTruncated']);

		// Pull out the jpgs
		// Grab the last thumbnail frame with pattern  <key>/<filename>.{resolution}.{count}.jpg
		$thumbUris = preg_grep('@.*\.jpg$@', $thumbUris);
		$thumbUri = end($thumbUris);

		return $thumbUri;
	}

	private function dbRemoveTranscodingJobs($aid) {
		$dbw = self::getDB('write');
		$dbw->delete('wikivideo_transcoding_status', array("article_id" => $aid), __METHOD__);
	}

	private static function createTranscodingJob($dir, $filename) {
		$dir = $dir . "/";
		$svc = self::getTranscoderService();
		$params = array(
			'PipelineId' => self::AWS_PIPELINE_ID, 
			'Input' => array(
				'Key' => $dir . $filename,
				'FrameRate' => 'auto',
				'Resolution' =>	'auto',
				'AspectRatio' => 'auto',
				'Interlaced' => 'auto',
				'Container' => 'auto'),
			'Output' => array(
				'Key' => $dir . basename($filename, ".mp4") . self::VIDEO_EXTENSION,
				'ThumbnailPattern' => $dir . basename($filename, ".mp4") . ".{resolution}.{count}",
				'Rotate' => '0',
				'PresetId' => self::TRANSCODER_360P_16x9_PRESET));
		$ret = $svc->createJob($params);
		return $ret['Job'];
	}

	private static function getAwsTranscodingJobStatus($jobId) {
		$svc = self::getTranscoderService();
		$response = $svc->readJob(array("Id" => $jobId));
		return $response['Job'];
	}

	/**
	 * Upload a file to S3
	 */
	private static function postFile(&$s3, $file, $uri, $bucket = self::AWS_BACKUP_BUCKET) {
		$err = '';
		$ret = $s3->putObject(array('file' => $file), $bucket, $uri);
		if (!$ret) {
			$err = "unable to upload $file to S3";
		}
		return $err;
	}

	/**
	 * Download files from S3
	 */
	private static function pullFiles($id, &$s3, $prefix, &$files, $bucket = self::AWS_UPLOAD_BUCKET) {
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
			$ret = $s3->getObject($bucket, $aws_file, $local_file);
			if (!$ret || $ret->error) {
				$err = "problem retrieving file from S3: s3://" . $bucket . "/$aws_file";
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
			$upcase = array_map('strtoupper', self::$videoExts);
			$exts = array_merge($upcase, self::$videoExts);
			$ret = glob($dir . '/*.{' . join(',', $exts) . '}', GLOB_BRACE);
			if (false === $ret) {
				$err = 'no files unzipped';
			} else {
				$files = $ret;
			}

			/**
			 * Also need to check for images as they should exist
			 * and also need to record how many there will be in the db
			 */
			$upcaseImages = array_map('strtoupper', self::$imgExts);
			$extsImages = array_merge($upcaseImages, self::$imgExts);
			$ret = glob($dir . '/*.{' . join(',', $extsImages) . '}', GLOB_BRACE);
			if(false == $ret) {
				$images = array();
			}
			else {
				$images = $ret;
			}
		}
		return array($err, $files, $images);
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
			$saved = $article->doEdit($wikitext, 'Saving new step-by-step videos');
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

	public static function getAws() {
		global $IP;
		if (is_null(self::$aws)) {
			// Create a service builder using a configuration file
			self::$aws = Aws::factory(array(
				'key'    => WH_AWS_BACKUP_ACCESS_KEY,
				'secret' => WH_AWS_BACKUP_SECRET_KEY,
				'region' => 'us-east-1'
			));
		}
		return self::$aws;
	}

	public static function getTranscoderService() {
		$aws = self::getAws();
		return $aws->get('ElasticTranscoder');
	}

	public static function getS3Service() {
		$aws = self::getAws();
		return $aws->get('S3');
	}

	private static function processTranscodingArticles() {
		$err = '';
		$articles = self::dbGetTranscodingArticles();
		foreach ($articles as $a) {
			$aid = $a['article_id'];
			$creator = $a['creator'];

			self::dbUpdateArticleJobsStatus($aid);

			if ($err = self::hasTranscodingErrors($aid)) {
				//self::removeOldTranscodingJobs($aid);
				self::dbSetArticleProcessed($aid, $creator, $err, '', '', 0, '', 0, self::STATUS_ERROR);
			} elseif (self::isStillTranscoding($aid)) {
				continue;
			} else {
				self::processWikitext($aid, $creator);
			}
		}

		if ($stageDir) {
			self::safeCleanupDir($stageDir);
		}

	}	

	private static function isStillTranscoding($aid) {
		$dbr = self::getDB('read');
		return 0 < $dbr->selectField('wikivideo_transcoding_status', 'count(*)', array('article_id' => $aid, "status != 'Complete'"));
	}

	private static function hasTranscodingErrors($aid) {
		$err = '';
		$dbr = self::getDB('read');	
		$rows = $dbr->select('wikivideo_transcoding_status', array('aws_uri_in', 'status_msg'), array('article_id' => $aid, "status" => 'Error'), __METHOD__);
		$errors = array();
		foreach ($rows as $row) {
			$errors[] = $row->aws_uri_in . ' - ' . $row->status_msg;
		}

		if(sizeof($errors)) {
			$err = implode("\n", $errors);
		}
		return $err;
	}
	private static function dbGetTranscodingArticles() {
		$articles = array();
		$dbr = self::getDB('read');
		$rows = $dbr->select('wikivideo_article_status', array('*'), array('status' => self::STATUS_TRANSCODING), __METHOD__);
		foreach ($rows as $row) {
			$articles[] = get_object_vars($row);
		}
		return $articles;
	}

	private static function dbGetTranscodingArticleJobs($aid) {
		$articles = array();
		$dbr = self::getDB('read');
		// Update any article job status for articles in the transcoding state
		$rows = $dbr->select('wikivideo_transcoding_status', array('*'), array('article_id' => $aid), __METHOD__);
		$jobs = array();
		foreach ($rows as $row) {
			$jobs[] = get_object_vars($row);	
		}
		return $jobs;
	}

	private static function dbUpdateArticleJobsStatus($aid) {
		$dbJobs = self::dbGetTranscodingArticleJobs($aid);
		$svc = self::getTranscoderService();
		foreach ($dbJobs as $dbJob) {
			$awsJob = self::getAwsTranscodingJobStatus($dbJob['aws_job_id']);
			self::dbRecordTranscodingJobStatus($aid, $awsJob);
		}
	}

	/**
	 * Entry point for main processing loop
	 */
	public static function main() {
		$opts = getopt('bcd:e:f:t:', array('backup', 'cleanup', 'staging-dir:', 'exclude-article-id:', 'force:'));
		$doCleanup = isset($opts['c']) || isset($opts['cleanup']);

		self::$stagingDir = @$opts['d'] ? @$opts['d'] : @$opts['staging-dir'];
		if (empty(self::$stagingDir)) self::$stagingDir = self::DEFAULT_STAGING_DIR;

		self::$debugArticleID = @$opts['f'] ? @$opts['f'] : @$opts['force'];
		$skipID = @$opts['e'] ? $opts['e'] : @$opts['exclude-article-id'];
		if ($skipID) self::$excludeArticles[] = $skipID;

		if ($_ENV['USER'] != 'apache') {
			print "script must be run as part of wikivideo-process-videos-hourly.sh\n";
			//exit;
		}

		self::$stepsMsg = wfMsg('steps');

		Misc::loginAsUser(self::VIDEO_USER);
		if ($doCleanup) {
			self::doS3Cleanup();
		} else {
			self::processTranscodingArticles();
			self::processS3Uploads();
		}
	}
}

WikiVideoProcess::main();

