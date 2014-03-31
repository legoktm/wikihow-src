<?php


if (!defined('MEDIAWIKI')) die();

class Easyimageupload extends UnlistedSpecialPage {

	const PHOTO_UPLOAD_BOT = 'imageuploadbot';

	public function __construct() {
		parent::__construct('Easyimageupload');
	}

	/**
	 * Set html template path for Easyimageupload actions
	 */
	public static function setTemplatePath() {
		EasyTemplate::set_path( dirname(__FILE__).'/' );
	}

	/**
	 * Hook into toolbar display on advanced edit or sectional edit page. Adds
	 * the image upload icon to the end of the toolbar html.
	 */
	public static function postDisplayAdvancedToolbarHook(&$toolbar) {
		self::setTemplatePath();
		$html = EasyTemplate::html('eiu_advanced_edit_button.tmpl.php') .
			self::getUploadBoxJS() .
			self::getUploadBoxJSAddLoadHook();
		$toolbar .= $html;
		return true;
	}

	/**
	 * Hook into the pre-parser article wiki text.  Inserts the wiki text
	 * '{{IntroNeedsImage}}' close to start of intro text if article is
	 * deemed to need it.
	 */
	public static function preParserIntroImageNotFoundHook(&$article, &$text) {
		$validArticle =
			$article &&
			$article->getTitle() &&
			$article->getTitle()->getNameSpace() == NS_MAIN &&
			$article->getTitle()->getText() != 'Main Page';

		// Make sure the article is typical and there is no IntroNeedsImage
		// template already in the article
		if ($validArticle &&
			!preg_match('@\{\{IntroNeedsImage\}\}@', $text))
		{
			// grab the intro section
			if (preg_match('@^((.|\n)*)==(.|\n)*$@U', $text, $m)) {
				$intro = $m[1];
			} else {
				$intro = $text;
			}
			// if there's no image in the intro section, add this
			// IntroNeedsImage template
			if (!preg_match('@\[\[Image:@', $intro)) {
				// add this new template to article after existing templates
				$text = preg_replace('@^(\{\{([^}][^}])+\}\})?@', '$1{{IntroNeedsImage}}', $text);
			}
		}

		// make sure the article continues parsing
		return true;
	}

	const EIU_MAX_THUMB_SIZE = 130;

	/**
	 * Make a URL to refer to a Flick image. See also:
	 * http://www.flickr.com/services/api/misc.urls.html
	 */
	private static function makeFlickrURL($image, $size) {
		if ($size == 'thumb') {
			$size_token = '_t';
		}
		elseif ($size == 'large') {
			$size_toke = '_b';
		}
		else {
			$size_token = '';
		}
		return 'http://farm'.$image['farm'].'.static.flickr.com/'.$image['server'].'/'.$image['id'].'_'.$image['secret'].$size_token.'.jpg';
	}

	const RESULTS_PER_PAGE = 8;

