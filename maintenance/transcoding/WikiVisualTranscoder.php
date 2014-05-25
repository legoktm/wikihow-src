<?php

/*
	data schema: 
    CREATE TABLE wikivisual_article_status ( 
		article_id INT(10) UNSIGNED NOT NULL, 
		status INT UNSIGNED not null default 0, 
		creator VARCHAR(32) NOT NULL default '', 
		reviewed TINYINT(3) UNSIGNED NOT NULL default 0, 
		processed VARCHAR(14) NOT NULL default '', 
		vid_processed VARCHAR(14) NOT NULL default '', 
		photo_processed VARCHAR(14) NOT NULL default '', 
		warning TEXT not null, 
		error TEXT not null, 
		article_url VARCHAR(255) NOT NULL default '', 
		retry TINYINT(3) UNSIGNED NOT NULL default 0, 
		vid_cnt INT(10) UNSIGNED NOT NULL default 0, 
		photo_cnt INT UNSIGNED NOT NULL default 0, 
		replaced INT(10) UNSIGNED NOT NULL default 0, 
		steps INT(10) UNSIGNED NOT NULL default 0,
		staging_dir VARCHAR(255) NOT NULL default '',
		incubation TINYINT(3) UNSIGNED NOT NULL default 0,
		PRIMARY KEY (article_id) 
	) ENGINE=InnoDB DEFAULT CHARSET=latin1; 
		
	CREATE TABLE wikivisual_vid_names ( 
		filename VARCHAR(255) NOT NULL, 
		wikiname VARCHAR(255) NOT NULL 
	) ENGINE=InnoDB DEFAULT CHARSET=latin1; 
	
	CREATE TABLE wikivisual_photo_names ( 
		filename VARCHAR(255) NOT NULL, 
		wikiname VARCHAR(255) NOT NULL 
	) ENGINE=InnoDB DEFAULT CHARSET=latin1; 
	
	CREATE TABLE wikivisual_vid_transcoding_status ( 
		article_id INT(10) UNSIGNED NOT NULL, 
		aws_job_id VARCHAR(32) NOT NULL default '', 
		aws_uri_in TEXT, 
		aws_uri_out TEXT, 
		aws_thumb_uri TEXT, 
		processed VARCHAR(14) NOT NULL default '', 
		status VARCHAR(32) NOT NULL default '', 
		status_msg TEXT NOT NULL, 
		PRIMARY KEY (aws_job_id), 
		KEY article_id (article_id) 
	) ENGINE=InnoDB DEFAULT CHARSET=latin1

*/

require_once ('../commandLine.inc');

global $IP;

require_once ("$IP/extensions/wikihow/common/S3.php");
require_once ("$IP/extensions/wikihow/DatabaseHelper.class.php");
require_once ("Utils.php");
require_once ("Mp4Transcoder.php");
require_once ("ImageTranscoder.php");

use Aws\Common\Aws;
use Guzzle\Http\EntityBody;
/**
* Setup steps
*  1. Create AWS_BUCKET
*  2. Create MEDIA_USER
*  3. Create schema by running above mentioned DDL
*  4. Create DEFAULT_STAGING_DIR
*
* To run this in stage (or doh)
*  1. Use AWS_BUCKET='wikivisual-upload-test'
*  2. In WHVid.body.php::getVidUrl use  $domain = self::S3_DOMAIN_DEV;
*  3. In WikiVideo.class.php use const AWS_PROD_BUCKET = 'wikivideo-prod-test';
*  4. to run
*   1. sudo su -
*   2. cd ${REPOPATH}/wikihow/prod/maintenance/transcoding
*   3. sudo -u apache /usr/local/bin/php WikiVisualTranscoder.php
*/

if (IS_DEV_SITE) {
	define(AWS_BUCKET_TMP, 'wikivisual-upload-test');
} else {
	define(AWS_BUCKET_TMP, 'wikivisual-upload');
}

class WikiVisualTranscoder {

// 	const AWS_BUCKET = 'wikivisual-upload';
// 	const AWS_BUCKET = 'wikivisual-upload-test';
	const AWS_BUCKET = AWS_BUCKET_TMP;
	
