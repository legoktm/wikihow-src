<?
/*
* Experimental Points scoring class created by Travis to determine whether an edit is a significant edit.  
* Currently not included in LocalSettings.php but may be of use later
*/
class Points extends UnlistedSpecialPage {

	function __construct() {
		parent::__construct( 'Points' );
	}

	function getRandomEdit($t = null) {
		// get a random page
		if (!$t) {
			$rp = new RandomPage(); 
			$t = $rp->getRandomTitle();
		}
		
		// pick a random one
		$dbr = wfGetDB(DB_SLAVE);
		$revid = $dbr->	selectField('revision', array('rev_id'), array('rev_page'=>$t->getArticleID()), 
				"RandomEdit::getRandomEdit", array("ORDER BY" => "rand()", "LIMIT"=>1));
		$r = Revision::newFromID($revid);
		return $r;
	}

	function getDiffToMeasure($r) {

		$dbr = wfGetDB(DB_SLAVE);
		$result = array(); 
		// get the low, we compare this against the last edit
		// which was made by a different user
		$revlo = $dbr->selectField('revision', 'rev_id', 
			array('rev_page'=>$r->mTitle->getArticleID(), 
					'rev_user_text != ' . $dbr->addQuotes($r->mUserText),
				'rev_id < ' . $r->mId
				),
			"RandomEdit::getDiffToMeasure",
			array("ORDER BY"=>"rev_id desc", "LIMIT"=>1)
			);

		
		// get the highest edit in this sequence of edits by this user
		$not_hi_row  = $dbr->selectRow('revision', array('rev_id', 'rev_comment', 'rev_user_text'), 
			array('rev_page'=>$r->mTitle->getArticleID(), 
					'rev_user_text != ' . $dbr->addQuotes($r->mUserText),
				'rev_id > ' . $r->mId
				)
			);
		$revhi = null;
		if (!$not_hi_row) {
			$revhi = $r->mId;
		} else {
			$revhi = $dbr->selectField('revision', 'rev_id', 
				array('rev_page'=>$r->mTitle->getArticleID(), 'rev_id <  ' . $not_hi_row->rev_id),
				"RandomEdit::getDiffToMeasure",
				array("ORDER BY"=>"rev_id desc", "LIMIT"=>1)
				);
			$result['nextcomment'] = $not_hi_row->rev_comment;
			$result['nextuser'] = $not_hi_row->rev_user_text;
		}

		$hi = Revision::newFromID($revhi);
		$hitext = $hi->getText();

		$lotext = "";
		if ($revlo) {
			$lo = Revision::newFromID($revlo);
			$lotext = $lo->getText();
		}

		if ($lotext == "") {
			$result['newpage']= 1;
		} else {
			$result['newpage']= 0;
		}
		$opts = array('rev_page'=>$r->mTitle->getArticleID(), 'rev_id <= ' . $revhi);
		if ($revlo) {
			$opts[] = 'rev_id >  ' . $revlo;
		}
		$result['numedits'] = $dbr->selectField('revision', 'count(*)', $opts);
		$result['diff'] =  wfDiff($lotext, $hitext);
		$result['revhi'] = $hi;
		$result['revlo'] = $lo;
		return $result;
	}

	function getPoints($r, $d, $de, $showdetails = false) {
		global $wgOut;
		$points = 0; 

		$oldText = "";
		if ($d['revlo']) {
			$oldText = $d['revlo']->mText;
		}
		$newText = $d['revhi']->mText;

		$flatOldText = preg_replace("@[^a-zA-z]@", "", WikihowArticleEditor::textify($oldText));

		// get the points based on number of new / changed words
		$diffhtml = $de->generateDiffBody( $d['revlo']->mText, $d['revhi']->mText);
		$addedwords = 0;
		preg_match_all('@<span class="diffchange diffchange-inline">[^>]*</span>@m', $diffhtml, $matches);
		foreach ($matches[0] as $m) {
			$m = WikihowArticleEditor::textify($m);
			preg_match_all("@\b\w+\b@", $m, $words);
			$addedwords += sizeof($words[0]);
		}
		preg_match_all('@<td class="diff-addedline">(.|\n)*</td>@Um', $diffhtml, $matches);
		#echo $diffhtml; print_r($matches); exit;
		foreach ($matches[0] as $m) {
			if (preg_match("@diffchange-inline@", $m)) {
				// already accounted for in change-inline
				continue;
			}
			$m = WikihowArticleEditor::textify($m);
			
			// account for changes in formatting and punctuation 
			// by flattening out the change piece of text and comparing to the 
			// flattened old version of the text
			$flatM = preg_replace("@[^a-zA-z]@", "", $m); 
			if (!empty($flatM) && strpos($flatOldText, $flatM) !== false) {
				continue;
			}
			preg_match_all("@\b\w+\b@", $m, $words);
			$addedwords += sizeof($words[0]);
		}

		if ($showdetails) $wgOut->addHTML("<h3>Points for edit (10 max):</h3><ul>");
		if (preg_match("@Reverted@", $r->mComment)) {
			if ($showdetails) $wgOut->addHTML("<li>No points : reverted edit.</li></ul><hr/>");
			return 0;
		}
		if (preg_match("@Reverted edits by.*" . $d['revhi']->mUserText . "@", $d['nextcomment'])) {
			if ($showdetails) $wgOut->addHTML("<li>No points: This edit was reverted by {$d['nextuser']}\n</li></ul><hr/>");
			return 0;
		}

		$wordpoints = min(floor($addedwords / 100), 5);
		if ($showdetails) $wgOut->addHTML("<li>Approx # of new words: " . $addedwords . ": $wordpoints points (1 point per 100 words, max 5)</li>");  
		$points += $wordpoints;

		// new images
		$newimagepoints = array();
		preg_match_all("@\[\[Image:[^\]|\|]*@", $newText, $images);
		$newimages = $newimagepoints = 0;
		foreach ($images[0] as $i) {
			if (strpos($oldText, $i) === false) {
				$newimagepoints++;
				$newimages++;
			}
		}
		$newimagepoints = min($newimagepoints, 2);
		$points += $newimagepoints;
		if ($showdetails) $wgOut->addHTML("<li>Number of new images: " . $newimages . ": $newimagepoints points (1 point per image, max 2)</li>");  

		// new page points
		if ($d['newpage']) {
			if ($showdetails) $wgOut->addHTML("<li>New page: 1 point</li>");
			$points += 1;
		}


		// template points
		preg_match_all("@\{\{[^\}]*\}\}@", $newText, $templates);
		foreach ($templates[0] as $t) {
			if (strpos($oldText, $t) === false && $t != "{{reflist}}") {
				if ($showdetails) $wgOut->addHTML("<li>Template added: 1 point</li>");
				$points++;
				break;
			}
		}

		// category added points
		preg_match_all("@\[\[Category:[^\]]*\]\]@", $newText, $cats);
		foreach ($cats[0] as $c) {
			if (strpos($oldText, $c) === false) {
				if ($showdetails) $wgOut->addHTML("<li>Category added: 1 point</li>");
				$points++;
				break;
			}
		}
			
		$points = min($points, 10);
		if ($showdetails) $wgOut->addHTML("</ul>");
		if ($showdetails) $wgOut->addHTML("<b>Total points: {$points}</b><hr/>");
		
		return $points;
	}

