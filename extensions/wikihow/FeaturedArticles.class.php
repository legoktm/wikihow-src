<?php

class FeaturedArticles {

	const CACHE_TIMEOUT = 300; // 5 minutes in seconds

	// TODO: to optimize: this function should either be memcached, or should
	// load the same wikitext from the DB once (other methods in this
	// class called at the same time load the same text)
	static function getNumberOfDays($default, $feedTitle = "RSS-feed") {
		$header = "==Number of Days==";
		$header_len = strlen($header);
		$t = Title::newFromText($feedTitle, NS_PROJECT);
		if (!$t) return $default;
		$r = Revision::newFromTitle($t);
		if (!$r) return $default;
		$text = $r->getText();
		if (!$text) return $default;
		$x = strpos($text, $header);
		if ($x === false) return $default;
		$y = strpos($text, "==", $x+$header_len);
		if ($y === false) { $y = strlen($text); }
		$days = substr($text, $x + $header_len, $y - $x - $header_len);
		return trim($days);
	}

	private static function getDatesForFeed($numdays) {
		global $wgRSSOffsetHours;

		$result = array();
		$tstamp = mktime() - $wgRSSOffsetHours * 3600;
		$last_tz = date('Z', $tstamp);
		for ($i = 0; $i < $numdays; $i++) {
			$xx = getdate($tstamp);
			$d = $xx['mday'];
			$m = $xx['mon'];
			$y = $xx['year'];
			if ($d < 10)
				$d = "0".$d;
			if ($m < 10)
				$m = "0".$m;
			$result[] = "$y-$m-$d";
			// set the time stamp back a day 86400 seconds in 1 day
			$tstamp -= 86400;
			$tz = date('Z', $tstamp);
			if ($tz != $last_tz) {
				$tstamp -= ($tz - $last_tz);
				$last_tz = $tz;
			}
		}
		return $result;
	}

	// Get a number of title objects, up to $MAX_DAYS worth of days
	static function getTitles($numTitles) {
		global $wgMemc, $wgLanguageCode;

		$cachekey = wfMemcKey('featured-titles', $numTitles);
		$feeds = $wgMemc->get($cachekey);
		if (!is_array($feeds)) {
			$MAX_DAYS = 100;
			$days = ceil($numTitles / 6); // roughly 6 FAs per day
			while ($days <= $MAX_DAYS) {
				$feeds = self::getFeaturedArticles($days);
				if (count($feeds) >= $numTitles) break;
				$days *= 2;
			}

			if (is_array($feeds)) {
				foreach ($feeds as &$item) {
					$item[0] = preg_replace('@^(http://[^/]+)?/@', '', $item[0]);
				}
				$wgMemc->set($cachekey, $feeds, self::CACHE_TIMEOUT);
			}
		}

		$ret = array();
		if (is_array($feeds)) {
			foreach ($feeds as $item) {
				if($wgLanguageCode != "en") {
					$title = Title::newFromURL(urldecode($item[0]));
				}
				else {
					$title = Title::newFromURL($item[0]);
				}

				if ($title) {
					$ret[] = array(
						'published' => $item[1],
						'title' => $title);
					if (count($ret) == $numTitles) break;
				}
			}
		}

		return $ret;
	}

	// Get a list of FAs for some last number of days
	static function getFeaturedArticles($numdays, $feedTitle = "RSS-feed") {
		global $wgRSSOffsetHours, $wgMemc;
		static $texts = array(); // local cache so that we retrieve text once

		$titleHash = md5($feedTitle);
		$cachekey = wfMemcKey('featured', $numdays, $titleHash);
		$feeds = $wgMemc->get($cachekey);
		if (is_array($feeds)) return $feeds;

		if (!$texts[$titleHash]) {
			$title = Title::newFromText($feedTitle, NS_PROJECT);
			$rev = Revision::newFromTitle($title);
			if (!$rev) return array();

			$texts[$titleHash] = $rev->getText();
		}
		$text = $texts[$titleHash];

		$dates = self::getDatesForFeed($numdays);
		$d_count = array();
		$feeds = array();
		foreach ($dates as $d) {
			preg_match_all("@^==[ ]*{$d}[ ]*==\s*\n.*@m", $text, $matches);
			foreach ($matches[0] as $entry) {
				// now entry is
				// ==2011-03-18==
				// http://www.wikihow.com/Article How to Alternative Title
				$lines = split("\n", $entry);
				$parts = split(" ", trim($lines[1]));
				$item = array();
				$item[] = $parts[0]; // the url
				$item[] = $d; // the date
				if (sizeof($parts) > 1) {
					array_shift($parts);
					$item[] = implode(" ", $parts); // the alt title
				}
				$feeds[] = $item;
				if (!isset($d_count[$d])) {
					$d_count[$d] = 0;
				}
				$d_count[$d] += 1;
			}
		}

		// convert dates to timestamps based
		// on the number of feeds that day
		$d_index = array();
		$new_feeds = array();
		$t_array = array();
		$t_url_map = array();
		foreach ($feeds as $item) {
			$d = $item[1];
			$index = 0;
			$count = $d_count[$d];
			if (isset($d_index[$d]))
				$index = $d_index[$d];
			$hour = floor( $index  * (24 / ($count) ) ) + $wgRSSOffsetHours;
			$d_array = split("-", $d);
			$ts = mktime($hour, 0, 0, $d_array[1], $d_array[2], $d_array[0]);
			$t_array[] = $ts;

			// inner array
			$xx = array();
			$xx[0] = $item[0];
			if (isset($item[2]))
				$xx[1] = $item[2];

			$t_url_map[$ts] = $xx; // assign the url / override title array
			$item[1] = $ts;
			$d_index[$d] = $index+1;
			$new_feeds[] = $item;
		}

		// sort by timestamp descending
		sort($t_array);
		$feeds = array();
		for ($i = sizeof($t_array) - 1; $i >= 0; $i--) {
			$item = array();
			$ts = $t_array[$i];
			$item[1] = $ts;
			$xx = $t_url_map[$ts];
			$item[0] = $xx[0];
			if(isset($xx[1])) $item[2] = $xx[1];
			$feeds[] = $item;
		}

		$wgMemc->set($cachekey, $feeds, self::CACHE_TIMEOUT);

		return $feeds;
	}

}

