<?php

class Categorylisting extends SpecialPage {

	const CAT_WIDTH = 201;
	const CAT_HEIGHT = 134;

    function __construct($source = null) {
        parent::__construct( 'Categorylisting' );
    }

	function execute($par) {
		global $wgOut;

		$this->setHeaders();
		$wgOut->setRobotpolicy('index,follow');
		$wgOut->setSquidMaxage(6 * 60 * 60);
		$wgOut->addHTML(wfMessage('categorylisting_subheader')->text());
		$wgOut->addHTML("<br /><br />");
		$this->displayCategoryTable();

		//$wgOut->addHTML(preg_replace('/\<[\/]?pre\>/', '', wfMsg( 'categorylisting_categorytable', wfGetPad() )));

		return;
	}

	function displayCategoryTable() {
		global $wgOut;

		$catmap = Categoryhelper::getIconMap();

		ksort($catmap);

		$queryString = WikihowCategoryViewer::getViewModeParam();
		if (!empty($queryString)) {
			$queryString = "?" . $queryString;
		}

		$wgOut->addHTML("<div class='section_text'>");
		foreach($catmap as $cat => $image) {
			$title = Title::newFromText($image);

			if($title) {
				$file = wfFindFile($title, false);

				$sourceWidth = $file->getWidth();
				$sourceHeight = $file->getHeight();
				$heightPreference = false;
				if(self::CAT_HEIGHT > self::CAT_WIDTH && $sourceWidth > $sourceHeight) {
					//desired image is portrait
					$heightPreference = true;
				}
				$thumb = $file->getThumbnail(self::CAT_WIDTH, self::CAT_HEIGHT, true, true, $heightPreference);

				$category = urldecode(str_replace("-", " ", $cat));

				$catTitle = Title::newFromText("Category:" . $category);
				if($catTitle) {
					$wgOut->addHTML("<div class='thumbnail'><a href='{$catTitle->getLocalUrl()}{$queryString}'><img src='" . wfGetPad($thumb->getUrl()) . "' /><div class='text'><p><span>{$category}</span></p></div></a></div>");
				}
			}
		}
		$wgOut->addHTML("<div class='clearall'></div>");
		$wgOut->addHTML("</div><!-- end section_text -->");
	}
}
