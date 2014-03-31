<?

if (!defined('MEDIAWIKI')) die();

class AdminTitles extends UnlistedSpecialPage {

	function __construct() {
		$this->action = $GLOBALS['wgTitle']->getPartialUrl();
		parent::__construct($this->action);
		$GLOBALS['wgHooks']['ShowSideBar'][] = array('AdminTitles::removeSideBarCallback');
	}

    static function removeSideBarCallback(&$showSideBar) {
		$showSideBar = false;
		return true;
	}

	static function displayRecentChanges() {
		$html = '';
		$html .= '<table width="100%">';
		$changes = CustomTitleChangesLog::dbGetRecentChanges(10);
		$html .= "<tr><th>When</th><th>User</th><th>Summary</th></tr>\n";
		$users = array();
		foreach ($changes as $change) {
			$html .= '<tr>';
			$ts = wfTimestamp(TS_UNIX, $change['tcc_timestamp']);
			$html .= "<td>" . date('Y-m-d', $ts) . "</td>";

			$userid = $change['tcc_userid'];
			if (!isset($users[$userid])) {
				$users[$userid] = User::newFromId($userid);
			}
			$user = $users[$userid];
			$usertext = $user ? $user->getName() : '';
			$html .= "<td>$usertext</td>";

			$summary = substr($change['tcc_summary'], 0, 200);
			if ($summary != $change['tcc_summary']) {
				$summary .= '...';
			}
			$html .= "<td>{$summary}</td>";
			$html .= "</tr>\n";
		}
		$html .= '</table>';
		return $html;
	}

	static function downloadTitleChanges() {
		self::httpDownloadHeaders('custom_titles_' . date('Ymd') . '.txt');
		$titles = CustomTitleChanges::getCustomTitles();
		print "pageid\tcustom_title\tcustom_note\n";
		foreach ($titles as $id => $title) {
			print "{$id}\t{$title['tt_custom']}\t{$title['tt_custom_note']}\n";
		}
	}

    static function httpDownloadHeaders($filename) {
		header('Content-type: application/force-download');
		header('Content-disposition: attachment; filename="' . $filename . '"');
	}

	static function processTitleChangeUpload($filename) {
		$content = file_get_contents($filename);
		if ($content === false) {
			$error = 'internal error opening uploaded file';
			return array('error' => $error);
		}
		$lines = preg_split('@(\r|\n|\r\n)@m', $content);
		$changes = array();
		foreach ($lines as $line) {
			$fields = split("\t", $line);
			// skip any line that doesn't have at least a pageid and a custom title
			if (count($fields) < 2) continue;
			$fields = array_map(trim, $fields);
			// skip first line if it's the pageid\t... header
			$pageid = intval($fields[0]);
			$custom = $fields[1]; // can be the empty string
			$custom_note = count($fields) > 2 ? $fields[2] : '';
			if (!$pageid) continue;
			$changes[$pageid] = array(
				'custom' => $custom,
				'custom_note' => $custom_note);
		}
		if (!$changes) {
			return array('error' => 'No lines to process in upload');
		} else {
			return CustomTitleChanges::processTitleChanges($changes);
		}
	}

	/**
	 * Execute special page.  Only available to wikihow staff.
	 */
	function execute() {
		global $wgRequest, $wgOut, $wgUser, $wgLang;

		// Check permissions
		$userGroups = $wgUser->getGroups();
		if ($wgUser->isBlocked() || !in_array('staff', $userGroups)) {
			$wgOut->setRobotpolicy('noindex,nofollow');
			$wgOut->showErrorPage('nosuchspecialpage', 'nospecialpagetext');
			return;
		}

		if ($wgRequest->wasPosted()) {
			set_time_limit(0);
			$wgOut->setArticleBodyOnly(true);
			$action = $wgRequest->getVal('action');
			if ($action == 'save-list') {
				$filename = $wgRequest->getFileTempName('adminFile');
				$ret = self::processTitleChangeUpload($filename);
			} elseif ($action == 'retrieve-list') {
				self::downloadTitleChanges();
			} else {
				$error = 'unknown action';
			}
			if ($error) {
				print json_encode(array('error' => $error));
			}
			return;
		}

		$wgOut->setHTMLTitle('Admin - Custom Titles - wikiHow');
		$wgOut->setPageTitle('Customize Titles');

		$tmpl = $this->getGuts();
		$wgOut->addHTML($tmpl);
	}
		
	function getGuts() {
		$action = $this->action;
		$recent = self::displayRecentChanges();
		return <<<EOHTML
		<script src='/extensions/min/?f=extensions/wikihow/common/download.jQuery.js,extensions/wikihow/mobile/webtoolkit.aim.min.js'></script>
		<form id='admin-upload-form' name='adminUploadForm' enctype='multipart/form-data' method='post' action='/Special:$action' onsubmit="return AIM.submit(this, { onStart: function () { $('#admin-result').html('sent!'); }, onComplete: function (data) { console.log('d',data); $('#admin-result').html(''); onFormSubmitted(); } });">
		<input type="hidden" name="action" value="save-list" />
		<br/>
		<style>
			.sm { font-variant:small-caps; letter-spacing:2px; margin-right: 25px; }
			.bx { padding: 5px 10px 5px 10px; margin-bottom: 15px; border: 1px solid #dddddd; border-radius: 10px 10px 10px 10px; }
		</style>
		<div class=bx>
			<span class=sm>Download</span>
			<button id="admin-get">retrieve current list</button><br/>
		</div>
		<div class=bx>
			<span class=sm>Upload</span>
			<input type="file" id="adminFile" name="adminFile"><br/>
		</div>
		<br/>
		<div class=bx>
			<span class=sm>Processing Results</span><br/>
			<br/>
			<div id="admin-result">
			</div>
		</div>
		</form>
		<br/>
		<div class=bx id="recent-summary">
			<span class=sm>RECENT ACTIVITY</span><br/><br/>
			<tt>
				$recent
			</tt>
		</div>

		<script>
		(function($) {
			$(document).ready(function() {

				$('#admin-get').click(function () {
					$('#admin-result').html('retrieving list ...');
					var url = '/Special:$action';
					var form = 'action=retrieve-list';
					$.download(url, form);
					return false;
				});

				$('#adminFile').change(function () {
					var filename = $('#adminFile').val();
					if (!filename) {
						alert('No file selected!');
					} else {
						$('#admin-result').html('sending list ...');
						$('#admin-upload-form').submit();
					}
					return false;
				});

			});
		})(jQuery);

		function onFormSubmitted(data) {
			$('#admin-result').html('saved! reload this page to see status.');
			console.log('d',data);
		}
		</script>
EOHTML;
	}
}

class CustomTitleChanges {

