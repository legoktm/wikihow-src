<?php

class BlogPosts {

	function getBlogPosts($numdays) { 
	
		$rssfeed = 'http://blog.wikihow.com/feed/';
		$posts = array();

		$xmlDoc = new DOMDocument();
		$xmlDoc->load($rssfeed);

		//get and output "<item>" elements
		$x=$xmlDoc->getElementsByTagName('item');
		for ($i=0; $i<=2; $i++) {
			if ($x->item($i)) {
				$item[0]=$x->item($i)->getElementsByTagName('link')->item(0)->childNodes->item(0)->nodeValue;
				$item[1]=strtotime($x->item($i)->getElementsByTagName('pubDate')->item(0)->childNodes->item(0)->nodeValue);
				$item[2]=$x->item($i)->getElementsByTagName('title')->item(0)->childNodes->item(0)->nodeValue;
				$item[3]=$x->item($i)->getElementsByTagName('description')->item(0)->childNodes->item(0)->nodeValue;
				$item[4]='wikihowblog';  //dc:creator so we can id these separately from the featured articles
				$posts[] = $item;
			}
		}
		return $posts;
	}

}
?>
