<?php

class Sitemap extends SpecialPage {

	function __construct() {
		parent::__construct( 'Sitemap' );
	}

	function getTopLevelCategories() {
		global $wgCategoriesArticle;
		wfLoadExtensionMessages('Sitemap');
		$results = array (); 
		$revision = Revision::newFromTitle( Title::newFromText( wfMsg('categories_article') ) );
		if (!$revision) return $results;

		// INTL: If there is a redirect to a localized page name, follow it
		if(strpos($revision->getText(), "#REDIRECT") !== false) {
			$revision = Revision::newFromTitle( Title::newFromRedirect($revision->getText()));
		}

		$lines = split("\n", $revision->getText() );
		foreach ($lines as $line) {
			if (preg_match ('/^\*[^\*]/', $line)) {
				$line = trim(substr($line, 1)) ;
				switch ($line) {
					case "Other":
					case "wikiHow":
						break;
					default:
						$results [] = $line;
				}
			}
		}
		return $results;
	}

	function getSubcategories($t) {
		$dbr =& wfGetDB( DB_SLAVE );
		$subcats = array();
		$res = $dbr->select ( array ('categorylinks', 'page'),
			array('page_title'),
			array('page_id=cl_from',
				'cl_to' => $t->getDBKey(),
				'page_namespace=' .NS_CATEGORY
			),
			"Sitemap:wfGetSubcategories"
		);
		while ($row = $dbr->fetchObject($res)) {
			if (strpos($row->page_title, 'Requests') !== false) continue;
			$subcats[] = $row->page_title;
		}
		return $subcats;
	}
	
	function execute($par) {
		global $wgOut, $wgUser;
		$wgOut->setRobotPolicy("index,follow");
		$sk = $wgUser->getSkin();
		$topcats = $this->getTopLevelCategories();

		$wgOut->setHTMLTitle('wikiHow Sitemap');

		$count = 0;
		$wgOut->addHTML("
			<style>
				#catentry li {
					margin-bottom: 0;
				}
				table.cats {
					width: 100%;
				}
				.cats td {
					vertical-align: top;
					border: 1px solid #e5e5e5;
					padding: 10px;
					background: white;
					-moz-border-radius: 4px;
					-webkit-border-radius: 4px;
					-khtml-border-radius: 4px;
					border-radius: 4px;
				}
			</style>
			<table align='center' class='cats' cellspacing=10px>");

		foreach ($topcats as $cat) {
			$t = Title::newFromText($cat, NS_CATEGORY);
			$subcats = $this->getSubcategories($t);
			if ($count % 2 == 0)
				$wgOut->addHTML("<tr>");
			$wgOut->addHTML ( "<td><h3>" . $sk->makeLinkObj($t, $t->getText()) . "</h3><ul id='catentry'>");
			foreach ($subcats as $sub) {
				$t = Title::newFromText($sub, NS_CATEGORY);
				$wgOut->addHTML ( "<li>" . $sk->makeLinkObj($t, $t->getText()) . "</li>\n");
			}
			$wgOut->addHTML("</ul></td>\n");
			if ($count % 2 == 1)
				$wgOut->addHTML("</tr>");
			$count++;
		}

		$wgOut->addHTML("</table>");
	}

}

