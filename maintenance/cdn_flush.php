<?php
//
// Remove / refresh an object on the CDN (CDNetworks). Must pass in a username (email
// address of portal user), their password, and an object / URL to clear. Wildcards
// can be used in the object to clear, but should be used with caution because a
// cache clear of a bunch of objects could cause a whole bunch of requests to
// our varnish servers.
//

require_once('commandLine.inc');

function main($params) {
	$params = parseParams($argv);

	if ($params['file']) {
		$locations = file($params['file']);
		# Per chris.chih from CDNetworks <support@cdnetworks.com>
		# in March, 2014 email to Reuben, we can do 60 cache clear requests
		# with up to 1,000 urls per request
		$MAX_URLS = 1000;
		if (!is_array($locations) || count($locations) > $MAX_URLS) {
			print "error: specified file '{$params['file']}' must contain a list of urls, one per line, at most $MAX_URLS\n";
			exit;
		}
		$locations = array_map(parseLocation, $locations);
	} else {
		$locations = array( parseLocation($params['url']) );
	}

	$html = doCDnetworksApiCall($params, $locations);
	print "response from CDNetworks:\n";
	print $html . "\n";
}

function doCDnetworksApiCall($params, $locations) {
	$url = 'https://openapi.us.cdnetworks.com/purge/rest/doPurge';

	if (count($locations) == 1 && strpos($location[0], '*') !== false) {
		$type = 'wildcard';
	} else {
		foreach ($locations as $location) {
			if (strpos($location, '*') !== false) {
				print "error: since multiple locations are specified, none may have a '*' (wildcard)\n";
				exit;
			}
		}
		$type = 'item';
	}
	//$double = strpos($location, '**') !== false;
	//if ($type == 'wildcard' && !$double) {
		// Per justin.rodriguez from CDNetworks <support@cdnetworks.com>
		// in March 20, 2013 email to Reuben
	//	print "Notice: to clear 'recursively' all subdirs, it's necessary to use /path/fo/**\n";
	//}
	$sendParams = array_map(function ($l) { return 'path=' . $l; }, $locations);
	$sendParams += array(
		'pad=pad1.whstatic.com',
		'user=' . $params['user'],
		'pass=' . $params['password'],
		'type=' . $type,
		'output=json',
	);

	$paramStr = join('&', $sendParams);
	$ch = curl_init($url . '?' . $paramStr);
	#curl_setopt($ch, CURLOPT_VERBOSE, true);
	#curl_setopt($ch, CURLOPT_POST, true);
	#curl_setopt($ch, CURLOPT_POSTFIELDS, $paramStr);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

	$ret = curl_exec($ch);
	curl_close($ch);

	return $ret;
}

function parseLocation($location) {
	$location = trim( preg_replace('@^http://[^/]+@', '', $location) );
	if (!preg_match('@^/@', $location)) {
		print "error: path '$location' should start with '/'\n";
		exit;
	}
	return $location;
}

function parseParams($argv) {
	$opts = getopt('u:p:l:f:', array('user:', 'password:', 'location:', 'file:'));

	$user = isset($opts['u']) ? $opts['u'] : @$opts['user'];
	$password = isset($opts['p']) ? $opts['p'] : @$opts['password'];
	$url = isset($opts['l']) ? $opts['l'] : @$opts['location'];
	$file = isset($opts['f']) ? $opts['f'] : @$opts['file'];

	if (!$user || !$password || (!$url && !$file)) {
		die("usage: php cdn_flush.php --user=<cdnetworks-email-login> --password=<cdnetworks-password> [--location=<url-to-flush> | --file=<file-with-urls>]\n");
	}

	return array(
		'user' => $user,
		'password' => $password,
		'url' => $url,
		'file' => $file,
	);
}

main($argv);

