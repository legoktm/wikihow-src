<?php

if (!defined('MEDIAWIKI')) die();

class ImageUploadHandler extends UnlistedSpecialPage {
	private $executeAsSpecialPage;
	private $maxFilesize;

	public function __construct() {
		global $wgMaxUploadSize;

		parent::__construct('ImageUploadHandler');
		$this->executeAsSpecialPage = false;
		$this->maxFilesize = $wgMaxUploadSize;	
	}

	public function execute() {
		global $wgUser, $wgOut, $wgRequest;

		if ($wgUser->isBlocked()) {
			$wgOut->blockedPage();
			return;
		}

		$wgOut->setArticleBodyOnly(true);

		$this->executeAsSpecialPage = true;

		$result = array();
		$delImage = $wgRequest->getVal('delete');
		if ($delImage) {
			$result = $this->deleteImage($delImage);
		} else {
			$result = $this->uploadImage();
		}
		echo json_encode($result);
	}

	private static function splitFilenameExt($name) {
		preg_match('@^(.*)(\.([^.]+))?$@U', $name, $m);
		return array($m[1], $m[3]);
	}

	/**
	 * Formats bytes into human readable form.
	 * E.g.: formatBytes(10241233) == '9.8 MB'
	 */
	private static function formatBytes($bytes) {
		$base = log($bytes) / log(1024);
		$suffixes = array(' bytes', ' kB', ' MB', ' GB', ' TB');
		if ($bytes < pow(1024, 4))
			return round(pow(1024, $base - floor($base)), 1) . $suffixes[floor($base)];
		else
			return round(pow(1024, $base - 4), 1) . $suffixes[4];
	}

	protected function addToDB($data) {
		global $wgUser;

		$uci_row_data = array(
			'uci_image_name' => $data['titleDBkey'],
			'uci_image_url' => $data['fileURL'],
			'uci_user_id' => intval($wgUser->getId()),
			'uci_user_text' => $wgUser->getName(),
			'uci_timestamp' => $data['timestamp'],
			'uci_article_id' => intval($data['titleArtID']),
			'uci_article_name' => $data['fromPage']);

		$dbw = wfGetDB(DB_MASTER);
		$dbw->begin();
		$dbw->insert(
			'user_completed_images',
			$uci_row_data,
			__METHOD__,
			array());
		$dbw->commit();
	}

	protected function insertImage($name, $mwname, $result) {
		global $wgRequest, $wgImageMagickConvertCommand, $wgServer;

		if (!$result)
			$result = array();
		elseif ($result['error'])
			return $result;

		$fromPage = $wgRequest->getVal('viapage');

		if (!empty($mwname) && !empty($name)) {
			$name = trim(urldecode($name));

			$dateTime = new DateTime();
			$mwDate = wfTimestamp(TS_MW); // Mediawiki timestamp: 'YmdHis'

			list($first, $ext) = self::splitFilenameExt($name);
			$ext = strtolower($ext);
			$validExts = array('GIF', 'JPG', 'JPEG', 'PNG');

			if (!in_array(strtoupper($ext), $validExts)) {
				$result['error'] = 'Error: Invalid file extension ' . strtoupper($ext) . '. Valid extensions are:';
				foreach($validExts as $validExt) {
					$result['error'] .= ' ' . strtoupper($validExt);
				}
				$result['error'] .= '.';
				return $result;
			}

			$saveName = false;
			$titleExists = false;
			$suffixNum = 1;

			while (!$saveName || $titleExists) {
				$saveName = 'User Completed Image ' . $fromPage . ' ' . $dateTime->format('Y.m.d H.i.s') . ' ' . $suffixNum . '.' . $ext;

				$title = Title::makeTitleSafe(NS_IMAGE, $saveName);

				$newFile = true;
				$titleExists = $title->exists();

				$suffixNum++;
			}

			$temp_file = new TempLocalImageFile(Title::newFromText($mwname, NS_IMAGE), RepoGroup::singleton()->getLocalRepo());
			if (!$temp_file || !$temp_file->exists()) {
				$result['error'] = 'Error: A server error has occurred. Please try again.';
				return $result;
			}

			// Image orientation is a bit wonky on some mobile devices; use ImageMagick's auto-orient to try fixing it.
			$tempFilePath = $temp_file->getPath();
			$cmd = $wgImageMagickConvertCommand . ' ' . $tempFilePath . ' -auto-orient ' . $tempFilePath;
			exec($cmd);

			// Use a CC license
			$comment = '{{Self}}';

			$file = new LocalFile($title, RepoGroup::singleton()->getLocalRepo());

			$file->upload($tempFilePath, $comment, $comment);
			if (!$file || !$file->exists()) {
				$result['error'] = 'Error: A server error has occurred. Please try again.';
				return $result;
			}
			$temp_file->delete('');

			$fileTitle = $file->getTitle();
			$fileURL = $file->getUrl();

			$thumbURL = '';
			$thumb = $file->getThumbnail(200, -1, true, true);
			if (!$thumb) {
				$result['error'] = 'Error: A server error has occurred. Please try again.';
				$file->delete('');
				return $result;
			}
			$thumbURL = $thumb->getUrl();

			$result['titleText'] = $fileTitle->getText();
			$result['titleDBkey'] = substr($fileTitle->getDBkey(), 21); // Only keep important info
			$result['titlePreText'] = '/' . $fileTitle->getPrefixedText();
			$result['titleArtID'] = $fileTitle->getArticleID();
			$result['timestamp'] = $mwDate;
			$result['fromPage'] = $wgRequest->getVal('viapage');
			$result['thumbURL'] = $thumbURL;
			$result['fileURL'] = $wgServer . $fileURL;
		}

		return $result;
	}

