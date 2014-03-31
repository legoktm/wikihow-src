<?
class BabelfishDB extends WAPDB {
	var $simulate = false;
	var $rowPos = array('url' => 1, 'aid' => 0, 'rank' => 2, 'score' => 3, 'zh_exclude' => 4);
	var $delimeter = ",";
	const CENSORED_REASON = 'censored for chinese';

	public function importArticles($filePath, $simulate = false, $batchSize = 200) {
		$startTime = wfTimestampNow();
		// init some vars
		$this->simulate = $simulate;
		$data = array();
		$invalidUrls = array();
		$rowPos = $this->rowPos;

		ini_set('auto_detect_line_endings',TRUE);
		$handle = fopen($filePath, 'r');
		$langs = $this->getWAPConfig()->getSupportedLanguages();
		$i = 0;

		// remove header row
		fgetcsv($handle, 0, $this->delimeter);
		$numRows = 1;
		while (($datum = fgetcsv($handle, 0, $this->delimeter)) !== FALSE ) {
			if (!$this->validDataRow($datum)) {
				$invalidUrls[$datum[$rowPos['url']]] = "missing row data (url, id, score or rank)";
				continue;
			} else {
				$data[] = $datum;
			}

			if ($i == $batchSize) {
				$invalidUrls = array_merge($invalidUrls, $this->importBatch($data));	
				$data = array(); 
				$i = 0;
			} else{
				$i++;
			}
			$numRows++;
		}
		if (sizeof($data)) {
			$invalidUrls = array_merge($invalidUrls, $this->importBatch($data));	
		}
		$this->emailResults($startTime, $numRows, $invalidUrls);
		ini_set('auto_detect_line_endings',FALSE);		
	}

	protected function importBatch(&$data) {
		$dbw = wfGetDB(DB_MASTER);
		$rowPos = $this->rowPos;
		$langs = $this->getWAPConfig()->getSupportedLanguages();

		$invalidUrls = array();  // Urls that are invalid for some reason or another

		$batchSize = sizeof($data);
		foreach ($langs as $lang) {
			$checkBatch = true;
			$tagMap = array(); // Tags to be added to babelfish
			$rows = array(); // The rows to be inserted into Babelfish

			foreach ($data as $i => $datum) {
				$url = $datum[$rowPos['url']];

				// Special case for Chinese.  Certain articles are excluded from being put into 
				// babelfish for translation to stay on the good side of the Chinese firewalls
				if ($lang == 'zh' && 1 == $datum[$rowPos['zh_exclude']]) {
					// A bit of a hack to account for the special zh case to exclude certain 
					// articles from babelfish.  Normally any url, so long as valid (ie title 
					// exists and not excluded) would be available for all languages in Babelfish.
					// For zh we'll skip the article if excluded and set a flag not to check this 
					// batch (as the counts will be off between batchSize and valid/invalid urls) 
					// rather than unset the data since it will be imported by other 
					// languages in the next iteration of the foreach
					$checkBatch = false; 
				} else {
					$state = $this->getArticleState($url, $lang);
					if ($state == WAPArticle::STATE_INVALID || $state == WAPArticle::STATE_EXCLUDED) {
						$invalidUrls[$url] = $state;
						unset($data[$i]);
						continue;
					}  
					$aid = $datum[$rowPos['aid']];
					$t = Title::newFromId($aid);
					if ($t) {
						// Add tags for language
						$row['ct_page_id'] = $t->getArticleId();
						$row['ct_lang_code'] = $lang;
						$row['ct_page_title'] = $dbw->strencode($t->getDBKey());
						$row['ct_catinfo'] = Categoryhelper::getTitleCategoryMask($t);
						$row['ct_categories'] = implode(",", $this->getTopLevelCategories($t));
						$row['ct_rank'] = $datum[$rowPos['rank']];
						$row['ct_score'] = $datum[$rowPos['score']];
						$rows[] = $row;

						// Add tags to tag map for language
						$tagMap[$lang][] = $aid;
					} else {
						$invalidUrls[$url] = 'title not found';
					}
				}
			}

			if ($checkBatch) {
				$this->checkBatch($batchSize, $rows, $invalidUrls);
			}
			$this->insertRows($rows);
			$this->addTags($tagMap, $lang);
			
			// Complete articles that are in the Babelfish DB and have been translated
			$this->completeTranslatedArticlesInAids($this->getAidsFromRows($rows), $lang);
		}

		return $invalidUrls;	
	}

