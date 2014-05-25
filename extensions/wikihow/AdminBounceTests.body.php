<?

if (!defined('MEDIAWIKI')) die();

class AdminBounceTests extends UnlistedSpecialPage {

	static $discardThreshold = 0;
	static $domains = array('bt'=>'www','mb'=>'mobile');

	var $specialPage;

	public function __construct() {
		global $wgTitle;
		$this->specialPage = $wgTitle->getPartialUrl();
		parent::__construct($this->specialPage);
	}

	/**
	 * Parse the input field into an array of URLs and Title objects
	 */
	public static function parseURLlist($pageList) {
		$pageList = preg_split('@[\r\n]+@', $pageList);
		$urls = array();
		foreach ($pageList as $url) {
			$url = trim($url);
			if (!empty($url)) {
				$title = WikiPhoto::getArticleTitle($url);
				$urls[] = array('url' => $url, 'title' => $title);
			}
		}
		return $urls;
	}

	/**
	 * Reset everything for URLs on Petametrics
	 */
	private static function resetPM(&$urls, $resetDesktop = true, $resetMobile = true, $resetTablet = true) {
		$ids = array();
		foreach($urls as $url) {
			$ids[] = "" . $url['title']->getArticleId();	
		}
		$url = 'https://api.petametrics.com/v1/metrics/bydevice/reset';

		$data = array('$apiKey' => WH_PETAMETRICS_API_KEY,
					  '$resetDesktop' => $resetDesktop,
					  '$resetMobile' => $resetMobile,
					  '$resetTablet' => $resetTablet,
					  '$ids' => $ids);
		$json = json_encode($data);
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		$str = curl_exec($ch);
		curl_close($ch);
	}
	/**
	 * Reset the bounce stats for a bunch of articles
	 */
	private static function resetStats(&$urls,$domain='bt') {
		foreach ($urls as &$url) {
			$err = '';
			if ($url['title']) {
				$pages[] = $url['title']->getDBkey();
			}
		}

		if (!$pages) {
			return "ERROR: no pages could be found for reset action";
		}

		$query = array(
			'delete' => '*',
			'from' => $domain,
			'pages' => $pages,
		);

		$ret = self::doBounceQuery($query);
		if (!$ret['err']) {
			$count = 0;
			foreach ($urls as &$url) {
				if ($url['title'] && $url['title']->getArticleID() > 0) {
					$count++;
				}
			}
			return '<i>Stats reset for ' . $count . ' page(s) in '.self::$domains[$domain].'</i><br/>';
		} else {
			return "ERROR: {$ret['err']}";
		}
	}

	private static function resetVarnishes($urls) {
		foreach ($urls as $url) {
			if ($url['title']) {
				$url['title']->purgeSquid();
			}
		}
	}

	/**
	 * Fetch the bounce stats for a bunch of articles
	 */
	private static function fetchStats(&$urls, $domain='bt') {
		$pages = array();
		foreach ($urls as &$url) {
			$err = '';
			if ($url['title']) {
				$pages[] = $url['title']->getDBkey();
			}
		}

		$query = array(
			'select' => '*',
			'from' => $domain,
			'pages' => $pages,
		);

		$ret = self::doBounceQuery($query);
		if (!$ret['err'] && $ret['results']) {
			self::cleanBounceData($ret['results']);
			$stats = self::computeAvgs($ret['results']);
			$html = self::markupStats($stats,$domain);
			return $html;
		} else {
			$err = $ret['err'] ? $ret['err'] : 'stats were not found for any pages you specified';
			return "<i>ERROR: $err</i><br/>";
		}
	}

