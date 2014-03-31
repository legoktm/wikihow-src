<?

if (!defined('MEDIAWIKI')) exit;

class TitleSearch extends UnlistedSpecialPage {

	function __construct() {
		parent::__construct( 'TitleSearch' );
	}

	function matchKeyTitles($text, $limit = 10) {
		global $wgMemc;

		$text = trim($text);
		if (!$text) return array();

		// remove stop words
		$key = generateSearchKey($text);
		if (!$key || strlen($key) < 3) return array();

		$cacheKey = wfMemcKey('title_search', $limit, $key);
		$result = $wgMemc->get($cacheKey);
		if ($result) {
			return $result;
		}

		$gotit = array();
		$result = array();

		$base = "SELECT tsk_title, page_counter, page_len, page_is_featured
				FROM title_search_key 
				LEFT JOIN page ON tsk_title = page_title
					AND tsk_namespace = page_namespace
				WHERE page_is_redirect = 0
					AND tsk_namespace = 0";
		$sql = $base . "
					AND tsk_key LIKE '%" . str_replace(" ", "%", $key) . "%'
					AND tsk_namespace = 0
				GROUP BY page_id
				LIMIT $limit";
		$db = wfGetDB(DB_MASTER);
		$res = $db->query( $sql, __METHOD__ );
		if ( $res->numRows() ) {
			while ( $row = $res->fetchObject() ) {
				$con = array(
					$row->tsk_title,
					$row->page_counter,
					$row->page_len,
					$row->page_is_featured,
				);
				$result[] = $con;
				$gotit[$row->tsk_title] = 1;
			}
		}

		if (count($result) < $limit) {
			$sql = $base . " AND ( tsk_key LIKE '%" . str_replace(" ", "%", $key) . "%' ";
			$ksplit = split(" ", $key);
			if (count($ksplit) > 1) {
				foreach ($ksplit as $k) {
					$sql .= " OR tsk_key LIKE '%$k%'";
				}
			}
			$sql .= " ) ";
			$sql .= " LIMIT $limit;";
			$res = $db->query( $sql, __METHOD__ );
			while ( count($result) < $limit && $row = $res->fetchObject() ) {
				if (!isset($gotit[$row->tsk_title]))  {
					$con = array(
						$row->tsk_title,
						$row->page_counter,
						$row->page_len,
						$row->page_is_featured,
					);
					$result[] = $con;
				}
			}
		}

		$wgMemc->set($cacheKey, $result);
		return $result;
	}

	function execute() {
		global $wgRequest, $wgOut, $wgLanguageCode;

		$t1 = time();
		$search = $wgRequest->getVal("qu");
		$limit = $wgRequest->getInt("lim", 10);

		if ($search == "") exit;

		$search = strtolower($search);
		$howto = strtolower(wfMsg('howto', ''));
		
		// hack for german site
		if ($wgLanguageCode != 'de') {
			if (strpos($search, $howto) === 0) {
				$search = substr($search, 6);
				$search = trim($search);
			}
		}

		$t = Title::newFromText($search, 0);
		if (!$t) {
			echo 'sendRPCDone(frameElement, "' . $search . '", new Array(""), new Array(""), new Array(""));';
			$wgOut->disable();
			return;
		}
		$dbkey = $t->getDBKey();

		// do a case insensitive search
		echo 'sendRPCDone(frameElement, "' . $search . '", new Array(';

		$array = "";
		$titles = $this->matchKeyTitles($search, $limit);
		foreach ($titles as $con) {
			$t = Title::newFromDBkey($con[0]);
			$array .= '"' . str_replace("\"", "\\\"", $t->getFullText()) . '", ' ;
		}
		if (strlen($array) > 2) $array = substr($array, 0, strlen($array) - 2); // trim the last comma
		echo $array;

		echo '), new Array(';

		$array = "";
		foreach ($titles as $con) {
			$counter = number_format($con[1], 0, "", ",");
			$words = number_format( ceil($con[2]/5), 0, "", ",");
			$tl_from = $con[3];
			if ($tl_from)
				$array .=  "\"<img src='/skins/common/images/star.png' height='10' width='10'> $counter ". wfMsg('ts_views') . " $words " . wfMsg('ts_words') . "\", ";
			else
			$array .=  "\" $counter " . wfMsg('ts_views') . " $words " . wfMsg('ts_words') . "\", ";
		}
		if (strlen($array) > 2) $array = substr($array, 0, strlen($array) - 2); // trim the last comma
		echo $array;
		echo '), new Array(""));';
		$wgOut->disable();
	}

}

