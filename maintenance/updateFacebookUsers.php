<?
// Removes duplicate facebook_connect table entries
require_once('commandLine.inc');

$dbw = wfGetDB(DB_MASTER);
$dbw->selectDB($wgSharedDB);
$sql = 'select count(fb_user) as cnt, fb_user, wh_user from facebook_connect group by fb_user order by cnt desc';
$res = $dbw->query($sql);
while ($row = $dbw->fetchObject($res)) {
	if($row->cnt == 1) {
		break;
	}
	if ($row->wh_user != 0) {
		var_dump($row);
		$dbw->delete('facebook_connect', array('fb_user' => $row->fb_user));
		$dbw->insert('facebook_connect', array('fb_user' => $row->fb_user, 'wh_user' => $row->wh_user));
	}
}
