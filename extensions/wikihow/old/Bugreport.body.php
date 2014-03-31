<?
class Bugreport extends UnlistedSpecialPage {

    function __construct() {
        parent::__construct( 'Bugreport' );
    }


    function execute ($par) {
		global $wgRequest, $wgOut, $wgUser;
		$wgOut->disable();
		if ($wgRequest->wasPosted()) {
			$dbw = wfGetDB(DB_MASTER);
			$dbw->insert('bugreport',
				array(
						'br_user'			=> $wgUser->getId(), 
						'br_user_text'		=> $wgUser->getName(),
						'br_timestamp'		=> wfTimestamp(TS_MW),
						'br_summary'		=> $wgRequest->getVal('summary'), 
						'br_details'		=> $wgRequest->getVal('details'),
						'br_history'		=> $wgRequest->getVal('history'),
				)
			);
			echo "Bug submitted " . wfTimestamp(TS_MW);
		}else {
			echo "Hi, post only.";
		}
		return;
	}
}