	/**
	 * Fetch the bounce stats for a bunch of articles
	 */
	private static function outputCSV(&$urls) {
		$domains = array('bt'=>'www','mb'=>'mobile');
		$pages = array();
		foreach ($urls as &$url) {
			$err = '';
			if ($url['title']) {
				$pages[] = $url['title']->getDBkey();
			}
		}

		$queryResults = array();
		foreach ($domains as $domain => $foo) {
			$query = array(
				'select' => '*',
				'from' => $domain,
				'pages' => $pages,
			);
			$queryResults[$domain] = self::doBounceQuery($query);
		}

		foreach ($queryResults as $ret) {
			if ($ret['err'] || !$ret['results']) {
				$err = $ret['err'] ? $ret['err'] : 'stats were not found for any pages you specified';
				return "<i>ERROR: $err</i><br/>";
			}
		}

		$retDesktop = $queryResults['bt'];
		self::cleanBounceData($retDesktop['results']);
		$retMobile = $queryResults['mb'];
		self::cleanBounceData($retMobile['results']);

		foreach ($retDesktop['results'] as $key => $val) {
			$retDesktop['results'][$key]['mobile-0-10s'] = $retMobile['results'][$key]['0-10s'];
			$retDesktop['results'][$key]['mobile__'] = $retMobile['results'][$key]['__'];
		}

		$ret = $retDesktop;
		// Merge them with the original query so ElizaJack can whip up some Excel nonsense
		$results = self::mergeResults($pages, $ret['results']);

		self::displayDataCSV($results);
		$stats = self::computeAvgs($ret['results']);
		$html = self::markupStats($stats,$domain);
		return $html;
	}

	private static function mergeResults(&$pages, &$results) {
		$pages = array_flip($pages);
		foreach ($pages as $page => $data) {
			$result = $results[$page];
			$pages[$page] = $result ? $result : array();
		}
		return $pages;
	}

	/**
	* Hack for Jack/Eliz - add a 3+ min field
	*/
	private static function add3PlusMinField(&$page) {
		// hack for jack/eliz
		$over3 = 0;
		$over3 += isset($page['3-10m']) ? $page['3-10m'] : 0;
		$over3 += isset($page['10-30m']) ? $page['10-30m'] : 0;
		$over3 += isset($page['30+m']) ? $page['30+m'] : 0;
		$page['3+m'] = $over3;
	}

	/**
	 * Remove all data items that don't start with an '_' or end with 'm' or 's'
	 */
	public static function cleanBounceData(&$results) {
		foreach ($results as &$page) {
			foreach ($page as $k => $v) {
				if (!preg_match('@^(_.*|[^_].*[ms]$)@', $k)) {
					unset($page[$k]);
				}
			}
			self::add3PlusMinField($page);
			//self::reorderData($page);
		}
		//var_dump($results);exit;
	}
		
	/**
	 * Computer averages over all pages returned.
	 */
	private static function computeAvgs($stats) {
		$totals = array();
		$averages = array();
		$count = count($stats);
		$discard = 0;
		foreach ($stats as $page) {
			$page_sum = $page['__'];
			if ($page_sum < self::$discardThreshold) {
				$discard++;
				$count--;
				continue;
			}

			foreach ($page as $stat => $total) {
				if (strpos($stat, '_') !== 0) {
					$avg = (float)$total / $page_sum;
				} else {
					$avg = 0.0;
				}
				$totals[$stat] += $total;
				$averages[$stat] += $avg;
			}
		}

		$sum = $totals['__'];

		$filter = function (&$arr) {
			foreach ($arr as $k => $v) {
				if (preg_match('@^_@', $k)) {
					unset($arr[$k]);
				}
			}
		};
		$filter($totals);
		$filter($averages);

		$percentages = array();
		foreach ($totals as $range => $total) {
			$weightedAvg = round( 100 * (float)$total / $sum, 1);
			if ($count > 0) {
				$uniformAvg = round( 100 * $averages[$range] / (float)$count, 1);
			} else {
				$uniformAvg = 0.0;
			}
			$percentages[] = array(
				'range' => $range,
				'weightedAvg' => $weightedAvg . '%',
				'uniformAvg' => $uniformAvg . '%');
		}
		$stats = array(
			'pages' => count($stats),
			'discardedPages' => $discard,
			'exits' => $sum,
			'percentages' => $percentages,
		);
		return $stats;
	}

	/**
	 * Used internal to compare to row heads.
	 */
	public static function cmpBounceDataFunc($ur, $vr) {
		$u_ = strpos($ur, '_') !== false;
		$v_ = strpos($vr, '_') !== false;
		if ($u_ !== $v_) {
			return !$u_ ? 1 : -1;
		}

		$um = strpos($ur, 'm') !== false;
		$vm = strpos($vr, 'm') !== false;
		if ($um !== $vm) {
			return $um ? 1 : -1;
		}
		$un = intval(preg_replace('@^(\d+)[+-].*$@', '$1', $ur));
		$vn = intval(preg_replace('@^(\d+)[+-].*$@', '$1', $vr));
		return $un - $vn;
	}