	const DEFAULT_STAGING_DIR = '/usr/local/wikihow/wikivisual';
	const MEDIA_USER = 'wikivisual'; 
	const AWS_BACKUP_BUCKET = 'wikivisual-backup'; 

	const AWS_TRANSCODING_IN_BUCKET = 'wikivideo-transcoding-in';
	const AWS_TRANSCODING_OUT_BUCKET = 'wikivideo-transcoding-out';
	
	const AWS_PIPELINE_ID = '1373317258162-6npnrl'; // Prod Pipeline

	const VIDEO_PORTRAIT_WIDTH = '220px';
	const VIDEO_LANDSCAPE_WIDTH = '300px';
	const VIDEO_WARNING_MIN_WIDTH = 500;
	const VIDEO_EXTENSION = '.360p.mp4';
	const DEFAULT_VIDEO_WIDTH = '500px';
	const DEFAULT_VIDEO_HEIGHT = '375px';
	
	const TRANSCODER_360P_16x9_PRESET = '1373409713520-t0nqq0'; // wikivideo - Generic 360p 16:9
	
	const IMAGE_PORTRAIT_WIDTH = '220px';
	const IMAGE_LANDSCAPE_WIDTH = '300px';
	const WARNING_MIN_WIDTH = 3200;
	const ERROR_MAX_IMG_DIMEN = 10000;
	
	const PHOTO_LICENSE = 'cc-by-sa-nc-3.0-self';
	const SCREENSHOT_LICENSE = 'Screenshot';

	const REPROCESS_EPOCH = 1359757162; // Fri Feb  1 14:19:26 PST 2013
	
	const STATUS_ERROR = 10;
	const STATUS_PROCESSING_UPLOADS = 20;
	const STATUS_TRANSCODING = 30;
	const STATUS_COMPLETE = 40;
	
	const FINALSTR = 'final';
	const FINALSTRLBL = 'Final';
	const FINISHED = 'Finished';
	
	const ADMIN_CONFIG_INCUBATION_LIST = 'wikivisual-incubation-list';
	
	static $DEBUG = false;
    static $exitAfterNumArticles = 1;
	static $stagingDir = '', 
		$debugArticleID = '', 
		$videoExts = array('mp4', 'avi', 'flv', 'aac', 'mov'),
		$assocVideoExts,
		$imgExts = array('jpg', 'jpeg', 'gif', 'png'),
		$assocImgExts,
		$excludeArticles = array (
			57203,
			1251223,
			354106),
		$excludeUsers = array('old', 'backup'),
        $aws = null;

	private $mp4Transcoder, $imageTranscoder;
	
	public static function d($msg, $debugOverride = false, $msgType = "DEBUG") {
		if ((self::$DEBUG === true || $debugOverride === true) && !empty($msg)) {
			echo "$msgType ". date ( 'Y/M/d H:i' ) ." |$msg\n";
		}
	}
	
	public static function i($msg) {
		self::d($msg, true, "INFO");
	}
	
	public static function e($msg) {
		self::d($msg, true, "ERROR");
	}
	
	private static function timeDiffStr($t1, $t2) {
		$diff = abs ( $t1 - $t2 );
		if ($diff >= 2 * 24 * 60 * 60) {
			$days = floor ( $diff / (24 * 60 * 60) );
			return "$days days";
		} else {
			$hours = $diff / (60 * 60);
			return sprintf ( '%.2f hours', $hours );
		}
	}
		