	/**
	 * List Flickr images matching search terms and our license requirements.
	 *
	 * This method is used by the Findimages class later in this file.
	 *
	 * @param $query search keywords for flickr search
	 * @return JSON listing flickr images
	 */
	public function findImagesFlickr($query, $page = 1) {
		global $IP, $wgUser;

		require_once($IP.'/extensions/3rdparty/phpFlickr-2.3.1/phpFlickr.php');
		$flickr = new phpFlickr(WH_FLICKR_API_KEY);
		// licence info:
		// http://www.flickr.com/services/api/flickr.photos.licenses.getInfo.html
		// details on selected licences:
		// <license id="4" name="Attribution License"
		//   url="http://creativecommons.org/licenses/by/2.0/" />
		// <license id="5" name="Attribution-ShareAlike License"
		//   url="http://creativecommons.org/licenses/by-sa/2.0/" />
		$images = $flickr->photos_search(array(
			'text' => $query,
			'tag_mode' => 'all',
			'page' => intval($page),
			'per_page' => self::RESULTS_PER_PAGE,
			'license' => '4,5',
			'extras' => 'url_t,url_l,url_m',
			'sort' => 'relevance'
		));

		if ($images) {
			$total = intval($images['total']);

			$photos = array();
			foreach ($images['photo'] as $image) {
				// remove file extension if there was one in the title
				$title = preg_replace('@\.(jpg|gif|png)$@i', '', $image['title']);
				$details = array(
					'photoid' => @$image['id'],
					'ownerid' => @$image['owner'],
					'name' => $title.'.jpg',
					'url' => @$image['url_m'],
					'url_l' => @$image['url_l'],
				);
				$photos[] = array(
					'found' => true,
					'thumb_url' => @$image['url_t'],
					'details' => json_encode($details),
				);
			}
		}
		else {
			$total = 0;
		}

		$next_available = min(self::RESULTS_PER_PAGE, $total - ($page * self::RESULTS_PER_PAGE));
		$userid = $wgUser->getID();
		$formattedTotal = number_format($images['total'], 0, '', ',');
		$vars = array(
			//'isLoggedIn' => !empty($userid),
			//'src' => 'flickr',
			'msg' => wfMsg('eiu-flickrresults', $formattedTotal),
			'photos' => $photos,
			'page' => $page,
			'next_available' => $next_available,
			//'RESULTS_PER_PAGE' => self::RESULTS_PER_PAGE,
		);
		return json_encode($vars);
	}

	/**
	 * Return html for user selection of which step to add the image
	 */
	private function getCurrentStepBox() {
		return EasyTemplate::html('eiu_current_step_box.tmpl.php');
	}

	/**
	 * Return html for find images (via flickr or wikimedia.org) box.
	 */
	private function getFindBox($articleTitle) {
		$vars = array(
			'title' => $articleTitle,
		);
		return EasyTemplate::html('eiu_find_box.tmpl.php', $vars);
	}

	/**
	 * Return html for image upload JS load hook.
	 */
	public function getUploadBoxJSAddLoadHook() {
		global $wgRequest;
		if ($wgRequest->getVal('subaction', '') === 'add-image-to-intro') {
			self::setTemplatePath();
			$html = EasyTemplate::html('eiu_js_load_hook.tmpl.php');
			return $html;
		}
		else {
			return '';
		}
	}

	/**
	 * Return html for image upload and bootstrap JS
	 */
	public function getUploadBoxJS($includeCSSandJS = true) {
		self::setTemplatePath();
		$vars = array(
			'GOOGLE_SEARCH_API_KEY' => WH_GOOGLE_AJAX_IMAGE_SEARCH_API_KEY,
			'includeCSSandJS' => $includeCSSandJS,
		);
		return EasyTemplate::html('eiu_js.tmpl.php', $vars) .
			   self::getUploadBoxJSAddLoadHook();
	}

	/**
	 * Return html for user (POST form data) image upload box.
	 */
	private function getUploadBox() {
		$me = Title::makeTitle(NS_SPECIAL, 'Easyimageupload');
		$submitUrl = $me->getFullURL();
		return EasyTemplate::html( 'eiu_upload_box.tmpl.php', array('submitUrl' => $submitUrl) );
	}

