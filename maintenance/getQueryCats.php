<?php
require_once(__DIR__ . '/Maintenance.php');
class GetQueryCats extends Maintenance {                                                                    
	public function __construct() {
		parent::__construct();
		$this->mDescription = "Find categories of queries";
		$this->addOption( 'f', 'CSV Filename with queries', true, true );
		$this->addOption('s', 'Skip title',false,false);
	}
	public function execute() {
		$fname = $this->getOption('f');
		$skip = $this->hasOption('s');
		$f = fopen($fname, "r");
		$first = true;
		while($f && !feof($f)) {	
			$l = fgets($f);
			if($skip && $first) {
				$first = false;	
				continue;
			}
			if($l) {
				$l = rtrim($l);
				$fs = preg_split("@,@",$l);
				$fs[0] = str_replace("\"","",$fs[0]);
				if($fs[0]) { 
					QueryCat::printQueryLevelCat($fs[0]);
				}
			}
		}
	}
}

$maintClass = "GetQueryCats";
require_once RUN_MAINTENANCE_IF_MAIN;                                                                            

