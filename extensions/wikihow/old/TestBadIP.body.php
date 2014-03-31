<?
class TestBadIP extends SpecialPage {

    function __construct() {
        parent::__construct( 'TestBadIP' );
    }

    function execute ($par) {
		global $wgRequest, $wgOut, $wgUser;
		$target = isset( $par ) ? $par : $wgRequest->getVal( 'target' );

		if ($target == 'getip') {
			//$h = print_r($_SERVER, true);
			$wgOut->disable();
			echo wfGetIP();
			return;	
		}	

		$wgOut->addHTML(<<<END
<script type='text/javascript'>

			$(document).ready(function() {
						var obj = $.ajax({
   type: 'POST',
   url:'/Special:TestBadIP',
	data: 'target=getip',
   success: function(msg){
     alert( msg +   obj.getAllResponseHeaders());
	
   }
 });
					}
				);
			
		</script>
END
);
		return;
	}
}
