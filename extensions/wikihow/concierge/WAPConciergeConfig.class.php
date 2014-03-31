<?
class WAPConciergeConfig implements WAPConfig {
	public function getArticleTableName() {
		return 'concierge_articles';
	}

	public function getTagTableName() {
		return 'concierge_tags';
	}

	public function getArticleTagTableName() {
		return 'concierge_article_tags';
	}

	public function getUserTagTableName() {
		return 'concierge_artist_tags';
	}
	
	public function getUserTableName() {
		throw new Exception("Concierge doesn't have an artist table");
	}

	public function getWikiHowGroupName() {
		return 'concierge';
	}

	public function getWikiHowPowerUserGroupName() {
		return 'concierge_power';
	}

	public function getWikiHowAdminGroupName() {
		return 'staff';
	}

	public function getDBType() {
		return WAPDB::DB_CONCIERGE;
	}

	public function getWAPUITemplatesLocation() {
		global $IP;
		return "$IP/extensions/wikihow/wap/templates/";
	}

	public function getSystemUITemplatesLocation() {
		global $IP;
		return "$IP/extensions/wikihow/concierge/templates/";
	}

	public function getDBClassName() {
		return 'WAPDB';
	}

	public function getMaintenanceClassName() {
		return 'ConciergeMaintenance';
	}

	public function getArticleClassName() {
		return 'ConciergeArticle';
	}

	public function getUserClassName() {
		return 'ConciergeArtist';
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
		return "Concierge";
	}

	public function getAdminPageName() {
		return "ConciergeAdmin";
	}

	public function getExludedArticlesKeyName() {
		return 'wikiphoto-article-exclude-list';
	}

	public function getSystemName() {
		return 'Concierge';
	}

	public function getUserDisplayName() {
		return 'Artist';
	}

	public function getSupportEmailAddress() {
		return 'wikihowphotos@gmail.com';
	}

	public function getNewArticleMessage($supportEmail) {
		return "This article is not yet in the system. Please email $supportEmail to request this article.";
	}

	public function getSupportedLanguages() {
		return array('en');
	}

	public function getMaintenanceStandardEmailList() {
		//return 'jordan@wikihow.com';
		return 'thom@wikihow.com,wikihowphotos@gmail.com,elizabeth@wikihow.com,jordan@wikihow.com,chris@wikihow.com,daniel@wikihow.com,krystle@wikihow.com';
	}

	public function getMaintenanceCompletedEmailList() {
		//return 'jordan@wikihow.com';
		return 'thom@wikihow.com,wikihowphotos@gmail.com,elizabeth@wikihow.com,john@wikihow.com,jordan@wikihow.com,daniel@wikihow.com';
	}

	public function getDefaultUserName() {
		throw new Exception('no default user name defined in WAPConciergeConfig');
	}

	public function getUIUserControllerClassName() {
		return 'WAPUIConciergeUser';
	}

	public function isMaintenanceMode() {
		return false;
	}
}