	/**
	 * Format the percentages as a table in HTML.
	 */
	private static function markupStats($stats,$domain) {

		$domain = self::$domains[$domain];
		$total = number_format($stats['exits']);
$html = <<<EOHTML
	<i>
		Stats for {$stats['pages']} page(s) on <b>$domain</b>.
		Discarded page(s): {$stats['discardedPages']}.
		Exits collected: $total.
	</i><br>
	<style>
		#ast * th { text-decoration: underline; padding-bottom: 10px }
		#ast * td { text-align:right; padding: 3px }
		#ast * td:nth-child(even), #ast * th:nth-child(even)
			{ background-color:rgba(0,0,0,0.1) }
	</style>
	<br>
	<code><table id="ast" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>
EOHTML;
		$percent = $stats['percentages'];
		usort($percent,
			function ($u, $v) {
				return AdminBounceTests::cmpBounceDataFunc($u['range'], $v['range']);
			});
		// Note: headings look like this:
		// 11-30s	10-30m	3-10m	0-10s	1-3m	31-60s	30+m
		// we want them to look like this:
		// 0-10s	3+m	11-30s	31-60s	3-10m	10-30m	30+m

		// Find the position of the 3+m column and move it to the second, shifting everything else to the right
		if (sizeof($percent) > 0) {
			$threePlusPos = self::find3PlusMinPos($percent);
			if ($threePlusPos == -1) {
				return "Error: Couldn't find '3+m' column";

			}
		}
		array_splice($percent, 1, 0, array($percent[$threePlusPos]));
		unset($percent[$threePlusPos + 1]);

		$column1 = array(
			'range' => '',
			'uniformAvg' => 'UNIFORM',
			'weightedAvg' => 'WEIGHTED');
		array_unshift($percent, $column1);
		foreach ($percent as $val) {
			$html .= '<th>' . $val['range'] . '</th>';
		}
		$html .= '</tr><tr>';
		foreach ($percent as $val) {
			$html .= '<td>' . $val['uniformAvg'] . '</td>';
		}
		$html .= '</tr><tr>';
		foreach ($percent as $val) {
			$html .= '<td>' . $val['weightedAvg'] . '</td>';
		}
		$html .= '</tr></table></code><br/>&nbsp;';
		return $html;
	}

	private function find3PlusMinPos(&$percentages) {
		$pos = -1;
		foreach ($percentages as $pos => $percent) {
			if ($percent['range'] == '3+m') {
				$ret = $pos;
				break;
			}
		}
		return $pos;
	}

	private static function httpDownloadHeaders() {
		//header("Content-Type: text/csv");
		header('Content-type: application/force-download');
		header('Content-disposition: attachment; filename="views.csv"');
	}

	/**
	 * Display data as CSV, not as a summary.
	 */
	private static function displayDataCSV($data) {
		self::httpDownloadHeaders();

		$headers = array('__', '0-10s', '3+m', 'mobile-0-10s','mobile__');

		print "page," . implode(",", $headers) . "\n";
		foreach ($data as $page => $datum) {
			$line = Misc::makeUrl($page);
			self::computePercentagesForCSV($datum);
			foreach ($headers as $header) {
				$line .= ',' . (isset($datum[$header]) ? $datum[$header] : '');
			}
			print "$line\n";
		}
		exit;
	}

	public static function computePercentagesForCSV(&$page, $pctSign = '%') {
		$total = $page['__'];
		if ($data = $page['0-10s']) {
			$page['0-10s'] = round(100 * (float)$data / $total, 2) . $pctSign;
		}

		if ($data = $page['3+m']) {
			$page['3+m'] = round(100 * (float)$data / $total, 2) . $pctSign;
		}

		$total = $page['mobile__'];
		if ($data = $page['mobile-0-10s']) {
			$page['mobile-0-10s'] = round(100 * (float)$data / $total, 2) . $pctSign;
		}
	}

