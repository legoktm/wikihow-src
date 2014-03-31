<?php 
class WHVid {
   
	const S3_DOMAIN_DEV = 'http://d2mnwthlgvr25v.cloudfront.net/'; //wikivideo-prod-test
	const S3_DOMAIN_PROD = 'http://d5kh2btv85w9n.cloudfront.net/'; //wikivideo-prod
	const NUM_DIR_LEVELS = 2;

	public static function setParserFunction () { 
		# Setup parser hook
		global $wgParser;
		$wgParser->setFunctionHook( 'whvid', 'WHVid::parserFunction' );
		return true;    
	}

    public static function languageGetMagic( &$magicWords ) {
		$magicWords['whvid'] = array( 0, 'whvid' );
        return true;
    }

    public static function parserFunction($parser, $vid=null, $img=null, $mobileImg=null) {
		global $wgTitle, $wgContLang;
		wfLoadExtensionMessages('WHVid');

        if ($vid === null || $img === null) {
			return '<div class="errorbox">'.wfMsg('missing-params').'</div>';
		}

        $vid = htmlspecialchars($vid);
		$divId = "whvid-" . md5($vid . mt_rand(1,1000));
		$vidUrl = self::getVidUrl($vid);

		$imgTitle = Title::newFromText($img, NS_IMAGE);
		$imgUrl = null;
		if ($imgTitle) {
			$imgFile = RepoGroup::singleton()->findFile($imgTitle);
			$smallImgUrl = '';
			$largeImgUrl = '';
			if ($imgFile) {
				$width = 550;
				$height = 309;
				$thumb = $imgFile->getThumbnail($width, $height);
				$largeImgUrl = wfGetPad($thumb->getUrl());

				$width = 240;
				//$height = 135;
				$thumb = $imgFile->getThumbnail($width);
				$smallImgUrl = wfGetPad($thumb->getUrl());
			}
		}

		$altImgTitle = '';
		if ($mobileImg) {
			$imgTitle = Title::newFromText($mobileImg, NS_IMAGE);
			if ($imgTitle && $imgTitle->exists()) {
				$altImgTitle = $mobileImg;
			}
		}

		return $parser->insertStripItem(wfMsgForContent('embed-html', $divId, $vidUrl, $largeImgUrl, $smallImgUrl, $altImgTitle));
    }

	public static function getVidDirPath($filename) {
		return FileRepo::getHashPathForLevel($filename, self::NUM_DIR_LEVELS);
	}

	public static function getVidFilePath($filename) {
		return self::getVidDirPath($filename) . $filename;
	}

	public static function getVidUrl($filename) {
		$domain = self::S3_DOMAIN_PROD;

		// Uncomment below line if you are doing Wilma testing on doh and don't want
		// to upload videos to productions. Make sure to also change AWS_UPLOAD_BUCKET
		// to 'wikivideo-upload-test' in wikivideoProcessVideos.php
		//$domain = self::S3_DOMAIN_DEV;

		return $domain . self::getVidFilePath($filename);
	}

	/*
	* Handle optional wikiphoto image parameter included in the {{whvid}} template
	*/
	public static function handleAlternateMobileImages() {
		global $wgParser, $wgTitle;
		$whvids = pq('.whvid_cont');
		foreach ($whvids as $whvid) {
			$altImg = pq($whvid)->find('.altimg-whvid');
			if (strlen($altImg->text())) {
				$wikitext = "[[Image:{$altImg->text()}|center|550px]]";
				$title = Title::newFromText("{$altImg->text()}", NS_IMAGE);
				$html = $wgParser->parse($wikitext, $wgTitle, new ParserOptions())->getText();
				pq($html)->insertBefore($whvid);
				pq($whvid)->remove();
			}
		}
	}

}
