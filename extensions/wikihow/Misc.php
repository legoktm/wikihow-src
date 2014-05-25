<?php
if ( ! defined( 'MEDIAWIKI' ) )
	die();

$wgExtensionMessagesFiles['Misc'] = dirname(__FILE__) . '/Misc.i18n.php';
$wgAutoloadClasses['Misc'] = dirname(__FILE__) . '/Misc.body.php';

//$wgHooks['IsTrustedProxy'][] = array('Misc::checkCloudFlareProxy');
$wgHooks['IsTrustedProxy'][] = array('Misc::checkFastlyProxy');

$wgHooks['ArticleConfirmDelete'][] = array('Misc::getDeleteReasonFromCode');
$wgHooks['MakeGlobalVariablesScript'][] = array('Misc::addGlobalVariables');
$wgHooks['EditPage::showEditForm:fields'][] = array('Misc::onShowEditFormFields');
$wgHooks['BeforeWelcomeCreation'][] = array('Misc::onBeforeWelcomeCreation');
$wgHooks['MaybeAutoPatrol'][] = array('Misc::onMaybeAutoPatrol');

$wgHooks['SpecialRecentChangesPanel'][] = array('Misc::onSpecialRecentChangesPanel');
$wgHooks['SpecialRecentChangesQuery'][] = array('Misc::onSpecialRecentChangesQuery');

// Reuben, 1/9/14 - I commented out use of this hook below because it stopped
// our own Special:LSearch page from loading (Mediawiki's Special:Search page
// would load instead). I can't figure out how the hook below is supposed to work
// with our LSearch page, so I'm disabling it (to fix bugs) until I can ask 
// Jordan.
//$wgHooks['LanguageGetSpecialPageAliases'][] = array('Misc::onLanguageGetSpecialPageAliases');

// Mediawiki 1.21 seems to redirect pages differently from 1.12, so we recreate
// the 1.12 functionality from "redirect" articles that are present in the DB.
//   - Reuben, 12/23/2013
$wgHooks['InitializeArticleMaybeRedirect'][] = array('Misc::onInitializeArticleMaybeRedirect');
$wgHooks['BeforeInitialize'][] = array('Misc::onBeforeInitialize');

$wgHooks['TitleSquidURLs'][] = array('Misc::onTitleSquidURLs');
$wgHooks['wgQueryPages'][] = array('Misc::onPopulateWgQueryPages');

function checkFastlyProxy() {
	$value = isset($_SERVER[WH_FASTLY_HEADER_NAME]) ? $_SERVER[WH_FASTLY_HEADER_NAME] : '';
	return $value == WH_FASTLY_HEADER_VALUE;
}

function decho($name, $value = "", $html = true) {
	$lineEnd = "<br>\n";
	if (!$html) {
		$lineEnd = "\n";
	}
	$prefix = wfGetCaller(2);

	if (is_string($value)) {
		echo "$prefix: $name: $value";
	} else if ((!is_array($value) || !is_object($value)) && method_exists($value, '__toString')) {
		print_r("$prefix: $name: $value");
	} else {
		echo "$prefix: $name: ";
		print_r($value);
		echo $lineEnd;
	}

	echo $lineEnd;
}

