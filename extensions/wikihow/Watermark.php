<?php

if ( !defined('MEDIAWIKI') ) die();

class WatermarkSupport {
	const NO_WATERMARK = "noWatermark";
	const ADD_WATERMARK = "addWatermark";
	const FORCE_TRANSFORM = "forcetransform";

	static function isWikihowCreator($userName) {
		global $wgWatermarkUsers;
		return (is_array($wgWatermarkUsers) && in_array($userName, $wgWatermarkUsers));
	}

	// NOTE: Reuben deprecated the $heightPreference param -- it no longer does anything
	function getUnwatermarkedThumbnail( $image, $width, $height=-1, $render = true, $crop = false, $heightPreference = false ) {
		$params = array( 'width' => $width );
		if ( $height != -1 ) {
			$params['height'] = $height;
		}
		if ($crop) {
			$params['crop'] = 1;
		}
		$params[WatermarkSupport::NO_WATERMARK] = true;
		$params['heightPreference'] = $heightPreference;
		// NOTE: Reuben removed use of the RENDER_NOW param because it makes no
		// effect if not using the transformVia404 Mediawiki functionality
		//$flags = $render ? File::RENDER_NOW : 0;
		$flags = 0;
		return $image->transform( $params, $flags );
	}

	function validImageSize($width, $height) {
		if ($width < 220 || $height < 140) {
			return false;
		}
		return true;
	}

	function isCMYK($srcPath) {
		global $wgImageMagickIdentifyCommand;

		$cmd = wfEscapeShellArg($wgImageMagickIdentifyCommand) . " -format '%r' ".wfEscapeShellArg($srcPath)." | grep CMY";
		wfShellExec( $cmd, $retval );
		return $retval == 0;
	}

	function addWatermark($srcPath, $dstPath, $width, $height) {
		global $IP, $wgImageMagickConvertCommand, $wgImageMagickCompositeCommand;

		// do not add a watermark if the image is too small
		if (WatermarkSupport::validImageSize($width, $height) == false) {
			return;
		}

		$wm = $IP.'/skins/WikiHow/images/watermark.svg';
		$watermarkWidth = 1074.447;
		$targetWidth = $width / 8;
		$density = 72 * $targetWidth / $watermarkWidth;

		// we have a lower limit on density so the watermark is readable
		if ($density < 4.0) {
			$density = 4.0;
		}

		$cmd =  "";
		// make sure image is rgb format so the watermark applies correctly
		if(WatermarkSupport::isCMYK($srcPath)) {
			$cmd = wfEscapeShellArg($wgImageMagickConvertCommand)." ".
					wfEscapeShellArg($srcPath)." ".
					"-colorspace RGB ".
					wfEscapeShellArg($dstPath).";";
			$srcPath = $dstPath;
		}

		$cmd = $cmd . wfEscapeShellArg($wgImageMagickConvertCommand) . " -density $density -background none ".wfEscapeShellArg($wm).
				" miff:- | " . wfEscapeShellArg($wgImageMagickCompositeCommand) . " -gravity southeast -quality 100 -geometry +8+10 - ". wfEscapeShellArg($srcPath) ." ".
				wfEscapeShellArg($dstPath)." 2>&1";

		$beforeExists = file_exists($dstPath);
		wfDebug( __METHOD__.": running ImageMagick: $cmd\n");
		$err = wfShellExec( $cmd, $retval );
		$afterExists = file_exists($dstPath);
		$currentDate = `date`;
		wfErrorLog(trim($currentDate) . " $cmd b:" . ($beforeExists ? 't' : 'f') . " a:" . ($afterExists ? 't' : 'f') . "\n", '/tmp/watermark.log');
		wfProfileOut( 'watermark' );
	}

	// given an image file (local file object) delete the thumbnails from s3
	// functionality gotten from FileRepo.php quickpurgebatch and LocalFile.php purgethumblist
	public static function recreateThumbnails($file) {

		$thumbnails = $file->getThumbnails();

		// take out the directory from the list of thumbnails
		array_shift( $thumbnails );

		foreach ( $thumbnails as $thumbnail ) {
			// Check that the base file name is part of the thumb name
			// This is a basic sanity check to avoid acting on unrelated directories
			if ( strpos( $thumbnail, $file->getName() ) !== false ||
					strpos( $thumbnail, "-thumbnail" ) !== false ) {

				$vPath = $file->getThumbPath($thumbnail);
				$thumbPath = $file->repo->getLocalReference($vPath)->getPath();
				$thumbUrl = $file->getThumbUrl().'/'.$thumbnail;

				// make sure the file is the right format
				$imageSize = getimagesize($thumbPath);
				if ($imageSize["mime"] != $file->getMimeType()) {
					continue;
				}

				$params = array();
				$params["width"] = $imageSize[0];
				if (strpos($thumb, "crop") != false) {
					$params['crop'] = 1;
					if (strpos($thumb, "--") == false) {
						$params["height"] = $imageSize[1];
					}
				}

				$params[WatermarkSupport::FORCE_TRANSFORM] = true;
				$params[WatermarkSupport::ADD_WATERMARK] = true;

				$result = $file->getHandler()->doTransform($file, $thumbPath, $thumbUrl, $params);
				if ( get_class($result) == 	MediaTransformError) {
					echo "there was an error processing this file \n";
					echo $result->toText();
				}
			}
		}
	}
}
