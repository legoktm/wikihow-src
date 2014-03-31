<?php

$messages = array();

$messages['en'] = array (
	"fbc_form_prefill" => "
		<div id='fbc'>
			<div id='fbc_header'>
				<img id='fbc_icon' src ='$1'/>
				<div class='fbc_header_text' id='fbc_header_default'>To save you time, the registration form below has been prefilled using your Facebook profile.</div>
				<div class='fbc_header_text' id='fbc_header_prefill'><a href='#' id='fbc_prefilled' class='fbc_link'>Prefill the form below with my Facebook profile information</a></div>
			</div>
			<form method='POST' id='fbc_form' action='/Special:FBLogin'>
				<input name='proposed_username' class='fbc_readonly fbc_username' type='hidden' value='$2'/>
				<table>
					<tr><td colspan='2'><div id='fbc_error'>$3</div></td></tr>
					<tr>
						<td class='fbc_label'>wikiHow Username:</td>
						<td>
							<div id='fbc_faux_username' class='fbc_readonly'>
								<div id='fbc_x'>X</div>
								<img class='fbc_user_avatar' src='$4'>
								<div id='fbc_user_text'><div id='fbc_user_text_username'>$2</div>
								<div id='fbc_user_text_info'>$5 $6 friends</div></div>
							</div> 
							<input type='text' name='requested_username' id='fbc_requested_username' class='fbc_hidden'/>
						</td>
					<tr>
						<td class='fbc_label'>Email Address:</td>
						<td><input name='email' class='fbc_readonly' type='text' value='$7' readonly='readonly'/></td>
					</tr>
					<tr>
						<td></td>
						<td><input type='submit' id='fbc_submit' class='fbc_submit' value='Register'/> $8</td>
					</tr>
				</table>
			</form>
			<div id='fbc_footer'>Clicking Register will also give Developer Site access to your Facebook friends list and other public information. 
			<a href='http://www.facebook.com/about/login/' class='fbc_link'>Learn more</a></div>
		</div>
	",
	"fbc_form_no_prefill" => "
		<div id='fbc'>
			<div id='fbc_header'>
				<img id='fbc_icon' src ='$1'/>
				<div class='fbc_header_text' id='fbc_header_default'>Please fill out the form below to register.</div>
				<div class='fbc_header_text' id='fbc_header_prefill'><a href='#' id='fbc_prefilled' class='fbc_link'>Prefill the form below with my Facebook profile information</a></div>
			</div>
			<form method='POST' id='fbc_form' action='/Special:FBLogin'>
				<input name='proposed_username' class='fbc_readonly fbc_username' type='hidden' value='$2'/>
				<table>
					<tr><td colspan='2'><div id='fbc_error'>$3</div></td></tr>
					<tr>
						<td class='fbc_label'>wikiHow Username:</td>
						<td>
							<input type='text' name='requested_username' id='fbc_requested_username'/>
						</td>
					<tr>
						<td class='fbc_label'>Email Address:</td>
						<td><input name='email' type='text' value='$4'/></td>
					</tr>
					<tr>
						<td></td>
						<td><input type='submit' id='fbc_submit' class='fbc_submit' value='Register'/> $5</td>
					</tr>
				</table>
			</form>
			<div id='fbc_footer'>Clicking Register will also give Developer Site access to your Facebook friends list and other public information. 
			<a href='http://www.facebook.com/about/login/' class='fbc_link'>Learn more</a></div>
		</div>
	",
	"fbc_returnto" => "/Special:CommunityDashboard",
);