	/**
	 * Insert an image upload into the mediawiki database tables.  If the
	 * image insert was successful, a page showing the wiki text for their
	 * image is shown.  Otherwise, if the image file name already exists in
	 * the database, a conflict page is returned to the user.
	 *
	 * @param $type string with either 'overwrite' or blank -- specifies
	 *   whether to force-overwrite an existing image
	 * @param $name filename chosen by user for uploaded image
	 * @param $mwname filename of the file in mediawiki DB
	 * @return outputs either a wikitext results page (if image filename
	 *   didn't exist or force overwrite was selected) or a conflict page.
	 *   Returns an error string or empty string if no error.
	 */
	private function insertImage($type, $name, $mwname) {
		global $wgRequest, $wgUser, $wgOut, $wgFileExtensions;

		$license = $wgRequest->getVal('wpLicense', '');
		if (!empty($license)) {
			$attrib = $wgRequest->getVal('attribution');
			$comment = '{{' . $license . (!empty($attrib) ? '|' . $attrib : '') . '}}';

			if($license != ''){
				$wgUser->setOption('image_license', $license);
				$wgUser->saveSettings();
			}
		} else {
			$comment = $wgRequest->getVal('ImageAttribution', '');
		}

		if (wfReadOnly()) {
			return wfMsg('eiu-readonly');
		}

		if (!empty($mwname) && !empty($name)) {
			$name = urldecode($name);
			$name = preg_replace('/[^'.Title::legalChars().']|[:\/\\\\]|\?/', '-', $name);
			$name = preg_replace('@&amp;@', '&', $name);
			$name = trim($name);

			// did they give no extension at all when they changed the name?
			list($first, $ext) = self::splitFilenameExt($name);
			$ext = strtolower($ext);

			$title = Title::makeTitleSafe(NS_IMAGE, $name);
			if (is_null($title) || !in_array($ext, $wgFileExtensions)) {
				return wfMsg('eiu-filetype-incorrect');
			}

			$newFile =  true;
			$titleExists = $title->exists();

			if (!$titleExists) {
				//
				// DB entry for file doesn't exist. User renamed their
				// upload or it never existed.
				//

				// is the target protected?
				$permErrors = $title->getUserPermissionsErrors('edit', $wgUser);
				$permErrorsUpload = $title->getUserPermissionsErrors('upload', $wgUser);

				if ($permErrors || $permErrorsUpload) {
					return wfMsg('This image is protected');
				}

				$temp_file = new LocalFile(Title::newFromText($mwname, NS_IMAGE), RepoGroup::singleton()->getLocalRepo());
				$file = new LocalFile($title, RepoGroup::singleton()->getLocalRepo());

				$file->upload($temp_file->getPath(), $comment, $comment);
				$temp_file->delete('');

			} elseif ($type == 'overwrite') {
				//
				// DB entry exists and user selected to overwrite it
				//

				$title = Title::newFromText($name, NS_IMAGE);

				// is the target protected?
				$permErrors = $title->getUserPermissionsErrors('edit', $wgUser);
				$permErrorsUpload = $title->getUserPermissionsErrors('upload', $wgUser);
				$permErrorsReupload = $title->getUserPermissionsErrors('reupload', $wgUser);
				$permErrorsCreate = ($title->exists() ? true : $title->getUserPermissionsErrors('create', $wgUser));

				if ($permErrors || $permErrorsUpload || $permErrorsReupload || $permErrorsCreate) {
					return 'This image cannot be overwritten: ' . $title;
				}

				$file_name = new LocalFile($title, RepoGroup::singleton()->getLocalRepo());
				$file_mwname = new LocalFile(Title::newFromText($mwname, NS_IMAGE), RepoGroup::singleton()->getLocalRepo());

				$file_name->upload($file_mwname->getPath(), $comment, $comment);
				$file_mwname->delete('');
				$newFile = false;

			} elseif ($type == 'existing') {
				//
				// DB entry exists and user doesn't want to overwrite or
				// rename, so they use the existing file from the DB.
				//

				$title = Title::newFromText($name, NS_IMAGE);
			} else {
				//
				// There was a conflict with an existing file in the
				// DB.  Title exists and overwrite action not taken yet.
				//
				// generate title if current one is taken
				$suggestedName = self::generateNewFilename($name);

				// extensions check
				list($first, $ext) = self::splitFilenameExt($suggestedName);

				$title = Title::newFromText($name, NS_IMAGE);
				$file = wfFindFile($title);

				$vars = array(
					'suggestedFirstPart' => $first,
					'extension' => strtolower($ext),
					'name' => $name,
					'mwname' => $mwname,
					'file' => $file,
					'image_comment' => $comment,
				);
				$wgOut->setStatusCode(200);
				$wgOut->addHTML(EasyTemplate::html('eiu_conflict.tmpl.php', $vars));
				// return no error
				return '';
			}

			// add watch to file is user needs it
			if ($wgUser->getOption('watchdefault') || ($newFile && $wgUser->getOption('watchcreations'))) {
				$wgUser->addWatch($title);
			}
		} elseif (empty($mwname)) {
			$title = Title::makeTitleSafe(NS_IMAGE, $name);
		} elseif ($name !== null) {
			return WfMsg('eiu-warn3');
		} else { // name === null
			$title = Title::newFromText($mwname, NS_IMAGE);
		}

		$file = wfFindFile($title);
		if (!is_object($file)) {
			return wfMsg('File not found');
		}

		$details = self::splitValuePairs($wgRequest->getVal('image-details'));
		$tag = self::makeImageWikiTag($title, $file, $details);

		$vars = array(
			'tag' => $tag,
			'file' => $file,
			'width' => $details['chosen-width'],
			'height' => $details['chosen-height'],
			'imageFilename' => $title->getText(),
		);
		$vars['details'] = $details;
		$html = EasyTemplate::html('eiu_upload_summary.tmpl.php', $vars);

		$wgOut->setStatusCode(200);
		$wgOut->addHTML($html);

		// return no error
		return '';
	}

