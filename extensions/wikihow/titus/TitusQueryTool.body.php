<?
/*
* 
*/
class TitusQueryTool extends UnlistedSpecialPage {
	var $titus = null;
	var $excluded = null;
	const MIME_TYPE = 'application/vnd.ms-excel';
	const FILE_EXT = '.xls';

	var $languageInfo  = array(); 

	function __construct() {
		parent::__construct('TitusQueryTool');
	
		$this->language="";
		$this->languageInfo = Misc::getActiveLanguageNames(); 
		$GLOBALS['wgHooks']['ShowSideBar'][] = array('TitusQueryTool::removeSideBarCallback');
	}

	static function removeSideBarCallback(&$showSideBar) {
		$showSideBar = false;
		return true;
	}

	function execute($par) {
		global $wgOut, $wgUser, $wgRequest, $isDevHost, $IP, $wgLoadBalancer;
		set_time_limit(600);
		$user = $wgUser->getName();
		$userGroups = $wgUser->getGroups();
		if (!(IS_SPARE_HOST || IS_CLOUD_SITE || $isDevHost) || $wgUser->isBlocked() || !in_array('staff', $userGroups)) {
			$wgOut->setRobotpolicy('noindex,nofollow');
			$wgOut->showErrorPage('nosuchspecialpage', 'nospecialpagetext');
			return;
		}

		require_once("$IP/extensions/wikihow/titus/Titus.class.php");
		$this->titus = new TitusDB(false); 

		if ($wgRequest->wasPosted()) {
			$this->loadExcluded();
			$this->handleQuery();
		} else {
			$wgOut->addScript(HtmlSnips::makeUrlTags('js', array('download.jQuery.js'), 'extensions/wikihow/common', false));
			$wgOut->addScript(HtmlSnips::makeUrlTags('js', array('jquery.sqlbuilder-0.06.js'), 'extensions/wikihow/titus', false));
			$wgOut->setPageTitle('Dear Titus...');
			$wgOut->addHtml($this->getToolHtml());
		}
	}


	function getHeaderRow(&$res, $delimiter = "\t") {
		$n = mysql_num_fields($res->result);
		$fields = array('titus_query_url', 'titus_status');
		for( $i = 0; $i < $n; $i++ ) {
			$meta = mysql_fetch_field( $res->result, $i );
			$field =  new MySQLField($meta);
			$fields[] = $field->name();
		}
		return implode($delimiter, $fields) . "\n";
	}

	function getTitusFields() {
		$data = array();
		$titus = $this->titus;
		$res = $titus->performTitusQuery("SELECT * FROM " . TitusDB::TITUS_TABLE_NAME . " LIMIT 1");
		$n = mysql_num_fields($res->result);
		for( $i = 0; $i < $n; $i++ ) {
			$meta = mysql_fetch_field( $res->result, $i );
			$field =  new MySQLField($meta);
			if($field->name() != ti_language_code) {
				$data[] = array(
				'field' => "titus" . '.' . $field->name(), 
				'name' => $field->name(), 
				'id'  => $i, 
				'ftype' => $field->type(),
				'defaultval' => '[enter val]');
			}	
		}
		return json_encode($data);
	}	

