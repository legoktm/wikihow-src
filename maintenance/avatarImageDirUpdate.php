<?php
require_once("commandLine.inc");
$dbr = wfGetDB(DB_SLAVE);
$avatarInBaseDir = "/var/www/images_en/avatarIn/";
$avatarOutBaseDir = "/var/www/images_en/avatarOut/";

$hash = array (0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 'a', 'b', 'c', 'd', 'e', 'f');

#$old = umask(0000);
foreach ($hash as $firstDir) {
	foreach ($hash as $secondDir) {
		$avatarInDir = "$avatarInBaseDir$firstDir/$firstDir$secondDir/";
		$avatarOutDir = "$avatarOutBaseDir$firstDir/$firstDir$secondDir/";

		if (is_dir($avatarInDir)) {
			delTree($avatarInDir);
		}
		if (!mkdir($avatarInDir, 0777, true)) {
		    die("CREATION FAILED: $avatarInDir\n");
		}
		chmod($avatarInDir, 0777);
		chown($avatarInDir, "nobody");
		chgrp($avatarInDir, "nobody");

		if (is_dir($avatarOutDir)) {
			delTree($avatarOutDir);
		}
		if (!mkdir($avatarOutDir, 0777, true)) {
		    die("CREATION FAILED: $avatarOutDir\n");
		}
		chmod($avatarOutDir, 0777);
		chown($avatarOutDir, "nobody");
		chgrp($avatarOutDir, "nobody");
	}
	$avatarInFirstDir = "$avatarInBaseDir$firstDir/";
	$avatarOutFirstDir = "$avatarOutBaseDir$firstDir/";
	chmod($avatarInFirstDir, 0777);
	chown($avatarInFirstDir, "nobody");
	chgrp($avatarInFirstDir, "nobody");

	chmod($avatarOutFirstDir, 0777);
	chown($avatarOutFirstDir, "nobody");
	chgrp($avatarOutFirstDir, "nobody");
}

function delTree($dir) { 
    $files = glob( $dir . '*', GLOB_MARK ); 
	foreach( $files as $file ){ 
			if( substr( $file, -1 ) == '/' ) 
				delTree( $file ); 
			else 
				unlink( $file ); 
	} 
	rmdir( $dir ); 
} 
?>
