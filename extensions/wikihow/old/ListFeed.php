<?php

function getTechnoratiTags($text) {
	$result = "";
	$i = strpos($text, "[http://www.technorati.com/tag");
	while ($i !== false) {
		$l = strpos($text, " ", $i);
		$j = strpos($text, "]", $l);
		$result .= "<a href=" . substr($text, $i+1, $l-$i-1) . ">" . substr($text, $l+1, $j-$l-1) . "</a> ";
		$i = strpos($text, "[http://www.technorati.com/tag", $i+1);
	}
	return $result;
}

function wfSpecialListFeed($par) {
	global $wgUser, $wgOut;

	$fname = "wfSpecialListFeed";

	$sk = $wgUser->getSkin();
	$feeds = FeaturedArticles::getFeaturedArticles(11);
	$wgOut->addHTML("<ul>");
	foreach ($feeds as $item) {
		$feed = $item[0];
		$x = str_replace("http://wiki.ehow.com/", "", $feed);
		$x = str_replace("http://www.wikihow.com/", "", $feed);
		$t = Title::newFromDBKey($x);
		$summary = "";
		$a = null;
		if ($t->getArticleID() > 0) {
			$a = new Article(&$t);
			$summary = Article::getSection($a->getContent(false), 0);
			$summary = ereg_replace("<.*>", "", $summary);
			$summary = ereg_replace("\[\[.*\]\]", "", $summary);
			$summary = ereg_replace("\{\{.*\}\}", "", $summary);

			$summary = trim($summary);
			$tags = getTechnoratiTags($a->getContent(false));
		}

		$wgOut->addHTML("<div style='width:400px; border: 1px #ccc solid; margin-bottom:20px; padding: 10px; '>");
		$wgOut->addHTML("<img height=16 src='http://wiki.ehow.com/skins/common/images/check.jpg'><a href='$feed'>How to " . $t->getText() . "</a><br/><br/>");
		$wgOut->addHTML($summary);
		$wgOut->addHTML("<br/><a href='$feed'><i>Read more...</i></a><br/><br/><font size=-2>Posted " . $item[1] . " - (<a href='$feed'>Permalink</a>)");
		if ($tags != null)
			$wgOut->addHTML(" (Technorati Tags: "  . trim($tags) . ")" );
		$wgOut->addHTML("</font>");
		$wgOut->addHTML("</div>");
	}
	$wgOut->addHTML("</ul>");
	$wgOut->addHTML('<script type="text/javascript" src="http://embed.technorati.com/embed/unj3heqw9.js"></script>');
}

