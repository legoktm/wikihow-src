<?
//
// Class used to manage title tests, to display the correct title and meta 
// description data based on which test is being run.
//

/*db schema:
CREATE TABLE title_tests(
	tt_pageid INT UNSIGNED NOT NULL,
	tt_page VARCHAR(255) NOT NULL,
	tt_test INT(2) UNSIGNED NOT NULL,
	tt_custom TEXT DEFAULT NULL,
	tt_custom_note TEXT DEFAULT NULL,
	PRIMARY KEY (tt_pageid)
);
*/

class TitleTests {

	const TITLE_DEFAULT = -1;
	const TITLE_CUSTOM = 100;
	const TITLE_SITE_PREVIOUS = 101;

	const MAX_TITLE_LENGTH = 65;

	var $title;
	var $row;
	var $cachekey;

	// called by factory method
	protected function __construct($title, $row) {
		$this->title = $title;
		$this->row = $row;
	}

	// get memcache key
	private static function getCachekey($pageid) {
		return wfMemcKey('titletests', $pageid);
	}

	// factory function to create a new object using pageid
	public static function newFromTitle($title) {
		global $wgMemc, $wgLanguageCode;

		if ($wgLanguageCode != 'en' || !$title || !$title->exists()) {
			// cannot create class
			return null;
		}

		$pageid = $title->getArticleId();
		$namespace = $title->getNamespace();
		if ($namespace != NS_MAIN || $pageid <= 0) {
			return null;
		}

		$cachekey = self::getCachekey($pageid);
		$row = $wgMemc->get($cachekey);
		if (!is_array($row)) {
			$dbr = wfGetDB(DB_SLAVE);
			$row = $dbr->selectRow(
				'title_tests',
				array('tt_test', 'tt_custom'),
				array('tt_pageid' => $pageid),
				__METHOD__);
			$row = $row ? (array)$row : array();
			$wgMemc->set($cachekey, $row);
		}

		$obj = new TitleTests($title, $row);
		return $obj;
	}

	public function getTitle() {
		$tt_test = isset($this->row['tt_test']) ? $this->row['tt_test'] : '';
		$tt_custom = isset($this->row['tt_custom']) ? $this->row['tt_custom'] : '';
	
		return self::genTitle($this->title, $tt_test, $tt_custom);
	}

	public function getDefaultTitle() {
		$wasEdited = $this->row['tt_test'] == self::TITLE_CUSTOM;
		$defaultPageTitle = self::genTitle($this->title, self::TITLE_DEFAULT, '');
		return array($defaultPageTitle, $wasEdited);
	}

	public function getOldTitle() {
		$isCustom = $this->row['tt_test'] == self::TITLE_CUSTOM;
		$testNum = $isCustom ? self::TITLE_CUSTOM : self::TITLE_SITE_PREVIOUS;
		$oldPageTitle = self::genTitle($this->title, $testNum, $this->row['tt_custom']);
		return $oldPageTitle;
	}

	private static function getWikitext($title) {
		$dbr = wfGetDB(DB_SLAVE);
		$wikitext = Wikitext::getWikitext($dbr, $title);
		$stepsText = '';
		if ($wikitext) {
			list($stepsText, ) = Wikitext::getStepsSection($wikitext, true);
		}
		return array($wikitext, $stepsText);
	}

	private static function getTitleExtraInfo($wikitext, $stepsText) {
		$numSteps = Wikitext::countSteps($stepsText);
		$numPhotos = Wikitext::countImages($wikitext);

		$showWithPictures = false;
		if ($numSteps >= 5 && $numSteps <= 25) {
			if ($numPhotos > ($numSteps / 2) || $numPhotos >= 6) {
				$showWithPictures = true;
			}
		} else {
			if ($numPhotos > ($numSteps / 2)) {
				$showWithPictures = true;
			}
		}

		return array($numSteps, $showWithPictures);
	}

