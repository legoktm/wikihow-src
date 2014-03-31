<?
class Netseer extends UnlistedSpecialPage {

    function __construct() {
        parent::__construct( 'Netseer' );
    }

    function execute ($par) {
		global $wgOut, $wgRequest, $wgUser;
		$wgOut->addHTML('
<script type="text/javascript">
      netseer_taglink_id = "2336";
      netseer_ad_width = "630";
      netseer_ad_height = "700";
      netseer_task = "lp";
</script>
<script src="http://contextlinks.netseer.com/dsatserving2/scripts/netseerads.js" type="text/javascript"></script>'
	);
	}
}
