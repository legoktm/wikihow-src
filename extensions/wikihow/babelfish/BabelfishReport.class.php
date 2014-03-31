<?
class BabelfishReport extends WAPReport {
	protected function formatData(&$rows) {
		global $IP;
		require_once("$IP/extensions/wikihow/TranslationLink.php");

		if (!empty($rows)) {
			$ids = array();
			foreach ($rows as $i => $row) {
				$ids[] = $row->ct_page_id;;
			}
			$links = TranslationLink::getLinks('en', $rows[0]->ct_lang_code, array("tl_from_aid in (" . implode(",", $ids) . ")"));
			$idLinkMap = array();
			foreach ($links as $link) {
				$idLinkMap["{$link->fromAID}"] = $link->toURL;
			}

			$keep = array('ct_page_id', 'ct_page_title', 'ct_user_text', 'ct_completed_timestamp', 'ct_price', 'ct_translated_title');
			foreach ($rows as $j => $row) {
				$articleClass = $this->config->getArticleClassName();
				$a = $articleClass::newFromDBRow($row, $this->dbType);
				$row = get_object_vars($row);
				$row['ct_price'] = $a->getPrice();
				$row['ct_completed_timestamp'] = $a->getCompletedDate();
				$row['ct_translated_title'] = $idLinkMap["{$row['ct_page_id']}"];
				foreach ($row as $k => $v) {
					if (!in_array($k, $keep)) {
						unset($row[$k]);
					}
				}
				$rows[$j] = $row;
			}
		}
	}
}