	public static function makeWikihowURL($title) {
		return 'http://www.wikihow.com/' . $title->getDBkey(); //using this form so the links look right in fred for eliz.
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
	
	public static function getS3Service() {
		$aws = self::getAws();
		return $aws->get('S3');
	}

    public static function downloadImagePreview(&$image) {
        $svc = self::getS3Service();
        $downloadPath = self::$stagingDir . "/prev-" . Misc::genRandomString();
        
        $svc->getObject(array(
          'Bucket' => self::AWS_TRANSCODING_OUT_BUCKET,
          'Key'    => $image['aws_thumb_uri'],
          'command.response_body' => EntityBody::factory(fopen("$downloadPath", 'w+'))));
        $image['filename'] = $downloadPath;
    }


	/**
	 * Get database handle for reading or writing
	 */
	public static function getDB($type) {
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
	

    public static function isStillTranscoding($aid) {
        $dbr = WikiVisualTranscoder::getDB('read');
        return 0 < $dbr->selectField('wikivisual_vid_transcoding_status', 'count(*)', array('article_id' => $aid, "status != 'Complete'"));
    }


	/**
	 * Grab the status of all articles processed.
	 */
	private function dbGetArticlesUpdatedAll() {
		$articles = array();
		$dbr = self::getDB('read');
	
		$res = DatabaseHelper::batchSelect('wikivisual_article_status',
				array('article_id', 'processed', 'vid_processed', 'photo_processed', 'error', 'retry'),
				'',
				__METHOD__,
				array(),
				DatabaseHelper::DEFAULT_BATCH_SIZE,
				$dbr);
	
		foreach ($res as $row) {
			// convert MW timestamp to unix timestamp
			$row->processed = wfTimestamp(TS_UNIX, $row->processed);
			$row->vid_processed = wfTimestamp(TS_UNIX, $row->vid_processed);
			$row->photo_processed = wfTimestamp(TS_UNIX, $row->photo_processed);
			$articles[ $row->article_id ] = (array)$row;
		}
		return $articles;
	}
	

	/**
	 * Set an article as processed in the database
	 */
	public static function dbSetArticleProcessed($articleID, $creator, $error, $warning, $articleUrl, $vidCnt, $photoCnt, $numSteps, $replaced, $status, 
			$reviewed, $stagingDir ) {
		$incubation = self::isIncubated($creator) !== false ? 1 : 0;
		$dbw = self::getDB('write');
		$numSteps = is_null($numSteps) ? 0 : $numSteps;
		if (!$warning) $warning = '';
		if (!$error) $error = '';
		$processed = wfTimestampNow(TS_MW);

		$sql = 'REPLACE INTO wikivisual_article_status SET
			article_id=' . $dbw->addQuotes($articleID) . ',
			replaced=' . $dbw->addQuotes($replaced) . ',
			retry=0,
			error=concat(error, ' . ' ' . $dbw->addQuotes($error) . '),
			processed=' . $dbw->addQuotes($processed) . ',
			warning=concat(warning,' . ' ' .$dbw->addQuotes($warning) . '),
			article_url=' . $dbw->addQuotes($articleUrl) . ',
			vid_cnt=' . $dbw->addQuotes($vidCnt) . ',
			photo_cnt=' . $dbw->addQuotes($photoCnt) . ',
			creator=' . $dbw->addQuotes($creator) . ',
			status=' . $dbw->addQuotes($status) . ',
			reviewed=' . $dbw->addQuotes($reviewed) . ',
			staging_dir=' . $dbw->addQuotes($stagingDir) . ',
			incubation=' . $dbw->addQuotes($incubation) . ',
			steps=' . $dbw->addQuotes($numSteps);
			
		$dbw->query($sql, __METHOD__);
	}
	
	private function updateArticleStatus($values, $condition) {
		$dbw = self::getDB('write');
		return $dbw->update('wikivisual_article_status', $values, $condition, __METHOD__);
	}
	
	private function updateArticleStatusMediaProcessed($mediaTypeCol, $articleId) {
		$values = array(
			$mediaTypeCol => wfTimestampNow(TS_MW)
		);
		$conditions = array(
			'article_id' => $articleId
		);
		$this->updateArticleStatus($values, $conditions);
	}
	
	private function appendErrNWarning($articleId, &$err, &$warning) {
		$article = $this->dbGetArticle($articleId);
		if (count($article) == 0) {
			return null;
		}
		$a = $article[0];
		
		if ($a['error']) $err = $a['error']. ' ' .$err; 
		if ($a['warning']) $warning = $a['warning']. ' ' .$warning; 
	}
	
	private function updateArticleStatusPhotoProcessed($articleId, $err, $warning, $url, $photoCnt, $replaced, $updateProcessed = FALSE) {
		$ts = wfTimestampNow(TS_MW);
		$values = array(
			'photo_processed' => $ts,
// 			'warning' => $warning,
// 			'error' => $err,
			'processed' => $ts,
			'article_url' => $url,
			'photo_cnt' => $photoCnt,
			'replaced' => $replaced
		);
		
		if ($err) {
			$values['status'] = self::STATUS_ERROR;
		} else {
			$values['status'] = self::STATUS_COMPLETE;
		}
		
		if(!empty($warning) || !empty($err)) {
			$this->appendErrNWarning($articleId, $err, $warning);
			if(!empty($warning)) $values['warning'] = $warning;
			if(!empty($err)) $values['error'] = $err;
		}
		
		//if ($updateProcessed) $values['processed'] = $ts;
		
		$conditions = array(
			'article_id' => $articleId
		);
		$this->updateArticleStatus($values, $conditions);
	}
	
	private function updateArticleStatusHybridMediaProcessed($articleId, $err, $warning, $url, $photoCnt, $videoCnt, $replaced, $updateProcessed = FALSE) {
		$ts = wfTimestampNow(TS_MW);
		$values = array(
			'photo_processed' => $photoCnt && $photoCnt > 0 ? $ts : '',
			'vid_processed' => $videoCnt && $videoCnt > 0 ? $ts : '',
			'processed' => $ts,
// 			'warning' => $warning,
// 			'error' => $err,
			'article_url' => $url,
			'photo_cnt' => $photoCnt,
			'vid_cnt' => $videoCnt,
			'replaced' => $replaced
		);
		
		if ($err) {
			$values['status'] = self::STATUS_ERROR;
		} else {
			$values['status'] = self::STATUS_COMPLETE;
		}
		
		if(!empty($warning) || !empty($err)) {
			$this->appendErrNWarning($articleId, $err, $warning);
			if(!empty($warning)) $values['warning'] = $warning;
			if(!empty($err)) $values['error'] = $err;
		}
		
// 		if ($updateProcessed) $values['processed'] = $ts;
		
		$conditions = array(
			'article_id' => $articleId
		);
		$this->updateArticleStatus($values, $conditions);
	}
	
	private function updateArticleStatusVideoTranscoding($articleId, $err, $warning, $url, $status) {
		$ts = wfTimestampNow(TS_MW);
		$values = array(
			'warning' => $warning,
			'error' => $err,
			'article_url' => $url,
			'status' => $status
		);

		$conditions = array(
			'article_id' => $articleId
		);
		$this->updateArticleStatus($values, $conditions);
	}
	
	private function dbGetTranscodingArticles() {
		$articles = array();
		$dbr = self::getDB('read');
		$rows = $dbr->select('wikivisual_article_status', array('*'), array('status' => self::STATUS_TRANSCODING), __METHOD__);
		foreach ($rows as $row) {
			$articles[] = get_object_vars($row);
		}
		return $articles;
	}
	
	private function dbGetArticle($articleId) {
		$articles = array();
		$dbr = self::getDB('read');
		$rows = $dbr->select('wikivisual_article_status', array('*'), array('article_id' => $articleId), __METHOD__);
		foreach ($rows as $row) {
			$articles[] = get_object_vars($row);
		}
		return $articles;
	}
	
	private function dbGetStagingDir($articleId) {
		$dbr = self::getDB('read');
		$rows = $dbr->select('wikivisual_article_status', array('*'), array('article_id' => $articleId), __METHOD__);
		foreach ($rows as $row) {
			$rowAssocArr = get_object_vars($row);
			return $rowAssocArr['staging_dir'];
		}
		return null;
	}
	
	/**
	 * List articles on S3
	 */
	private function getS3Articles(&$s3, $bucket) {
		$list = $s3->getBucket($bucket);
	
		// compile all the articles into a list of files/zips from s3
		$articles = array();
		foreach ($list as $path => $details) {
			// match string: username/1234.zip
			if (!preg_match('@^([a-z][-._0-9a-z]{0,30})/([0-9]+)\.zip$@i', $path, $m)) {
				continue;
			}
	
			list(, $user, $id) = $m;
			$id = intval($id);
			if (!$id) continue;
	
			if (in_array($user, self::$excludeUsers) 	// don't process anything in excluded people
				|| preg_match('@^[0-9]+$@', $user)) {	// don't allow usernames that are all digits
				continue;
			}
	
			// process the list of media files into a list of articles
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
			list($err, $files) = self::getUnzippedFiles($dir);
// 			$fileExts = array_unique(array_merge(self::$imgExts, self::$videoExts));
// 			$upcase = array_map('strtoupper', self::$fileExts);
// 			$exts = array_merge($upcase, self::$fileExts);
// 			$ret = glob($dir . '/*.{' . join(',', $exts) . '}', GLOB_BRACE);
// 			if (false === $ret) {
// 				$err = 'no files unzipped';
// 			} else {
// 				$files = $ret;
// 			}
		}
		return array($err, $files);
	}
	
	private static function getUnzippedFiles($dir) {
		$fileExts = array_unique(array_merge(self::$imgExts, self::$videoExts));
		$upcase = array_map('strtoupper', $fileExts);
		$exts = array_merge($upcase, $fileExts);
		$ret = glob($dir . '/*.{' . join(',', $exts) . '}', GLOB_BRACE);
		if (false === $ret) {
			$err = 'no files unzipped';
		} else {
			$files = $ret;
		}
		return array($err, $files);
	}
	
	private static function splitSrcMediaFileList($files) {
		foreach($files as $file) {
			$arr = array (
					'name' => basename ( $file ),
					'filename' => $file
			);
			if (array_key_exists ( Utils::getFileExt ( $file ), self::$assocImgExts )) {
				$photoList [] = $arr;
			}
			if (array_key_exists ( Utils::getFileExt ( $file ), self::$assocVideoExts )) {
				$videoList [] = $arr;
			}
		}
		return array($photoList, $videoList);
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
	 * Upload a file to S3
	 */
	public static function postFile(&$s3, $file, $uri, $bucket) {
		$err = '';
		$ret = $s3->putObject(array('file' => $file), $bucket, $uri);
		if (!$ret) {
			$err = "unable to upload $file to S3 in bucket [$bucket]";
		}
		return $err;
	}
	
	
	/**
	 * Download files from S3
	 */
	private function pullFiles($id, &$s3, $prefix, &$files) {
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
	 * Remove tmp directory.
	 */
	private static function safeCleanupDir($dir) {
		$staging_dir = self::$stagingDir;
		if ($dir && $staging_dir && strpos($dir, $staging_dir) === 0) {
            self::i(">>> safeCleanupDir($dir)");
			system("rm -rf $dir");
		}
	}
	
	
	/**
	 * Cleanup and remove all old copies of photos.
	 * If there's a zip file and
	 * a folder, delete the folder.
	 */
	private function doS3Cleanup() { 
		$s3 = new S3 ( WH_AWS_WIKIVISUAL_UPLOAD_ACCESS_KEY, WH_AWS_WIKIVISUAL_UPLOAD_SECRET_KEY );
		$src = $this->getS3Articles ( $s3, self::AWS_BUCKET );
		foreach ( $src as $id => $details ) {
			if ($details ['zip'] && $details ['files']) {
				$uri = $details ['user'] . '/' . $id . '.zip';
				$count = count ( $details ['files'] );
				if ($count <= 1) {
					$files = join ( ',', $details ['files'] );
					self::i("not enough files ($count) to delete $uri: $files");
				} else {
					self::i("deleting $uri");
					$s3->deleteObject ( self::AWS_BUCKET, $uri );
				}
			}
		}
	}
	
	private static $incubationCreators = null; //to cache creators list
	public static function isIncubated($creator) {
		if (!self::$incubationCreators) {
			$val = ConfigStorage::dbGetConfig(self::ADMIN_CONFIG_INCUBATION_LIST);
			$incubationCreators = preg_split('@\s+@', trim($val));
		}
		return array_search($creator, $incubationCreators);
	}
	
	private function isHybridMedia($articleStatusRow) {
		if (is_null($articleStatusRow)) return NULL;
		return $articleStatusRow['vid_cnt'] > 0 && $articleStatusRow['photo_cnt'] > 0;
	}

	private function isReadyForWikiTextProcessing($articleStatusRow) {
		$article = $this->dbGetArticle($articleStatusRow['article_id']);
		if (count($article) == 0) {
			return null;
		}
		$a = $article[0];
		
		if ($a[vid_cnt] > 0 && !isset($a['vid_processed'])) return false; 
		if ($a[photo_cnt] > 0 && !isset($a['photo_processed'])) return false; 
		return true;
	}
	
	private function processTranscodingArticles() {
		$articles = $this->dbGetTranscodingArticles();
		foreach ($articles as $a) {
			unset($err);
			
			$aid = $a['article_id'];
			$creator = $a['creator'];
            self::d("processTranscodingArticles, processing article: ". $aid);
			
			$retCode = 0;
			$msg = '';
			
			$isHybridMedia = $this->isHybridMedia($a);
			if (is_null($isHybridMedia)) {
				$err = 'Coluld not get article row data!';
                self::d("processTranscodingArticles ". $err);
			}
			
            self::d("Handle video articles by mp4Transcoder->processTranscodingArticle($aid, $creator);");	
            self::d("a['vid_cnt']:". $a['vid_cnt'] .", a['vid_processed']:". $a['vid_processed'] .", err:". $err);
			if (!$err
				&& isset($a['vid_cnt']) && $a['vid_cnt'] > 0 
				&& empty($a['vid_processed'])) { //handle video articles
				list($retCode, $msg) = $this->mp4Transcoder->processTranscodingArticle($aid, $creator);

                self::d("mp4Transcoder->processTranscodingArticle ret code : ". $retCode);
				if ($retCode == self::STATUS_ERROR) {
					$err = $msg;
				} elseif ($retCode == self::STATUS_COMPLETE) {
					$this->updateArticleStatusMediaProcessed('vid_processed', $aid);
				}
			}
			//presently no need to do this processing for imageonly as their transcoding process
			//is synchronous and not a real transcoding
            $stageDir = '';
			if ($err) {
				self::dbSetArticleProcessed($aid, $creator, $err, '', '', 0, 0, 0, 0, self::STATUS_ERROR, 0, ''); 
			} elseif (self::isStillTranscoding($aid)) {
                self::d("isStillTranscoding is true so skip this article ". $aid); 
				continue;
			} else {
				if ($this->isReadyForWikiTextProcessing($a)) {
                    self::d("isReadyForWikiTextProcessing result = ready");
					$photoCnt = $a['photo_cnt'] ? $a['photo_cnt'] : 0;
					$vidCnt = $a['vid_cnt'] ? $a['vid_cnt'] : 0;
					list($err, $warning, $url, $numSteps, $replaced) = $this->processWikitext($aid, $creator, $photoCnt, $vidCnt, $stageDir);
					$this->updateArticleStatusHybridMediaProcessed($aid, $err, $warning, $url, $photoCnt, $vidCnt, $replaced);
                    self::d("processTranscodingArticles after processWikitext err=$err, warning=$warning, url=$url, numSteps=$numSteps, replaced=$replaced");
                    
                    if (!empty($stageDir)) {
                    	self::safeCleanupDir($stageDir);
                    }
				} else {
					$err = 'Unknown error occured while checking, if ready for wiki text processing!';
					self::dbSetArticleProcessed($aid, $creator, $err, '', '', 0, 0, 0, 0, self::STATUS_ERROR, 0, ''); //TODO: do update here
                    self::d("isReadyForWikiTextProcessing not ready, err=$err");
				}
			}
		}
	}
	
	
	/**
	 * Process images on S3 instead of from the images web server dir
	 */
	private function processS3Media() {
		$s3 = new S3 ( WH_AWS_WIKIVISUAL_ACCESS_KEY, WH_AWS_WIKIVISUAL_SECRET_KEY );
		
		// $file = '/tmp/whp';
		// if (!file_exists($file)) {
		$articles = $this->getS3Articles ( $s3, self::AWS_BUCKET );
		$processed = $this->dbGetArticlesUpdatedAll ();
		// $out = yaml_emit(array($articles, $processed));
		// file_put_contents($file, $out);
		// } else {
		// list($articles, $processed) = yaml_parse(file_get_contents($file));
		// }
		
		// process all articles
        $articlesProcessed = 0;
		foreach ( $articles as $id => $details ) {
			$debug = self::$debugArticleID;
			if ($debug && $debug != $id)
				continue;
			
			if (@$details ['err']) {
				if (! $processed [$id]) {
					self::dbSetArticleProcessed ( $id, $details ['user'], $details ['err'], '', '', 0, 0, 0, 0, self::STATUS_ERROR, 0, '');
				}
				continue;
			}
			// if article needs to be processed again because new files were
			// uploaded, but article has already been processed, we should
			// just flag as a retry attempt
			if (! $debug 
				&& isset ( $processed [$id] ) 
				&& ! $processed [$id] ['retry'] 
				&& $processed [$id] ['processed'] < $details ['time']) {
				if ($details ['time'] >= self::REPROCESS_EPOCH) {
					$processed [$id] ['retry'] = 1;
					$processed [$id] ['error'] = '';
				} else {
                    self::d("don't reprocess stuff from before a certain point in time: Article id :". $id);
					// don't reprocess stuff from before a certain point in time
					continue;
				}
			}
			
			// if this article was already processed, and nothing about its
			// images has changes, and it's not set to be retried, don't
			// process it again
			if (! $debug 
				&& isset ( $processed [$id] ) 
				&& ! $processed [$id] ['retry'] 
				&& $processed [$id] ['processed'] > $details ['time']) {
                self::d("if this article was already processed, and nothing about its images has changes, and it's not set to be retried, don't process it again:". $id .", processed[id]['processed']=". $processed[$id]['processed']. " > details['time']=". $details['time']);
				continue;
			}
			
			// if article is not on Wikiphoto article exclude list
			if (WikiPhoto::checkExcludeList ( $id )) {
				$err = 'Article was found on Wikiphoto EXCLUDE list';
				self::dbSetArticleProcessed ( $id, $details ['user'], $err, '', '', 0, 0, 0, 0, self::STATUS_ERROR, 0, '');
				continue;
			}
			
			// pull zip file into staging area
			$stageDir = '';
			$photoList = array ();
			$videoList = array();
			if ($details ['zip']) { 
				$prefix = $details ['user'] . '/';
				$zipFile = $id . '.zip';
				$files = array (
						$zipFile 
				);
				list ( $err, $stageDir ) = $this->pullFiles ( $id, $s3, $prefix, $files );
				if (! $err) {
					list ( $err, $files ) = $this->unzip ( $stageDir, $zipFile );
				}
				if (! $err) {
					list($photoList, $videoList) = self::splitSrcMediaFileList($files);
				}
			} else { // no zip -- ignore
				continue;
			}
			
			if (! $err && in_array ( $id, self::$excludeArticles )) {
				$err = 'Forced skipping this article because there was an repeated error when processing it';
			}
			self::d("PhotoList size ". count($photoList) . ", VideoList size ". count($videoList) ." err=$err");
				
			$isHybridMedia = false;
			$photoCnt = 0;
			$vidCnt = 0;
			if (! $err) {
				$warning = @$details ['warning'];
				$photoCnt = count($photoList);
				$vidCnt = count($videoList);
				
				self::dbSetArticleProcessed ( $id, $details ['user'], $err, $warning, '', $vidCnt, $photoCnt, 0, 0, self::STATUS_PROCESSING_UPLOADS, 0, $stageDir);
				$isHybridMedia = $photoCnt > 0 && $vidCnt > 0;
                self::d("isHybridMedia=$isHybridMedia");
				//start processing uploads
				if ($photoCnt > 0 && $vidCnt <= 0) {
					list ( $err, $title, $warning, $url, $photoCnt, $replaced ) = 
						$this->imageTranscoder->processMedia($id, $details ['user'], $photoList, $warning, $isHybridMedia);
					$this->updateArticleStatusPhotoProcessed($id, $err, $warning, $url, $photoCnt, $replaced, true);
				} else {
					if (!$err && $vidCnt > 0 ) {
                        self::d("Processing mp4Transcoder->processMedia");
						list ( $err, $url, $status ) = $this->mp4Transcoder->processMedia($id, $details ['user'], $videoList, $warning, $isHybridMedia);
						$this->updateArticleStatusVideoTranscoding($id, $err, $warning, $url, $status);
					}
				}

                $articlesProcessed ++;
			} else {
				self::dbSetArticleProcessed ( $id, $details ['user'], $err, '', '', 0, 0, 0, 0, self::STATUS_ERROR, 0, '');
			}
			
			//don't cleanup if isHybridMedia is present and zip file contains images.
			if (!empty($stageDir) && $isHybridMedia === false) {
				self::safeCleanupDir ( $stageDir );
			}
			
			$titleStr = ($title ? ' (' . $title->getText () . ')' : '');
			$errStr = $err ? ', err=' . $err : '';
			$mediaCount = count ( $files );
			self::i("processed: {$details['user']}/$id$titleStr original mediaFilesCount=$mediaCount $errStr");
            if (self::$DEBUG !== false && self::$exitAfterNumArticles > 0 && $articlesProcessed >= self::$exitAfterNumArticles) {
                self::d("articlesProcessed $articlesProcessed >= self::\$exitAfterNumArticles ". self::$exitAfterNumArticles .", hence stopping further processing of articles if there are any.");
                break;
            }
		}
	}
	
	private function processWikitext($aid, $creator, $photoCnt, $vidCnt, &$stagingDir) {
        self::d("processWikitext aid=$aid, creator=$creator, photoCnt=$photoCnt, vidCnt=$vidCnt");
		//get all essential data from related media handlers
		$photoList = null;
		if ($photoCnt > 0) {
			$stagingDir = $this->dbGetStagingDir($aid);
			if ($stagingDir) {
				list($err, $files) = self::getUnzippedFiles($stagingDir);
				list($photoList, ) = self::splitSrcMediaFileList($files);
			}
            self::d("stagingDir=$stagingDir, photoCnt=$photoCnt > 0 actual file cnt=". count($photoList));
		}
		
		$videoList = null;
		if ($vidCnt > 0) {
			$videoList = $this->mp4Transcoder->dbGetTranscodingArticleJobs($aid);
		}
	
        self::d("Calling mp4Transcoder->processHybridMedia($aid, $creator, $videoList, $photoList);");
		$ret = $this->mp4Transcoder->processHybridMedia($aid, $creator, $videoList, $photoList);
		
		return $ret;
	}
	
			
	public function main() {
		date_default_timezone_set('America/Los_Angeles');
		
		if (!$assocVideoExts) {
			self::$assocVideoExts = Utils::arrToAssoArr(self::$videoExts);
			self::$assocImgExts = Utils::arrToAssoArr(self::$imgExts);
		}
		
		$opts = getopt ( 'bcd:e:f:', array (
				'backup',
				'cleanup',
				'staging-dir:',
				'exclude-article-id:',
				'force:' 
			) );
		
// 		$doBackup = isset ( $opts ['b'] ) || isset ( $opts ['backup'] );
		$doCleanup = isset ( $opts ['c'] ) || isset ( $opts ['cleanup'] );
		
		self::$stagingDir = @$opts ['d'] ? @$opts ['d'] : @$opts ['staging-dir'];
		if (empty ( self::$stagingDir ))
			self::$stagingDir = self::DEFAULT_STAGING_DIR;
		
		self::$debugArticleID = @$opts ['f'] ? @$opts ['f'] : @$opts ['force'];
		
		$skipID = @$opts ['e'] ? $opts ['e'] : @$opts ['exclude-article-id'];
		if ($skipID)
			self::$excludeArticles [] = $skipID;
		
		if ($_ENV ['USER'] != 'apache') {
			self::e("script must be run as part of wikivisual-process-media.sh");
			exit ();
		}
		
		Misc::loginAsUser ( self::MEDIA_USER );
		
// 		if ($doBackup) {
// 			$this->doS3Backup ();
// 		} else
		if ($doCleanup) {
			$this->doS3Cleanup ();
		} else {
			$this->mp4Transcoder = new Mp4Transcoder();
			$this->imageTranscoder = new ImageTranscoder();
				
			$this->processTranscodingArticles (); //1st take care of articles which are under processing.
			$this->processS3Media ();
		}
	}
}
$wmt = new WikiVisualTranscoder();
$wmt->main();
