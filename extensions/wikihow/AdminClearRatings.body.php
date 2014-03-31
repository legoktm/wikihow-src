<?

if (!defined('MEDIAWIKI')) die();

global $IP;

require_once("$IP/extensions/wikihow/Rating/RatingArticle.php");
require_once("$IP/extensions/wikihow/Rating/RatingSample.php");

class AdminClearRatings extends UnlistedSpecialPage {
	function __construct() {
		parent::__construct('AdminClearRatings');
	}

	/**
	 * Execute special page. Only available to wikihow staff.
	 */
	function execute() {
		global $wgRequest, $wgOut, $wgLang, $wgServer;

		if (!$this->userAllowed()) {
			$wgOut->setRobotpolicy('noindex,nofollow');
			$wgOut->showErrorPage('nosuchspecialpage', 'nospecialpagetext');
			return;
		}

		$wgOut->setHTMLTitle('Admin - Clear Ratings - wikiHow');
		$wgOut->setPageTitle('Clear Ratings for Multiple Pages');

		if ($wgRequest->wasPosted()) {
			$wgOut->setArticleBodyOnly(true);
			$html = '';

			set_time_limit(0);

			$pageList = $wgRequest->getVal('pages-list', '');
			$comment = '[Batch Clear] ' . $wgRequest->getVal('comment', '');

			if ($pageList) $pageList = urldecode($pageList);
			$pageList = preg_split('@[\r\n]+@', $pageList);
			$pageData = array();
			$failedPages = array();

			// Get the page titles from the URLs:
			foreach ($pageList as $url) {
				$trurl = trim($url);
				$partial = preg_replace('/ /', '-', self::getPartial($trurl));
				if (!empty($partial)) {
					$pageData[] = array('partial' => $partial, 'url' => $trurl);
				} elseif (!empty($trurl)) {
					$failedPages[] = $url;
				}
			}

			$html .= $this->generateResults($pageData, $failedPages, $comment);
			
			if (!empty($failedPages)) {
				$html .= '<br/><p>Unable to parse the following URLs:</p>';
				$html .= '<p>';
				foreach($failedPages as $p) {
					$html .= '<b>' . $p . '</b><br />';
				}
				$html .= '</p>';
			}
			$result = array('result' => $html);
			print json_encode($result);
			return;
		} else {
			$tmpl = self::getGuts('AdminClearRatings');
			$wgOut->addHTML($tmpl);
		}

	}

	/**
	 * Given a URL or partial, give back the page title
	 */
	public static function getPartial($url) {
		$partial = preg_replace('@^https?://[^/]+@', '', $url);
		$partial = preg_replace('@^/@', '', $partial);
		return $partial;
	}

	function getGuts($action) {
		return "		<form method='post' action='/Special:$action'>
		<h4>Enter a list of full URLs such as <code>http://www.wikihow.com/Kill-a-Scorpion</code> or partial URLs like <code>Sample/Research-Outline</code> for pages whose ratings should be cleared.  One per line.</h4>
		<br/>
		<table><tr><td>Pages:</td><td><textarea id='pages-list' type='text' rows='10' cols='70'></textarea></td></tr>
		<tr><td>Reason:</td><td><textarea id='reason' type='text' rows='1' cols='70'></textarea></td></tr></table>
		<button id='pages-clear' disabled='disabled'>Clear</button>
		<br/><br/>
		<div id='pages-result'>
		</div>
		</form>

		<script>
		(function($){
			$(document).ready(function() {
				$('#pages-clear')
					.prop('disabled', false)
					.click(function() {
						$('#pages-result').html('Loading ...');
						$.post('/Special:$action',
							{ 'pages-list': $('#pages-list').val(),
							  'comment' : $('#reason').val()
							},
							function(data) {
								$('#pages-result').html(data['result']);
								$('#pages-list').focus();
							},
							'json');
						return false;
					});
				$('#pages-list').focus();
			});
		})(jQuery);
		</script>";
	}

	public function getAllowedUsers() {
		return array("G.bahij");
	}

	public function userAllowed() {
		global $wgUser;

		$user = $wgUser->getName();
		$allowedUsers = $this->getAllowedUsers();

		$userGroups = $wgUser->getGroups();
		if ($wgUser->isBlocked() || !in_array($user, $allowedUsers) && !in_array('staff', $userGroups)) {
			return false;
		}

		return true;
	}

