<?
class CheckG extends UnlistedSpecialPage {

	function __construct() {
		parent::__construct( 'CheckG' );
	}

	function execute ($par) {
		global $wgRequest, $wgOut, $wgUser;
		
		$target = isset( $par ) ? $par : $wgRequest->getVal( 'target' );
		$createdate = $wgRequest->getVal( 'createdate' );
	  	if ( !in_array( 'sysop', $wgUser->getGroups() ) ) {
			$wgOut->showErrorPage( 'nosuchspecialpage', 'nospecialpagetext' );
		 	return;
	  	}

		// get the averages

		$dbr = wfGetDB(DB_SLAVE);
		$row = $dbr->selectRow('google_indexed', array('avg(gi_indexed) as A', 'count(*) as C' ),  array('gi_times_checked > 0'));
		$wgOut->addHTML("Number of pages checked: {$row->C} <br/>Average of those indexed: " . number_format($row->A * 100, 2) . "%<br/>");	

		$left = $dbr->selectField('google_indexed', array('count(*) as C'), array('gi_times_checked'=>0));
		$wgOut->addHTML("Pages which have not been checked: " . number_format($left, 0, "", ",") . "<br/><br/>");


		// do we have a target ? 
		if ($createdate && $target) {
			$sql = "select page_title, gl_err, gl_page, gl_pos, substr(gi_page_created, 1, 8) as createdate
                    from google_indexed_log left join google_indexed on gi_page=gl_page left join page on page_id=gl_page
					where substr(gi_page_created, 1, 8)='{$createdate}' and substr(gl_checked, 1, 8) = '{$target}';";
			$res = $dbr->query($sql); 
			$f = preg_replace("@([0-9]{4})([0-9]{2})([0-9]{2})@", "$1-$2-$3", $target);
			$c = preg_replace("@([0-9]{4})([0-9]{2})([0-9]{2})@", "$1-$2-$3", $createdate);
			$wgOut->addHTML("<h2>Detailed report for the {$f} check for pages created on {$c}</h2>
					<table width='80%' align='center'>
					<tr><td>Article</td><td>Indexed?</td><td>Error?</td><td>Check</td></tr>
				");
			while ($row = $dbr->fetchObject($res)) {
				$t = Title::newFromDBKey($row->page_title);
			    $query = $t->getText() . " site:wikihow.com";
   				$url = "http://www.google.com/search?q=" . urlencode($query) . "&num=100";
				$wgOut->addHTML("<tr><td><a href='{$t->getFullURL()}'>{$t->getText()}</td><td>{$row->gl_pos}</td><td>{$row->gl_err}</td><td><a href='{$url}' target='new'>Link</a></td></tr>");
			}
			$wgOut->addHTML("</table>");
		} else if ($target) {
			$sql = "select substr(gi_page_created, 1, 8) as D, count(*) as C, avg(gl_pos)  as A
                    from google_indexed_log left join google_indexed on gi_page=gl_page                     
					where gl_err = 0 group by D order by D desc;";
			$f = preg_replace("@([0-9]{4})([0-9]{2})([0-9]{2})@", "$1-$2-$3", $target);
			$wgOut->addHTML("<h2>Report for the {$f} check</h2>
					<table width='80%' align='center'><tr><td>Page creation date</td><td># of pages checked</td><td>Average indexed</td></tr>");
			$res = $dbr->query($sql); 
			while ($row = $dbr->fetchObject($res)) {
				$avg = number_format($row->A * 100, 2);
				$count = number_format($row->C, 0, "", ",");
				$f = preg_replace("@([0-9]{4})([0-9]{2})([0-9]{2})@", "$1-$2-$3", $row->D);
				$wgOut->addHTML("<tr><td><a href='/Special:CheckG/$target?createdate={$row->D}'>$f</a></td><td>$count</td><td>$avg%</td></tr>");
			}
			$wgOut->addHTML("</table>");
			$errs = $dbr->selectField("google_indexed_log", array("count(*)"), array("gl_checked like '$target%'", "gl_err"=>1));
			$wgOut->addHTML("<br/><br/>Number of errors occurred in this check: $errs<br/>");
			
		}

		// list the individual reports we ran
		$wgOut->addHTML("<br/><br/><h2>Individual reports</h2><ul>");
		$sql = "select substr(gl_checked, 1, 8) as D from google_indexed_log group by D order by D desc;";
		$res = $dbr->query($sql); 
		while ($row = $dbr->fetchObject($res)) {
			$f = preg_replace("@([0-9]{4})([0-9]{2})([0-9]{2})@", "$1-$2-$3", $row->D);
			if ($target == $row->D) {
				$wgOut->addHTML("<li>{$f} (you are looking at it)</li>\n");
			} else {
				$wgOut->addHTML("<li><a href='/Special:CheckG/{$row->D}'>{$f}</a></li>\n");
			}
		}		
		$wgOut->addHTML("</ul>");
	}
}
	
