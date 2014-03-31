<?

	require_once('commandLine.inc');


	function grabNextToken(&$tokens) {
		while (sizeof($tokens) > 0) {
			$x = trim(array_shift($tokens));
			if ($x != "") 
				return $x;
		}
		return null;
	}	

	function handleImages($x, &$dom, &$s) {
		// grab images
		global $wgOut;
		preg_match_all("@\[\[Image:[^\]]*\]\]@im", $x, $matches);
		$img = null;
		foreach($matches[0] as $m ) {
			if (!$img)
				$img = $dom->createElement("images");	
			$url = $wgOut->parse($m); 
			preg_match("@<img[^>]*class=\"mwimage101\"[^>]*>@im", $url, $mx);
			$url = preg_replace("@.*src=\"@", "", $mx[0]);
			$url = preg_replace("@\".*@", "", $url);
			$i = $dom->createElement("image");
			$i->appendChild($dom->createTextNode($url));
			$img->appendChild($i);
		}
		if ($img) 
			$s->appendChild($img);
		return;
	}
	function processListSection(&$dom, &$sec, $beef, $aresteps = true, $elem = "step") {
		global $wgOut;
		$toks = preg_split("@(^[#\*]+)@im", $beef, 0, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
		$substeps = null;
		while (sizeof($toks) > 0) {
			$x = grabNextToken(&$toks);
			if ($aresteps && preg_match("@^#[#\*]@", $x)) {
				if ($substeps == null) 
					$substeps = $dom->createElement("substeps");
			} else {
				if ($substeps)
					$sec->appendChild($substeps);
				$substeps = null;
			}
			$x = grabNextToken(&$toks);
			$s = $dom->createElement($elem);
			handleImages($x, &$dom, &$s);	
			$t = $dom->createElement("text");
			$x = cleanUpText($x);
			if ($x == "") continue;
			$t->appendChild($dom->createTextNode($x));
			$s->appendChild($t);
			if ($substeps)
				$substeps->appendChild($s);
			else
				$sec->appendChild($s);
		}
		if ($substeps)
			$sec->appendChild($substeps);
		return;
	}

	function cleanupText($text) {
		// strip templates
		$text= preg_replace("@{{[^}]*}}@", "", $text);
		$text= preg_replace("@\[\[Image:[^\]]*\]\]@", "", $text);
		$text= preg_replace("@\[\[Category:[^\]]*\]\]@", "", $text);
		$text= preg_replace("@<ref>.*</ref>@", "", $text);
		preg_match_all ("@\[\[.*\]\]@U", $text, $matches);
		foreach ($matches[0] as $m) {
			if (strpos($m, "|") !== false) 
				$n = preg_replace("@.*\|@", "", $m);
			else 
				$n = $m;
			$n = str_replace("]]", "", $n);
			$n = str_replace("[[", "", $n);
			$text = str_replace($m, $n, $text);
		}
		$text = preg_replace("@''[']?@", "", $text); // rid of bold, itaics;
		$text = preg_replace("@#[#]*@", "", $text);
		$text = preg_replace("@__[^_]*__@", "", $text);
		return trim($text);
	}


	if (!isset($argv[0])) {
		echo "Usage: php maintenance/generate_xml.php urls.txt\n";
		return;
	}

	$dbr = wfGetDB(DB_SLAVE);	
	$urls = split("\n", file_get_contents($argv[0]));
	$valid_sections = array("steps", "tips", "warnings", "things", "sources", "videos");

	$dom = new DOMDocument("1.0");
	$root = $dom->createElement("wikihowmedia");
	$dom->appendChild($root);

	foreach ($urls as $url) {
		if (trim($url) == "")
			continue;
		$url = str_replace("http://www.wikihow.com/", "", $url);
		$t = Title::newFromDBKey(urldecode($url));
		if (!$t) {
			echo "Can't get title from {$url}\n";
			continue;
		}
		$r = Revision::newFromTitle($t);
		if (!$r) {
			echo "Can't get revision from {$url}\n";
			continue;
		}
		$text = $r->getText(); 


		$a = $dom->createElement("article");

		// title
		$x = $dom->createElement("title");
		$x->appendChild($dom->createTextNode($t->getText()));
		$a->appendChild($x);

		// intro
		$content = $dom->createElement("content");
		$intro = Article::getSection($text, 0);
		$i = $dom->createElement("introduction");
		handleImages($intro, &$dom, &$i);
		$intro = cleanupText($intro);
		$n = $dom->createElement("text");
		$n->appendChild($dom->createTextNode($intro));
		$i->appendChild($n);
		$content->appendChild($i);

		$parts = preg_split("@(^==[^=]*==)@im", $text, 0, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
		$sources_element = null;
		while (sizeof($parts) > 0) {
			$x =trim(strtolower(str_replace('==', '', array_shift($parts)))); // title
			$x = preg_replace("@[^a-z]@", "", $x);
			if ($x == "thingsyoullneed") $x = "things";	
			if ($x == "sourcesandcitations") $x = "sources";	
			if ($x == "video") $x = "videos";	
			if (!in_array($x, $valid_sections))
				continue;
			$section = $dom->createElement($x);

			if ($x == "sources") {
				$sources_element = $section;
			}

			// process subsections
			$beef = array_shift($parts);
			if ($x == "steps") {
				if (preg_match("@(^===[^=]*===)@im", $beef))  {
					$subs = preg_split("@(^===[^=]*===)@im", $beef, 0, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
					while (sizeof($subs) > 0) {
						$y = array_shift($subs);
						if (!preg_match("@(^===[^=]*===)@", $y))
							continue;
						$sub = $dom->createElement("subsection");
						$x = str_replace("=", "", $y);
						$tnode = $dom->createElement("title");
						$tnode->appendChild($dom->createTextNode(cleanupText($x)));
						$sub->appendChild($tnode);
						$body = array_shift($subs);
						processListSection($dom, $sub, $body);
						$section->appendChild($sub);
					}
				} else {
					processListSection($dom, $section, $beef);	
				}
			} else if ($x == "videos") {
				$vid_t = Title::makeTitle(NS_VIDEO, $t->getText());
				$vid_r = Revision::newFromTitle($vid_t);
				if ($vid_r) {
					$vid_text = $vid_r->getText();
					$tokens = split("\|", $vid_text);
					$provider = $tokens[1];
					$id = $tokens[2];
					foreach ($wgEmbedVideoServiceList as $service=>$params) {
						if ($provider == $service) {
							$url = str_replace("$1", $id, $params['url']);
							$vid = $dom->createElement("video");
							$vid->appendChild($dom->createTextNode($url));
							$section->appendChild($vid);
							break;
						}
					}
				}
			} else {
				processListSection($dom, $section, $beef, false, preg_replace("@s$@", "", $x));
			}

			// append the section
			$content->appendChild($section);
		}

		// process references
		preg_match_all("@<ref>.*</ref>@im", $text, $matches); 
		foreach($matches[0] as $m) {
			if (!$sources_element) 		
				$sources_element = $dom->createElement("sources");
			$m = preg_replace("@<[/]*ref>@", "", $m);
			$e = $dom->createElement("source");	
			$tx = $dom->createElement("text");	
			$tx->appendChild($dom->createTextNode($m));
			$e->appendChild($tx);
			$sources_element->appendChild($e);
			$content->appendChild($sources_element);
		}	

		$a->appendChild($content);
	
		//attribution
		$attr = $dom->createElement("attribution");		
		$num = $dom->createElement("numeditors");
		$users = array();
		$res = $dbr->select("revision", array("distinct(rev_user_text)"), array("rev_page"=>$t->getArticleID(), "rev_user != 0"), "generate_xml.php", array("ORDER BY" => "rev_timestamp DESC"));
		$num->appendChild($dom->createTextNode($dbr->numRows($res)));
		$attr->appendChild($num);
		while ($row = $dbr->fetchObject($res)) {
			$u = User::newFromName($row->rev_user_text);
			$u->load();
			$name = $u->getRealName() != "" ? $u->getRealName() : $u->getName();
			$users[] = $name;
		}
		$names = $dom->createElement("names");
		$names_text = $dom->createElement("text");
		$names_text->appendChild($dom->createTextNode(implode(",", $users)));
		$names->appendChild($names_text);
		$attr->appendChild($names);
		$a->appendChild($attr);

		$root->appendChild($a);
			
	}

	echo $dom->saveXML();
