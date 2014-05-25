<?php

if ( !defined('MEDIAWIKI') ) exit;

class PageHooks {

	// Allow varnish to purge un-urlencoded version of urls so that articles such
	// as Solve-a-Rubik's-Cube-(Easy-Move-Notation) can be requested without
	// passing through the varnish caches. We had a site stability issue for
	// logged in users on 5/19/2014 because Google featured the Rubik's cube on
	// their home page and a lot of people suddenly searched for it. All requests
	// were passed to our backend, which caused stability issues.
	static function onTitleSquidURLs($title, &$urls) {
		$reverse = array_flip($urls);
		foreach (array_keys($reverse) as $url) {
			$decoded = urldecode($url);
			if ( !isset($reverse[$decoded]) ) {
				$reverse[$decoded] = true;
				$urls[] = $decoded;
			}
		}

		return true;
	}

}

