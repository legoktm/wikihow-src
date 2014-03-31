<?
/*
* 
*/
class ArticleRating extends UnlistedSpecialPage {

	function __construct() {
		parent::__construct('ArticleRating');
	}

	function execute($par) {
		global $wgRequest, $wgUser, $IP, $wgOut;
		require_once("$IP/extensions/wikihow/articlerating/ArticleRating.class.php");


		$wgOut->setArticleBodyOnly(true);

		$rating = intVal($wgRequest->getVal('rating', -1));
		$articleId = $wgRequest->getVal('aid', -1);
		$comment = $wgRequest->getVal('comment', "");
		$email = $wgRequest->getVal('email', "");
		//$userText = $wgUser->getUserText();
		$ar = new ArticleRatingModel($rating);
		$ar->setMainRating($articleId, $rating, $comment, $email);
	}
}