	/**
	 * Contact the bounce timer server.
	 */
	public static function doBounceQuery($query) {
		global $IP, $THRIFT_ROOT;
		$THRIFT_ROOT = "$IP/extensions/wikihow/common/thrift";
 
		require_once $THRIFT_ROOT.'/Thrift.php';
		require_once $THRIFT_ROOT.'/protocol/TBinaryProtocol.php';
		require_once $THRIFT_ROOT.'/transport/TSocket.php';
		require_once $THRIFT_ROOT.'/transport/TFramedTransport.php';

		require_once $THRIFT_ROOT.'/packages/BounceTimer/btLogProxy.php';
		require_once $THRIFT_ROOT.'/packages/BounceTimer/btLogServer.php';
		require_once $THRIFT_ROOT.'/packages/BounceTimer/BounceTimer_types.php';

		try {
			$socket = new TSocket(WH_BOUNCETIMER_SERVER, WH_BOUNCETIMER_PORT);
			$transport = new TFramedTransport($socket, 1024, 1024);
			$protocol = new TBinaryProtocol($transport);
			$client = new btLogServerClient($protocol);

			$transport->open();

			$results = $client->query(json_encode($query));
			$out = array(
				'err' => '',
				'results' => json_decode($results, true),
			);

			$transport->close();
		} catch(TException $e) {
			$err = $e->getMessage()."\n".
				print_r(debug_backtrace(), true);
			$out = array('err' => $err);
		}

		return $out;
	}

	public function mailUserResetDone($user, $urls) {
		if ($user->getEmail()) {
			$message = "I have finished reseting these URLs:\n\n";
			foreach ($urls as &$url) {
				if ($url['title']) {
					$message .= $url['title']->getFullURL() . "\n";
				}
			}
			$headers = "From: Stu <wiki@wikihow.com>";
			mail($user->getEmail(), 'Stu finished reseting URLs', $message, $headers);
		}
	}

