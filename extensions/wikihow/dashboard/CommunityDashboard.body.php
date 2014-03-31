<?

if (!defined('MEDIAWIKI')) die();

global $IP;
require_once("$IP/skins/WikiHowSkin.php");
require_once("$IP/extensions/wikihow/dashboard/DashboardWidget.php");
require_once("$IP/extensions/wikihow/dashboard/DashboardData.php");

class CommunityDashboard extends UnlistedSpecialPage {

	private $dashboardData = null;
	private $refreshData = null;

	// refresh stats from CDN every n seconds
	const GLOBAL_DATA_REFRESH_TIME_SECS = 15;
	const USER_DATA_REFRESH_TIME_SECS = 180;
	const USERNAME_MAX_LENGTH = 12;

	public function __construct() {
		global $wgHooks;
		parent::__construct('CommunityDashboard');
		$wgHooks['getToolStatus'][] = array('Misc::defineAsTool');
	}

	/**
	 * The callback made to process and display the output of the 
	 * Special:CommunityDashboard page.
	 */
	public function execute($par) {
		global $wgOut, $wgRequest, $wgHooks, $wgLanguageCode;

		if($wgLanguageCode != "en") {
			$dashboardPage = Title::makeTitle(NS_PROJECT, wfMessage("community")->text());
			$wgOut->redirect($dashboardPage->getFullURL());
			return;
		}

		$wgHooks['ShowSideBar'][] = array('CommunityDashboard::removeSideBarCallback');
		$wgHooks['ShowBreadCrumbs'][] = array('CommunityDashboard::removeBreadCrumbsCallback');
		$wgHooks['AllowMaxageHeaders'][] = array('CommunityDashboard::allowMaxageHeadersCallback');

		wfLoadExtensionMessages('CommunityDashboard');

		$this->dashboardData = new DashboardData();

		$target = isset( $par ) ? $par : $wgRequest->getVal( 'target' );
		if ($target == 'refresh') {
			$expiresSecs = self::GLOBAL_DATA_REFRESH_TIME_SECS;

			// get all commonly updating stats
			$refreshData = $this->dashboardData->getStatsData();

			$this->restResponse($expiresSecs, json_encode($refreshData));
		} else if ($target == 'userrefresh') {
			$expiresSecs = self::USER_DATA_REFRESH_TIME_SECS;

			// get user-specific stats
			$userData = $this->dashboardData->loadUserData();
			$this->shortenCompletionData($userData);

			// TODO: don't send all this data. But for now leaving so I can
			// see what's available
			$this->restResponse($expiresSecs, json_encode(@$userData));
		} else if ($target == 'leaderboard') {
			$widget = $wgRequest->getVal('widget', '');
			if ($widget) {
				$leaderboardData = $this->dashboardData->getLeaderboardData($widget);
				$this->restResponse($expiresSecs, json_encode($leaderboardData));
			}
		} else if ($target == 'userstats') {
			$data = $this->dashboardData->loadUserStats();
			$this->restResponse($expiresSecs, json_encode($data));
		} else if ($target == 'customize') {
			$wgOut->disable();

			$userData = $this->dashboardData->loadUserData();
			$prefs = $userData && $userData['prefs'] ? $userData['prefs'] : array();

			$ordering = $wgRequest->getVal('ordering', null);
			if ($ordering) $ordering = json_decode($ordering, true);
			if (!$ordering) $ordering = array();
			foreach ($ordering as $i => $item) {
				$ordering[$i] = (array)$item;
			}

			$prefs['ordering'] = $ordering;
			$this->dashboardData->saveUserPrefs($prefs);
			$result = array('error' => '');
			print json_encode($result);
		} else {
			$wgOut->setHTMLTitle( wfMsg('pagetitle', wfMsg('cd-html-title')) );

			$html = $this->displayContainer();
			$wgOut->addHTML($html);
		}
	}

