<?
class WAPEditfishConfig implements WAPConfig {
	public function getArticleTableName() {
		return 'editfish_articles';
	}

	public function getTagTableName() {
		return 'editfish_tags';
	}

	public function getArticleTagTableName() {
		return 'editfish_article_tags';
	}

	public function getUserTagTableName() {
		return 'editfish_user_tags';
	}
	
	public function getUserTableName() {
		throw new Exception("Editfish doesn't have an artist table");
	}

	public function getWikiHowGroupName() {
		return 'editfish';
	}

	public function getWikiHowPowerUserGroupName() {
		return 'editfish_power';
	}

	public function getWikiHowAdminGroupName() {
		return 'staff';
	}

	public function getDBType() {
		return WAPDB::DB_EDITFISH;
	}

	public function getWAPUITemplatesLocation() {
		global $IP;
		return "$IP/extensions/wikihow/wap/templates/";
	}

	public function getSystemUITemplatesLocation() {
		global $IP;
		return "$IP/extensions/wikihow/editfish/templates/";
	}

	public function getDBClassName() {
		return 'WAPDB';
	}

	public function getMaintenanceClassName() {
		return 'EditfishMaintenance';
	}

	public function getArticleClassName() {
		return 'EditfishArticle';
	}

	public function getUserClassName() {
		return 'EditfishArtist';
	}

	public function getLinkerClassName() {
		return 'WAPLinker';
	}

	public function getPagerClassName() {
		return 'WAPArticlePager';
	}

	public function getReportClassName() {
		return 'WAPReport';
	}

	public function getUserPageName() {
		return "Editfish";
	}

	public function getAdminPageName() {
		return "EditfishAdmin";
	}

	public function getExludedArticlesKeyName() {
		return 'editfish-article-exclude-list';
	}

	public function getSystemName() {
		return 'Editfish';
	}

	public function getUserDisplayName() {
		return 'Fellow';
	}

	public function getSupportEmailAddress() {
		return 'loni@gmail.com';
	}

	public function getNewArticleMessage($supportEmail) {
		return "This article is not yet in the system. Please email $supportEmail to request this article.";
	}

	public function getSupportedLanguages() {
		return array('en');
	}

	public function getMaintenanceStandardEmailList() {
		//return 'jordan@wikihow.com';
		return 'elizabeth@wikihow.com,loni@wikihow.com,jordan@wikihow.com,chris@wikihow.com';
	}

	public function getMaintenanceCompletedEmailList() {
		//return 'jordan@wikihow.com';
		return 'elizabeth@wikihow.com,loni@wikihow.com,jordan@wikihow.com';
	}

	public function getDefaultUserName() {
		return 'editfish';
	}

	public function getUIUserControllerClassName() {
		return 'WAPUIEditfishUser';
	}

	public function isMaintenanceMode() {
		return false;
	}
}
