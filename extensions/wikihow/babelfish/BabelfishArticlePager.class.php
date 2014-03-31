<? 
class BabelfishArticlePager extends WAPArticlePager {
	const MAX_NUM_ROWS = 1000;  // This is the max number of articles that should display for a tag list in babelfish
	public function getTagListPager($rawTag, $offset, $rows = NUM_ROWS) {
		global $wgUser;

		$vars['u'] = BabelfishUser::newFromUserObject($wgUser, $this->dbType);
		$vars['rows'] = $this->getTagListRows($rawTag, $offset, self::MAX_NUM_ROWS);
		$vars['tag'] = $rawTag;
		$vars['offset'] = $offset;
		$vars['numrows'] = $rows;

		$tmpl = new WAPTemplate($this->dbType);
		return $tmpl->getHtml('tag_list_pager.tmpl.php', $vars);
	}

	public function getTagListRows($rawTag, $offset, $rows = NUM_ROWS, $filter = null) {
		global $wgUser;

		$db = WAPDB::getInstance($this->dbType);
		$orderBy = 'ct_rank';
		$filter = intVal($filter);
		// Don't pass in a filter, will filter below
		$vars['articles'] = $db->getArticlesByTagName($rawTag, 0, self::MAX_NUM_ROWS, WAPArticleTagDB::ARTICLE_UNASSIGNED, '', $orderBy);
		if (!empty($filter)) {
			foreach ($vars['articles'] as $i => $a) {
				// bitwise filter by category
				if (!($a->getCatInfo() & $filter)) {
					unset($vars['articles'][$i]);
				}
			}
		}

		$vars['u'] = BabelfishUser::newFromUserObject($wgUser, $this->dbType);
		$vars['numrows'] = $rows;
		$vars['tag'] = $rawTag;
		$config = WAPDB::getInstance($this->dbType)->getWAPConfig();
		$linkerClass = $config->getLinkerClassName();
		$vars['linker'] = new $linkerClass($this->dbType);

		$tmpl = new WAPTemplate($this->dbType);
		$html = $tmpl->getHtml('tag_list_pager_rows.tmpl.php', $vars);
		return $html;

	}
}
