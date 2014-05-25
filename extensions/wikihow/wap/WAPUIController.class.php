<?
abstract class WAPUIController {
	protected $config = null;
	protected $dbType = null;
	protected $cu = null;
	protected $wapDB = null;

	public function __construct(WAPConfig $config) {
		global $wgUser;

		$this->config = $config;
		$this->dbType = $config->getDBType();

		$userClass = $config->getUserClassName();
		$this->cu = $userClass::newFromUserObject($wgUser, $this->dbType);
		$this->wapDB = WAPDB::getInstance($this->dbType);
	}

	abstract public function execute($par);

	protected function getDefaultVars() {
		global $wgUser; 

		$vars = array();
		$vars['js'] = HtmlSnips::makeUrlTags('js', array('chosen.jquery.min.js'), '/extensions/wikihow/common/chosen', false);
		$vars['js'] .= HtmlSnips::makeUrlTags('js', array('wap.js'), '/extensions/wikihow/wap', false);
		$vars['js'] .= HtmlSnips::makeUrlTags('js', array('jquery-ui-1.9.2.core_datepicker.custom.min.js','jquery.tablesorter.min.js', 'download.jQuery.js'), '/extensions/wikihow/common', false);
		$vars['css'] = HtmlSnips::makeUrlTags('css', array('chosen.css'), '/extensions/wikihow/common/chosen', false);
		$vars['css'] .= HtmlSnips::makeUrlTags('css', array('wap.css'), '/extensions/wikihow/wap', false);
		$vars['userPage'] = $this->config->getUserPageName();
		$vars['adminPage'] = $this->config->getAdminPageName();
		$vars['system'] = $this->config->getSystemName();

		$userClass = $this->config->getUserClassName();
		$cu = $userClass::newFromId($wgUser->getId(), $this->dbType);
		$admin = $cu->isAdmin() ? "<a href='/Special:{$vars['adminPage']}' class='button secondary'>Admin</a> " : "";
		$vars['nav'] = "<div id='wap_nav'>$admin <a href='/Special:{$vars['userPage']}' class='button primary'>My Articles</a></div>";
		$linkerClass = $this->config->getLinkerClassName();
		$vars['linker'] = new $linkerClass($this->dbType); 
		$vars['langs'] = $this->config->getSupportedLanguages();

		return $vars;
	}

	protected function outputNoPermissionsHtml() {
		global $wgOut;
		$wgOut->setRobotpolicy('noindex,nofollow');
		$wgOut->showErrorPage('nosuchspecialpage', 'nospecialpagetext');
	}
}
