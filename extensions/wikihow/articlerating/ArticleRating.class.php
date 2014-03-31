<?
class ArticleRatingModel {
	var $rating = 0;

	public function setRating($articleId, $rating) {
		EventLogger::logEvent('mrt', $articleId, $rating);
	}

	public function setMainRating($articleId, $rating, $comment, $email) {
		$dbw = wfGetDB(DB_MASTER);
		$rating = trim($dbw->strencode($rating));
		$comment = trim($dbw->strencode($comment));
		$email = trim($dbw->strencode($email));

		if (!empty($articleId) && ($rating == 0 || $rating == 1)) {
			$dbw->insert('article_rating', 
				array('ar_article_id' => $articleId, 'ar_rating' => $rating, 'ar_comment' => $comment, 'ar_email'  => $email, 'ar_timestamp' => wfTimestamp(TS_MW)), __METHOD__);
		}
	}
}

abstract class ArticleRatingView {

	protected function setTemplatePath() {
		EasyTemplate::set_path(dirname(__FILE__).'/');
	}

	public static function isValidArticle() {
		global $wgTitle;
		return $wgTitle && $wgTitle->getNamespace() == NS_MAIN && $wgTitle->getText() != wfMsg("mainpage") && $wgTitle->exists();
	}

	abstract public function getHtml();
}

class ArticleRatingMobileView extends ArticleRatingView {
	public function getHtml() {
		global $wgTitle;
		$html = "";
		if (self::isValidArticle()) {
			$this->setTemplatePath();
			$vars['ar_css'] .= HtmlSnips::makeUrlTags('css', array('jquery.rating.css'), 'extensions/wikihow/mqg/rating', false);	
			$vars['ar_js'] .= HtmlSnips::makeUrlTags('js', array('jquery.rating.pack.js', 'jquery.MetaData.js'), 'extensions/wikihow/mqg/rating', false);	
			$html = EasyTemplate::html('rating_mobile.tmpl.php', $vars);
		}
		return $html;
	}
}

class ArticleRatingDesktopView extends ArticleRatingView {
	public function getHtml() {
		global $wgTitle;
		$html = "";
		if (self::isValidArticle()) {
			$this->setTemplatePath();
			$vars['ar_css'] = "";	
			$vars['ar_js'] .= "";	
			$html = EasyTemplate::html('rating_desktop.tmpl.php', $vars);
		}
		return $html;
	}
}
