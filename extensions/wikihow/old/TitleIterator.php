<?

class TitleIterator {

	private $_dbr;
	private $_res; 
	private $_ns_col;
	private $_title_col;

	function __construct($tables = array('page'), $cond = array('page_namespace'=>NS_MAIN, 'page_is_redirect'=>0), 
						$options = array(), $ns_col = 'page_namespace', $title_col = 'page_title') {
		$this->_dbr = wfGetDB(DB_SLAVE);
		$this->_res = $this->_dbr->select($tables, array($ns_col, $title_col), $cond, "Titleiterator::construct", $options);
		$this->_ns_col = $ns_col;
		$this->_title_col = $title_col;
	}

	function __destruct() {
		if ($this->_dbr && $this->_res) {
			$this->_dbr->freeResult($this->_res);
		}
	}

	function _next() {
		while (true) {
			$row = $this->_dbr->fetchObject($this->_res);
			if (!$row) {
				return null;
			}
			$ns_col = $this->_ns_col;
			$title_col = $this->_title_col;
			$t = Title::makeTitle($row->$ns_col, $row->$title_col);
			if ($t) {
				return $t;	
			}	
		}
	}
}