	/**
	 * Uses image details to return a string like [[Image:foo.jpg|thumb|right|my caption]]
	 *
	 * @param $title Title object for image db entry
	 * @param $file File object for file storage info
	 * @param $details array of image and layout details
	 * @return string of mediawiki text
	 */
	private static function makeImageWikiTag($title, $file, $details) {
		$tag = '[[' . $title->getPrefixedText();
		$hasCaption = ($details['caption'] != '');
		if ($file->getMediaType() == 'BITMAP' || $file->getMediaType() == 'DRAWING')
		{
			$tag .= '|'.$details['layout'];
			$width_percent = intval($details['width']);
			if ($width_percent < 100) {
				$width = intval($details['chosen-width']);
				if ($width > 0) {
					$tag .= '|'.$width.'px';
				}
			}
		}
		if ($hasCaption) {
			$tag .= '|thumb|'.$details['caption'];
		}
		$tag .= ']]';
		return $tag;
	}

	/**
	 * Split a string like "foo=bar&x=1" into an array like:
	 * array('foo'=>'bar','x'=>'1');
	 *
	 * @param $encodedDetails param string
	 * @return key/value pair array
	 */
	private static function splitValuePairs($encodedDetails) {
		$vals = explode('&', $encodedDetails);
		$pairs = array();
		foreach ($vals as $val) {
			list($k, $v) = explode('=', $val);
			list($k, $v) = array(urldecode($k), urldecode($v));
			$pairs[$k] = $v;
		}
		return $pairs;
	}

	public static function createTempFilename() {
		global $wgUser;
		$tempname = 'Temp_file_'.$wgUser->getID().'_'.rand(0, 1000).'.jpg';
		return $tempname;
	}

	public static function getTempFileUser() {
		$user = User::newFromName(self::PHOTO_UPLOAD_BOT);
		if ( $user && !$user->isLoggedIn() ) {
			$user->addToDatabase();
			$user->addGroup( 'bot' );
		}
		return $user;
	}

	/**
	 * Split a file name such as "foo bar.jpg" into array('foo bar', 'jpg')
	 *
	 * @param $name file name string
	 * @return array with key 0 being the first part and key 1 being the
	 *   extension.
	 */
	private static function splitFilenameExt($name) {
		preg_match('@^(.*)(\.([^.]+))?$@U', $name, $m);
		return array($m[1], $m[3]);
	}

	/**
	 * Generate a new file name such as "foobar 2.jpg" if both filenames
	 * "foobar.jpg" and "foobar 1.jpg" exist in the database.
	 *
	 * @param $name original filename
	 * @return new, unique filename
	 */
	public static function generateNewFilename($name) {
		$name = preg_replace('/[^'.Title::legalChars().']|[:\/\\\\]|\?/', '-', $name);
		$newName = $name;
		list($first, $ext) = self::splitFilenameExt($name);

		//blank name? give it a name!
		if (empty($first))
			$first = mt_rand(100000,100000000);

		$i = 1;
		do {
			$title = Title::newFromText($newName, NS_IMAGE);
			if (!$title->exists()) break;
			$newName = $first . ' ' . $i++ . '.' . $ext;
		} while ($i < 1000);
		return $newName;
	}

