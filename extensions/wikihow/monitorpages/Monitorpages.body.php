<?
class Monitorpages extends UnlistedSpecialPage {

    function __construct() {
        parent::__construct( 'Monitorpages' );
    }

    function execute ($par) {
		global $wgOut, $wgUser, $wgRequest, $wgServer, $wgContLang;

        if ( !in_array( 'sysop', $wgUser->getGroups() ) ) {
            $wgOut->setArticleRelated( false );
            $wgOut->setRobotpolicy( 'noindex,nofollow' );
            $wgOut->showErrorPage( 'nosuchspecialpage', 'nospecialpagetext' );
            return;
        }

		$this->setHeaders();	
		$target = isset( $par ) ? $par : $wgRequest->getVal( 'target' );
		$dbw = wfGetDB(DB_MASTER);
		$sk = $wgUser->getSkin();
		$me = Title::makeTitle(NS_SPECIAL, "Monitorpages");
		
		$wgOut->addHTML('  <style type="text/css" media="all">/*<![CDATA[*/ @import "/extensions/wikihow/monitorpages/Monitorpages.css"; /*]]>*/</style>');
	    if (!strlen ($target)) {
	
			if ($wgRequest->getVal('deactivate', null) ) {
				$t = Title::newFromURL($wgRequest->getVal('deactivate'));
				$id = $t->getArticleId();
				$dbw->query("UPDATE google_monitor SET gm_active=0 WHERE gm_page=$id;");
			}
	        if ($wgRequest->getVal('activate', null) ) {
	            $t = Title::newFromURL($wgRequest->getVal('activate'));
	            $id = $t->getArticleId();
	            $dbw->query("UPDATE google_monitor SET gm_active=1 WHERE gm_page=$id;");
	        }
			if ($wgRequest->wasPosted() ){
				$vals = $wgRequest->getVal('pages');
				$vals = str_replace("\r\n", "\n", $vals);
				$pages = split("\n", $vals);
				foreach ($pages as $p) {
					$p = trim($p);
					if ($p == '') continue;
					$p = str_replace("http://www.wikihow.com/", "", $p);
					$p = str_replace($wgServer . "/", "", $p);
					$t = Title::newFromURL(urldecode($p));
					if (!$t) {
						$wgOut->addHTML("Error: couldn't make a title for '$p'<br/>");
						continue;
					}
					$id  = $t->getArticleID();
					$wgOut->addHTML("adding $id ");	
					$dbw->query("INSERT INTO google_monitor (gm_page) VALUES ($id);" );
				}
		
			}
			$res = $dbw->select ( array('page','google_monitor'),
					array('page_namespace', 'page_title'),
					array ('page_id=gm_page', 'gm_active=1')
				);
			$wgOut->addHTML("<h2>Pages being monitored</h2><ol>");
			while ($row = $dbw->fetchObject($res) ) {
				$t = Title::makeTitle($row->page_namespace, $row->page_title);
				$dest = SpecialPage::getTitleFor( 'Monitorpages', $t->getText() );
				$wgOut->addHTML("<li>" . $sk->makeLinkObj($dest, $t->getFullText()) . 
					 " - (" .  $sk->makeLinkObj($me, "deactivate", "deactivate=" . $t->getPrefixedURL() ) . ")</li>");
			}
			$wgOut->addHTML("</ol>");
			$dbw->freeResult($res);
	
	
			$res = $dbw->select ( array('page','google_monitor'),
					array('page_namespace', 'page_title'),
					array ('page_id=gm_page', 'gm_active=0')
				);
			$wgOut->addHTML("<h2>Pages previously monitored</h2><ol>");
			while ($row = $dbw->fetchObject($res) ) {
				$t = Title::makeTitle($row->page_namespace, $row->page_title);
				$dest = SpecialPage::getTitleFor( 'Monitorpages', $t->getPrefixedURL() );
				$wgOut->addHTML("<li>" . $sk->makeLinkObj($dest, $t->getFullText()) . 
	                 " - (" .  $sk->makeLinkObj($me, "activate", "activate=" . $t->getPrefixedURL() ) . ")</li>");
			}
			$wgOut->addHTML("</ol>");
			$dbw->freeResult($res);
	
			$wgOut->addHTML("Add these pages to be be monitored: <br/>
					<form method='POST' action={$me->getFullURL()}>
					<textarea name='pages' rows='3' cols='60'></textarea><br/><br/>
					<input type='submit' value='Submit'>
					</form>");
		} else {
			$t = Title::newFromURL($target);
			$id = $t->getArticleID();
			$res = $dbw->select('google_monitor_results', array('gmr_timestamp', 'gmr_position'), array("gmr_page=$id"));
			$wgOut->addHTML("Results for " . $sk->makeLInkObj($t, $t->getText())  
					. " - (<a href='http://www.google.com/search?q=" . urlencode("How to " . $t->getText()) . "' target='new'>link</a>)<br/><br/>");
			$wgOut->addHTML("<ol>");
			$lastpos = -1;
			while ($row = $dbw->fetchObject($res)) {
				$timestamp = $wgContLang->timeanddate( wfTimestamp( TS_MW, $row->gmr_timestamp ) );
				$class = "";
				if ($lastpos > 0) {
					if ($lastpos > $row->gmr_position) {
						$class = "monitor_good";
					} else if ($row->gmr_position > $lastpos) {
						$class = "monitor_bad";
					}	
				}
				$wgOut->addHTML ("<li class='$class'>$timestamp - Position : {$row->gmr_position}</li>");
				$lastpos = $row->gmr_position;
			}
			$dbw->freeResult($res);
			$wgOut->addHTML("</ol>");
			$wgOut->addHTML($sk->makeLinkObj($me, "Return to the main list"));
		}
	}
}
