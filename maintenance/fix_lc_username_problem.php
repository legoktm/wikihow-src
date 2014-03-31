<?
	require_once('commandLine.inc');
	$dbw = wfGetDB(DB_MASTER);
/*
	$dbr = wfGetDB(DB_SLAVE);
	$res = $dbr->select('user', array('user_name', 'user_editcount', 'user_id', 'user_registration'),
		array(),
		"testing",
		array("ORDER BY" => "user_id desc")
		);
	while ($row = $dbr->fetchObject($res)) {
		$user_name = $row->user_name;
		if (preg_match("@^[a-z]@", $user_name))  {
			$user_name = User::getCanonicalName($user_name);
			$dbw->update('user', 
				array('user_name' => $user_name),
				array('user_id' => $row->user_id));
			echo "updated user $user_name\n";	
		}
	}
*/
	$bad = split("\n", file_get_contents("bad_usernames.txt"));
	foreach ($bad as $b) {
		$old = trim($b);
		$old{0} = strtolower($old{0});
		if ($old == "") continue;
		$up = $dbw->update('revision',
			array('rev_user_text' => $b),
			array('rev_user_text' => $old)
		);
		echo "$old \t $up\n";
	}
