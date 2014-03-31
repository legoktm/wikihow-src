<?php
/**
 * Usage: php wikiphotoInfo.php 
 *
 * TABLE:
 * create table image_dims (
 * id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
 * dir varchar(255),
 * filename varchar(255),
 * width INT,
 * height INT
 * );
 */


require_once('commandLine.inc');

global $IP;
require_once("$IP/extensions/wikihow/common/S3.php");

class WikiPhotoInfo {
	const PHOTO_LICENSE = 'cc-by-sa-nc-2.5-self';
	const PHOTO_USER = 'Wikiphoto';
	const IMAGES_DIR = '/usr/local/pfn/images';
	const AWS_BUCKET = 'wikiphoto';
	const AWS_BACKUP_BUCKET = 'wikiphoto-backup';
	const DEFAULT_STAGING_DIR = '/usr/local/wikihow/wikiphoto';
	const IMAGE_PORTRAIT_WIDTH = '220px';
	const IMAGE_LANDSCAPE_WIDTH = '300px';
	
	static $debugArticleID = '',
		$stepsMsg,
		$imageExts = array('png', 'jpg'),
		$excludeUsers = array('old', 'backup'),
		$enlargePhotoUsers = array(),
		$stagingDir = '',
		$excludeArticles = array(
			57203, 1251223, 354106,
		);

	/**
	 * Generate a string of random characters
	 */
	private static function genRandomString($chars = 20) {
		$str = '';
		$set = array(
			'0','1','2','3','4','5','6','7','8','9',
			'a','b','c','d','e','f','g','h','i','j','k','l','m',
			'n','o','p','q','r','s','t','u','v','w','x','y','z',
			'A','B','C','D','E','F','G','H','I','J','K','L','M',
			'N','O','P','Q','R','S','T','U','V','W','X','Y','Z',
		);
		for ($i = 0; $i < $chars; $i++) {
			$r = mt_rand(0, count($set) - 1);
			$str .= $set[$r];
		}
		return $str;
	}
	
	private function listS3Images() {
		$s3 = new S3(WH_AWS_WIKIPHOTO_ACCESS_KEY, WH_AWS_WIKIPHOTO_SECRET_KEY);
		$bucket_name = self::AWS_BUCKET;
		$prefix = null;
		$marker = null;
		//$marker = 'paupau/257175/Make Chocolate-1.JPG';
		$maxKeys = 1;
		//$maxKeys = null;
		$delimiter = null;
		$returnCommonPrefixes = false;
		
		$buckets = $s3->getBucket($bucket_name,$prefix,$marker,$maxKeys,$delimiter,$returnCommonPrefixes);
		
		print "number of buckets: ". count($buckets) ."\n";
		
		foreach ($buckets as $path => $details) {
		
			// match string: username/(1234.zip or 1234/*.jpg)
			if (!preg_match('@^([a-z][-._0-9a-z]{0,30})/([0-9]+)(\.zip|/.+)$@i', $path, $m)) {
				continue;
			}
			
			list(, $user, $id, $ending) = $m;
			$id = intval($id);
			if (!$id) continue;

/*			if (in_array($user, self::$excludeUsers) // don't process anything in excluded people
				|| preg_match('@^[0-9]+$@', $user)) // don't allow usernames that are all digits
			{
				continue;
			}
*/			

			$prefix = $user . '/' . $id;
			$files = array($ending);
			
			list($err, $stageDir) = self::pullFiles($id, $s3, $prefix, $files);
		}

	}
	
	
	/**
	 * Download files from S3
	 */
	private static function pullFiles($id, &$s3, $prefix, &$files) {
		$err = '';
		$dir = self::DEFAULT_STAGING_DIR;
		
		if (!$dir) {
			$err = 'unable to create dir: ' . $dir;
			return array($err, '');
		}
		
		foreach ($files as &$file) {
			$aws_file = $prefix . $file;
			
			if ($file == '.zip') $file = $id.$file;			
			
			$file = preg_replace('@^/@','',$file);
			$file = preg_replace('@/@', '-', $file);
			
			$local_file = $dir . '/' . $file;
			
			$ret = $s3->getObject(self::AWS_BUCKET, $aws_file, $local_file);
			if (!$ret || $ret->error) {
				$err = "problem retrieving file from S3: s3://" . self::AWS_BUCKET . "/$aws_file";
				break;
			}
			else {
				$filetype = substr($file,-4);
				if ($filetype == '.zip') {
					list($err, $files) = self::unzip(self::DEFAULT_STAGING_DIR,$file);
					if (!$err) {
						self::getImageSizes($prefix,$files,true);
					}
				}
				else {
					$files = array(self::DEFAULT_STAGING_DIR.'/'.$file);
					self::getImageSizes($prefix,$files,false);
				}
			}
		}
		return array($err, $dir);
	}
	
	private static function getImageSizes($remote_dir,$files,$b_zipped) {
		foreach ($files as $file) {
			self::getImageSize($remote_dir,$file,$b_zipped);
			
			//remove local file
			unlink($file);
		}
	}
	
	private static function getImageSize($remote_dir,$file,$b_zipped) {
		//grab the width/height
		$size = getimagesize($file);
		$width = $size[0];
		$height = $size[1];
		
		$filename = preg_replace('@'.self::DEFAULT_STAGING_DIR.'/@','',$file);
		
		//now save the data to our table
		self::saveDbImageData($remote_dir,$filename,$width,$height,$b_zipped);
		
		//output
		print 'file: '.$remote_dir.'/'.$filename.' ('.$width.'x'.$height.')'."\n";
	}
	
	private static function saveDbImageData($dir, $file, $width, $height, $b_zipped) {
		$dbw = wfGetDB(DB_MASTER);
		$sql = 'INSERT INTO image_dims (dir,filename,width,height,zip) VALUES (
				'.$dbw->addQuotes($dir).', 
				'.$dbw->addQuotes($file).', 
				'.intval($width).',
				'.intval($height).',
				'.intval($b_zipped).')';
		$dbw->query($sql, __METHOD__);
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
	
	
	/**
	 * Entry point for main processing loop
	 */
	public static function main() {
		self::listS3Images();
	}

}

WikiPhotoInfo::main();

