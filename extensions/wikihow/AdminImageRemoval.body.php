<?php

class AdminImageRemoval extends UnlistedSpecialPage {

	static $imagesRemoved; //array to keep the name of each image that we remove

	function __construct() {
		parent::__construct( 'AdminImageRemoval' );
	}

	function execute($par) {
		global $wgOut, $wgUser, $wgRequest;

		$userGroups = $wgUser->getGroups();
		if ($wgUser->isBlocked() || !in_array('staff', $userGroups)) {
			$wgOut->setRobotpolicy('noindex,nofollow');
			$wgOut->showErrorPage( 'nosuchspecialpage', 'nospecialpagetext' );
			return;
		}

		if($wgRequest->wasPosted()) {
			$wgOut->setArticleBodyOnly(true);

			$urlList = $wgRequest->getVal("urls");
			$urlArray = explode("\n", $urlList);

			AdminImageRemoval::$imagesRemoved = array();

			$urlArray = array_map("urldecode", $urlArray);
			$pages = Misc::getPagesFromURLs($urlArray);

			foreach($pages as $page) {
				if($page['lang'] == "en") {
					$this->removeImagesFromArticle($page['page_id']);
				}
			}

			$errors = array();

			foreach($urlArray as $article) {
				if(!array_key_exists($article, $pages)){
					$errors[] = $article;
				}
			}

			if(count($errors) > 0) {
				$result['success'] = false;
				$result['errors'] = $errors;
			}
			else {
				$result['success'] = true;
			}

			echo json_encode($result);

			$filePath = "/tmp/images-removal-" . date('Ymd') . ".txt";
			$fo = fopen($filePath, 'a');

			foreach(AdminImageRemoval::$imagesRemoved as $fileName) {
				fwrite($fo, "Image:{$fileName}\n");
			}

			fclose($fo);

			return;
		}

		$wgOut->addJScode('airj');

		$s = Html::openElement( 'form', array( 'action' => '', 'id' => 'imageremoval' ) ) . "\n";
		$s .= Html::element('p', array(''), 'Input full URLs (e.g. http://www.wikihow.com/Kiss) for articles that should have images removed from them.');
		$s .= Html::element('br');
		$s .= Html::element( 'textarea', array('id' => 'urls', 'cols' => 55, 'rows' => 5) ) . "\n";
		$s .= Html::element('br');
		$s .= Html::element( 'input',
				array( 'type' => 'submit', 'class' => "button primary", 'value' => 'Process articles' )
			) . "\n";
		$s .= Html::closeElement( 'form' );
		$s .= Html::element('div', array('id' => 'imageremoval_results'));

		$wgOut->addHTML($s);

	}

	private function removeImagesFromArticle($articleId) {
		$title = Title::newFromID($articleId);

		if($title) {
			$revision = Revision::newFromTitle($title);

			$text = $revision->getText();

			//regular expressions copied out of maintenance/wikiphotoProcessImages.php
			//but modified to remove the leading BR tags if they exist
			//In the callback we keep track of each image name that we remove
			$text = preg_replace_callback(
				'@(<\s*br\s*[\/]?>)*\s*\[\[Image:([^\]]*)\]\]@im',
				function($matches) {
					$image = $matches[2];
					$pipeLoc = strpos($image, "|");
					if($pipeLoc !== false) {
						$image = substr($image, 0, $pipeLoc);
					}
					AdminImageRemoval::$imagesRemoved[] = $image;
					return '';
				},
				$text
			);
			$text = preg_replace_callback(
				'@(<\s*br\s*[\/]?>)*\s*\{\{largeimage\|([^\}]*)\}\}@im',
				function($matches) {
					$image = $matches[2];
					AdminImageRemoval::$imagesRemoved[] = $image;
					return '';
				},
				$text
			);
			$text = preg_replace('@(<\s*br\s*[\/]?>)*\s*\{\{largeimage\|[^\}]*\}\}@im', '', $text);

			$article = new Article($title);
			$saved = $article->doEdit($text, 'Removing all images from article.');
		}
	}
}