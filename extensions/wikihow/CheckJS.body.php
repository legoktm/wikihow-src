<?

class CheckJS extends UnlistedSpecialPage {

    function __construct() {
        parent::__construct( 'CheckJS' );
    }

    function execute($par) {
		global $wgOut, $wgRequest, $wgUser;
	
		$wgOut->setArticleBodyOnly(true);
		$dbw = wfGetDB(DB_MASTER);
		$js = $wgRequest->getVal('js', '');
		if ($js) {
			$val = intval($js == 'yes');
			$dbw->query("insert LOW_PRIORITY into checkjs values ($val, {$wgUser->getID()});");
		} elseif ($wgRequest->getVal('selection', null) != null ) {
			$dbw->query("insert LOW_PRIORITY into share_track (selection) values (" . intval($wgRequest->getVal('selection')) . ");");
		}
		return;	
	}
}
