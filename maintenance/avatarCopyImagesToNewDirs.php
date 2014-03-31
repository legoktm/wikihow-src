<?php
require_once("commandLine.inc");
$dbr = wfGetDB(DB_SLAVE);
$avatarInBaseDir = "/var/www/images_en/avatarIn/";
$avatarOutBaseDir = "/var/www/images_en/avatarOut/";

$hash = array (0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 'a', 'b', 'c', 'd', 'e', 'f');
// avatar in directory
if ($handle = opendir($avatarInBaseDir)) {
	while (false !== ($file = readdir($handle))) {
		$srcPath  = $avatarInBaseDir . "$file";
		$destPath = $avatarInBaseDir . getHashPathForLevel($file) . "$file";
		if(!is_dir($srcPath) && !copy($srcPath, $destPath)) {
			echo "ERROR: copy $srcPath to $destPath";
		}
		if(!is_dir($srcPath)) {
			chmod($destPath, 0666);
			chown($destPath, "nobody");
			chgrp($destPath, "nobody");
		}
	}
	closedir($handle);
}

// avatar out directory
if ($handle = opendir($avatarOutBaseDir)) {
	while (false !== ($file = readdir($handle))) {
		$srcPath  = $avatarOutBaseDir . "$file";
		$destPath = $avatarOutBaseDir . getHashPathForLevel($file) . "$file";
		if(!is_dir($srcPath) && !copy($srcPath, $destPath)) {
			echo "ERROR: copy $srcPath to $destPath";
		}
		if(!is_dir($srcPath)) {
			chmod($destPath, 0666);
			chown($destPath, "nobody");
			chgrp($destPath, "nobody");
		}
	}
	closedir($handle);
}
verifyCopy();

function verifyCopy() {
	global $avatarInBaseDir, $avatarOutBaseDir;
echo "here";
	// avatar in directory
	if ($handle = opendir($avatarInBaseDir)) {
		while (false !== ($file = readdir($handle))) {
			if (!is_dir($file)) {
				$srcPath  = $avatarInBaseDir . "$file";
				$destPath = $avatarInBaseDir . getHashPathForLevel($file) . "$file";
				if(!file_exists($destPath) && !is_dir($srcPath)) {
					echo "VERIFY ERROR: Couldn't find $srcPath at $destPath\n";
				}
			}
		}
		closedir($handle);
	}

	// avatar out directory
	if ($handle = opendir($avatarOutBaseDir)) {
		while (false !== ($file = readdir($handle))) {
			if (!is_dir($file)) {
				$srcPath  = $avatarOutBaseDir . "$file";
				$destPath = $avatarOutBaseDir . getHashPathForLevel($file) . "$file";
				if(!file_exists($destPath) && !is_dir($srcPath)) {
					echo "VERIFY ERROR: Couldn't find $srcPath at $destPath\n";
				}
			}
		}
		closedir($handle);
	}
}

function getHashPathForLevel( $name, $levels=2 ) {
	if ( $levels == 0 ) {
		return '';
	} else {
		$hash = md5( $name );
		$path = '';
		for ( $i = 1; $i <= $levels; $i++ ) {
			$path .= substr( $hash, 0, $i ) . '/';
		}
		return $path;
	}
}

?>
