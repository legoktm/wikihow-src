<?php
require_once('Flavius.class.php');

class FlaviusQueryTool extends UnlistedSpecialPage {
	private $flavius;
	
	public function __construct() {
		parent::__construct("FlaviusQueryTool");	
		$this->flavius = new Flavius();
    $GLOBALS['wgHooks']['ShowSideBar'][] = array('TitusQueryTool::removeSideBarCallback');
  }

	static function removeSideBarCallback(&$showSideBar) {
		$showSideBar = false;
		return true;
	}


	public function execute() {
		global $wgRequest, $wgOut, $wgUser;
	
		$userGroups = $wgUser->getGroups();	
		if($wgUser->isBlocked() ||  !in_array('staff', $userGroups)) {
			$wgOut->setRobotpolicy('noindex,nofollow');
                        $wgOut->showErrorPage('nosuchspecialpage', 'nospecialpagetext');
                        return;
		}	
		if($wgRequest->wasPosted()) {
			$query = $wgRequest->getVal('query');
			ini_set('memory_limit', '1024M');
			//Take up to 4 minutes to download big queries
			set_time_limit(240);
			$this->getQuery();
		}
		else {
			EasyTemplate::set_path(dirname(__FILE__).'/');
				
			$vars = array('fields'=>$this->getFields() );
			
			$html = EasyTemplate::html('flaviusquerytool.tmpl.php', $vars);
			$wgOut->addHTML($html);
		}

		return $html;
	}

	/**
	 * Get data
	 */
	public function getData($sql, $days) {
			
		header("Content-Type: text/tsv");
		header('Content-Disposition: attachment; filename="Flavius.xls"');

		//Exclude all and last week fields that aren't are from the day
		$intervalFields = $this->flavius->getIntervalFields();
		$intervalFieldExclude = array();
		foreach($intervalFields as $if) {
			$sql = preg_replace("@\b" . $if . "\b@",$if . "_" . $days,$sql);
			$times = $this->flavius->getDayTimes();
			$times[] = 'all';
			$times[] = 'lw';
			foreach($times as $time) {
				if($time != $days) {
					$intervalFieldExclude[] = $if . "_" . $time;
				}
			}
		}
		
		$res = $this->flavius->performQuery($sql);
		$first = true;	
		foreach($res as $row) {
			$rowArr  = get_object_vars($row);	
			foreach($intervalFieldExclude as $exclude) {
				unset($rowArr[$exclude]);	
			}
			if($first) {
				foreach($rowArr as $k => $v) {
					$this->fields[$k] = 1;	
				}
				foreach(array_keys($this->fields) as $field) {
					print($field . "\t");	
				}
				print("\n");
				$first =false;
			}
			foreach(array_keys($this->fields) as $field) {
				if($field == 'fe_username') {
					print("http://www.wikihow.com/User:" . $rowArr[$field] . "\t");
				}
				else {
					print($rowArr[$field] . "\t");
				}
			}
			print("\n");

		}
	  		
		exit;
	}

	/*
	 * Get a list of fields in the flavius_summary table for displaying in the query tool
	 */
	function getFields() {
		$sql = "select * from flavius_summary limit 1";
		$res = $this->flavius->performQuery($sql);
		$dbr = wfGetDB(DB_SLAVE);
		$n = $dbr->numFields($res);
		$fields = array();
		$intervalFields = $this->flavius->getIntervalFields();
		for($k = 0; $k < $n; $k++) {
			$name = $dbr->fieldName($res, $k);

			//Exclude interval fields except _all, and for _all drop the _all.
			$exclude = false;
			foreach($intervalFields as $iField) {
				if(preg_match("@^" . $iField . "_\d+@", $name) || preg_match("@^" . $iField . "_lw@", $name)) {
					$exclude = true;	
				}
				elseif(preg_match("@^" . $iField . "_all@",$name)) {
					$name = $iField;	
				}
			}
			if(!$exclude) { 
				$fields[] = array('field' => 'flavius_summary.' . $name,
													'name'  => $name,
													'id' => $i,
													'ftype' => "string",
													'defaultval' => '[enter val]');
				$i++;
			}
		}
		return($fields);
	}

	/*
	 * Call a query to get the Flavius row
	 */
	function getQuery() {
		global $wgRequest;
		
		$days = $wgRequest->getVal('days');
		$userList = $wgRequest->getVal('users');
		$usersType = $wgRequest->getVal('usersType');
		$sql = Misc::getUrlDecodedData($wgRequest->getVal('sql'), false);
		if($sql == "") {
			$sql = "select * from flavius_summary";	
		}
		// We only get active users in the last 90 days
		if($usersType == 'active') {
			$t = wfTimestamp(TS_MW, time() - 60*60*24*90);
			$ltSQL = "fe_last_touched > '" . $t . "'";
			if(preg_match("@where@i",$sql)) {
				$sql .= ' AND ' . $ltSQL;
			}
			else {
				$sql .= ' WHERE ' . $ltSQL;	
			}
		}
		// We get a list of users
		elseif($usersType != 'all') {
			$users = preg_split("@[\r\n]+@",urldecode($userList));
			$ids = array();
			foreach($users as $user) {
				if(preg_match("@http://www\.wikihow\.com/User:(.+)@i", $user, $matches)) {
					$u = User::newFromName($matches[1]);
					if($u) {
						$ids[] = $u->getId();
					}
				}
			}
			if(sizeof($ids) > 0) {
				if(preg_match("@where@i",$sql)) {
					$sql .= ' AND fe_user in ( ' . implode(',',$ids) . ')';
				}
				else {
					$sql .= ' WHERE fe_user in (' . implode(',',$ids) . ')';	
				}
			}
		}
		
		$rows = $this->getData($sql, $days);
		foreach($rows as $row) {
			foreach($row as $k => $v) {
				print "$v ";	
			}
			print "\n";
		}
	}
	
}
