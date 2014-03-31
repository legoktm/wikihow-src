<?php

if (!defined('MEDIAWIKI')) die();

class ImageUploadHandler extends UnlistedSpecialPage {
	private $maxFilesize;

	public function __construct() {
		global $wgMaxUploadSize;

		parent::__construct('ImageUploadHandler');
		$this->maxFilesize = $wgMaxUploadSize;	
	}

	public function execute() {

		if ($this->getUser()->isBlocked()) {
			throw new PermissionsError( 'imageuploadhandler' );
		}

		$this->getOutput()->setArticleBodyOnly(true);
		header('Content-type: application/json');

		$result = array();
		$toDelete = $this->getRequest()->getVal('delete');
		if ($toDelete) {
			$result = $this->deleteImage($toDelete);
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
		$user = $this->getUser();

		$uci_row_data = array(
			'uci_image_name' => $data['titleDBkey'],
			'uci_image_url' => $data['fileURL'],
			'uci_user_id' => intval($user->getId()),
			'uci_user_text' => $user->getName(),
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

	protected function insertImage($name, $mwname, &$result = array()) {
		global $wgImageMagickConvertCommand, $wgServer;

		$request = $this->getRequest();

		if ($result['error']) {
			return $result;
		}

		$fromPage = $request->getVal('viapage');

		if (!empty($mwname) && !empty($name)) {
			// TODO we might not even need to check the filename for extension
			// it is very likely that the LocalFile class will handle this for us
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

			// TODO refactor this section into it's own method for getting a unique file name
			$titleExists = false;
			$saveName = 'User Completed Image ' . $fromPage . ' ' . $dateTime->format('Y.m.d H.i.s') . ' ' . $suffixNum . '.' . $ext;
			$title = Title::makeTitleSafe(NS_IMAGE, $saveName);
			$titleExists = $title->exists();
			$result['debug'][] = "title exists $titleExists";

			// todo no need to get this file again we just created it above
			// or not even use a temp image at all...
			$temp_file = new LocalFile(Title::newFromText($mwname, NS_IMAGE), RepoGroup::singleton()->getLocalRepo());
			if (!$temp_file || !$temp_file->exists()) {
				$result['error'] = 'tmp file does not exist..';
				return $result;
			}

			// todo - do this part after the single file upload
			// Image orientation is a bit wonky on some mobile devices; use ImageMagick's auto-orient to try fixing it.
			$tempFilePath = $temp_file->getPath();
			$cmd = $wgImageMagickConvertCommand . ' ' . $tempFilePath . ' -auto-orient ' . $tempFilePath;
			exec($cmd);

			// Use a CC license
			$comment = '{{Self}}';

			// todo implement this
			//$title = $this->getFileSaveName();
			$file = new LocalFile($title, RepoGroup::singleton()->getLocalRepo());
			// then get the file path from this file
			// $filePath = $file->getPath();
			// then use it in the uplaod

			$file->upload($tempFilePath, $comment, $comment);
			if (!$file || !$file->exists()) {
				$result['debug'][] = $file;
				$result['error'] = 'uploaded file does not exist.';
				return $result;
			}
			// todo no need to delete the temp file if we don'e have one
			$temp_file->delete('');

			$fileTitle = $file->getTitle();
			$fileURL = $file->getUrl();

			$thumbURL = '';
			$thumb = $file->getThumbnail(200, -1, true, true);
			if (!$thumb) {
				$result['error'] = 'file thumbnail does not exist';
				$file->delete('');
				return $result;
			}
			$thumbURL = $thumb->getUrl();

			// TODO what does this section do?
			$result['titleText'] = $fileTitle->getText();
			$result['titleDBkey'] = substr($fileTitle->getDBkey(), 21); // Only keep important info
			$result['titlePreText'] = '/' . $fileTitle->getPrefixedText();
			$result['titleArtID'] = $fileTitle->getArticleID();
			$result['timestamp'] = $mwDate;
			$result['fromPage'] = $request->getVal('viapage');
			$result['thumbURL'] = $thumbURL;
			$result['fileURL'] = $wgServer . $fileURL;
		}

		return $result;
	}

	protected function uploadImage() {
		global $wgGroupPermissions;

		$request = $this->getRequest();

		$result = array();

		// sanity check on the page to link to
		$fromPage = $request->getVal('viapage');
		$title = Title::newFromText($fromPage, NS_MAIN);
		if (!$title->exists()) {
			$result['error'] = "Error: No article $fromPage found to link image.";
			return $result;
		}

		// create the temporary image..not sure if this is needed though.
		// todo probably don't need this temp file at all
		$tempname = Easyimageupload::createTempFileName($fromPage);
		$tempUser = Easyimageupload::getTempFileUser();
		$file = new LocalFile(Title::newFromText($tempname, NS_IMAGE), RepoGroup::singleton()->getLocalRepo());
		$name = $request->getFileName('wpUploadImage', '', '');

		$file->upload($request->getFileTempName('wpUploadImage'), '', '');
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

		$result['debug'][] = $file;
		$prevPermissions = $wgGroupPermissions['*']['upload'];
		$wgGroupPermissions['*']['upload'] = true;
		
		$this->insertImage($name, $tempname, &$result);

		$wgGroupPermissions['*']['upload'] = $prevPermissions;

		if (!$result['error']) {
			// Successfully uploaded; add to DB
			$this->addToDB(&$result);
		}

		return $result;
	}

	protected function deleteImage($imgName) {
		$user = $this->getUser();

		$localRepo = RepoGroup::singleton()->getLocalRepo();
		$file = $localRepo->findFile($imgName);
		if (!$file || !$file->exists()) {
			$result['error'] = 'Error: File not found.';
			return $result;
		}

		$fileTitle = $file->getTitle();
		$comment = 'UCI undo';
		$imgDBKey = substr($fileTitle->getDBkey(), 21);

		$userGroups = $user->getGroups();
		if (!in_array('staff', $userGroups) && $user->getName() != "G.bahij") {
			// If not staff, make sure the user deleting their own image and not someone else's
			$userID = $user->getID();
			$userDBKey = $userID > 0 ? 'uci_user_id' : 'uci_user_text';
			$userDBVal = $userID > 0 ? $userID : $user->getName();

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
			$userCompletedImage = $res->fetchRow();

			if ($userCompletedImage[$userDBKey] != $userDBVal) {
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