	protected function checkBatch($batchSize, &$rows, &$invalidUrls) {
		if ($batchSize != sizeof($rows) + sizeof($invalidUrls)) {
			var_dump ("batch size: $batchSize, rows to insert: " . sizeof($rows) . ", invalidUrls: " . sizeof($invalidUrls) * sizeof($langs));
			throw new Exception ("ERROR: batch size doesn't match sum of invalid and valid rows");
		}
	}

	protected function insertRows(&$rows) {
		$dbw = wfGetDB(DB_MASTER);
		if (!empty($rows)) {
			$table = $this->getWAPConfig()->getArticleTableName();
			$sql = WAPUtil::makeBulkInsertStatement($rows, $table, true);
			if ($this->simulate) {
				echo $sql , "\n\n====\n\n";
			} else {
				$dbw->query($sql, __METHOD__);
			}
		}
	}

	protected function getAidsFromRows(&$rows) {
		$aids = array();
		foreach ($rows as $row) {
			$aids[] = $row['ct_page_id'];
		}
		return $aids;
	}

	protected function addTags(&$tagMap, &$langCode) {
		if (!empty($tagMap)) {
			if ($this->simulate) {
				print_r($tagMap) . "\n\n====\n\n";
			} else {
				foreach ($tagMap as $tag => $aids) {
					$tag = array(array('tag_id' => -1, "raw_tag" => $tag));
					$this->tagArticles($aids, $langCode, $tag, false);
				}
			}
		}
	}


	protected function getArticleState($url, $langCode) {
		$urls = $this->processUrlListByLang($url, $langCode);		
		$urlState = WAPArticle::STATE_INVALID;
		foreach ($urls as $state => $urlList) {
			if (!empty($urlList)) {
				$urlState = $state;
				break;
			}
		}
		return $urlState;
	}

	protected function validDataRow(&$datum) {
		$rowPos = $this->rowPos;
		$aid = $datum[$rowPos['aid']];
		$url = $datum[$rowPos['url']];
		$rank = $datum[$rowPos['rank']];
		$score = $datum[$rowPos['score']];
		$valid = true;
		if (!is_numeric($aid) || empty($url) || !is_numeric($rank) || !is_numeric($score)) {
			$valid = false;
		}
		return $valid;
	}

	protected function emailResults($startTime, $numRows, &$invalidUrls) {
		$endTime = wfTimestampNow();
		$langs = implode(",", $this->getWAPConfig()->getSupportedLanguages());
		$body = "Import complete \n\n";
		$body .= "Number of rows processed: $numRows\n";
		$body .= "Languages processed: $langs\n";
		$body .= "Start: $startTime\n";
		$body .= "End: $endTime\n";
		$duration = gmdate("H:i:s", strtotime($endTime) - strtotime($startTime));
		$body .= "Duration: $duration\n\n";

		if (!empty($invalidUrls)) {
			$body .= "URLs NOT IMPORTED\n\n";
			foreach ($invalidUrls as $url => $reason) {
				if ($reason != self::CENSORED_REASON) {
					$body .=  "$url, REASON: $reason\n";
				}
			}
		} else {
			$body .= "All urls successfully imported :)\n\n";
		}

		$subject = "Babelfish Import Finishing on $endTime: Job Summary";
		if ($this->simulate) {
			echo $body;
		} else {
			mail('jordan@wikihow.com', $subject, $body);
		}
	}