	private static function genTitle($title, $test, $custom) {
		$titleTxt = $title->getText();
		$howto = wfMessage('howto', $titleTxt)->text();

		list($wikitext, $stepsText) = self::getWikitext($title);

		switch ($test) {
		case self::TITLE_CUSTOM: // Custom
			$title = $custom;
			break;
		case self::TITLE_SITE_PREVIOUS: // How to XXX: N Steps (with Pictures) - wikiHow
			list($numSteps, $withPictures) = self::getTitleExtraInfo($wikitext, $stepsText, $test);
			$inner = $numSteps > 0 ? "$howto: $numSteps Steps" : $howto;
			$inner = $withPictures ? "$inner (with Pictures)" : $inner;
			$title = wfMessage('pagetitle', $inner)->text();
			break;
		default: // How to XXX: N Steps (with Pictures) - wikiHow
		// From Chris's Mar 25 email
		case 5: // default, but not "with Pictures"
		case 6: // n Tips on How to ... "with Pictures"
		case 7: // n Tips on How to ... but not "with Pictures"
		case 8: // How to ...: Step-by-Step Instructions "with Pictures"
		case 9: // How to ...: Step-by-Step Instructions but not "with Pictures"
			
			$methods = Wikitext::countAltMethods($stepsText);
			
			$mw = MagicWord::get( 'parts' );
			$hasParts = ($mw->match($wikitext));
			
			if ($methods >= 3 && !$hasParts) {				
				$inner = "$methods Ways to $titleTxt";
				$title = wfMessage('pagetitle', $inner)->text();
				if (strlen($title) > self::MAX_TITLE_LENGTH) {
					$title = $inner;
				}
			} else {
				list($numSteps, $withPictures) = self::getTitleExtraInfo($wikitext, $stepsText, $test);
				$forceNoWithPictures = in_array($test, array(5, 7, 9));
				$withPictures = !$forceNoWithPictures ? $withPictures : false;
				if ($test == 6 || $test == 7) {
					$inner = $numSteps > 0 ? "$numSteps Tips on $howto" : $howto;
				} elseif ($test == 8 || $test == 9) {
					$inner = $numSteps > 0 ? "$howto: Step-by-Step Instructions" : $howto;
				} else {
					$inner = $numSteps > 0 ? "$howto: $numSteps Steps" : $howto;
				}
				$inner = $withPictures ? "$inner (with Pictures)" : $inner;
				$title = wfMessage('pagetitle', $inner)->text();
				// first, try articlename + metadata + wikihow
				if (strlen($title) > self::MAX_TITLE_LENGTH) {
					// next, try articlename + metadata
					$title = $inner;
					if ($numSteps > 0 && strlen($title) > self::MAX_TITLE_LENGTH) {
						// next, try articlename + steps
						if ($test == 6 || $test == 7) {
							$inner = "$numSteps Tips on $howto";
						} elseif ($test == 8 || $test == 9) {
							$inner = "$howto: Step-by-Step Instructions";
						} else {
							$title = "$howto: $numSteps Steps";
						}
					}
					if (strlen($title) > self::MAX_TITLE_LENGTH) {
						// next, try articlename + wikihow
						$title = wfMessage('pagetitle', $howto)->text();
						if (strlen($title) > self::MAX_TITLE_LENGTH) {
							// lastly, set title just as articlename
							$title = $howto;
						}
					}
				}
			}
			break;

		// start of new Title Tests from Chris's March 29 email
		//case 12: // How to XXX: N Tips - wikiHow
		//case 13: // N Tips on How to XXX - wikiHow
		//case 14: // How to XXX: Step-by-Step Instructions
		//case 15: // How to XXX: N Methods - wikiHow
		//case 16: // N Ways to XXX - wikiHow
		//case 17: // How to XXX with Step-by-Step Pictures

		// start of new title tests from Chris's Oct 2 email
		/*case 18: // How to XXX with Step-by-Step Pictures
			$inner = '';
			$methods = Wikitext::countAltMethods($stepsText);
			if ($methods >= 4) {
				$inner = "$methods Ways to $titleTxt";
			} else {
				$steps = Wikitext::countSteps($stepsText);
				if (3 <= $steps && $steps < 15) {
					$inner = "$steps Tips on $howto";
				}
			}
			if (!$inner) {
				$inner = "$howto: Step-by-Step Instructions";
			}

			$title = wfMsg('pagetitle', $inner);
			if (strlen($title) > self::MAX_TITLE_LENGTH) {
				$title = $inner;
			}
			break;*/

		}
		return $title;
	}

	public function getMetaDescription() {
		$tt_test = isset($this->row['tt_test']) ? $this->row['tt_test'] : '';
		return self::genMetaDescription($this->title, $tt_test);
	}

	private static function genMetaDescription($title, $test) {
		// no more tests -- always use site default for meta desription
		$ami = new ArticleMetaInfo($title);
		$desc = $ami->getDescription();
		return $desc;
	}

	/**
	 * Adds a new record to the title_tests db table.  Called by 
	 * importTitleTests.php.
	 */
	public static function dbAddRecord(&$dbw, $title, $test) {
		global $wgMemc;
		if (!$title || $title->getNamespace() != NS_MAIN) {
			throw new Exception('TitleTests: bad title for DB call');
		}
		$pageid = $title->getArticleId();
		$dbw->replace('title_tests', 'tt_pageid', 
			array('tt_pageid' => $pageid,
				'tt_page' => $title->getDBkey(),
				'tt_test' => $test),
			__METHOD__);
		$cachekey = self::getCachekey($pageid);
		$wgMemc->delete($cachekey);
	}

	/**
	 * Adds or replaces the current title with a custom one specified by
	 * a string from the admin. Note: must be a main namespace title.
	 */
	public static function dbSetCustomTitle(&$dbw, $title, $custom, $custom_note = '') {
		global $wgMemc;
		if (!$title || $title->getNamespace() != NS_MAIN) {
			throw new Exception('TitleTests: bad title for DB call');
		}
		$pageid = $title->getArticleId();
		$dbw->replace('title_tests', 'tt_pageid',
			array('tt_pageid' => $pageid,
				'tt_page' => $title->getDBkey(),
				'tt_test' => self::TITLE_CUSTOM,
				'tt_custom' => $custom,
				'tt_custom_note' => $custom_note),
			__METHOD__);
		$cachekey = self::getCachekey($pageid);
		$wgMemc->delete($cachekey);
	}

	/**
	 * List all "custom-edited" titles in one go
	 */
	public static function dbListCustomTitles(&$dbr) {
		$res = $dbr->select('title_tests',
			array('tt_pageid', 'tt_page', 'tt_custom', 'tt_custom_note'), 
			array('tt_test' => self::TITLE_CUSTOM),
			__METHOD__);
		$pages = array();
		foreach ($res as $row) {
			$pages[] = (array)$row;
		}
		return $pages;
	}

	/**
	 * Remove a title from the list of tests
	 */
	public static function dbRemoveTitle(&$dbw, $title) {
		self::dbRemoveTitleID( $dbw, $title->getArticleId() );
	}

	public static function dbRemoveTitleID(&$dbw, $pageid) {
		global $wgMemc;
		$dbw->delete('title_tests',
			array('tt_pageid' => $pageid),
			__METHOD__);
		$cachekey = self::getCachekey($pageid);
		$wgMemc->delete($cachekey);
	}

}

