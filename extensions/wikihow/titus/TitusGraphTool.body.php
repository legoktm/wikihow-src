<?
/*
* 
*/
class TitusGraphTool extends UnlistedSpecialPage {
	var $fields = null;

	function __construct() {
		parent::__construct('TitusGraphTool');
	}

	function execute($par) {
		global $wgOut, $wgUser, $wgRequest, $isDevServer;
		$user = $wgUser->getName();
		$userGroups = $wgUser->getGroups();
		if (!(IS_SPARE_HOST || $isDevServer) || $wgUser->isBlocked() || !in_array('staff', $userGroups)) {
			$wgOut->setRobotpolicy('noindex,nofollow');
			$wgOut->showErrorPage('nosuchspecialpage', 'nospecialpagetext');
			return;
		}

		if ($wgRequest->getVal('json', 0)) {
			$this->configureDB();
			$this->handleQuery();
		} else {
			$wgOut->setPageTitle('Titus Graphing Tool');
			$wgOut->addHtml($this->getToolHtml());
		}
	}

	// Use the spare DB for the query tool to reduce load on production dbs
	function configureDB() {
		global $wgDBservers, $wgDBname;

		if (IS_SPARE_HOST) {
			$wgDBservers[1] = array(
				'host'     => WH_DATABASE_BACKUP,
				'dbname'   => $wgDBname,
				'user'     => WH_DATABASE_USER,
				'password' => WH_DATABASE_PASSWORD,
				'load'     => 1
			);
		}
	}

	function getHeaderRow(&$res, $delimiter = "\t") {
		$n = mysql_num_fields($res->result);
		$fields = array();
		for( $i = 0; $i < $n; $i++ ) {
			$meta = mysql_fetch_field( $res->result, $i );
			$field =  new MySQLField($meta);
			$fields[] = $field->name();
		}
		return $fields;
	}

	function getTitusFields() {
		$data = array();
		$dbr = wfGetDB(DB_SLAVE);
		$res = $dbr->query("SELECT * FROM titus LIMIT 1");
		$n = mysql_num_fields($res->result);
		for( $i = 0; $i < $n; $i++ ) {
			$meta = mysql_fetch_field( $res->result, $i );
			$field =  new MySQLField($meta);
			$data[] = array(
				'field' => $field->name(), 
				'name' => $field->name(), 
				'id'  => $i, 
				'ftype' => $field->type(),
				'defaultval' => '[enter val]');
			
		}
		return $data;
	}	

	function getFieldType($name) {
		$type = '';
		if ($this->fields == null) {
			$this->fields = $this->getTitusFields();
		}
		$fields = $this->fields;
		foreach ($fields as $field) {
			if ($name = $field['name']) {
				$type = $field['ftype'];
			}
		}
		
		return $type;
	}

	function handleQuery() {
		global $wgRequest; 

		$sql = $this->buildSQL();
		
		$dbr = wfGetDB(DB_SLAVE);
		$res = $dbr->query($sql);
		$data = array($this->getHeaderRow($res));
		while ($row = $dbr->fetchObject($res)) {
			$row = array_values(get_object_vars($row));
			$this->transformRow($row);
			$data[] = $row;
		}
		echo $this->outputJSON($data); 
		return;
	} 

	function transformRow(&$row) {
		$date = date_parse($row[0]);
		$row[0] = $date['year'] . "-" . $date['month'] . "-" . $date['day'];
	}
	function extractData(&$row) {
				
	}

	function getDataType($fieldName) {
	}

	function buildSQL() {
		global $wgRequest;

		$ids = $this->getIdsFromUrls(trim(urldecode($wgRequest->getVal('urls'))));
		$ids = array(28163);
		$fields = explode(",", trim(urldecode($wgRequest->getVal('fields'))));
		$fields = array("ti_stu_10s_percentage_www", "ti_stu_3min_percentage_www", "ti_stu_views_www", "ti_stu_10s_percentage_mobile", "ti_stu_views_mobile");
		$lowDate = substr(wfTimestamp(TS_MW, strtotime("-30 day", strtotime(date('Ymd', time())))), 0, 8);
		$highDate = substr(wfTimestamp(TS_MW, strtotime(date('Ymd', time()))), 0, 8);

		$sql = "SELECT ti_datestamp as date, " . implode(",", $fields) . " FROM titus_historical " .
			" WHERE ti_page_id IN (" . implode(",", $ids) . ") and ti_datestamp >= '$lowDate' and ti_datestamp <= '$highDate'";

		//var_dump($sql);
		return $sql;
	}

	function getIdsFromUrls(&$urls) {
		$ids = array();
		$urls = explode("\n", trim($urls));
		foreach ($urls as $url) {
			$t = WikiPhoto::getArticleTitle($url);
			if ($t && $t->exists()) {
				$ids[] = $t->getArticleId();
			}
		}
		return $ids;
	}

	function outputJSON(&$data) {
		global $wgOut, $wgRequest;

		$wgOut->setArticleBodyOnly(true);
		$wgOut->addHtml(json_encode($data));
		return;
	}

	function getToolHtml() {
		EasyTemplate::set_path(dirname(__FILE__).'/');
		$vars = array();
	 	return EasyTemplate::html('titusgraphtool.tmpl.php', $vars);
	}
}
