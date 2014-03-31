<?php

// script to regenerate thumbnails for an image and clear then from caches
require_once( __DIR__ . '/../Maintenance.php' );

class RemoveThumbnailsSince extends Maintenance {

	const AWS_BUCKET = 'image_backups';

	public function __construct() {
		parent::__construct();
		$this->addOption( 'title', 'start article id', false, true, 't');
		$this->addOption( 'start', 'start article id', false, true, 's');
		$this->addOption( 'purgelocal', 'clear local thumbnail files', false, false);
		$this->addOption( 'regenerate', 'recreate the thumbs', false, false);
		$this->addOption( 's3', 'clear from s3', false, false);
		$this->addOption( 'cdn', 'clear from cdn', false, false);
		$this->addOption( 'cdnuser', 'cdnetworks username (usually email)', false, true, 'u');
		$this->addOption( 'cdnpass', 'cdnetworks password', false, true, 'p');
	}

	public function execute() {
		global $IP;
		require_once("$IP/extensions/wikihow/common/S3.php");

		define('WH_USE_BACKUP_DB', true);

		$pageIds = array();

		// if provided title, add that to the array
		$titleText = $this->getOption('title');
		if ($titleText) {
			$title = Title::newFromText($titleText, 6);
			$id = $title->getArticleID();
			$pageIds[] = $id;
		}

		// if there is a start, add articles to the array
		$start = $this->getOption('start');
		if ($start) {
			$dbr = wfGetDB(DB_SLAVE);
			$options = array("page_id > $start", "page_namespace = 6");
			$res = $dbr->select("page",
					"page_id",
					$options,
					__FILE__);

			// put them all into an array first
			foreach($res as $row) {
				$pageIds[] = $row->page_id;
			}
		}

		$purgeTitles = array();
		$filesToPurgeFromCDN = array();
		foreach ($pageIds as $pageId) {
			$title = Title::newFromID($pageId);
			$file = wfLocalFile($title);
			if (!$file) {
				continue;
			}

			$userName = $file->getUser("text");
			if ($userName == "Wikiphoto" || $userName == "Maintenance script") {

				if ($this->getOption('s3')) {
					decho("will purge from s3", false, false);
					self::purgeThumbnailsFromS3($file);
				}

				if ($this->getOption('regenerate')) {
					decho("will regenerate thumbnails", $file->getThumbnails(), false);
					WatermarkSupport::recreateThumbnails($file);
					decho("regeneration of thumbnails complete", false, false);
				}

				if ($this->getOption('purgelocal')) {
					decho("will purge local cache thumbnails", $file->getThumbnails(), false);
					$file->purgeCache();

					// get titles that point here so we can purge them and
					// regenerate those pages so the thumbnails come back
					$purgeTitles = array_unique(array_merge($purgeTitles, ImageHelper::getLinkedArticles($title)));
					decho("purging of thumbnails complete", false, false);
				}

				if ($this->getOption('cdn')) {
					$filesToPurgeFromCDN[] = $file;
				}
			}
		}

		//purge the titles that have linked to these images
		foreach( $purgeTitles as $linkedTitle ) {
			decho('will purge', $linkedTitle, false);

			// will now get the parser output to refresh the thumbnail
			// or else cdn may get reset before the thumbnails are regenerated
			$wp = new WikiPage($linkedTitle);
			$wp->doPurge();
			$po = ParserOptions::newFromUser( $wgUser );
			$wp->getParserOutput($po);
		}

		if ($this->getOption('cdn')) {
			decho("will purge from cdn", false, false);
			foreach( $filesToPurgeFromCDN as $file) {
				$this->purgeThumbnailsFromCDNetworks($file);
			}
		}
	}


	// given an image file (local file object) delete the thumbnails from s3
	// functionality gotten from FileRepo.php quickpurgebatch and LocalFile.php purgethumblist
	public static function purgeThumbnailsFromS3($file) {
		$s3 = new S3(WH_AWS_BACKUP_ACCESS_KEY, WH_AWS_BACKUP_SECRET_KEY);

		$thumbnails = $file->getThumbnails();
		$dir = array_shift( $thumbnails );

		// set up the directory path for s3
		$dirPath = str_replace("mwstore://local-backend/local-thumb/", "", $dir);
		$dirPath = "images_en/thumb/".$dirPath;

		foreach ( $thumbnails as $thumbnail ) {
			// Check that the base file name is part of the thumb name
			// This is a basic sanity check to avoid erasing unrelated directories
			if ( strpos( $thumbnail, $file->getName() ) !== false ||
					strpos( $thumbnail, "-thumbnail" ) !== false ) {
				$uri = "{$dirPath}/{$thumbnail}";
				//$path = str_replace("mwstore://local-backend/local-thumb/", "", $item);
				decho("s3 object to delete", $uri, false);
				$s3->deleteObject(self::AWS_BUCKET, $uri);
			}
		}
	}

	// copied from cdn_flush for now because can't import it easily
	// todo don't have two copies of the same function
	public static function doCDnetworksApiCall($params) {
		$url = 'https://openapi.us.cdnetworks.com/purge/rest/doPurge';

		$object = preg_replace('@http://[^/]+@', '', $params['url']);
		if (!preg_match('@^/@', $object)) {
			print "Error: Path should start with '/'\n";
			exit;
		}
		$type = strpos($object, '*') !== false ? 'wildcard' : 'item';
		$double = strpos($object, '**') !== false;
		#if ($type == 'wildcard' && !$double) {
			# Per justin.rodriguez from CDNetworks <support@cdnetworks.com>
			# in March 20, 2013 email to Reuben
			#print "Notice: to clear 'recursively' all subdirs, it's necessary to use /path/fo/**\n";
		#}
		$sendParams = array(
			'pad=pad1.whstatic.com',
			'user=' . $params['user'],
			'pass=' . $params['password'],
			'type=' . $type,
			'path=' . $object, //urlencode($object),
			'output=json',
		);
		$paramStr = join('&', $sendParams);
		$ch = curl_init($url . '?' . $paramStr);
		#curl_setopt($ch, CURLOPT_VERBOSE, true);
		#curl_setopt($ch, CURLOPT_POST, true);
		#curl_setopt($ch, CURLOPT_POSTFIELDS, $paramStr);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

		$ret = curl_exec($ch);
		curl_close($ch);

		return $ret;
	}

	// todo make this function take username and password then it can be static too
	function purgeThumbnailsFromCDNetworks($file) {
		$thumbnails = $file->getThumbnails();
		$dir = array_shift( $thumbnails );

		// set up the directory path for cdnetworks
		$dirPath = str_replace("mwstore://local-backend/local-thumb/", "", $dir);
		$cdnUrl = "/images_en/thumb/".$dirPath.'/*';

		$user = $this->getOption('cdnuser');
		$pass = $this->getOption('cdnpass');

		if ($user && $pass) {
			$params = array($user, $pass, $cdnUrl);
			decho("will call to cdnet with", $cdnUrl, false);
			//$html = self::doCDnetworksApiCall($params);
			decho("response from CDN", $html, false);
		}
	}
}

$maintClass = "RemoveThumbnailsSince";
require_once( RUN_MAINTENANCE_IF_MAIN );
