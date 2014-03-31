<?

/*
* Ajax end-point for logging "bounce times" (time between
* page-load and when the user navigates away.) 
* This is for a small experiment, and not for use with more than
* a few pages.
*
* @author Ryo
*/
class BounceTimeLogger extends UnlistedSpecialPage {

	public function __construct() {
		parent::__construct('BounceTimeLogger');

		//this page gets requested onUnload. set ignore_user_abort()
		//to make sure this script finishes executing even if the
		//client disconnects mid-way.
		ignore_user_abort(true);
	}

	private static function getBuckets() {
		return array(
				'0-10s'	=> 0,
				'11-30s' => 11,
				'31-60s' => 31,
				'1-3m'	=> 60,
				'3-10m'	=> 180,
				'10-30m' => 600,
				'30+m'	=> 1800
			);
	}

	private static function bucketize($n) {
		$buckets = self::getBuckets();
		$b = false; 
		foreach ($buckets as $label => $threshold) {
			//find highest bucket that $n is above
			if ($n >= $threshold) $b = $label;
		}
		return $b;
	}

	public function execute($par) {
		global $wgRequest, $wgOut;

		$priority = $wgRequest->getVal('_priority', 0);
		$domain = $wgRequest->getVal('_domain');
		$message = $wgRequest->getVal('_message');
		$build = $wgRequest->getInt('_build');
		$v = $wgRequest->getVal('v');

		$wgOut->setArticleBodyOnly(true);

		if ($build < 4) {
			print 'ignoring';
			return;
		}

		if ($v != 6) {
			print 'wrong version';
			return;
		}

		if (!is_numeric($priority) || $priority < 0 || $priority > 3) {
			print 'bad priority';
			return;
		}

		$parts = explode(' ', $message);
		if (count($parts) < 2) {
			print 'bad message';
			return;
		}

		if ($parts[1] == 'ct') {
			$msg = $message;
		} elseif ($parts[1] == 'btraw' && is_numeric($parts[2])) {
			$bucket = self::bucketize($parts[2]);
			if (!$bucket) return; //bad bucket
			$msg = "{$parts[0]} bt $bucket {$parts[2]}";
		} elseif ($parts[1] == 'bt') {
			$msg = $message;
		} else {
			print 'bad message';
			return;
		}

		$msg = "$priority $domain $msg\r\n";
		print $msg;

		self::logwrite($msg);
	}

	private static function logwrite($msg) {
		$fp = fsockopen("127.0.0.1", 30302);
		if (!$fp) return;

		fwrite($fp, $msg);
		fclose($fp);
	} 

}
