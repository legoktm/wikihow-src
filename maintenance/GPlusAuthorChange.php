<?php
global $IP;

require_once('commandLine.inc');

if ( count( $args ) == 0 ) {
	echo "Usage: php GPlusAuthorChange.php <user_name>\n";
	exit(1);
}

class GPlusAuthorChange {

	function toggleAuthorship($u) {
		$author_flag = $u->getOption('show_google_authorship');
		$new_flag = !$author_flag;
		$u->setOption('show_google_authorship',$new_flag);
		$u->saveSettings();
		
		return $new_flag;
	}

	function main($username) {
		$u = User::newFromName($username);
		
		if ($u->getID() == 0) {
			echo "Invalid user name.\n";
			return;
		}
		
		//toggle authorship flag
		$res = self::toggleAuthorship($u);		
		($res) ? $new = 'on' : $new = 'off';
		
		echo 'The G+ authorship flag for '.$username.' is now '.$new.".\n";
	}
}

GPlusAuthorChange::main($args[0]);
