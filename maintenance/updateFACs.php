<?
require_once( "commandLine.inc" );

$wgUser->setId(1236204);

	$dbr =& wfGetDB( DB_SLAVE );
	$res = $dbr->query(
		'select page_title, page_namespace, page_id  from templatelinks, page  where tl_from=page_id and tl_title=\'Fac\';'
			);
	while ( $row = $dbr->fetchObject($res) ) {
		$title = Title::makeTitle( $row->page_namespace, $row->page_title );
		$res2 = $dbr->query("select rev_id, rev_timestamp from revision where rev_page={$row->page_id} order by rev_id;");
		while ($row2 = $dbr->fetchObject($res2)) {
			$r = Revision::newFromId($row2->rev_id);
			if (strpos($r->getText(), "{{fac") !== false) {
				$lt = $row2->rev_timestamp;
				$last_id = $row2->rev_id;
				break; 
			} else {
			}
		}
		$dbr->freeResult($res2);
		$d = substr($lt, 0, 4) . "-" . substr($lt, 4, 2) . "-" . substr($lt, 6, 2);
		//echo "{$title->getFullURL()} first revision with {{fac}} was {$d}\n";
		$revision = Revision::newFromTitle($title);
		$text = $revision->getText();
		if (strpos($text, "{{fac|date=") === false) {
			$text = str_replace("{{fac}}", "{{fac|date=$d}}", $text);
			//echo $text;
			$a = new Article(&$title);
			$a->updateArticle($text, "Adding date to {{fac}}", true, false);
			echo "updating {$title->getFullURL()}\n";
		} else {
			echo "NOT UPDATING {$title->getFullURL()}\n";
		}
	}	
	$dbr->freeResult($res);
?>
