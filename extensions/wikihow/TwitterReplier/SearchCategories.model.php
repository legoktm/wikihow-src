<?php

class SearchCategoryModel {
	
	private $dbr;
	
	const SEARCH_TABLE = 'twitterreplier_search_categories';
	function __construct()
	{
		if ( empty( $this->dbr ) ) {
			$this->dbr = wfGetDB( DB_SLAVE );
		}
	}
	
	public function getSearchCategories( $type = 'inboxq' )
	{
		$keywords = array();
		
		$fields = array();
		$fields[] = 'keywords';
		$fields[] = 'id';
		
		$where = array();
		$where['type'] = $type;
		
		try {
			$res = $this->dbr->select( self::SEARCH_TABLE, $fields, $where );
			
			if( $this->dbr->numRows( $res ) > 0 ) {
				while( $row = $this->dbr->fetchRow( $res ) ) {
					$keywords[] = $row;
				}
			}
		}
		catch( Exception $e ) {
			echo $e->getMessage();
		}
		
		return $keywords;
	}
}