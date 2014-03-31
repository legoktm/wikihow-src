<?

$wgHooks['UserLogout'][] = array("wfLogoutOfVanilla");
$wgHooks['UserLoginComplete'][] = array("wfProcessVanillaRedirect"); 
$wgHooks['UserLoginComplete'][] = array("wfLogoutOfVanilla"); 
$wgHooks['BlockIpComplete'][] = 'wfBlockVanillaUser';
$wgHooks['AvatarUpdated'][] = 'wfUpdateVanillaPicture';

function wfLogoutOfVanilla() {
	global $wgCookieDomain;
	$cookies = array('Vanilla', 'Vanilla-Volatile');
	foreach ($cookies as $c) {
		setcookie($c, ' ', time() - 3600, '/', '.' . $wgCookieDomain);
		unset($_COOKIE[$c]);
	}
	return true;
}

function wfProcessVanillaRedirect() {
	global $wgRequest, $wgOut;
	if ($wgRequest->getVal('returnto') == 'vanilla') {
		$wgOut->redirect('http://forums.wikihow.com');
	}
	return true;
}

$wgSpecialPages['Vanilla'] = 'Vanilla';
$dir = dirname(__FILE__) . '/';
$wgAutoloadClasses['Vanilla']		  = $dir . 'Vanilla.body.php';


#$wgHooks['ArticleSaveComplete'][] = array("wfCheckIp");
#$wgHooks['GeneratingUrl'][] = array("wfCheckPAD");

function wfCheckIp($article, $user, $text) {
	global $wgUser;
	$ip = wfGetIP();
	if (strpos($ip, "192.168.100") !== false ){	
		$alerts = new MailAddress("alerts@wikihow.com");
		$subject = "Bad ip connected to " . wfHostname() . " - " . date("r");
		$body = "UHOH: $ip User {$wgUser->getName()} " 
				. "\n-------------------------------------\n" 
				. print_r(getallheaders(), true) 
				. "\n-------------------------------------\n" 
				. print_r($_POST, true) 
				. "\n-------------------------------------\n" 
				. print_r($_SERVER, true) 
				. "\n-------------------------------------\n" 
				. print_r($wgUser, true) 
				. "\n-------------------------------------\n" 
				.  wfBacktrace() 
				. "\n-------------------------------------\n" 
				. print_r($article) 
				. "\n";
		UserMailer::send($alerts, $alerts, $subject, $body, $alerts); 
		error_log($body);
		wfDebug($body);	
	}
	return true;
}

function wfBlockVanillaUser($block, $user) {
	global $wgVanillaDB;
	try {
		$target = $block->getTargetAndType();
		if(!is_array($target) || $target[1] != Block::TYPE_USER) return true;
		$user = User::newFromName($target[0], false);
		if($user) $user->load();
		if (!$user || $user->getId() == 0) return true;
		Vanilla::setUserRole($user->getId(), 1);
	} catch (Exception $e) {
		wfDebug( 'VANILLA FORUMS ERROR ' . print_r($e, true) );
		print_r($e); exit;
	}
	return true;
}

function wfUpdateVanillaPicture($user) {
	Vanilla::setAvatar($user); 
	return true;
}

function wfCheckPAD($url, $pad) {
	global $wgServer, $wgTitle, $wgCookieDomain; 
	if ($wgServer == "http://testers.wikihow.com") return true;
	if (($wgServer == "http://www.wikihow.com" || strpos(wfHostname(), "wikihow.com") !== false)
		&& strpos($pad, "whstatic") === false) {
        $alerts = new MailAddress("alerts@wikihow.com");
		// format of date to correspond to varnish file 04/Dec/2010:07:19:06 -0800
		$now = date("d/M/Y:h:i:s O");
        $subject = "Not using PAD for thumbnail on " . wfHostname() . " - " . $now;
		$body  = "article {$wgTitle->getFullURL()}\n\n
Url: $url \n\n 
pad $pad \n\n
server variables " . print_r($_SERVER, true) . "\n\n allheaders: " 
			. print_r(getallheaders(), true) 
			. "\n\n wgserver $wgServer
\ncookie domain $wgCookieDomain
\n Title " . print_r($wgTitle, true) 
			. "\n\nbacktrace: " . strip_tags(wfBacktrace()); 
        UserMailer::send($alerts, $alerts, $subject, $body, $alerts);
        error_log($body);
        wfDebug($body);
	}
	return true;
}
