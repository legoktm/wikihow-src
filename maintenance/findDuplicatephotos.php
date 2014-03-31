<?
require_once('commandLine.inc');
echo "Starting to find all images..this could take awhile...\n";
$return = shell_exec('find images/ -type f | egrep -v \'images/thumb\'') ;
$files   = split("\n", $return);
echo "Found all imagese... starting to check md5sums\n";
$count = 0;
$md5s = array();
foreach ($files as $file){
	if (trim($file) =='') continue;
	$sum = exec("/usr/bin/md5sum \"$file\" | awk '{print $1}'");
	#echo "Checking $file $sum\n";
	#echo ("/usr/bin/md5sum $file | awk '{print $1}'"); continue;
	if (isset($md5s[$sum])) {
		echo "$file\t{$md5s[$sum]}\n";
	} else {
		$md5s[$sum] = $file;
	}
	$count++;
	if ($count % 1000 == 0)
		echo "at $count\n";
}
echo "Done!\n";
?>