	/**
	 * Returns a relative URL by querying all the widgets for what 
	 * JS or CSS files they use.
	 *
	 * @param $type must be the string 'js' or 'css'
	 * @return a string like this: /extensions/min/?f=/foo/file1,/bar/file2
	 */
	private function makeUrlTags($type, $localFiles = array()) {
		$widgets = $this->dashboardData->getWidgets();
		$files = $localFiles;
		foreach ($widgets as $widget) {
			$moreFiles = $type == 'js' ? $widget->getJSFiles() : $widget->getCSSFiles();
			foreach ($moreFiles as &$file) $file = 'widgets/' . $file;
			$files = array_merge($files, $moreFiles);
		}
		$files = array_unique($files);
		return HtmlSnips::makeUrlTags($type, $files, 'extensions/wikihow/dashboard', COMDASH_DEBUG);
	}

	/**
	 * Display the HTML for this special page with all the widgets in it
	 */
	private function displayContainer() {
		global $wgWidgetList, $wgUser, $wgWidgetShortCodes;

		$containerJS = array(
			'community-dashboard.js',
			'dashboard-widget.js',
			'jquery.ui.sortable.min.js',
			'jquery.json-2.2.min.js',
		);
		$containerCSS = array(
			'community-dashboard.css',
		);

		$jsTags = $this->makeUrlTags('js', $containerJS);
		$cssTags = $this->makeUrlTags('css', $containerCSS);

		// get all commonly updating stats, to see the initial widget
		// displays with
		$this->refreshData = $this->dashboardData->getStatsData();

		// get all data such as wikihow-defined structure goals, dynamic 
		// global data, and user-specific data
		$staticData = $this->dashboardData->loadStaticGlobalOpts();
		$priorities = json_decode($staticData['cdo_priorities_json'], true);
		if (!is_array($priorities)) $priorities = array();
		$thresholds = json_decode($staticData['cdo_thresholds_json'], true);
		DashboardWidget::setThresholds($thresholds);
		$baselines = (array)json_decode($staticData['cdo_baselines_json']);
		DashboardWidget::setBaselines($baselines);

		DashboardWidget::setMaxUsernameLength(CommunityDashboard::USERNAME_MAX_LENGTH);

		// display the user-defined ordering of widgets inside an outside
		// container
		$userData = $this->dashboardData->loadUserData();
		$prefs = !empty($userData['prefs']) ? $userData['prefs'] : array();
		$userOrdering = isset($prefs['ordering']) ? $prefs['ordering'] : array();

		$completion = !empty($userData['completion']) ? $userData['completion'] : array();
		DashboardWidget::setCompletion($completion);

		// add any new widgets that have been added since the user last
		// customized
		foreach ($wgWidgetList as $name) {
			$found = false;
			foreach ($userOrdering as $arr) {
				if ($arr['wid'] == $name) { $found = true; break; }
			}
			if (!$found) {
				$userOrdering[] = array('wid'=>$name, 'show'=>1);
			}
		}

		// create the user-defined ordering list, removing any community
		// priority widgets from the list so their not displayed twice
		$userWidgets = array();
		foreach ($userOrdering as $arr) {
			$found = false;
			foreach ($priorities as $name) {
				if ($arr['wid'] == $name) { $found = true; break; }
			}

			if (!$found && $arr['show']) $userWidgets[] = $arr['wid'];
		}

		$func = array($this, 'displayWidgets');
		$out = call_user_func($func, array('test'));

		$langKeys = array(
			'howto','cd-pause-updates','cd-resume-updates',
			'cd-current-priority','cd-network-error',
		);
		$langScript = Wikihow_i18n::genJSMsgs($langKeys);

		//TODO: Likely should move this somewhere else
		//but not sure where yet
		//load user specific info that only needs to be loaded
		//once
		if ($wgUser->getID() > 0) {
			$u = new User();
			$u->setID($wgUser->getID());
			$img = Avatar::getPicture($u->getName(), true);
			if ($img == '') {
				$img = Avatar::getDefaultPicture();
			}

			$sk = $wgUser->getSkin();
			$userName = $sk->makeLinkObj($u->getUserPage(), $u->getName());
			$tipsLink = "/Special:TipsPatrol";
		}
		else{
			$tipsLink = "/Special:Userlogin?returnto=Special:TipsPatrol";
		}

		$tmpl = new EasyTemplate( dirname(__FILE__) );
		$tmpl->set_vars(array(
			'jsTags' => $jsTags,
			'cssTags' => $cssTags,
			'thresholds' => $staticData['cdo_thresholds_json'],
			'GLOBAL_DATA_REFRESH_TIME_SECS' => self::GLOBAL_DATA_REFRESH_TIME_SECS,
			'USER_DATA_REFRESH_TIME_SECS' => self::USER_DATA_REFRESH_TIME_SECS,
			'USERNAME_MAX_LENGTH' => self::USERNAME_MAX_LENGTH,
			'widgetTitles' => DashboardData::getTitles(),
			'priorityWidgets' => $priorities,
			'userWidgets' => $userWidgets,
			'prefsOrdering' => $userOrdering,
			'userCounts' => $userData['counts'],
			'userImage' => $img,
			'userName' => $userName,
			'displayWidgetsFunc' => array($this, 'displayWidgets'),
			'appShortCodes' => $wgWidgetShortCodes,
			'tipsLink' => $tipsLink
		));
		$html = $tmpl->execute('dashboard-container.tmpl.php');

		return $langScript . $html;
	}

