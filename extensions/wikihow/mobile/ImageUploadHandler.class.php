<?php

if (!defined('MEDIAWIKI')) die();

class ImageUploadHandler extends UnlistedSpecialPage {

	public function __construct() {
		parent::__construct('ImageUploadHandler');
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

	protected function uploadImage() {
		global $wgImageMagickConvertCommand, $wgServer;

		$request = $this->getRequest();
		$result = array();

		$fromPage = $request->getVal('viapage');

		// sanity check on the page to link to
		$title = Title::newFromText($fromPage, NS_MAIN);
		if (!$title->exists()) {
			$result['error'] = "Error: No article $fromPage found to link image.";
			return $result;
		}

		// try to get a unique file name by appending a suffix and the current time to the save name here
		$dateTime = new DateTime();

		$webUpload = $request->getUpload('wpUploadImage');
		$info = new SplFileInfo($webUpload->getName());
		$ext = $info->getExtension();
		$info = new SplFileInfo($request->getVal("name"));
		for ($i = 0; $i < 100; $i++) {
			$saveName = "User Completed Image {$fromPage} {$dateTime->format('Y.m.d H.i.s')}.$i.{$ext}";
			$title = Title::newFromText($saveName, NS_IMAGE);
			if (!$title->getArticleID()) {
				break;
			}
		}

		// if the title still exists, show an error
		if ($title->getArticleID()) {
			$result['error'] = 'file with this name already exists';
			return $result;
		}

		$upload = new UploadFromFile();
		$upload->initialize($saveName, $webUpload);
		$verification = $upload->verifyUpload();
		if ( $verification['status'] !== UploadBase::OK ) {
			$result['error'] = "verification error: " .$verification['status'];
			return $result;
		}

		$warnings = $upload->checkWarnings();
		if ( $warnings) {
			$result['warnings'] = $warnings;

			// todo this should be toggled on off for testings perhaps
			// since it might get kind of annoying
			if ($warnings['duplicate']) {
				$result['debug'][] = $warnings['duplicate-archive'];
				$result['error'] = "this file was already uploaded";
				return $result;
			}
		}

		$comment = '{{Self}}';
		$status = $upload->performUpload( $comment, '', true, $this->getUser() );
		if ( !$status->isGood() ) {
			$error = $status->getErrorsArray();
			$result['error'] = 'perform upload error: '. $error;
			return $result;
		}

		$upload->cleanupTempFile();

		// todo - do this part after the single file upload
		// Image orientation is a bit wonky on some mobile devices; use ImageMagick's auto-orient to try fixing it.
		//$tempFilePath = $temp_file->getPath();
		//$cmd = $wgImageMagickConvertCommand . ' ' . $tempFilePath . ' -auto-orient ' . $tempFilePath;
		//exec($cmd);

		$file = $upload->getLocalFile();
		$thumb = $file->getThumbnail(200, -1, true, true);
		if (!$thumb) {
			$result['error'] = 'file thumbnail does not exist';
			$file->delete('');
			return $result;
		}

		$fileTitle = $file->getTitle();
		$result['titleText'] = $fileTitle->getText();
		$result['titleDBkey'] = substr($fileTitle->getDBkey(), 21); // Only keep important info
		$result['titlePreText'] = '/' . $fileTitle->getPrefixedText();
		$result['titleArtID'] = $fileTitle->getArticleID();
		$result['timestamp'] = wfTimestamp(TS_MW);
		$result['fromPage'] = $request->getVal('viapage');
		$result['thumbURL'] = $thumb->getUrl();
		$result['fileURL'] = $wgServer . $file->getUrl();

		$this->addToDB($result);

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