// Generate a link to our external CDN
function wfGetPad($relurl = '') {
	global $wgServer, $wgIsDomainTest, $wgRequest, $wgSSLsite, $wgIsStageHost;

	$isCanonicalServer = $wgServer == 'http://www.wikihow.com' ||
		$wgServer == 'http://m.wikihow.com' ||
		$wgIsDomainTest;
	$isCachedCopy = $wgRequest && $wgRequest->getVal('c') == 't';

	// Special case for www.wikihow.com urls being requested for international
	if (!IS_PROD_EN_SITE && preg_match('@http://www.wikihow.com@', $relurl)) {
		$relurl = str_replace('http://www.wikihow.com', '', $relurl);
	} else {
		// Don't translate CDN URLs in 4 cases:
		//  (1) if the URL is non-relative (starts with http://),
		//  (2) if the hostname of the machine doesn't end in .wikihow.com and
		//  (3) the site is being served via SSL/https (to get around
		//      mixed content issues with chrome)
		//  (4) if the image being requested is from an international server
		if (preg_match('@^https?://@i', $relurl)
			|| $isCachedCopy
			|| IS_IMAGE_SCALER
			|| (!$isCanonicalServer
				&& (!preg_match('@\.wikihow\.com$@', @$_ENV['HOSTNAME'])
					|| $wgSSLsite
					|| $wgIsStageHost
					|| !IS_PROD_EN_SITE))
		) {
			return $relurl;
		}
	}

	$numPads = 3;
	// Mask out sign or upper bits to make 32- and 64-bit machines produce
	// uniform results.
	$crc = crc32($relurl) & 0x7fffffff;
	$pad = ($crc % $numPads) + 1;
	$prefix = 'pad';

	return "http://{$prefix}{$pad}.whstatic.com{$relurl}";
	// Code to send half of the requests to one CDN then half to the other
	/*
	global $wgTitle, $wgLanguageCode;
	if ($wgLanguageCode != 'en') {
		return "http://{$prefix}{$pad}.whstatic.com{$relurl}";
	} elseif (preg_match('@^/images/(.*)$@', $relurl, $m)) {
		$rest = $m[1];
		$title = $wgTitle && strlen($wgTitle->getText()) > 0 ? $wgTitle->getText() : 'Z';
		if (ord($title{0}) <= ord('D')) {
			return "http://d1cu6f3ciowfok.cloudfront.net/images_en/" . $rest;
		} else {
			return "http://{$prefix}{$pad}.whstatic.com{$relurl}";
		}
	}
	return $relurl;
	*/
}

/* function wfStrr_replace($text, $find, $replace) {
	$i = strrpos($text, $find);
	if ($i === false)
		return $text;
	#echo $text . "\n--------\n" . substr($text, 0, $i) . "\n--------\n" . substr($text, $i+strlen($find));
	$s = substr($text, 0, $i)
			. $replace
			. substr($text, $i+strlen($find));
	#echo "\n---------\n\n{$s}\n"; exit;
	return $s;
} */

/*
 * Function written by Travis. Takes a date (in a string format such that
 * php's strtotime() function will work with it) or a unix timestamp
 * (if you pass in $isUnixTimestamp == true) and converts to format
 * "x Days/Seconds/Minutes Ago" format relative to current date.
 */
function wfTimeAgo($date, $isUnixTimestamp = false) {
	// INTL: Use the internationalized time function based off the original wfTimeAgo
	return Misc::getDTDifferenceString($date, $isUnixTimestamp);
}

function wfFlattenArrayCategoryKeys($arg, &$results = array()) {
	if (is_array($arg)) {
		foreach ($arg as $a=>$p) {
			$results[] = $a;
			if (is_array($p)) {
			   wfFlattenArrayCategoryKeys($p, $results);
			}
		}
	}
	return $results;
}

// WHMWUP -- Reuben 11/19: Empty stub of a deprecated function
function wfLoadExtensionMessages($module) {
}

define('CAT_ARTS', 1);
define('CAT_CARS', 2);
define('CAT_COMPUTERS', 4);
define('CAT_EDUCATION', 8);
define('CAT_FAMILY', 16);
define('CAT_FINANCE', 32);
define('CAT_FOOD', 64);
define('CAT_HEALTH', 128);
define('CAT_HOBBIES', 256);
define('CAT_HOME', 512);
define('CAT_HOLIDAYS', 524288); // oops
define('CAT_PERSONAL', 1024);
define('CAT_PETS', 2048);
define('CAT_PHILOSOPHY', 4096);
define('CAT_RELATIONSHIPS', 8192);
define('CAT_SPORTS', 16384);
define('CAT_TRAVEL', 32768);
define('CAT_WIKIHOW', 65536);
define('CAT_WORK', 131072);
define('CAT_YOUTH', 262144);

