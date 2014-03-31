<?php

if (!defined('MEDIAWIKI')) die();

global $IP;
require_once("$IP/extensions/wikihow/titus/Titus.class.php");

class ApiTitus extends ApiBase {
	/**
	 * Get language and article info 
	 */
	function execute() {
		$params = $this->extractRequestParams();
		$command = $params['subcmd'];
		$result = $this->getResult();
		$module = $this->getModuleName();

		switch($command) {
			case 'article':
				if(!isset($params['page_id']) || !isset($params['language_code'])) {
					$error = "pageId or lang parameters not set";		
				}
				$pageId = $params['page_id'];
				$lang = $params['language_code'];
				$dbr = wfGetDB(DB_SLAVE);
				$t = new TitusDB();
				$sql = "select * from titus_intl where ti_page_id=" . $dbr->addQuotes($pageId) . " AND ti_language_code=" . $dbr->addQuotes($lang);
				$res = $t->performTitusQuery($sql); 
				$found = false;
				foreach($res as $row) {
					$result->addValue(null, $module, get_object_vars($row));
					$found = true;
				}
				if(!$found) {
					$error = "No data for article found";
				}
				break;
			default:
				$error = "Command " . $command . " not found";
		}
		if($error) {
			$result->addValue(null, $module, array('error' => $error));	
		}

	}

	function getVersion() {
		return("1.0");	
	}

  function getAllowedParams() {
		return(array('subcmd' => '', 'page_id' => '', 'language_code' => ''));
	}
}
