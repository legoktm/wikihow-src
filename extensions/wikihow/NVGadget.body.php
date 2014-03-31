<?php

class NVGadget extends UnlistedSpecialPage {

	function __construct() {
		parent::__construct( 'NVGadget' );
	}

	function addTargetBlank($source) {
		$preg = '/<a href=/';
		$source = preg_replace($preg, '<a target="_blank" href=', $source);
		return $source;
	}

	function getRelatedWikihowsFromSource($title, $num) {
		global $wgParser;
		$r = Revision::newFromTitle($title);
		if ($r) {
			$text = $r->getText();
			$whow = WikihowArticleEditor::newFromText($text);
			$related = $whow->getSection('related wikihows');
			$preg = "/\\|[^\\]]*/";
			$related = preg_replace($preg, "", $related);

			//splice and dice
			$rarray = split("\n", $related);
			$related = implode("\n", array_splice($rarray, 0, $num));
			$options = new ParserOptions();
			$output = $wgParser->parse($related, $title, $options);
			$ra = $this->addTargetBlank($output->getText());
			return $ra;
		} else {
			return "";
		}
	}

	// GoodRevision::newArticleFromLatest() should be used in place. The only
	// reason it isn't is that we don't know how to test this code.
	function getLastPatrolledRevision($title) {
		$a = null;
		$dbr = wfGetDB(DB_SLAVE);
		$page_id = $title->getArticleID();

		$sql =	"SELECT max(rc_this_oldid) as A from recentchanges
				WHERE rc_cur_id = $page_id and rc_patrolled = 1";
		$res = $dbr->query($sql);
		if ( false !== $res
			&& $dbr->numRows($res) > 0
			&& $row = $dbr->fetchObject($res) )
		{
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
			$a = new Article($title);
		}
		return $a;
	}

	function execute($par) {
		global $wgOut, $wgTitle, $wgMemc, $wgServer;
		global $wgScriptPath, $wgServer, $wgRequest;

		require_once('NVGadgetTMPL.php');

		header("Content-Type: text/html");
		$wgOut->setSquidMaxage(3600);

		$nvtmpl = new NetVibes();
		$nvtmpl->outHeader();

		// extract the number of days
		$days = 6;
		$numitems = 5;
		date_default_timezone_set("UTC");
		$days = FeaturedArticles::getNumberOfDays($days);
		$feeds = FeaturedArticles::getFeaturedArticles($days);

		if (count($feeds) > 2) {
			$spotlight = rand(0, 3);
		} else {
			$spotlight = rand(0, (count($feeds)));
		}

		if ($getSpotlight = $wgRequest->getVal('spotlight')) {
			$spotlight = $getSpotlight ;
		}

		$now = time();
		$count = 0;
		$itemsshown = 0;
		$itemlist = "";
		foreach ($feeds as $f) {
			$url = $f[0];
			$d = $f[1];
			if ($d > $now) continue;

			$url = str_replace("http://wiki.ehow.com/", "", $url);
			$url = str_replace("http://www.wikihow.com/", "", $url);
			$url = str_replace($wgServer . $wgScriptPath . "/", "", $url);
			$title = Title::newFromURL(urldecode($url));
			$summary = "";
			$image = "";
			$mtext = "";
			$a = "";
			if ($title == null) {
				echo "title is null for $url";
				exit;
			}
			if ($title->getArticleID() > 0) {
				$a = $this->getLastPatrolledRevision($title);
				$summary = Article::getSection($a->getContent(true), 0);

				global $wgParser;
					$summary = ereg_replace("\{\{.*\}\}", "", $summary);
				$output = $wgParser->parse($summary, $title, new ParserOptions() );

				$title_text = $title->getPrefixedText();
				if (isset($f[2]) && $f[2] != null && trim($f[2]) != '') {
					$title_text = $f[2];
				} else {
					$title_text = wfMsg('howto', $title_text);
				}

				$artbloblen = 480;
				if ($count == $spotlight) {
					$summary = $output->getText();

					// REMOVE MAGNIFY IMAGE
					$summary = preg_replace('/<img src="\/skins\/common\/images\/magnify-clip\.png" width="15" height="11" alt="" \/>/', '', $summary);

					// REBUILD IMAGE TAG. RESIZE IMAGE AND SET ABSOLUTE PATH.
					if (preg_match('/<img alt=".*?" src="(.*?)" width="(\d+)" height="(\d+)"/', $summary, $match)) {
						if ($match[3] != "") {
							if ($match[3] > 100) {
								$hrat = 100/$match[3];
							} else {
								$hrat = 1;
							}
							$width = number_format(($match[2] * $hrat), 0, '.', '');
							$height = number_format(($match[3] * $hrat), 0, '.', '');
						}
						$summary = preg_replace('/width="'.$match[2].'"/', 'width="'.$width.'"', $summary);
						$summary = preg_replace('/height="'.$match[3].'"/', 'height="'.$height.'"', $summary);
						$p = preg_replace('/\//', '\/', $match[1]);
						$p = '/src="'.$p.'"/';
						$rval = 'src="'. $wgServer . $match[1].'"';
						$summary = preg_replace($p, $rval, $summary);
					}

					// REMOVE ALL TAGS EXCEPT IMG AND SETUP IMAGE DIV
					$summary = strip_tags($summary,'<img>');
					if (preg_match('/<img(.*?)>/', $summary, $match)) {
						$m = preg_replace('/\//', '\/', $match[1]);
						$pat = '/<img'. $m .'>/';
						$rval = '<div class="floatright"><span>' .
								'<a href="'.$wgServer.'/'.$url.'" target="_blank">' .
								'<img' . $match[1] .'></a></span></div>';
						$summary = preg_replace($pat, $rval, $summary);
					}

					// TRUNCATE ARTICLE
					if (strlen($summary) > $artbloblen) {
						$summary = substr($summary, 0 , $artbloblen);
						$summary .= '... <a href="'.$wgServer.'/'.$url.'" target="_blank">[Read More]</a>' . "\n";
					} else {
						$summary .= ' <a href="'.$wgServer.'/'.$url.'" target="_blank">[Read More]</a>' . "\n";
					}
					$nvtmpl->outMain( $title_text, $summary, $url );
				} else {
					if ($itemsshown < $numitems) {
						$itemlist .= $nvtmpl->outItem( $title_text, $summary, $url, $count );
						$itemsshown++;
					}
				}
				$count++;
			}
		}
		$nvtmpl->outItemList( $itemlist );
		$nvtmpl->outFooter();
	}
}

