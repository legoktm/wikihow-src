<?php
//
// Copy all files in a wikiHow images dir to Rackspace cloud files.
//

require_once('commandLine.inc');
require_once("$IP/extensions/wikihow/common/php-cloudfiles/cloudfiles.php");

class CopyFilesToRackspaceCloudFiles {

	const DEST_PATH_PREFIX = 'images',
		SRC_PATH = '/var/www/html/wiki/images',
		CLOUD_LIST_BATCH_SIZE = 10000;

	static $epoch = 0,
		$bucketName = '';

	// cache the auth params for 1 day to make cloud access faster
	private static function getAuth() {
		global $wgMemc;

		$cacheKey = wfMemcKey('rscloudauth');
		$auth = new CF_Authentication(WH_RSCLOUD_USERNAME, WH_RSCLOUD_API_KEY);
		$creds = $wgMemc->get($cacheKey);
		if (!$creds) {
			# $auth->ssl_use_cabundle();  # bypass cURL's old CA bundle
			$auth->authenticate(); // makes a call to a remote web server
			$creds = $auth->export_credentials();
			$wgMemc->set($cacheKey, $creds);
		} else {
			$auth->load_cached_credentials($creds['auth_token'],
				$creds['storage_url'], $creds['cdnm_url']);
		}
		return $auth;
	}

	private static function getBucket($bucketName) {
		$auth = self::getAuth();

		$conn = new CF_Connection($auth);
		try {
			$images = $conn->get_container($bucketName);
		} catch (NoSuchContainerException $e) {
			$images = $conn->create_container($bucketName);
			$uri = $images->make_public();
		}
		return $images;
	}

	private static function copyAllFiles() {
print "not used\n";
exit;
		$files = self::listFilesFlat($path);
		$bucket = self::getBucket( self::$bucketName );
		foreach ($files as $file) {
			$src = $path . '/' . $file;
			self::copyFile($bucket, $src, $file);
		}
	}

	private static function syncFiles() {
		$file_cache_local = 'cache_local_list.txt';
		$file_cache_remote = 'cache_remote_list.txt';
		$contents = @file_get_contents($file_cache_local);
		if ($contents) $localFiles = unserialize($contents);
		if (!@$localFiles) {
			$localFiles = self::listFiles( self::SRC_PATH );
			file_put_contents($file_cache_local, serialize($localFiles));
		}

		$bucket = self::getBucket( self::$bucketName );

		$contents = @file_get_contents($file_cache_remote);
		if ($contents) $remoteFiles = unserialize($contents);
		if (!$remoteFiles) {
			$remoteFiles = self::listCloudFiles($bucket);
			//file_put_contents($file_cache_remote, serialize($remoteFiles));
		}

		self::updateRemoteTree($bucket, $localFiles, $remoteFiles);
	}

	private static function listCloudFiles($bucket) {
		$start = 0;
		$tree = array();
		do {
			$files = $bucket->list_objects(self::CLOUD_LIST_BATCH_SIZE, $start, self::DEST_PATH_PREFIX . '/');
			self::buildCloudTree($tree, $files);
		} while(count($files) == self::CLOUD_LIST_BATCH_SIZE);
		return $tree;
	}

	private static function buildCloudTree(&$tree, $files) {
		foreach ($files as $path) {
			// add a cloud tree leaf node
			$dirs = split('/', $path);
			if (count($dirs) && $dirs[0] == self::DEST_PATH_PREFIX) {
				array_shift($dirs);
			}
			if (!count($dirs)) continue;
			$last = count($dirs) - 1;
			$node = &$tree;
			foreach ($dirs as $i=>$dir) {
				if ($i == $last) break;
				if (!isset($node[$dir])) $node[$dir] = array();
				$node = &$node[$dir];
			}
			$node[ $dirs[$last] ] = 1;
		}
	}

	private static function updateRemoteTree($bucket, $local, $remote, $dir_path='') {
		foreach ($local as $file => $details) {
			$new_path = $dir_path !== '' ? $dir_path . '/' . $file : $file;
			if (is_array($details)) {
				if (!isset($remote[$file]) || !is_array($remote[$file])) {
					self::copyTree($bucket, $details, $new_path);
				} else {
					self::updateRemoteTree($bucket, $details, $remote[$file], $new_path);
				}
			} else {
				if (!isset($remote[$file]) || self::$epoch < $details) {
					self::copyTree($bucket, false, $new_path);
				}
			}
		}
	}

	private static function copyTree($bucket, $dir, $path) {
		if (is_array($dir)) {
			foreach ($dir as $file => $details) {
				self::copyTree($bucket, $details, $path . '/' . $file);
			}
		} else {
			$src = self::SRC_PATH . '/' . $path;
			$dest = self::DEST_PATH_PREFIX . '/' . $path;
			self::copyFile($bucket, $src, $dest);
			//print "copied:$src to $dest\n";
		}
	}

	private static function listFiles($base_path) {
		$files = scandir($base_path);
		if (!$files) return array();

		$results = array();
		foreach ($files as $path) {
			if (strpos($path, '.') === 0) continue;
			$new_base_path = $base_path . '/' . $path;
			if (is_dir($new_base_path)) {
				$result = self::listFiles($new_base_path);
			} else {
				$result = filemtime($new_base_path);
			}
			$results[$path] = $result;
		}

		return $results;
	}

	private static function listFilesFlat($base_path, $rel_path = '') {
		$files = scandir($base_path);
		if (!$files) return array();

		$results = array();
		foreach ($files as $path) {
			if (strpos($path, '.') === 0) continue;
			$new_base_path = $base_path . '/' . $path;
			$new_rel_path = $rel_path === '' ? $path : $rel_path . '/' . $path;
			if (is_dir($new_base_path)) {
				$new_results = self::listFilesFlat($new_base_path, $new_rel_path);
				$results = array_merge($results, $new_results);
			} else {
				$results[] = $new_rel_path;
			}
		}

		return $results;
	}

	private static function copyFile($bucket, $src, $dest) {
		#$fname = "/home/user/photos/birthdays/birthday1.jpg";
		#$size = (float) sprintf("%u", filesize($fname));
		#$fp = open($fname, "r");
		#$bday->write($fp, $size);

		try {
			$upload = $bucket->create_object($dest);
			$upload->load_from_filename($src);
			print $upload->public_uri() . "\n";
		} catch (InvalidResponseException $e) {
			// missing Content-Type header
			print "skip: $dest\n";
		}
	}

	private static function doCopy() {
		//$file = 'wikihow.jpg';
		//$bucket = self::getBucket( self::$bucketName );
		//self::copyFile($bucket, self::SRC_PATH . '/' . $file, self::DEST_PATH_PREFIX . '/' . $file); 
	}

	public static function main() {
		$opts = getopt('', array('epoch:', 'bucket:'));
		// if no epoch is passed in, only add new files to RS cloud store
		self::$epoch = intval(@$opts['epoch']);
		self::$bucketName = @$opts['bucket'];
		if (!self::$bucketName) self::$bucketName = 'images_dev';

		self::syncFiles();
	}

}

CopyFilesToRackspaceCloudFiles::main();