	/**
	 * Generate a MW tag for a URL scraped from wikimedia.org.
	 *
	 * Note: this code was copied from extensions/ImportFreeImages/ImportFreeImages.body.php
	 */
	private function getWPLicenseTag($imgUrl) {
		$validLicenses = array(
			'cc-by-sa-all', 'PD', 'GFDL', 'cc-by-sa-3.0', 'cc-by-sa-2.5',
			'FAL', 'cc-by-3.0', 'cc-by-2.5', 'GDL-en', 'cc-by-sa-2.0',
			'cc-by-2.0', 'attribution');

		$pathOnly = str_replace('http://upload.wikimedia.org/', '', $imgUrl);
		$parts = split('/', $pathOnly);

		$img_title = '';
		if (sizeof($parts) == 7)
			$img_title = $parts[5];
		else if(sizeof($parts) == 5)
			$img_title = $parts[4];

		if (!empty($img_title)) {
			$wpUrl = "http://commons.wikimedia.org/wiki/Image:{$img_title}";
			$license = 'unknown';
			$contents = @file_get_contents("http://commons.wikimedia.org/w/index.php?title=Image:{$img_title}&action=raw");
			foreach ($validLicenses as $lic) {
				if (strpos($contents, "{{$lic}") !== false ||
					strpos($contents, "{{self|{$lic}") !== false ||
					strpos($contents, "{{self2|{$lic}") !== false)
				{
					$license = $lic;
					break;
				}
			}
			$comment = "{{commons|{$imgUrl}|{$wpUrl}|{$license}}}";
		} else {
			$comment = "{{commons|{$imgUrl}}}";
		}

		return $comment;
	}

