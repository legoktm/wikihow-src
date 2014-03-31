<?php
require_once 'commandLine.inc';
global $wgParser, $wgServer;

$qPrefix = array ('What is the best way to ',
			'How do you ',
			'Can you tell me how to ',
			'Does anyone know how you ',
			'Who knows how to ',
			'Can someone tell me how you ');

$dbw = wfGetDB( DB_MASTER );
$dbr = wfGetDB( DB_SLAVE );

	function yHeader() {
      echo '<?xml version="1.0" encoding="utf-8"?>' . "\n";
?>
<yedda:Content xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:yedda="http://yedda.com/api/schema/qna/1.0"  xmlns="http://www.w3.org/1999/xhtml" xsi:schemaLocation="http://yedda.com/api/schema/qna/1.0 Yedda-QnA.xsd">
<?php
	}

	function yQuestion($id,$title,$url,$text,$topics,$postTS,$user) {
?>
        <yedda:Question id="<?php echo $id ?>" >
                <yedda:Context>
                        <yedda:Title><?php echo $title ?></yedda:Title>
                        <yedda:Url><?php echo $url ?></yedda:Url>
                </yedda:Context>
                <yedda:Text><?php echo $text ?></yedda:Text>
                <yedda:Topics>
<?php foreach ($topics as $topic) {
			?>
                        <yedda:Topic><?php echo $topic ?></yedda:Topic>
<?php } ?>
                </yedda:Topics>
                <yedda:PostTimestamp><?php echo $postTS ?></yedda:PostTimestamp>
                <yedda:UserRef>
                        <yedda:XmlRef><?php echo $user ?></yedda:XmlRef>
                </yedda:UserRef>
        </yedda:Question>
<?php
	}

	function yAnswer($id,$qref,$text,$postTS,$user) {
?>
        <yedda:Answer id="<?php echo $id  ?>">
                <yedda:QuestionRef>
                        <yedda:XmlRef><?php echo $qref ?></yedda:XmlRef>
                </yedda:QuestionRef>
                <yedda:Text><![CDATA[
<?php echo $text,"\n" ?>
						]]>
                </yedda:Text>
                <yedda:PostTimestamp><?php echo $postTS ?></yedda:PostTimestamp>
                <yedda:UserRef>
                        <yedda:XmlRef><?php echo $user ?></yedda:XmlRef>
                </yedda:UserRef>
        </yedda:Answer>
<?php
	}

	function yFooter() {
		echo '</yedda:Content>' . "\n";
	}


	function getLastPatrolledRevision (&$title) {
		$a = null;
		$dbr =& wfGetDB( DB_SLAVE );
		$page_id = $title->getArticleID();
		$sql =	"SELECT max(rc_this_oldid) as A from recentchanges
							WHERE rc_cur_id = $page_id and rc_patrolled = 1";
		$res = $dbr->query($sql);
		if ( false !== $res && $dbr->numRows( $res ) > 0 && $row = $dbr->fetchObject( $res ) )  {
					if ($row->A) $a = new Article($title, $row->A);	
		}
		$dbr->freeResult( $res );
		// if that didn't work, get the last edit that's not in recentchanges	
		if ($a == null) {
			$sql = "select max(rev_id) as A from revision where rev_page = $page_id and rev_id 
				NOT IN (select rc_this_oldid from recentchanges where rc_cur_id = $page_id and rc_patrolled = 0 );";
			$res = $dbr->query ( $sql );
			if ( false !== $res ) {
						if ($row = $dbr->fetchObject( $res ) ) { 
							// why does this work in the line above? $row->A > 0 ????
							if ($row->A > 0) $a = new Article($title, $row->A);
						}	
			}
		}
		if ($a == null) {
			$a = new Article(&$title);
		}
		return $a;	
	}

	function yeddaCleanup ($text, $intro = false) {
		global $wgServer;

		$text = preg_replace('/<div class="magnify".*?<\/div>/m', '', $text);
		$text = preg_replace('/<div class="thumbcaption".*?<\/div>/m', '', $text);

		//REMOVE TAGS (except those accepted by yedda)
		$text = strip_tags($text, '<b><i><a><img><embed><em><br><strong><u><blockquote><code><ol><ul><li><p><h2>');

		//ADDITIONAL ARTICLE MODS
		$text = preg_replace('/\[<a href="\/index.php\?.*?>edit<\/a>\]/m', '', $text);
		$text = preg_replace('/href="\//', 'href="'.$wgServer.'/', $text);
		$text = preg_replace('/src="\//', 'src="'.$wgServer.'/', $text);

		if (!$intro) {
			$text = preg_replace('/<img.*?\/>/m', '<strong>See Image</strong>', $text);
			$text = preg_replace('/<a name="Related_wikiHows">.*/ms','', $text);

			preg_match('/^<a href=.*?class="image".*?<\/a>/m', $text, $introimg_match);
			$text = preg_replace('/^<a href=.*?class="image".*?<\/a>/m','', $text);
			preg_match('/<a name=/',$text,$anchor_match,PREG_OFFSET_CAPTURE);
			$text = substr($text,0,($anchor_match[0][1] - 1)) ."\n". $introimg_match[0] ."\n". substr($text,$anchor_match[0][1]);
		} else {
			preg_match('/<img(.*?)\/>/',$text,$matches);
			$text = preg_replace('/<a href=.*?class="image".*?<\/a>/m', '', $text);
			$text .= '<img'. $matches[1] . '/>';

		}

		return(trim($text));

	}

	function getTopCategories($ptree) {
		$topcats = array ();

		foreach (array_keys($ptree) as $key) {
			if ($key != 'Category:Featured-Articles') {
	
				$a = $ptree[$key];
				if (is_array($a)) {
					$last = $a;
					while (sizeof($a) > 0 && $a = array_shift($a) ) {
						$last = $a;
					}
					$keys = array_keys($last);	
					$cat = str_replace("Category:", "", $keys[0]);
					$cat = str_replace("-", " ", $cat);
					$cat = str_replace("&", "and", $cat);
					array_push($topcats, $cat);
				} else {
					$cat = str_replace("Category:", "", $key);
					$cat = str_replace("-", " ", $cat);
					$cat = str_replace("&", "and", $cat);
					array_push($topcats, $cat);
				}
			}
		}

		return $topcats;
	}




	// Question Fields
	$yqPostTS = '1971-01-01T00:00:00';
	$yqUser = 'WikiHow';

	// Answer Fields
	$yaPostTS = '1971-01-01T00:00:00';
	$yaUser = 'WikiHow Community';

	$urlfile = "yeddaURLs.txt";
	if (isset($argv[0]) && $argv[0] != "") {
		$urlfile = $argv[0];
	}

	$fh = fopen($urlfile, 'r');

	if (!$fh) {
		echo "File $urlfile could not be opened. File can also be passed as first argument.\n";
		exit ;
	}

	// GET URLs
	$urls = array();

	while ($url = fgets($fh)) {
		if (preg_match('/^#/',$url)) {
			//COMMENT
		} else {
			array_push($urls,trim($url));
		}
	}

	fclose($fh);


	$aid_arr = array();
	yHeader();
	foreach ($urls as $url) {

		$url = str_replace("http://www.wikihow.com/", "", $url);
		$url1 = urldecode($url);
		$fullurl = $wgServer . "/" . urlencode($url);
		$title = Title::newFromURL( $url1 );
		if (!$title) {
			$title = Title::newFromURL( $url );
		}
		if (!$title) {
			fwrite(STDERR, "ERROR: article not found for $url1 fullurl $fullurl \n");
			continue;	
		}

		$aid = $title->getArticleID();

		if ($aid > 0) {
	
			$title_text = "";
			$topics = array();
	
			//GET ARTICLE
			$a = getLastPatrolledRevision(&$title);
			$intro = Article::getSection($a->getContent(true), 0);

			if (preg_match('/^#REDIRECT \[\[(.*?)\]\]/', $intro,$matches)) {
				$title = Title::newFromText($matches[1]);
				$aid = $title->getArticleID();
				if ($aid <= 0) {
					fwrite(STDERR, "ERROR: article id does not exist url: $url\n");
					continue;
				} else {
					$a = getLastPatrolledRevision(&$title);
					$intro = Article::getSection($a->getContent(true), 0);
				}
			}
  			$intro = ereg_replace("\{\{.*\}\}", "", $intro);

			if (in_array($aid, $aid_arr)) {
				fwrite(STDERR, "ERROR: article id duplicate for id: $aid and url: $url \n");
				continue;
			} else {
				$aid_arr[] = $aid;
			}

			//GET IDs
			$yqID = 'whq'.$aid;
			$yaID = 'wha'.$aid;

			//GET TITLE
			$title_text = $title->getPrefixedText();
			$prefix_idx = rand(0,(count($qPrefix) - 1));
			$title_text = $qPrefix[$prefix_idx] . $title_text . '?';
			$title_text = str_replace("&", "and", $title_text);
	
			//GET CATEGORIES
			$topics = getTopCategories($title->getParentCategoryTree());			


			//GET ARTICLE
			$article = $a->getContent(true);
  			$article = ereg_replace("\{\{fa\}\}", "", $article);
			$output2 = $wgParser->parse($article, $title, new ParserOptions() ); 
			$article = $output2->getText();


			$attribution = "\n\n" . '<br />This answer was provided by <a href="'.$wgServer.'">wikiHow</a>, a wiki building the world\'s largest, highest quality how-to manual. Please edit this article and find author credits at the original wikiHow article on <a href="'.$fullurl .'">How to '.$title->getPrefixedText().'</a> . Content on wikiHow can be shared under a <a href="http://creativecommons.org/licenses/by-nc-sa/2.5/">Creative Commons License</a>.' . "\n";

			$article = yeddaCleanup($article) . $attribution;


			//GET TIMESTAMP
			$ts = $a->getTimestamp();
			if (preg_match('/^(\d{4})(\d\d)(\d\d)(\d\d)(\d\d)(\d\d)$/D',$ts,$da)) {
				$uts=gmmktime((int)$da[4],(int)$da[5],(int)$da[6],(int)$da[2],(int)$da[3],(int)$da[1]);
				$postTS = gmdate( 'Y-m-d\TH:i:s', $uts );
			} else {
				$postTS = wfTimestamp(TS_ISO_8601, $ts);
			}

			yQuestion($yqID, $title_text, $fullurl, $title_text, $topics, $postTS, $yqUser);
			yAnswer($yaID, $yqID, $article, $postTS, $yaUser);
		
		} else {
			echo "ERROR: No article for url $url\n";
		}
	}	
	yFooter();
?>