	// group the edits of the page together by user
	function getEditGroups($title) {
		$dbr = wfGetDB(DB_MASTER);
		$res = $dbr->select('revision', array('rev_id', 'rev_user_text', 'rev_timestamp', 'rev_user'), 
				array('rev_page'=>$title->getArticleID()));
		$results = array(); 
		$last_user = null;
		$x = null;
		while ($row = $dbr->fetchObject($res)) {
			if ($last_user == $row->rev_user_text) {
				$x['edits']++;
				$x['max_revid'] = $row->rev_id;
				$x['max_revtimestamp'] = $row->rev_timestamp;
			} else {
				if ($x) {
					$results[] = $x;
				}
				$x = array();
				$x['user_id'] = $row->rev_user;
				$x['user_text'] = $row->rev_user_text;
				$x['max_revid'] = $row->rev_id;
				$x['min_revid'] = $row->rev_id;
				$x['max_revtimestamp'] = $row->rev_timestamp;
				$x['edits'] = 1;
				$last_user = $row->rev_user_text;
			}
		}
		$results[] = $x;
		return array_reverse($results);
	}

	function execute($par)  {
		global $wgRequest, $wgOut, $wgUser; 
		$target = isset( $par ) ? $par : $wgRequest->getVal( 'target' );
        
		if ( !in_array( 'sysop', $wgUser->getGroups() ) ) {
            $wgOut->setArticleRelated( false );
            $wgOut->setRobotpolicy( 'noindex,nofollow' );
            $wgOut->showErrorPage( 'nosuchspecialpage', 'nospecialpagetext' );
            return;
        }

		if ($target) {
			if (preg_match("@[^0-9]@", $target)) {
				$t = Title::newFromURL($target);
			} else {
				$r = Revision::newFromID($target);		
				if ($wgRequest->getVal('popup')) {
					$wgOut->setArticleBodyOnly(true);
					$wgOut->addHTML("<style type='text/css'>
						table.diff  {
							margin-left: auto; margin-right: auto;
						}
						table.diff td {
							max-width: 400px;
						}
						</style>");
				}
				$wgOut->addHTML("Revid: {$r->mId}\n");
				$d = self::getDiffToMeasure($r);
				$de = new DifferenceEngine($r->mTitle, $d['revlo']->mId, $d['revhi']->mId);
				self::getPoints($r, $d, $de, true);
				if (!$d['revlo']) {
					$de->mOldRev = null;
					$de->mOldid = null;
				}
				$de->showDiffPage();
				return;
			}
		} else {
			$rp = new RandomPage(); 
			$t = $rp->getRandomTitle();
		}
	
		$wgOut->addHTML("<script type='text/javascript'>
function getPoints(rev) {
	$('#img-box').load('/Special:Points/' + rev + '?popup=true', function() {
			$('#img-box').dialog({
			   width: 750,
			   modal: true,
				title: 'Points', 
			   show: 'slide',
				closeOnEscape: true,
				position: 'center'
			});
	});
	return false;
}
</script>
");
		// get the groups of edits
		$group = self::getEditGroups($t); 
		$wgOut->addHTML("Title: <a href='{$t->getFullURL()}?action=history' target='new'>{$t->getFullText()}</a><br/><br/>");
		$wgOut->addHTML("<table width='100%'><tr><td><u>User</u></td><td><u># Edits</u></td>");
		$wgOut->addHTML("<td><u>Date</u></td><td><u>Points</u></td></tr>");
		foreach ($group as $g) {
			$r = Revision::newFromID($g['max_revid']);
			$d = self::getDiffToMeasure($r);
			$de = new DifferenceEngine($r->mTitle, $d['revlo']->mId, $d['revhi']->mId);
			$points = self::getPoints($r, $d, $de);
			$date = date("Y-m-d", wfTimestamp(TS_UNIX, $g['max_revtimestamp']));
			$wgOut->addHTML("<tr><td>{$g['user_text']}</td><td>{$g['edits']}</td><td>{$date}</td>");
			$wgOut->addHTML("<td><a href='#' onclick='return getPoints({$g['max_revid']});'>{$points}</a></td></tr>");
		}
		$wgOut->addHTML("</table>");

	
			
	}
}