	/**
	 * Accept a request to upload an image either via POST data (user upload)
	 * or via flickr or google / wikimedia.org search.
	 *
	 * @param $src string with value 'upload', 'flickr' or 'wiki'
	 * @return html outputs image details page
	 */
	private function uploadImage($src) {
		global $wgRequest, $wgUser, $IP, $wgOut;

		$error = '';
		$debugInfo = array();
		if ($src == 'upload') {
			$tempname = self::createTempFilename();
			$tempUser = self::getTempFileUser();
			$file = new LocalFile(Title::newFromText($tempname, NS_IMAGE), RepoGroup::singleton()->getLocalRepo());
			$name = $wgRequest->getFileName('wpUploadFile');
			$comment = '';
			$file->upload($wgRequest->getFileTempName('wpUploadFile'), $comment, '', 0, false, false, $tempUser );
			$filesize = $file->getSize();
			if (!$filesize) {
				$error = wfMsg('eiu-upload-error');
			}
		} elseif ($src == 'flickr' || $src == 'wiki') {
			$sourceName = $src == 'flickr' ? 'Flickr' : 'Mediawiki Commons';
			$tempname = self::createTempFilename();
			$file = new LocalFile(Title::newFromText($tempname, NS_IMAGE), RepoGroup::singleton()->getLocalRepo());

			$details = (array)json_decode($wgRequest->getVal('img-details'));
			$name = $details['name'];

			// scrape the file using curl
			$filename = '/tmp/tmp-curl-'.mt_rand(0,100000000).'.jpg';
			$remoteFile = strlen($details['url_l']) ? $details['url_l'] : $details['url'];
			$ch = curl_init($remoteFile);
			curl_setopt($ch, CURLOPT_HEADER, false);
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
			$fp = fopen($filename, 'w');
			curl_setopt($ch, CURLOPT_FILE, $fp);
			$ret = curl_exec($ch);
			$err = curl_error($ch);
			curl_close($ch);
			fclose($fp);

			if ($err) {
				$debugInfo['curl'] = $err;
			}

			$filesize = @filesize($filename);
			if ($filesize) {
				if ($src == 'flickr' || preg_match('@^http://[^/]*flickr@', $details['url'])) {
					require_once($IP.'/extensions/3rdparty/phpFlickr-2.3.1/phpFlickr.php');
					$flickr = new phpFlickr(WH_FLICKR_API_KEY);
					$photo = $flickr->photos_getInfo($details['photoid']);
					$err = $flickr->getErrorMsg();
					if ($err) {
						$debugInfo['flickrAPI'] = $err;
					}
					$license = $photo['license'];
					$username = $photo['owner']['username'];
					$comment = '{{flickr'.intval($license).'|'.wfEscapeWikiText($details['photoid']).'|'.wfEscapeWikiText($details['ownerid']).'|'.wfEscapeWikiText($username).'}}';
				} else {
					$comment = self::getWPLicenseTag($details['url']);
				}

				// finish initializing the $file obj
				$tempUser = self::getTempFileUser();
				$status = $file->upload($filename, '', '', 0, false, false, $tempUser );
				if (!$status->ok) {
					$error = wfMsg('eiu-upload-error');
				}
			} else {
				$error = wfMsg('eiu-download-error', $sourceName);
			}
		}

		if ($error) {
			$html = EasyTemplate::html('eiu_file_error.tmpl.php', array('error' => $error));
			$wgOut->addHTML($html);
			error_log("file from $src error msgs: " . print_r($debugInfo,true));
		} else {
			$mwname = $tempname;
			$props = array(
				'src' => $src,
				'name' => $name,
				'mwname' => $mwname,
				'is_image' => $file->getMediaType() == 'BITMAP' || $file->getMediaType() == 'DRAWING',
				'width' => $file->getWidth(),
				'height' => $file->getHeight(),
				'upload_file' => $file,
				'image_comment' => $comment,
				'license' => $wgUser->getOption('image_license'),
				'file' => $file,
			);

			$html = EasyTemplate::html('eiu_image_details.tmpl.php', $props);
			$wgOut->addHTML($html);
		}
	}

	/**
	 * Resize (to max dimensions of 500x500) then output an image for display
	 *
	 * @param $url URL to scrape from uploads.wikimedia.org
	 */
	private function resizeAndDisplayImage($url) {
		global $wgImageMagickConvertCommand;
		$MAX_DIMENSIONS = '500x500';

		// I couldn't find a way to output a JPEG file in binary using
		// the MediaWiki framework, so I just do it myself using php functions

		// scrape image
		$tmpfile = tempnam('/tmp', 'eiu-resize-in-');
		$tmpfile_small = tempnam('/tmp', 'eiu-resize-out-') . '.jpg';
		if (preg_match('@^http://upload.wikimedia.org/@', $url)) {
			$ch = curl_init($url);
			curl_setopt($ch, CURLOPT_FAILONERROR, true);
			$fp = fopen($tmpfile, 'w');
			curl_setopt($ch, CURLOPT_FILE, $fp);
			$success = curl_exec($ch);
			curl_close($ch);
			fclose($fp);
		} else {
			$success = false;
		}

		// resize image
		if ($success) {
			$cmd = $wgImageMagickConvertCommand . ' ' . $tmpfile . ' -resize ' . $MAX_DIMENSIONS . ' ' . $tmpfile_small;
		} else {
			$msg = 'Image preview error';
			$cmd = $wgImageMagickConvertCommand . ' -size 320x100 xc:white  -font Bitstream-Charter-Regular -pointsize 24 -fill black -draw "text 25,65 \'' . $msg . '\'" ' . $tmpfile_small;
		}
		exec($cmd);

		// output image
		$img = file_get_contents($tmpfile_small);
		header('Content-type: image/jpeg');
		print $img;

		// cleanup
		@unlink($tmpfile);
		@unlink($tmpfile_small);
	}