	/**
	 * Called by the dashboard-container.tmpl.php template to generate the
	 * widget boxes for a list of widgets.
	 *
	 * @param $widgetList an array like array('RecentChangesAppWidget', ...)
	 */
	public function displayWidgets($widgetList) {
		global $wgWidgetShortCodes;

		$widgets = $this->dashboardData->getWidgets();

		foreach ($widgetList as $name) {
			$widget = $widgets[$name];
			$code = @$wgWidgetShortCodes[$name];
			if ($widget) {
				$initialData = @$this->refreshData['widgets'][$code];
				$html .= $widget->getContainerHTML($initialData);
			}
		}

		return $html;
	}

	/**
	 * Make the completion data response use short codes instead of widget
	 * names.
	 */
	private function shortenCompletionData(&$userData) {
		global $wgWidgetShortCodes;

		if ($userData && $userData['completion']) {
			$completion = &$userData['completion'];
			$keys = array_keys($completion);
			foreach ($keys as $app) {
				$code = @$wgWidgetShortCodes[$app];
				if ($code) {
					$data = $completion[$app];
					unset($completion[$app]);
					$completion[$code] = $data;
				}
			}
		}
	}

	/**
	 * Form a REST response (JSON encoded) using the data in $data.  Does a
	 * JSONP response if requested.  Expires in $expiresSecs seconds.
	 */
	private function restResponse($expiresSecs, $data) {
		global $wgOut, $wgRequest;

		$wgOut->disable();
		$this->controlFrontEndCache($expiresSecs);

		if (!$data) {
			$data = array('error' => 'data not refreshing on server');
		}

		$funcName = $wgRequest->getVal('function', '');
		if ($funcName) {
			print "$funcName($data)";
		} else {
			print $data;
		}
	}

	/**
	 * Add HTTP headers so that the front end caches for the right number of
	 * seconds.
	 */
	private function controlFrontEndCache($maxAgeSecs) {
		global $wgOut, $wgRequest;
		$wgRequest->response()->header( 'Cache-Control: s-maxage=' . $maxAgeSecs . ', must-revalidate, max-age=' . $maxAgeSecs );
		$future = time() + $maxAgeSecs;
		$wgRequest->response()->header( 'Expires: ' . gmdate('D, d M Y H:i:s T', $future) );
		$wgOut->setArticleBodyOnly(true);
		$wgOut->sendCacheControl();
	}

	public static function removeSideBarCallback(&$showSideBar) {
		$showSideBar = false;
		return true;
	}

	public static function removeBreadCrumbsCallback(&$showBreadCrumb) {
		$showBreadCrumb = false;
		return true;
	}

	public static function allowMaxageHeadersCallback() {
		return false;
	}

}

