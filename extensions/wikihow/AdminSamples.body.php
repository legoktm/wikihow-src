<?

if (!defined('MEDIAWIKI')) die();

class AdminSamples extends UnlistedSpecialPage {

	public function __construct() {
		parent::__construct('AdminSamples');
	}

	/**
	 * Parse the input field into an array of URLs and Title objects
	 */
	private static function parseURLlist($pageList) {
		$pageList = preg_split('@[\r\n]+@', $pageList);
		$urls = array();
		$dbr = wfGetDB(DB_SLAVE);
		foreach ($pageList as $url) {
			$url = trim($url);
			if (!empty($url)) {
				$sample = preg_replace('@http://www.wikihow.com/Sample/@','',$url);
				$err = self::validateSample($sample,$dbr);
				$urls[] = array('url' => $url, 'sample' => $sample, 'err' => $err);
			}
		}
		return $urls;
	}
	
	private static function validateSample($sample,$db) {
		//is it in the main db table?
		$res = $db->select('dv_sampledocs','*',array('dvs_doc' => $sample), __METHOD__);
		if (!$res->fetchObject()) return 'invalid sample';
		
		//make sure it isn't linked from any articles
		$res = $db->select('dv_links','*',array('dvl_doc' => $sample), __METHOD__);
		if ($res->fetchObject()) return 'Still linked from articles';
		
		//still here?
		return '';
	}
	
	private static function fourOhFourSamples($urls) {
		$sample_array = array();
		
		//gather up the articles
		foreach ($urls as $url) {
			if (!$url['err']) {
				$sample_array[] = $url['sample'];
			}
		}
		
		//do the deletes
		if ($sample_array) {
			$dbw = wfGetDB(DB_MASTER);
			$result = '';
			
			//ready it for the db
			$the_samples = implode("','",$sample_array);
			$the_samples = "('".$the_samples."')";
			
			//remove the sample from dv_sampledocs
			$res = $dbw->delete('dv_sampledocs', array('dvs_doc' => $sample_array), __METHOD__);
			if ($res) $result .= '<p>Samples removed.</p>';
			
			//remove custom names from dv_display_names
			$res = $dbw->delete('dv_display_names', array('dvdn_doc' => $sample_array), __METHOD__);
			if ($res) $result .= '<p>Sample display names removed.</p>';
			
			//remove the sample from qbert
			$res = $dbw->delete('dv_sampledocs_status', array('sample' => $sample_array), __METHOD__);
			if ($res) $result .= '<p>Qbert updated.</p>';
		}
		
		return $result;
	}

	/**
	 * Execute special page.  Only available to wikihow staff.
	 */
	public function execute() {
		global $wgRequest, $wgOut, $wgUser, $wgLang;

		$user = $wgUser->getName();
		$userGroups = $wgUser->getGroups();
		if ($wgUser->isBlocked() || !in_array('staff', $userGroups)) {
			$wgOut->setRobotpolicy('noindex,nofollow');
			$wgOut->showErrorPage('nosuchspecialpage', 'nospecialpagetext');
			return;
		}

		if ($_SERVER['HTTP_HOST'] != 'parsnip.wikiknowhow.com') {
			$wgOut->redirect('https://parsnip.wikiknowhow.com/Special:AdminSamples');
		}

		if ($wgRequest->wasPosted()) {
			// this may take a while...
			set_time_limit(0);

			$wgOut->setArticleBodyOnly(true);
			$action = $wgRequest->getVal('action');
		
			if ($action == 'process') {
				$maintDir = getcwd() . '/maintenance';
				$log = '/usr/local/wikihow/log/samples.log';
				system("cd $maintDir; echo -n '++ starting: ' >> $log; date >> $log; pwd >> $log; php SampleDocProcess.php >> $log 2>&1");
				$email = $wgRequest->getVal('email');
				if ($email) {
					$to = new MailAddress($email);
					$from = new MailAddress('qbert@wikihow.com');
					$subject = 'Samples processed';
					$body = 'Your samples are done processing. Get back to work: https://qbert.wikiknowhow.com/samples.php';
					UserMailer::send($to, $from, $subject, $body);
				}
				$result = array('result' => 'done');
			} else {
				$pageList = $wgRequest->getVal('pages-list', '');

				$urls = self::parseURLlist($pageList);
				if (empty($urls)) {
					$result = array('result' => '<i>ERROR: no URLs given</i>');
					print json_encode($result);
					return;
				}

				$res = self::fourOhFourSamples($urls);

				$html = '<style>.tres tr:nth-child(even) {background: #ccc;}</style>';
				$html .= $res.'<table class="tres"><tr><th width="400px">URL</th><th>Error</th></tr>';
				foreach ($urls as $row) {
					$html .= "<tr><td><a href='{$row['url']}'>{$row['sample']}</a></td><td>{$row['err']}</td></tr>";
				}
				$html .= '</table>';

				$result = array('result' => $html);
			}
			print json_encode($result);

			return;
		}

		$wgOut->setHTMLTitle('Admin - Samples - wikiHow');
		$userEmail = $wgUser->getEmail();

$tmpl = <<<EOHTML
<form id="images-resize" method="post" action="/Special:AdminSamples">
<div style="font-size: 16px; letter-spacing: 2px; margin-bottom: 15px;">
	Admin Samples
</div>

<h3>Import Samples</h3>
<p style='margin-bottom: 15px;'><input type='button' value='Import now!' id='import_samples' style='padding: 5px;' /> <span id='import_result'></span></p>

<h3>404 Samples</h3>
<div style="font-size: 13px; margin: 20px 0 7px 0;">
	Enter a list of URLs such as <code style="font-weight: bold;">http://www.wikihow.com/Sample/Functional-Resume</code> to process.<br />
	One per line.
</div>
<input type='hidden' name='action' value='remove' />
<textarea id="pages-list" name="pages-list" type="text" rows="10" cols="70"></textarea>
<button id="pages-go" disabled="disabled" style="padding: 5px;">process</button><br/>
<br/>
<div id="pages-result">
</div>
</form>

<script>
(function($) {
	$(document).ready(function() {
		$('#pages-go')
			.removeAttr('disabled')
			.click(function () {
				var form = $('#images-resize').serializeArray();
				$('#pages-result').html('loading ...');
				$.post('/Special:AdminSamples',
					form,
					function(data) {
						$('#pages-result').html(data['result']);
						$('#pages-list').focus();
					},
					'json');
				return false;
			});

		$('#pages-list')
			.focus();

		$('#import_samples').click(function () {
			var form = 'action=process&email=$userEmail';
			$('#import_samples').attr('disabled', 'disabled');
			$.post('/Special:AdminSamples',
				form,
				function(data) {
					$('#import_result').html('<i>import done</i>');
					 $('#import_samples').removeAttr('disabled');
				},
				'json');
			return false;
		});

	});
})(jQuery);
</script>
EOHTML;

		$wgOut->addHTML($tmpl);
	}
}
