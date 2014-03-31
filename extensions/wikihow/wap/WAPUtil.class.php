<?
class WAPUtil {
	public static function makeBulkInsertStatement(&$data, $table, $updateOnDup = true) {
		$sql = "";
		if (!empty($data)) {
			$keys = "(" . implode(", ", array_keys($data[0])) . ")";
			$values = array();
			foreach ($data as $datum) {
				$values[] = "('" . join("','", array_values($datum)) . "')";
			}
			$values = implode(",", $values);

			$sql = "INSERT IGNORE INTO $table $keys VALUES $values";

			if ($updateOnDup) {
				$set = array();
				foreach ($data[0] as $col => $val) {
					$set[] = "$col = VALUES($col)";
				}
				$set = join(",", $set);
				$sql .= " ON DUPLICATE KEY UPDATE $set";
			}
		}

		return $sql;
	}

	public static function createTagArrayFromRequestArray(&$requestArray) {
		array_walk($requestArray, function(&$tag) {
			$parts = explode(",", $tag);
			$tag = array('tag_id' => $parts[0], 'raw_tag' => $parts[1]);
		});
		return $requestArray;
	}

	public static function generateTSVOutput(&$rows) {
		$output = "";
		if (!empty($rows)) {
			$output = implode("\t", array_keys((array) $rows[0])) . "\n";
			foreach ($rows as $row) {
				$row = (array) $row;
				$row['ct_page_title'] = WAPLinker::makeWikiHowUrl($row['ct_page_title']); 
				$output .= implode("\t", $row) . "\n";
			}
		}
		return $output;
	}

	public static function getUserNameFromUserUrl(&$url) {
		$uname = str_replace('http://www.wikihow.com/User:', '', $url);
		$uname = str_replace('-', ' ', $uname);
		return urldecode($uname);
	}

}