	/*
	* Override this function for performance optimization purposes.  This is largely redundant with WAPDB function
	* but uses indexes unique to the babelfish_articles table. Also uses the language_code
	*/
	public function getArticlesByTagName($tag, $offset, $limit, $articleState, $catFilter, $orderBy = '') {
		if (!isset($tag)) {
			return false;
		}		

		$tag = $this->articleTagDB->getTagByRawTag($tag);
		$tagId = $tag['tag_id'];
		$dbr = $this->dbr;

		$limitSql = $this->articleTagDB->getLimitSql($offset, $limit);
		$reserved = $articleState == WAPArticleTagDB::ARTICLE_ALL ? "" : "ca_reserved = $articleState AND ";


		$catWhere = "";
		if (!empty($catFilter)) {
			$catWhere = " AND ct_catinfo & $catFilter > 0 ";
		}

		// Add language code for perf optimization purposes
		$langs = $this->getWAPConfig()->getSupportedLanguages();
		if (in_array($tag['raw_tag'], $langs)) {
			$catWhere .= " AND ca_lang_code = '" . $tag['raw_tag'] . "'";
		}

		if (!empty($orderBy)) {
			$orderBy = " ORDER BY $orderBy ";
		}

		$articleTagTable = $this->getWAPConfig()->getArticleTagTableName();
		$articleTable = $this->getWAPConfig()->getArticleTableName();
		$sql = "SELECT ct.*
			FROM  $articleTagTable, $articleTable ct USE INDEX(ct_lang_code_ct_rank)
			WHERE  
			$reserved
			ca_tag_id = $tagId AND 
			ca_page_id = ct_page_id 
			$catWhere
			AND ca_lang_code = ct_lang_code
			$orderBy
			$limitSql
			";
		$dbr = wfGetDB(DB_SLAVE);
		$res = $dbr->query($sql, __METHOD__);
		$articles = array();
		$articleClass = $this->getWAPConfig()->getArticleClassName();
		foreach ($res as $row) {
			$articles[] = $articleClass::newFromDBRow($row, $this->dbType);
		}
		return $articles;
	}

	public function getTranslatedArticlesFromIds($aids, $langCode) {
		$where = "ct_page_id IN (" . implode(",", $aids) . ")";
		return $this->getTranslatedArticles($where, $langCode);
	}

	public function getTranslatedArticlesFromDate($langCode, $startDate, $endDate) {
		$where = "(tl_timestamp >= '$startDate' AND tl_timestamp < '$endDate')";
		return $this->getTranslatedArticles($where, $langCode);
	}

	/*
	* Get all articles that have been translated (ie have translation link data)
	* that are in Babelfish
	*/
	private function getTranslatedArticles($moreWhere, $lang) {
		$where = array('tl_from_lang' => 'en', 'tl_to_lang' => $lang, "tl_from_aid = ct_page_id", "tl_to_lang = ct_lang_code");
		$where[] = $moreWhere;

		$dbr = wfGetDB(DB_SLAVE);
		$res = $dbr->select(
			array('translation_link', 'babelfish_articles'), 
			array('babelfish_articles.*'), 
			$where,
			__METHOD__);
		$articles = array();
		foreach ($res as $row) {
			$articles[] = BabelfishArticle::newFromDBRow($row, $this->dbType);
		}
		return $articles;
	}

	protected function completeTranslatedArticlesInAids($aids, $lang) {
		$articles = $this->getTranslatedArticlesFromIds($aids, $lang);
		$this->completeTranslatedArticles($articles, $lang);
	}

	protected function completeTranslatedArticlesFromDate($lowDate, $lang) {
		$articles = $this->getTranslatedArticlesFromDate($lowDate, $lang);
		$this->completeTranslatedArticles($articles, $lang);
	}

	/*
	* Completes articles that have ALREADY been translated.  Completes the 
	* article under the currently assigned user, or against the babelfish user if article not assigned.
	* Please use completeArticles() to complete articles by user
	*/
	public function completeTranslatedArticles(&$articles, $lang) {
		if (!empty($articles)) {
			$defaultUserText = $this->getWAPConfig()->getDefaultUserName();
			$urls = array();
			$userAidMap = array();
			foreach ($articles as $a) {
				// Don't complete again if it's already been completed
				if (!$a->isCompleted()) {
					$userText = $a->getUserText();
					$userText = empty($userText) ? $defaultUserText : $userText; 
					$userText = strtolower($userText);
					$userAidMap[$userText][] = $a->getPageId();
				}
			}

			foreach ($userAidMap as $userText => $aids) {
				$u = BabelfishUser::newFromName($userText, $this->dbType);
				$this->completeArticles($aids, $lang, $u);		
			}
		}
	}
}