	private function generateResults($pageData, $failedPages, $comment) {
		// Set up the output table:
		$html = '<style>.tres tr:nth-child(even) {background: #e0e0e0;} .failed {color: #a84810;} .cleared {color: #48a810;}</style>';
		$html .= '<table class="tres"><tr>';
		$html .= '<th width="350px"><b>Page</b></th>';
		$html .= '<th width="50px"><b>Type</b></th>';
		// $html .= '<th><b>Page ID</b></th>';
		// $html .= '<th><b>Rating</b></th>';
		// $html .= '<th><b>Active ratings</b></th>';
		$html .= '<th width="240px"><b>Status</b></th></tr>';

		$articleRatingTool = new RatingArticle();
		$sampleRatingTool = new RatingSample();
		$dbr = wfGetDB(DB_SLAVE);
		$samplePrefix = 'Sample/';

		foreach($pageData as &$dataRow) {
			global $wgUser;

			$p = $dataRow['partial'];
			$html .= '<tr>';
			$title = Title::makeTitleSafe(NS_MAIN, $p);
			$dataRow['title'] = $title;
			$dataRow['type'] = 'none';
			$tool = null;
			$notFound = false;

			if (!preg_match('/:/', $p) && $title->exists()) {
				// It's an article in NS_MAIN:
				$artId = $title->getArticleID();
				if ($artId > 0) {
					$dataRow['type'] = 'article';
					$dataRow['pageId'] = $artId;
					$tool = $articleRatingTool;
				} else {
					$notFound = true;
				}
			} elseif (preg_match('@^Sample/@', $p)) {
				// It's a Sample:
				$dbKey = $title->getDBKey();
				$name = substr($dbKey, strlen($samplePrefix));
				$sampleId = $dbr->selectField('dv_sampledocs', 'dvs_doc', array('dvs_doc' => $name));
				if (!empty($sampleId)) {
					$dataRow['type'] = 'sample';
					$dataRow['pageId'] = $sampleId;
					$tool = $sampleRatingTool;
				} else {
					$notFound = true;
				}
			} else {
				$notFound = true;
			}
			if ($notFound) {
				$html .= "<td>{$dataRow['url']}</td>"; // Title/URL
				$html .= "<td></td>"; // Type
				// $html .= "<td></td>"; // ID
				// $html .= "<td></td>"; // Rating
				// $html .= "<td></td>"; // Rating count
				$html .= "<td><b><span class=\"failed\">Page not found</span></b></td>"; // Status
			} else {
				$status = '';
				$dataRow['pageRating'] = '';
				$dataRow['ratingCount'] = '';
				if ($tool) {
					$tablePrefix = $tool->getTablePrefix();
					// Active ratings (flag '_isdeleted' is 0):
					$ratRes = $dbr->select(
						$tool->getTableName(),
						array(
							"{$tablePrefix}page",
							"AVG({$tablePrefix}rating) as R",
							'count(*) as C'),
						array("{$tablePrefix}page" => "{$dataRow['pageId']}",
							  "{$tablePrefix}isdeleted" => 0),
						__METHOD__);
					// Active + inactive ratings:
					$ratResDel = $dbr->select(
						$tool->getTableName(),
						array(
							"{$tablePrefix}page",
							"AVG({$tablePrefix}rating) as R"),
						array("{$tablePrefix}page" => "{$dataRow['pageId']}"),
						__METHOD__);
					$ratResDelData = $ratResDel->fetchRow();
					if ($ratResDel->numRows() == 0 || !isset($ratResDelData['R'])) {
						$status = '<span class="failed">No ratings found</span>';
					} elseif ($wgUser->getId() == 0) {
						$status = '<span class="failed">No permission: Not logged in?</span>';
					} else {
						$ratResData = $ratRes->fetchRow();
						$dataRow['pageRating'] = isset($ratResData['R']) ? $ratResData['R'] : 'N/A';
						$dataRow['ratingCount'] = $ratResData['C'] or 0;
						$tool->clearRatings($dataRow['pageId'], $wgUser, $comment);
						if ($dataRow['type'] == 'sample') {
							// Also delete rating reasons for samples
							$tool->deleteRatingReason($dataRow['pageId']);
						}
						if (isset($ratResData['R'])) {
							$status = '<span class="cleared">Cleared</span>';
						} else {
							$status = 'No active ratings (already cleared?)';
						}
					}
				} else {
					$status = '<span class="failed">Server error (rating tool null)</span>';
				}

				$html .= "<td><a href='{$wgServer}/{$dataRow['title']}' rel='nofollow'>{$dataRow['title']}</a></td>";
				$html .= "<td>{$dataRow['type']}</td>";
				// $html .= "<td>{$dataRow['pageId']}</td>";
				// $html .= "<td>{$dataRow['pageRating']}</td>";
				// $html .= "<td>{$dataRow['ratingCount']}</td>";
				$html .= "<td><b>{$status}</b></td>";
			}
		}
		unset($dataRow);

		$html .= '</table>';

		return $html;
	}
}