	/**
	 * Execute special page.  Only available to wikihow staff.
	 */
	public function execute() {
		global $wgRequest, $wgOut, $wgUser, $wgLang;

		$user = $wgUser->getName();
		$userGroups = $wgUser->getGroups();
		$extraUsers = array('Gersh');
		if ($wgUser->isBlocked()
			|| (!in_array('staff', $userGroups)
			    && !in_array($user, $extraUsers)))
		{
			$wgOut->setRobotpolicy('noindex,nofollow');
			$wgOut->showErrorPage('nosuchspecialpage', 'nospecialpagetext');
			return;
		}

		if ($wgRequest->wasPosted()) {
			// handle more URLs at once
			ini_set('memory_limit', '512M');
			set_time_limit(0);
			ignore_user_abort(true);

			$wgOut->setArticleBodyOnly(true);
			$dbr = wfGetDB(DB_SLAVE);

			$action = $wgRequest->getVal('action', '');
			self::$discardThreshold = $wgRequest->getInt('discard-threshold', 0);
			$dataType = $wgRequest->getVal('data-type');
			$pageList = $wgRequest->getVal('pages-list', '');
			$domain = $wgRequest->getVal('domain','bt');
			$pageList = Misc::getUrlDecodedData($pageList);

			$urls = self::parseURLlist($pageList);
			if (empty($urls)) {
				$result = array('result' => '<i>ERROR: no URLs given</i><br/>');
				print json_encode($result);
				return;
			}

			if ('reset' == substr($action,0,5)) {
				if ('reset' == $action) { // both www and mobile
					foreach (self::$domains as $domain=>$foo) {
						$html .= self::resetStats($urls, $domain);
											}
				} else { // just one domain
					$domain = substr($action, -2);
					$html = self::resetStats($urls, $domain);
				}
				self::resetPM($urls);
				self::resetVarnishes($urls);

				self::mailUserResetDone($wgUser, $urls);
			} elseif ('fetch' == $action) {
				$html = '';
				if ($dataType == 'csv') {
					self::outputCSV($urls);
					return;
				} else {
					foreach (self::$domains as $domain=>$foo) {
						$html.= self::fetchStats($urls, $domain);
					}
				}
			} else {
				$html = 'ERROR: unknown action';
			}
			$result = array('result' => $html);

			print json_encode($result);
			return;
		}

		$wgOut->setHTMLTitle('Admin - Bounce Tests (Stu) - wikiHow');

		$defaultDiscardThreshold = self::$discardThreshold;

		$domain_opts = '';
		foreach (self::$domains as $domain=>$label){
			$domain_opts.= "<option value=\"$domain\">$label</option>\n";
		}
		$specialPage = $this->specialPage;
$tmpl = <<<EOHTML
<script src="/extensions/wikihow/common/download.jQuery.js"></script>
<form id="admin-form" method="post" action="/Special:{$specialPage}">
<div style="font-size: 16px; letter-spacing: 2px; margin-bottom: 15px;">
	Fetch or Reset Bounce Timer (Stu) Stats
</div>
<div style="font-size: 13px; margin-bottom: 10px; border: 1px solid #dddddd; padding: 10px;">
	<div>
		Views discard threshold: <input id="discard-threshold" type="text" size="4" name="discard-threshold" value="{$defaultDiscardThreshold}" />
		<!--
		&nbsp;&nbsp;Domain
		<select name="domain" id="pages-domains">
		<option value="all">All</option>
		$domain_opts
		</select>
		//-->
	</div>
	<div style="margin-top: 5px;">
		<input type="radio" name="data-type" value="summary" checked> Summary</input>
		<input type="radio" name="data-type" value="csv"> CSV</input>
	</div>
</div>
<div style="font-size: 13px; margin: 20px 0 7px 0;">
	Enter a list of URL(s) such as <code style="font-weight: bold;">http://www.wikihow.com/Lose-Weight-Fast</code> to which this tool will apply.  One per line.
</div>
<textarea id="pages-list" name="pages-list" type="text" rows="10" cols="70"></textarea><br/>
<button id="pages-fetch" disabled="disabled" style="padding: 5px;">fetch stats</button>
<button id="pages-reset" disabled="disabled" style="padding: 5px;">reset all</button>
<br/>
<br/>
<div id="pages-result">
</div>
</form>

<script>
(function($) {
	function doServerAction(action) {
		var dataType = $('input:radio[name=data-type]:checked').val();
		var url = '/Special:{$specialPage}/views.csv?action=' + action + '&data-type=' + dataType;
		if ('summary' == dataType) {
			var form = $('#admin-form').serializeArray();
			$('#pages-result').html('loading ...');
			var finished = false;
			$.post(url,
				form,
				function(data) {
					finished = true;
					$('#pages-result').html(data['result']);
					$('#pages-list').focus();
				},
				'json')
				.complete(function () {
					if (!finished) {
						$('#pages-result').html('Server call is taking too long. Wait for an email.');
					}
				});
		} else { // csv
			var form = 'pages-list=' + encodeURIComponent($('#pages-list').val());
			$.download(url, form);
		}
	}

	$(document).ready(function() {
		$('#pages-resetbt, #pages-resetmb, #pages-reset, #pages-fetch')
			.prop('disabled', false)
			.click(function () {
				var action = $(this).attr('id').replace(/^pages-/, '');
				var answer = true;
				if ('reset' == action.substring(0,5)) {
					var count = $('#pages-list').val().split(/\\n/).length;
					var domain = 'www';
					if ('resetmb'==action) domain='mobile';
					else if ('reset'==action) domain='all domains';
					answer = confirm('Are you sure you want to reset data for approx. ' + count + ' URL(s) on ' + domain + '?');
				}
				if (answer) {
					doServerAction(action);
				}
				return false;
			});
		/*
		$('#pages-allcheck')
			.click(function() {
				if ($(this).prop('checked')){
					$('#pages-reset').prop('disabled',false);
				}else{
					$('#pages-reset').prop('disabled',true);
				}
			});
		$('#pages-domains')
			.change(function(){
				if ($(this).attr('value')=='all'){
					$('#pages-reset').prop('disabled',true);
					$('#pages-allcheck').prop('disabled',false);
					$('#pages-check').css('color','');
				}else{
					$('#pages-reset').prop('disabled',false);
					$('#pages-allcheck').prop('disabled',true).prop('checked',false);
					$('#pages-check').css('color','#ccc');
				}
				$('#pages-result').html('');
			});
		*/

		$('#pages-list')
			.focus();
	});
})(jQuery);
</script>
EOHTML;

		$wgOut->addHTML($tmpl);
	}
}