	/**
	 * Executes the Easyimageupload special page and all its sub-calls
	 */
	public function execute($par) {
		global $wgRequest, $wgUser, $wgOut, $wgLang, $wgServer;

		wfLoadExtensionMessages('Easyimageupload');

		self::setTemplatePath();

		if ($wgUser->isBlocked()) {
			$wgOut->blockedPage();
			return;
		}

		$wgOut->setArticleBodyOnly(true);

		if ($wgRequest->getVal('getuploadform')) {
			$wgOut->addHTML(self::getUploadBox());
		} elseif ($wgRequest->getVal('uploadform1')) {
			$wgOut->addHTML(EasyTemplate::html('eiu_error_box.tmpl.php'));
			$this->uploadImage( $wgRequest->getVal('src') );
		} elseif ($wgRequest->getVal('uploadform2'))  {
			$wgOut->addHTML(EasyTemplate::html('eiu_error_box.tmpl.php'));
			$type = $wgRequest->getVal('type');
			$name = $wgRequest->getVal('name');
			$mwname = $wgRequest->getVal('mwname');
			$error = $this->insertImage($type, $name, $mwname);
			$vars = !empty($error) ? array('error' => $error) : array();
			$wgOut->addHTML(EasyTemplate::html('eiu_add_error.tmpl.php', $vars));
		} elseif ($wgRequest->getVal('ImageIsConflict'))  {
			$wgOut->addHTML(EasyTemplate::html('eiu_error_box.tmpl.php'));
			if ($wgRequest->getVal('ImageUploadUseExisting')) {
				$name = $wgRequest->getVal('ImageUploadExistingName');
				$wgRequest->setVal('type', 'existing');
			} elseif ($wgRequest->getVal('ImageUploadRename')) {
				$name = $wgRequest->getVal('ImageUploadRenameName').'.'.$wgRequest->getVal('ImageUploadRenameExtension');
				$wgRequest->setVal('type', 'overwrite');
			}
			$wgRequest->setVal('name', $name);
			$type = $wgRequest->getVal('type');
			$name = $wgRequest->getVal('name');
			$mwname = $wgRequest->getVal('mwname');
			$error = $this->insertImage($type, $name, $mwname);
			$vars = !empty($error) ? array('error' => $error) : array();
			$wgOut->addHTML(EasyTemplate::html('eiu_add_error.tmpl.php', $vars));
		} elseif ($wgRequest->getVal('preview-resize')) {
			$url = $wgRequest->getVal('url');
			self::resizeAndDisplayImage($url);
		} else { // initial menu
			$separator = EasyTemplate::html('eiu_separator.tmpl.php');
			$articleTitle = $wgRequest->getVal('article-title');
			$vars = array('title' => $articleTitle);
			$html = EasyTemplate::html('eiu_header.tmpl.php', $vars);
			$wgOut->addHTML($html);
			$wgOut->addHTML(EasyTemplate::html('eiu_error_box.tmpl.php'));
			// assert wgRequest->wasPosted() == false;
			$wgOut->addHTML(self::getCurrentStepBox());
			$wgOut->addHTML(self::getFindBox($articleTitle));
			$html = EasyTemplate::html('eiu_find_box_end.tmpl.php');
			$wgOut->addHTML($html);
			$html = EasyTemplate::html('eiu_footer.tmpl.php');
			$wgOut->addHTML($html);
		}
	}
}

/**
 * Used to find images on flickr or in the current wiki
 */
class Findimages extends UnlistedSpecialPage {

	public function __construct() {
		parent::__construct('Findimages');
	}

	public function execute($par) {
		global $wgRequest, $wgOut;
		Easyimageupload::setTemplatePath();
		wfLoadExtensionMessages('Easyimageupload');
		$page = intval($wgRequest->getVal('page', 1));
		$query = $wgRequest->getVal('q');
		if ($wgRequest->getVal('src') == 'flickr') {
			$json = Easyimageupload::findImagesFlickr($query, $page);
		}
		$wgOut->disable(true);
		print $json;
		return;
	}
}

