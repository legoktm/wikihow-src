<?php


class Pageview {
	
	public static function get30day($pageId) {
		global $wgMemc;
		
		$key = "30day-" . $pageId;
		$val = $wgMemc->get($key);
		
		if(!$val) {
			$dbr = wfGetDB(DB_SLAVE);
			$val =  $dbr->selectField('pageview', 'pv_30day', array('pv_page' => $pageId));
			$wgMemc->set($key, $val);
		}
		
		return $val;
		
	}
	
	public static function update30day($pageId, $val) {
		global $wgMemc;
		
		$key = "30day-" . $pageId;
		
		$wgMemc->set($key, $val);
	}
}