	function handleQuery() {
		global $wgRequest; 
		global $wgWikiHowLanguages;

		$ids = array();
		$urlQuery = $wgRequest->getVal('page-filter') == 'urls';
		$pageFilter = $wgRequest->getVal('page-filter');
		if($pageFilter == 'urls') {
			$ids = $this->getIdsFromUrls(urldecode(trim($wgRequest->getVal('urls'))));
		}
		else {
			if(in_array($pageFilter,$wgWikiHowLanguages) || $pageFilter=="en") {
				$this->language = $pageFilter;	
			}
		}
		try { 
			$this->checkForErrors($ids);
		} catch (Exception $e) {
			$this->outputFile("titus_error.titus", $e->getMessage());
			return;
		}
		$sql = $this->buildSQL($ids);
		$titus = $this->titus;
		$res = $titus->performTitusQuery($sql);
		$output = $this->getHeaderRow($res);
		$outputValid = $wgRequest->getVal('ti_exclude');
		$this->outputFile("titus_query.xls", $output);

		if($urlQuery) {
			$rows = array();
			foreach($res as $row) {
				$r = get_object_vars($row);
				if(isset($r['ti_page_id']) && isset($r['ti_language_code'])) {
					$rows[$r['ti_language_code'] . $r['ti_page_id']]=$r;	
				}
			}
			foreach($ids as $id) {
				$row = array();
				if(isset($rows[$id['language'] . $id['page_id']])) {
					$row = $rows[$id['language'] . $id['page_id']];	
				}
				$status = 'invalid';
				if(!empty($id['language'])) {
					if ($id['language']=="en" && $this->isExcludedPageId($id['page_id'])) {
						$status = 'excluded';	
					} else {
						$status = empty($row) ? 'not found' : 'found';	
					}
				}
				if (!$outputValid || ($status == 'found' && $outputValid)) {
					$this->addOutput($this->outputRow($row, $id['url'], $status));	
				}
			}
			exit;
		} else { 
			$url = 'N/A';
			foreach ($res as $row) {
				$row = get_object_vars($row);
				$status = 'found';
				if ($row->ti_language_code =="en" && $this->isExcludedPageId($row->ti_page_id)) {
					$status = 'excluded';	
				}
				if (!$outputValid || ($status == 'found' && $outputValid)) {
					$this->addOutput($this->outputRow($row, $url, $status));
					ob_flush();	
				}
			}
			exit;
		}
	}

	function checkForErrors(&$ids) {
		// Check that there aren't any redirects
		$pageUrls = array();
		$langQuery = array();
		if (sizeof($langeIds)) {
			foreach($ids as $id) {
				if(!array_key_exists($id['language'],$langQuery)) {
					if($lang == "en") {
						$pageTable = "wikidb_112.page";
					}
					else {
						$pageTable = $lang . ".page";
					}

					$langQuery[$id['language']] = "SELECT page_id, page_title FROM ". $pageTable . "  where page_namespace = 0 and page_is_redirect = 1 AND page_id in (" . $id['page_id']; 
				}
				else {
					$langQuery[$id['language']] .= "," . $id['page_id']; 
				}
			}
			$dbr = wfGetDB(DB_SLAVE);
			foreach($langQuery as $lang => $sql) {
				$res = $dbr->query($sql . ")");
				if ($row = $dbr->fetchObject($res)) {
					$error = "ERROR: Following urls are redirects\n";
					$error .= implode("\n", $pageUrls);
					throw new Exception($error);
				}
			}
		}
	}

	function buildSQL(&$ids) {
		global $wgRequest;

		$sql = Misc::getUrlDecodedData($wgRequest->getVal('sql'), false);
		if(stripos($sql, "FROM titus") > 0) {
			// Always get the language_code 
			if(stripos($sql, "ti_language_code") == FALSE) {
				$sql = str_replace("FROM titus", ', ti_language_code as "ti_language_code" FROM titus',$sql);
			}
			$sql = str_replace("FROM titus","FROM " . TitusDB::TITUS_TABLE_NAME , $sql);	
			//Hack to include ti_page_id and ti_language_code
			if (!preg_match('@SELECT\ +\*@i', $sql) && !preg_match('@ti_page_id as "ti_page_id"@', $sql)) {
			    $sql = preg_replace('@SELECT @', 'SELECT ti_page_id as "ti_page_id",', $sql);
			}

		}
		else {
			$sql = "SELECT * FROM " . TitusDB::TITUS_TABLE_NAME;
		}
		$pageCondition = "";
		if($this->language != "") {
			$pageCondition = " ti_language_code='".  mysql_escape_string($this->language). "'";
		}
		$langConditions = array();
		if(is_array($ids) && sizeof($ids) > 0) {
			$sz=sizeof($ids);
			foreach($ids as $id) {
				if(!empty($id['page_id'])) {
					if(!isset($langConditions[$id['language']]) ) {
						$langConditions[$id['language']] = array();	
					}
					$langConditions[$id['language']][] = $id['page_id'];
				}
				#$pageCondition .= "(ti_page_id ='" . $id['page_id'] . "' AND ti_language_code='" . $id['language'] ."')";
			}
			foreach($langConditions as $lang => $langIds) {
				if($pageCondition != "") {
					$pageCondition .= " OR ";	
				}

				$pageCondition .= "(ti_language_code='" . $lang . "' AND ti_page_id in (" . implode(",",$langIds) . "))";	
			}

		}
		if (stripos($sql, "WHERE ") ) {
			if($pageCondition) {
				$pageCondition = " AND (" . $pageCondition . ")";	
			}
			$sql = preg_replace("@WHERE (.+)$@", "WHERE (\\1) $pageCondition", $sql);
		} elseif($pageCondition!="") {
			$sql .= " WHERE $pageCondition $orderBy";
		}
		else {
			$sql .= " $orderBy";	
		}
		
		
		return $sql;
	}