	protected function uploadImage() {
		global $wgRequest, $wgOut, $wgUser, $wgGroupPermissions;

		header('Content-type: application/json');

		$result = array();

		$fromPage = $wgRequest->getVal('viapage');
		$title = Title::newFromText($fromPage, NS_MAIN);

		if (!$title->exists()) {
			$result['error'] = "Error: No article $fromPage found to link image.";
			return $result;
		}

		$tempname = TempLocalImageFile::createTempFileName($fromPage);
		$file = new TempLocalImageFile(Title::newFromText($tempname, NS_IMAGE), RepoGroup::singleton()->getLocalRepo());

		$name = $wgRequest->getFileName('wpUploadImage', '', '');

		$file->upload($wgRequest->getFileTempName('wpUploadImage'), '', '');
		$filesize = $file->getSize();
		if (!$filesize) {
			$result['error'] = 'Error: We didn\'t get a file. Please try again.';
			return $result;
		} else if ($file->getMediaType() != 'BITMAP') {
			$result['error'] = 'Error: Only images are accepted.';
			return $result;
		} elseif ($filesize > $this->maxFilesize) {
			$result['error'] = 'Error: Your file is too big (' . ImageUploadHandler::formatBytes($filesize) . '). Max file size is ' . ImageUploadHandler::formatBytes($this->maxFilesize) . '.';
			return $result;
		}

		$prevPermissions = $wgGroupPermissions['*']['upload'];
		$wgGroupPermissions['*']['upload'] = true;
		
		$result = $this->insertImage($name, $tempname, $result);

		$wgGroupPermissions['*']['upload'] = $prevPermissions;

		if (!$result['error']) {
			// Successfully uploaded; add to DB
			$this->addToDB($result);
		}

		return $result;
	}

	protected function deleteImage($imgName) {
		global $wgUser;

		$localRepo = RepoGroup::singleton()->getLocalRepo();
		$file = $localRepo->findFile($imgName);
		if (!$file || !$file->exists()) {
			$result['error'] = 'Error: File not found.';
			return $result;
		}

		$fileTitle = $file->getTitle();
		$comment = 'UCI undo';
		$imgDBKey = substr($fileTitle->getDBkey(), 21);

		$userGroups = $wgUser->getGroups();
		if (!in_array('staff', $userGroups) && $wgUser->getName() != "G.bahij") {
			// If not staff, make sure the user deleting their own image and not someone else's
			$userID = $wgUser->getID();
			$userDBKey = $userID > 0 ? 'uci_user_id' : 'uci_user_text';
			$userDBVal = $userID > 0 ? $userID : $wgUser->getName();

			$dbr = wfGetDB(DB_SLAVE);
			$res = $dbr->select(
				'user_completed_images',
				array('uci_user_id', 'uci_user_text'),
				array('uci_image_name' => $imgDBKey),
				__METHOD__);
			if ($res->numRows() == 0) {
				$result['error'] = 'Could not find image in database.';
				return $result;
			}
			$user = $res->fetchRow();

			if ($user[$userDBKey] != $userDBVal) {
				$result['error'] = 'You do not have permission to delete this image.';
				return $result;
			}
		} else {
			$comment = '[Staff] ' . $comment;
		}

		$file->delete($comment);
		$dbw = wfGetDB(DB_MASTER);
		$dbw->begin();
		$dbw->delete(
			'user_completed_images',
			array('uci_image_name' => $imgDBKey),
			__METHOD__);
		$dbw->commit();

		$result['success'] = 1;

		return $result;
	}
}

class TempLocalImageFile extends LocalFile {
	public static function createTempFileName($pageUrl) {
		$date = new DateTime();
		return 'TMP_FILE-User-completed-' . $pageUrl . '-' . $date->format('Y.m.d H.i.s') . '-' . rand(0,65535) . '-image.tmp';
	}

	public function recordUpload2($oldver, $comment, $pageText, $props = false, $timestamp = false) {
		if (!$props) {
			$virtURL = $this->getVirtualUrl();
			$props = $this->repo->getFileProps($virtURL);
		}
		$this->setProps($props);
		$this->purgeThumbnails();
		$this->saveToCache();
		return true;
	}

	public function upgradeRow() {}

	public function doDBInserts() {}
}
