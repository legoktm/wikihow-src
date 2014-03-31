<?php

if ( !defined('MEDIAWIKI') ) exit;

class FollowWidget extends UnlistedSpecialPage {

	function __construct() {
		parent::__construct( 'FollowWidget' );
	}


	function showWidget() {

?>
	<h3><?= wfMessage('fw-header')->text() ?></h3>
	<?= wfMessage('fw-table', wfGetPad())->text() ?>

<?php
	}
	
	public function getForm() {
?>
		<h3 style="margin-bottom:10px"><?= wfMessage('fw-title')->text() ?></h3>
		<!--<form action="#" >
			<input id="followEmail" type="text" value="" style="float:right; width:320px;" />
			<label for="followEmail">Your email:</label><br /><br />
			<p><?= wfMessage('fw-message')->text() ?></p>
			<br />
			<a href="#" class="button button52" onmouseout="button_unswap(this);" onmouseover="button_swap(this);" style="float:right; margin-left:10px;" onclick="followWidget.submitEmail(jQuery('#followEmail').val()); return false;">OK</a> <a href="#" style="float:right; line-height:26px;" onclick="closeModal(); return false;" >Cancel</a> 
		</form>-->
		

		<?php
	}

	public function execute($par) {
		global $wgOut, $wgRequest;

		wfLoadExtensionMessages('FollowWidget');


		$wgOut->setArticleBodyOnly(true);
		
		$email = $wgRequest->getVal('getEmailForm');
		if($email == "1") {
			$form = '<link type="text/css" rel="stylesheet" href="/extensions/wikihow/common/jquery-ui-themes/jquery-ui.css" />
				<form id="ccsfg" name="ccsfg" method="post" action="/extensions/wikihow/common/CCSFG/signup/index.php" style="display:none;">

		<h4>'.wfMessage('fw-head')->text().'</h4>
		<p style="width:220px; margin-bottom: 23px; font-size:14px;">'.wfMessage('fw-blurb')->text().'</p>
		<img src="' . wfGetPad('/skins/WikiHow/images/kiwi-small.png') . '" nopin="nopin" style="position:absolute; right:90px; top:68px;" />';
		
		$form .= <<<EOHTML
		<table>
		
			<tr><td colspan="2">
					<!-- ########## Email Address ########## -->
					<label for="EmailAddress">Email Address</label><br />
					<input type="text" name="EmailAddress" value="" id="EmailAddress" style="width:350px; height:25px; font-size:13px;" /><br /><br />
				</td>
			</tr>
			<tr>
				<td styel="padding-right:4px;">
					<!-- ########## First Name ########## -->
					<label for="FirstName">First Name (optional):</label><br />
					<input type="text" name="FirstName" value="" id="FirstName" style="width:215px; height:25px; margin-right:10px; font-size:13px;" /><br />
				</td>
				<td>
					<!-- ########## Last Name ########## -->
					<label for="LastName">Last Name (optional):</label><br />
					<input type="text" name="LastName" value="" id="LastName" style="width:215px; height:25px; font-size:13px;" /><br />
				</td>
			<tr>
			<tr><td colspan="2">
				<!-- ########## Contact Lists ########## -->
				<input type="hidden"  checked="checked"  value="General Interest" name="Lists[]" id="list_General Interest" />

				<input type="submit" name="signup" id="signup" value="Join" class="button primary" />
			</td></tr>
		</table>

		</form>	
EOHTML;
		echo $form;
		}
		else
			$wgOut->addHTML($this->getForm());

	}

}

class SubmitEmail extends UnlistedSpecialPage {

	public function __construct() {
		parent::__construct('SubmitEmail');
	}

	public function execute($par) {
		global $wgRequest, $wgOut;
		wfLoadExtensionMessages('FollowWidget');
		
		$wgOut->disable(true);
		
		$email = $wgRequest->getVal('newEmail');
		
		if(!User::isValidEmailAddr($email)) {
			$arr = array ('success' => false, 'message' => wfMessage('invalidemailaddress')->text() );
			echo json_encode($arr);
		
			return;
		}
		
		$dbw =& wfGetDB(DB_MASTER);
		$res = $dbw->select(
			array('emailfeed'),
			array('email'),
			array('email' => $email)
		);
		
		if($res->numRows() == 0) {
			$res = $dbw->insert(
				'emailfeed',
				array('email' => $email)
			);
			$arr = array ('success' => true, 'message' => wfMessage('fw-added')->text() );
		} else {
			$arr = array ('success' => false,'message' => wfMessage('fw-exists')->text() );
		}

		echo json_encode($arr);
	}
}