	function outputRow(&$data, $inputUrl, $status) {
		// Stupid hack because people can't make a url from a title
		if($data['ti_page_title']) {
			if($data['ti_language_code'] != "en") {
				$data['ti_page_title'] = 'http://' .$data['ti_language_code'] . '.wikihow.com/' . rawurlencode($data['ti_page_title']);
			}
			else {
				$data['ti_page_title'] = 'http://www.wikihow.com/' . rawurlencode($data['ti_page_title']);
			}
		}
		return "$inputUrl\t$status\t" . implode("\t", array_values($data)) . "\n";
	}
  function loadExcluded() {
		if (is_null($this->excluded)) {
			$this->excluded = explode("\n", ConfigStorage::dbGetConfig('wikiphoto-article-exclude-list'));
		}
		return $ids;
	}
	function isExcludedPageId($pageId) {
		return in_array($pageId, $this->excluded);
	}


	function getIdsFromUrls(&$urls) {
		global $wgWikiHowLanguages;

		$ids = array();
		$urls = explode("\n", trim($urls));
		$dbh = wfGetDB(DB_SLAVE);
		foreach ($urls as $url) {
			$url2=urldecode($url);
			$url2=str_replace(" ","+",$url2);
			if(!preg_match('/http:\/\/([a-z]+)\.wikihow\.com\/(.+)/', $url2, $matches)) {
				$ids[] = array('url'=>$url);
				continue;
			}
		  $databaseName = false;
			$language = "en";
			if($matches[1] == "www") {
				$databaseName = "wikidb_112";
			}
			else if(in_array($matches[1],$wgWikiHowLanguages)) {
				$databaseName = "wikidb_" . $matches[1];
				$language = $matches[1];
			}
			if(!$dbh || !$databaseName) {
				$ids[] = array('url'=>$url);
				continue;
			}
		  $res = $dbh->query("select page_id FROM " . $databaseName . ".page WHERE page_title = '" . $dbh->strencode($matches[2])  . "' AND page_is_redirect = '0' AND page_namespace = '0'");
			$row = $dbh->fetchObject($res);
			$ids[] = array('url'=>$url,'language'=>$language,'page_id'=>$row->page_id);
		}
		return $ids;
	}

	function outputFile($filename, &$output, $mimeType  = 'text/tsv') {
		global $wgOut, $wgRequest;
		#$wgOut->setArticleBodyOnly(true);
		#$wgRequest->response()->header('Content-type: ' . $mimeType);
		#$wgRequest->response()->header('Content-Disposition: attachment; filename="' . addslashes($filename) . '"');
		header("Content-Type: $mimeType");
		header('Content-Disposition: attachment; filename="' . addslashes($filename) . '"');
		print $output;
	}
	function addOutput(&$output) {
		print $output;
	}

	function getToolHtml() {
		EasyTemplate::set_path(dirname(__FILE__).'/');
		$vars = array('dbfields' => $this->getTitusFields(), 'languages' => $this->languageInfo);
		return EasyTemplate::html('titusquerytool.tmpl.php', $vars);
	}
}
