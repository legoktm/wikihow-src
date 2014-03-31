<?php

class EditRedirect extends UnlistedSpecialPage
{
	const OUR_SESSION_NAME = 'hydra_editredirect';
	const EXPERIMENT_NAME = 'after_edit_choice_redirect2';
	
	public function __construct() {
		parent::__construct("EditRedirect");
	}

	/*
	 * Callback to active Hydra on the first non-tool edit
	 */
	static function onHydraMainEdit($experiment, $noTools, $main, $isTool) {
		if($experiment == self::EXPERIMENT_NAME && $noTools == 1 && !$isTool) {
				$_SESSION[self::OUR_SESSION_NAME] = true;	
		}
		return true;
	}
	static function beforeHeaderDisplay($isMobile) {
		global $whEditRedirectSave, $wgOut, $wgRequest, $wgUser, $wgTitle;
		/*
		 * We only want to display the edit redirect page on desktop after the first edit on a main namespace page. We check a bunch of criteria to ensure this is he case.
		 * criteria to ensure this is indeed the first edit
		 */
		if((!$isMobile && isset($_SESSION[self::OUR_SESSION_NAME]) && $_SESSION[self::OUR_SESSION_NAME] && Hydra::isEnabled(self::EXPERIMENT_NAME) ) || $wgRequest->getVal("abtest_test")=="1") {
			// Turn off cache because this is a onetime thing
			$wgOut->enableClientCache(false);
			unset($_SESSION[self::OUR_SESSION_NAME]);
			// We only display the edit redirect dialog for main namespace edits
			if($wgTitle->getNamespace() == NS_MAIN && $wgRequest->getText( 'action', 'view' ) =='view' ) {
				$wgOut->addScript( <<<EOD
	<style type="text/css">
	.no-close .ui-dialog-titlebar-close {
		display:none;	
	}
	</style>
		<script type="text/javascript">
(function($) {
	$(document).ready(function() {
		var txt = "<span>There are tons of other ways to contribute around here! What do you want to try next?</span><br/><br/>";
		txt += '<ul style="list-style:none;"><li style="padding-bottom:10px;"><a href="/Special:TipsPatrol?utm_source=post+edit+link&utm_medium=link&utm_campaign=abtest_choice_tips" style="font-size:12pt;" id="edit_redirect_tips_link" style="display:inline;border:none;" ><span style="font-weight:bold;"> Take me to Tips Patrol</span>: Review, edit, and add tips </a></li>'; 
		txt += '<li><a tabindex="3" href="/Special:RCPatrol?utm_source=post+edit+link&utm_medium=link&utm_campaign=abtest_choice_rc_patrol" style="font-size:12pt;" id="edit_redirect_rc_patrol_link" style="display:inline;border:none;font-weight:bold;"><span style="font-weight:bold;">Take me to RC Patrol</span>: Check and approve recent edits</a></li></ul>'; 
		txt += '<div style="float:right;"><a href="#" style="text-decoration:underline;" class="cancel_button" tabindex="1">No, Thanks</a>';
		$("#dialog-box").html(txt);
		$("#dialog-box").dialog({
			width: 600,
			modal: true,
			title: 'Thanks for your help!',
			closeText: 'Close',
			open: function() {
				$(".cancel_button").click(function() {
					$("#dialog-box").dialog("close");
					return false;
				});
				$(".cancel_button").focus();
			}
		});
	});
})(jQuery);
</script>
EOD
);
	}
		}
		return true;
	}
}
