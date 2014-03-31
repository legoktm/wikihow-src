<?php

if ( !defined('MEDIAWIKI') ) die();

class WatermarkSupport {
	const NO_WATERMARK = "noWatermark";
	const ADD_WATERMARK = "addWatermark";
	const WIKIPHOTO_CREATOR = "Wikiphoto";
	const FORCE_TRANSFORM = "forcetransform";

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
		$flags = $render ? File::RENDER_NOW : 0;
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
		global $IP, $wgImageMagickConvertCommand;

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
				" miff:- | composite -gravity southeast -quality 100 -geometry +8+10 - ". wfEscapeShellArg($srcPath) ." ".
				wfEscapeShellArg($dstPath)." 2>&1";

		$beforeExists = file_exists($dstPath);
		wfDebug( __METHOD__.": running ImageMagick: $cmd\n");
		wfRunHooks("AddWatermark", $cmd);
		$err = wfShellExec( $cmd, $retval );
		$afterExists = file_exists($dstPath);
		$currentDate = `date`;
		wfErrorLog(trim($currentDate) . " $cmd b:" . ($beforeExists ? 't' : 'f') . " a:" . ($afterExists ? 't' : 'f') . "\n", '/tmp/watermark.log');
		wfProfileOut( 'watermark' );
	}

}