	static function getCustomTitles() {
		$dbr = wfGetDB(DB_SLAVE);
		$titles = TitleTests::dbListCustomTitles($dbr);
		$notFound = 0;
		$output = array();
		foreach ($titles as $row) {
			$id = $row['tt_pageid'];
			$output[ $id ] = $row;
		}
		return $output;
	}

	static function processTitleChanges($changes) {
		$titles = self::getCustomTitles();
		$summary = '';
		$stats = array('new' => 0, 'delete' => 0, 'change' => 0, 'nochange' => 0);
		foreach ($changes as $pageid => $change) {
			$pageid = intval($pageid);
			if (!$pageid) continue;

			if (!isset($titles[$pageid])) {
				if ($change['custom']) {
					$titles[$pageid] = array(
						'tt_custom' => $change['custom'],
						'tt_custom_note' => $change['custom_note'],
						'status' => 'new');
					$summary .= "New custom $pageid: {$change['custom']}\n";
					$stats['new']++;
				} else {
					// ignore any title changes set to "delete" if they
					// already don't exist
				}
			} elseif ($titles[$pageid]['status']) {
				return array('error' => "Error: pageid $pageid exists twice in input file");
			} elseif (!$change['custom']) {
				$titles[$pageid]['status'] = 'delete';
				$summary .= "Delete $pageid\n";
				$stats['delete']++;
			} else {
				if ($titles[$pageid]['tt_custom'] != $change['custom']
					|| $titles[$pageid]['tt_custom_note'] != $change['custom_note'])
				{
					$titles[$pageid] = array(
						'tt_custom' => $change['custom'],
						'tt_custom_note' => $change['custom_note'],
						'status' => 'change');
					$summary .= "Changed custom $pageid: {$change['custom']}\n";
					$stats['change']++;
				} else {
					// No custom title or note change
					$titles[$pageid]['status'] = 'nochange';
					$stats['nochange']++;
				}
			}
		}

		// For any titles no longer in change set, we delete them (per Chris, 7/12)
		// so that there is effectively a "Master" spreadsheet of custom titles
		foreach ($titles as $pageid => &$title) {
			if (!$title['status']) {
				$title['status'] = 'delete';
				$summary .= "Delete $pageid\n";
				$stats['delete']++;
			}
		}

		// I separated this into a different function so we could fairly
		// easily do dry runs
		$stats['errors'] = self::applyTitleChanges($titles, $summary);
		return array('stats' => $stats, 'summary' => $summary);
	}

	static function applyTitleChanges($titles, $summary) {
		global $wgUser;

		$dbw = wfGetDB(DB_MASTER);
		$errors = array();
		foreach ($titles as $pageid => $title) {
			$status = $title['status'];
			if ($status == 'delete') {
				TitleTests::dbRemoveTitleID($dbw, $pageid);
			} elseif ($status == 'change' || $status == 'new') {
				$titleObj = Title::newFromID($pageid);
				if ($titleObj && $titleObj->exists() && $titleObj->getNamespace() == NS_MAIN) {
					TitleTests::dbSetCustomTitle($dbw, $titleObj, $title['tt_custom'], $title['tt_custom_note']);
				} else {
					$errors[] = $pageid;
				}
			} else {
				// status == 'nochange'
			}
		}
		
		if ($errors) {
			$summary = "Warning: there were unexpected errors with page IDs: " . join(',', $errors) . "\n" . $summary;
		}
		if (!$summary) {
			$summary = 'No changes';
		}
		CustomTitleChangesLog::dbAddTitleChangeSummary( $dbw, wfTimestampNow(), $wgUser->getID(), $summary );

		return $errors;
	}

}

/*schema:
 *
CREATE TABLE title_custom_changes (
	tcc_timestamp VARCHAR(14) NOT NULL,
	tcc_userid INT(8) UNSIGNED NOT NULL,
	tcc_summary BLOB,
	PRIMARY KEY(tcc_timestamp)
);
 */
class CustomTitleChangesLog {

	function dbGetRecentChanges($numChanges) {
		$dbr = wfGetDB(DB_SLAVE);
		$res = $dbr->select('title_custom_changes',
			array('tcc_timestamp', 'tcc_userid', 'tcc_summary'),
			array(),
			__METHOD__,
			array('LIMIT' => $numChanges, 'ORDER BY' => 'tcc_timestamp DESC'));
		$changes = array();
		foreach ($res as $row) {
			$changes[] = (array)$row;
		}
		return $changes;
	}

	function dbAddTitleChangeSummary($dbw, $timestamp, $userid, $summary) {
		$row = array(
			'tcc_timestamp' => $timestamp,
			'tcc_userid' => $userid,
			'tcc_summary' => $summary);
		$dbw->insert('title_custom_changes', $row, __METHOD__);
	}

}

