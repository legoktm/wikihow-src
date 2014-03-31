<?
class WAPBabelfishConfig implements WAPConfig {
	public function getArticleTableName() {
		return 'babelfish_articles';
	}

	public function getTagTableName() {
		return 'babelfish_tags';
	}

	public function getArticleTagTableName() {
		return 'babelfish_article_tags';
	}

	public function getUserTagTableName() {
		return 'babelfish_user_tags';
	}
	
	public function getUserTableName() {
		return 'babelfish_users';
	}

	public function getWikiHowGroupName() {
		return 'babelfish';
	}

	public function getWikiHowPowerUserGroupName() {
		return 'babelfish_power';
	}

	public function getWikiHowAdminGroupName() {
		return 'staff';
	}

	public function getDBType() {
		return WAPDB::DB_BABELFISH;
	}

	public function getWAPUITemplatesLocation() {
		global $IP;
		return "$IP/extensions/wikihow/wap/templates/";
	}

	public function getSystemUITemplatesLocation() {
		global $IP;
		return "$IP/extensions/wikihow/babelfish/templates/";
	}

	public function getDBClassName() {
		return 'BabelfishDB';
	}

	public function getMaintenanceClassName() {
		return 'BabelfishMaintenance';
	}

	public function getArticleClassName() {
		return 'BabelfishArticle';
	}

	public function getUserClassName() {
		return 'BabelfishUser';
	}

	public function getLinkerClassName() {
		return 'WAPLinker';
	}

	public function getPagerClassName() {
		return 'BabelfishArticlePager';
	}

	public function getReportClassName() {
		return 'BabelfishReport';
	}

	public function getUserPageName() {
		return "Babelfish";
	}

	public function getAdminPageName() {
		return "BabelfishAdmin";
	}

	public function getExludedArticlesKeyName() {
		return 'intl-article-exclude-list';
	}

	public function getSystemName() {
		return 'Babelfish';
	}

	public function getUserDisplayName() {
		return 'Translator';
	}

	public function getSupportEmailAddress() {
		return 'bridget@wikihow.com';
	}

	public function getNewArticleMessage($supportEmail) {
		return "This article is not prioritized for translation at this time. It may become available at a future date.";
	}

	public function getSupportedLanguages() {
		return array('es', 'de', 'pt', 'fr', 'it', 'nl', 'ru', 'zh');
	}

	public function getMaintenanceStandardEmailList() {
		//return 'jordan@wikihow.com';
		return 'chris@wikihow.com,bridget@wikihow.com,allyson@wikihow.com,elizabeth@wikihow.com,jordan@wikihow.com';
	}

	public function getMaintenanceCompletedEmailList() {
		//return 'jordan@wikihow.com';
		return 'chris@wikihow.com,bridget@wikihow.com,allyson@wikihow.com,elizabeth@wikihow.com,jordan@wikihow.com';
	}

	public function getDefaultUserName() {
		return 'Babelfish';
	}

	public function getUIUserControllerClassName() {
		return 'WAPUIBabelfishUser';
	}

	public function isMaintenanceMode() {
		return false;
	}
}
