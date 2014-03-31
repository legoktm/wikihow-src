<?php

class Newarticleboost extends SpecialPage {

	/**
	 * A constant to change when JS/CSS has been updated so that a new
	 * version is pulled off the CDN.
	 */
	const REVISION = 4;

	public function __construct() {
		global $wgHooks;
		parent::__construct('Newarticleboost');
		$wgHooks['getToolStatus'][] = array('Misc::defineAsTool');
	}

	/**
	 * Returns the total number of New Articles waiting to be
	 * NAB'd.
	 */
	public function getNABCount(&$dbr) {
		// Disabled the templatelinks Inuse part of the query because it 
		// doesn't substantially affect the number (changes it by about 
		// 1.5%, currently), and it changes the query speed from 0.18s to
		// 2.39s (again, currently).
		//
		// LEFT JOIN templatelinks ON tl_from = page_id AND tl_title='Inuse'
		// AND tl_title IS NULL
		$one_hour_ago = wfTimestamp(TS_MW, time() - 60 * 60);
		$sql = "SELECT count(*) as C
				  FROM newarticlepatrol, page 
				  WHERE page_id = nap_page
				    AND page_is_redirect = 0 
				    AND nap_patrolled = 0
					AND nap_timestamp < '$one_hour_ago'";
		$res = $dbr->query($sql, __METHOD__);
		$row = $dbr->fetchObject($res);

		return $row->C;
	}

	/**
	 * Returns the id of the last NAB.
	 */
	public function getLastNAB(&$dbr) {
		$res = $dbr->select('newarticlepatrol',
			array('nap_user_ci', 'nap_timestamp_ci'),
			array('nap_patrolled' => 1),
			__METHOD__,
			array('ORDER BY' => 'nap_timestamp_ci DESC', 'LIMIT' => 1));

		$row = $dbr->fetchObject($res);
		$nabuser = array();
		$nabuser['id'] = $row->nap_user_ci;
		$nabuser['date'] = wfTimeAgo($row->nap_timestamp_ci);

		return $nabuser;
	}

	/**
	 * Gets the total number of articles patrolled by the given user after 
	 * the given timestamp.
	 */
	public function getUserNABCount(&$dbr, $userId, $starttimestamp) {
		$row = $dbr->selectField('newarticlepatrol',
			'count(*) as count',
			array('nap_patrolled' => 1,
				'nap_user_ci' => $userId,
				'nap_timestamp_ci > "' . $starttimestamp . '"'),
			__METHOD__);
		return $row;
	}

	private static function getNabbedCachekey($page) {
		return wfMemcKey('nabbed', $page);
	}

	/**
	 * Check whether or not a page ID has been nabbed.
	 * @param int $page
	 * @return boolean true iff it's been nabbed
	 */
	public function isNABbed(&$dbr, $page) {
		global $wgMemc;

		$cachekey = self::getNabbedCachekey($page);
		$val = $wgMemc->get($cachekey);
		if (is_string($val)) return (bool)$val;

		$nap_patrolled = $dbr->selectField(
			'newarticlepatrol',
			'nap_patrolled',
			array('nap_page' => $page),
			__METHOD__);

		if ($nap_patrolled === '0') {
			$boosted = 0;
		} else {
			//is == 1 or isn't in the table
			$boosted = 1;
		}
		
		$wgMemc->set($cachekey, $boosted, 5 * 60); // cache for 5 minutes

		return (bool)$boosted;
	}

	/**
	 * Used in community dashboard to find out the most recently nabbed 
	 * article.
	 */
	public function getHighestNAB(&$dbr, $period = '7 days ago') {
		$startdate = strtotime($period);
		$starttimestamp = date('YmdG', $startdate) . floor(date('i', $startdate) / 10) . '00000';

		$res = $dbr->select('logging', 
			array('*', 
				'count(*) as C',
				'MAX(log_timestamp) as recent_timestamp'),
			array("log_type" => 'nap',
				'log_timestamp > "' . $starttimestamp . '"'),
			__METHOD__,
			array("GROUP BY" => 'log_user',
				"ORDER BY"=>"C DESC",
				"LIMIT"=>1));
		$row = $dbr->fetchObject($res);

		$nabuser = array();
		$nabuser['id'] = $row->log_user;
		$nabuser['date'] = wfTimeAgo($row->recent_timestamp);

		return $nabuser;
	}

	/**
	 * Display the HTML for the NAB list. Displays all articles to be nabbed.
	 */
	private function displayNABList(&$dbw, &$dbr) {
		global $wgOut, $wgLang, $wgRequest;

		$wgOut->addHTML("<div class='minor_section'>");
		if ($this->can_newbie) {
			$btn_class = "style='margin-bottom: 10px;' class='button secondary buttonright'";
			if ($this->do_newbie) {
				$wgOut->addHTML("<a $btn_class href='/Special:Newarticleboost'>All articles</a><br/>");
			} else {
				$wgOut->addHTML("<a $btn_class href='/Special:Newarticleboost?newbie=1'>Newbie articles</a><br/>");
			}
		}

		list( $limit, $offset ) = wfCheckLimits();

		$patrolled_opt = $this->do_newbie ? '' : 'AND nap_patrolled = 0';
		$newbie_opt = $this->do_newbie ? 'AND nap_newbie = 1' : '';

		$one_hour_ago = wfTimestamp(TS_MW, time() - 60 * 60);

		$sql = "SELECT page_namespace, page_title, nap_timestamp, st_title,
				    nap_page
				  FROM newarticlepatrol, page 
				    LEFT JOIN templatelinks ON tl_from = page_id
				      AND tl_title = 'Inuse'
				    LEFT JOIN suggested_titles ON page_title = st_title
				  WHERE page_id = nap_page
				    AND page_is_redirect = 0
				    {$patrolled_opt}
				    AND nap_timestamp < '$one_hour_ago'
				    AND tl_title is NULL
				    {$newbie_opt}
				  GROUP BY page_title
				  ORDER BY nap_page DESC
				  LIMIT $offset, $limit";
		$res = $dbr->query($sql, __METHOD__);
		$wgOut->addHTML("<table width='100%' class='nablist section_text'><tr class='toprow'><td>#</td><td>Article</td><td title='Was this article from the Suggested Title list?'>ST?</td><td style='width:180px;'>Created</td></tr>");
		$index = 0;
		foreach ($res as $row) {
			$index++;
			$title = Title::makeTitle($row->page_namespace, $row->page_title);
			$s = SpecialPage::getTitleFor( 'Newarticleboost', $title->getPrefixedText() );
			$wgOut->addHTML("<tr><td>{$index}.<!--{$row->nap_page}--></td><td class='link'>" . $this->skin->makeLinkObj($s, $title->getText(), $this->do_newbie ? "newbie=1&page=" . $row->nap_page : "page=" . $row->nap_page) . "</td><td class='sugg'>");
			if ($row->st_title != null) {
				$wgOut->addHTML("<img src='" . wfGetPad('/skins/WikiHow/images/checkmark-nab.png') . "' height='16' width='16' alt='suggestion'/>");
			}
			$wgOut->addHTML("</td><td>");
			if ($row->nap_timestamp != '') {
				$dateStr = $wgLang->timeanddate($row->nap_timestamp, true);
				$wgOut->addHTML($dateStr);
			}
			$wgOut->addHTML("</td></tr>\n");
		}
		$wgOut->addHTML("</table>");
		$wgOut->addHTML("</div>");
		$dbr->freeResult($res);
	}

	/**
	 * If a user is nabbing an article, there are Skip/Cancel and Mark as
	 * Patrolled buttons at the buttom of the list of NAB actions.  When
	 * either of these buttons are pushed, this function processes the
	 * submitted form.
	 */
	private function doNABAction(&$dbw) {
		global $wgRequest, $wgOut, $wgUser;

		$err = false;
		$aid = $wgRequest->getVal('page', 0);
		$aid = intval($aid);

		if ($wgRequest->getVal('nap_submit', null) != null) {
			$title = Title::newFromID($aid);

			// MARK ARTICLE AS PATROLLED
			self::markNabbed($dbw, $aid, $wgUser->getId());

			if (!$title) {
				$wgOut->addHTML('Error: target page for NAB was not found');
				return;
			}

			// LOG ENTRY
			$params = array($aid);
			$log = new LogPage('nap', false);
			$log->addEntry( 'nap', $title, wfMsg('nap_logsummary', $title->getFullText()), $params );

			// ADD ANY TEMPLATES
			self::addTemplates($title);

			// Rising star actions FS RS
			$this->flagRisingStar($title);

			// DELETE ARTICLE IF PATROLLER WANTED THIS
			if ($wgRequest->getVal('delete', null) != null && $wgUser->isAllowed('delete')) {
				$article = new Article($title);
				$article->doDelete($wgRequest->getVal("delete_reason"));
			}

			// MOVE/RE-TITLE ARTICLE IF PATROLLER WANTED THIS
			if ($wgRequest->getVal('move', null) != null
				&& $wgUser->isAllowed('move'))
			{
				if ($wgRequest->getVal('move_newtitle', null) == null) {
					$wgOut->addHTML('Error: no target page title specified.');
					return;
				}
				$newTarget = $wgRequest->getVal('move_newtitle');
				$newTitle = Title::newFromText($newTarget);
				if (!$newTitle) {
					$wgOut->addHTML("Bad new title: $newTarget");
					return;
				}

				$ret  = $title->moveTo($newTitle);
				if (is_string($ret)) {
					$wgOut->addHTML("Renaming of the article failed: " . wfMsg($ret));
					$err = true;
				}

				// move the talk page if it exists
				$oldTitleTalkPage = $title->getTalkPage();
				if( $oldTitleTalkPage->exists() ) {
					$newTitleTalkPage = $newTitle->getTalkPage();
					$err = $oldTitleTalkPage->moveTo($newTitleTalkPage) === true;
				}

				$title = $newTitle;
			}

			// MARK ALL PREVIOUS EDITS AS PATROLLED IN RC
			$maxrcid = $wgRequest->getVal('maxrcid');
			if ($maxrcid) {
				$res = $dbw->select('recentchanges',
					'rc_id',
					array('rc_id<=' . $maxrcid,
						'rc_cur_id=' . $aid,
						'rc_patrolled=0'), 
					__METHOD__);
				while ($row = $dbw->fetchObject($res)) {
					RecentChange::markPatrolled( $row->rc_id );
					PatrolLog::record( $row->rc_id, false );
				}
				$dbw->freeResult($res);
			}

			wfRunHooks("NABArticleFinished", array($aid));
		}

		// GET NEXT UNPATROLLED ARTICLE
		if ($wgRequest->getVal('nap_skip') && $wgRequest->getVal('page') ) {
			// if article was skipped, clear the checkout of the 
			// article, so others can NAB it
			$dbw->update('newarticlepatrol',
				array('nap_user_co=0'),
				array("nap_page", $aid),
				__METHOD__);
		}

		$title = $this->getNextUnpatrolledArticle($dbw, $aid);
		if (!$title) {
			$wgOut->addHTML("Unable to get next id to patrol.");
			return;
		}

		$nap = SpecialPage::getTitleFor( 'Newarticleboost', $title->getPrefixedText() );
		$url = $nap->getFullURL() . ($this->do_newbie ? '?newbie=1' : '');
		if (!$err) {
			$wgOut->redirect($url);
		} else {
			$wgOut->addHTML("<br/><br/>Click <a href='{$nap->getFullURL()}'>here</a> to continue.");
		}
	}

	/**
	 * Mark an article as NAB'bed.
	 */
	private static function markNabbed(&$dbw, $aid, $userid) {
		global $wgMemc;

		$wgMemc->delete( self::getNabbedCachekey($aid) );

		$ts = wfTimestampNow();
		$dbw->update('newarticlepatrol',
			array('nap_timestamp_ci' => $ts,
				'nap_user_ci' => $userid,
				'nap_patrolled' => '1'),
			array('nap_page' => $aid),
			__METHOD__);

		wfRunHooks('NABMarkPatrolled', array($aid));
	}

	/**
	 * Look up the next NAB page in sequence.
	 * @param string $aid The article ID to look up
	 * @return Title the representing Title object or null if not found
	 */
	private function getNextUnpatrolledArticle(&$dbw, $aid, $recurse=true) {
		global $wgUser;

		$patrolled_opt = $this->do_newbie ? '' : 'AND nap_patrolled = 0';
		$newbie_opt = $this->do_newbie ? 'AND nap_newbie = 1' : '';

		$half_hour_ago = wfTimestamp(TS_MW, time() - 30 * 60);
		$sql = "SELECT page_title, nap_page
				  FROM newarticlepatrol, page
				  LEFT OUTER JOIN templatelinks ON page_id = tl_from
				    AND tl_title='Inuse'
				  WHERE nap_page < $aid
				    AND page_id = nap_page
				    AND page_is_redirect = 0
				    {$patrolled_opt} 
				    AND (nap_user_co = 0 OR nap_timestamp_co < '$half_hour_ago')
				    AND tl_title IS NULL
				    {$newbie_opt}
				  ORDER BY nap_page DESC
				  LIMIT 1";
		$res = $dbw->query($sql, __METHOD__);

		$id = 0;
		if (($row = $dbw->fetchObject($res)) != null) {
			$id = $row->nap_page;
			if ($id && $recurse) {
				$target = $row->page_title;
				// title contains bad character '?', try again!
				if (strstr($target, '?') !== false) {
					// HACK NOTE
					// Auto-mark as patrolled illegal article titles -- this
					// was done by hand before.  Not the best solution, but
					// at least it's automated.
					self::markNabbed($dbw, $id, $wgUser->getId());
					return $this->getNextUnpatrolledArticle($dbw, $aid, false);
				}
			}
		}

		return $id ? Title::newFromID($id) : null;
	}

	/**
	 * Check whether templates needed to be added to article (via posted
	 * request).  If there are, add them to wikitext.
	 */
	private static function addTemplates($title) {
		global $wgRequest, $wgOut;

		// Check if there are templates to add to article
		$formVars = $wgRequest->getValues();
		$newTemplates = '';
		$templatesArray = array();
		foreach ($formVars as $key => $value) {
			if (strpos($key, 'template') === 0 && $value == 'on') {
				$len = strlen('template');
				$i = substr($key, $len, 1);
				$template = substr($key, $len + 2, strlen($key) - $len - 2);
				$params = '';
				foreach ($formVars as $key2=>$value2) {
					if (strpos($key2, "param$i") === 0) {
						$params .= '|';
						$params .= $value2;
					}
				}
				if ($template == 'nfddup') {
					$template = 'nfd|dup';
				}
				$newTemplates .= '{{' . $template . $params . '}}';
				$templatesArray[] = $template;
			}
		}

		// Add templates if there were some to add
		if ($newTemplates) {
			$rev = Revision::newFromTitle($title);
			$article = new Article($title);
			$wikitext = $rev->getText();
			// were these templates were already added, maybe
			// a back button situation?
			if (strpos($wikitext, $newTemplates) === false) {
				$wikitext = "$newTemplates\n$wikitext";
				$watch = $title->userIsWatching(); // preserve watching just in case
				$updateResult = $article->updateArticle($wikitext, 
					wfMsg('nap_applyingtemplatessummary', implode(', ', $templatesArray)),
					false, $watch);
				if ($updateResult) {
					$wgOut->redirect('');
				}
			}
		}
	}

	/**
	 * NAB user flagged this article as a rising star in the Action section
	 * of NAB'ing an article.
	 */
	private function flagRisingStar($title) {
		global $wgLang, $wgUser, $wgRequest;

		if ($wgRequest->getVal('cb_risingstar', null) != "on") {
			return;
		}

		$dateStr = $wgLang->timeanddate(wfTimestampNow());

		$patrollerName = $wgUser->getName();
		$patrollerRealName = User::whoIsReal($wgUser->getID());
		if (!$patrollerRealName) {
			$patrollerRealName = $patrollerName;
		}

		// post to user talk page
		$contribUsername = $wgRequest->getVal('prevuser', '');
		if ($contribUsername) {
			$this->notifyUserOfRisingStar($title, $contribUsername);
		}

		// Give user a thumbs up. Set oldId to -1 as this should be the 
		// first revision
		//if (class_exists('ThumbsUp')) {
		//	ThumbsUp::thumbNAB(-1, $title->getLatestRevID(), $title->getArticleID());
		//}

		// post to article discussion page
		$wikitext = "";
		$article = "";

		$contribUser = new User();
		$contribUser->setName($contribUsername);
		$contribUserPage = $contribUser->getUserPage();
		$contribUserName = $contribUser->getName();
		$patrolUserPage = $wgUser->getUserPage();
		$patrolUserName = $wgUser->getName();

		$talkPage = $title->getTalkPage();
		$comment = '{{Rising-star-discussion-msg-2|[['.$contribUserPage.'|'.$contribUserName.']]|[['.$patrolUserPage.'|'.$patrolUserName.']]}}' . "\n";
		$formattedComment = wfMsg('postcomment_formatted_comment', $dateStr, $patrollerName, $patrollerRealName, $comment);

		wfRunHooks("MarkTitleAsRisingStar", array($title));

		if ($talkPage->getArticleId() > 0) {
			$rev = Revision::newFromTitle($talkPage);
			$wikitext = $rev->getText();
		}
		$article = new Article($talkPage);

		$wikitext = "$comment\n\n" . $wikitext;

		$watch = false;
		if ($wgUser->getID() > 0)
			$watch = $wgUser->isWatched($talkPage);

		if ($talkPage->getArticleId() > 0) {
			$article->updateArticle($wikitext, wfMsg('nab-rs-discussion-editsummary'), true, $watch);
		} else {
			$article->insertNewArticle($wikitext, wfMsg('nab-rs-discussion-editsummary'), true, $watch, false, false, true);
		}

		// add to fs feed page
		$wikitext = "";
		$article = "";
		$fsfeed = Title::newFromURL('wikiHow:Rising-star-feed');
		$rev = Revision::newFromTitle($fsfeed);
		$article = new Article($fsfeed);
		$wikitext = $rev->getText();

		$watch = false;
		if ($wgUser->getID() > 0) {
			$watch = $wgUser->isWatched($title->getTalkPage());
		}

		$wikitext .= "\n".  date('==Y-m-d==') . "\n" . $title->getFullURL() . "\n";
		$article->updateArticle($wikitext, wfMsg('nab-rs-feed-editsummary'), true, $watch);

	}

	private function displayNABConsole(&$dbw, &$dbr, $target) {
		global $wgOut, $wgRequest, $wgUser, $wgParser;

		$not_found = false;
		$title = Title::newFromURL($target);
		if (!$title || !$title->exists()) {
			$articleName = $title ? $title->getFullText() : $target;
			$wgOut->addHTML("<p>Error: Article &ldquo;{$articleName}&rdquo; not found. Return to <a href='/Special:Newarticleboost'>New Article Boost</a> instead.</p>");
			$not_found = true;
		}

		if (!$not_found) {
			$rev = Revision::newFromTitle($title);
			if (!$rev) {
				$wgOut->addHTML("<p>Error: No revision for &ldquo;{$title->getFullText()}&rdquo;. Return to <a href='/Special:Newarticleboost'>New Article Boost</a> instead.</p>");
				$not_found = true;
			}
		}

		if (!$not_found) {
			$in_nab = $dbr->selectField('newarticlepatrol', 'count(*)', array('nap_page'=>$title->getArticleID()), __METHOD__) > 0;
			if (!$in_nab) {
				$wgOut->addHTML("<p>Error: This article is not in the NAB list.</p>");
				$not_found = true;
			}
		}

		if ($not_found) {
			$pageid = $wgRequest->getVal('page');
			if (strpos($target, ':') !== false && $pageid) {
				$wgOut->addHTML('<p>We can to try to <a href="/Special:NABClean/' . $pageid . '">delete this title</a> if you know this title exists in NAB yet is likely bad data.</p>');
			}
			return;
		}

		$locked = false;

		$min_timestamp = $dbr->selectField("revision", "min(rev_timestamp)", "rev_page=" . $title->getArticleId(), __METHOD__);
		$first_user = $dbr->selectField("revision", "rev_user_text", array("rev_page=" . $title->getArticleId(), 'rev_timestamp' => $min_timestamp), __METHOD__);
		$first_user_id = $dbr->selectField("revision", "rev_user", array("rev_page=" . $title->getArticleId(), 'rev_timestamp' => $min_timestamp), __METHOD__);
		$user = new User();
		if ($first_user_id) {
			$user->setId($first_user_id);
			$user->loadFromDatabase();
		} else {
			$user->setName($first_user);
		}

		$user_talk = $user->getTalkPage();
		$ut_id = $user_talk->getArticleID();
		$display_name = $user->getRealName() ? $user->getRealName() : $user->getName();

		$wgOut->setPageTitle(wfMsg('nap_title', $title->getFullText()));
		$count = $dbr->selectField('suggested_titles', array('count(*)'), array('st_title' => $title->getDBKey()), __METHOD__);
		$extra = $count > 0 ? ' - from Suggested Titles database' : '';
		$wgOut->addWikiText(wfMsg('nap_writtenby', $user->getName(), $display_name, $extra));

		$wgOut->addHTML( wfMsgExt('nap_quicklinks', 'parseinline', $this->me->getFullText() . "/" . $title->getFullText()) );

		/// CHECK TO SEE IF ARTICLE IS LOCKED OR ALREADY PATROLLED
		$aid = $title->getArticleID();
		$half_hour_ago = wfTimestamp(TS_MW, time() - 30 * 60);

		$patrolled = $dbr->selectField('newarticlepatrol', 'nap_patrolled', array("nap_page=$aid"), __METHOD__);
		if ($patrolled) {
			$locked = true;
			$wgOut->addHTML(wfMsgExt("nap_patrolled", 'parse'));
		} else {
			$user_co = $dbr->selectField('newarticlepatrol', 'nap_user_co', array("nap_page=$aid", "nap_timestamp_co > '$half_hour_ago'"), __METHOD__);
			if ($user_co != '' && $user_co != 0 && $user_co != $wgUser->getId()) {
				$x = User::newFromId($user_co);
				$wgOut->addHTML(wfMsgExt("nap_usercheckedout", 'parse', $x->getName()));
				$locked = true;
			} else {
				// CHECK OUT THE ARTICLE TO THIS USER
				$ts = wfTimestampNow();
				$dbw->update('newarticlepatrol', array('nap_timestamp_co' => $ts, 'nap_user_co' => $wgUser->getId()), array("nap_page = $aid"), __METHOD__);
			}
		}

		$expandSpan = '<span class="nap_expand">&#9660;</span>';
		$externalLinkImg = '<img src="' . wfGetPad('/skins/common/images/external.png') . '"/>';

		/// SIMILAR RESULT
		$wgOut->addHTML("<div class='nap_section minor_section'>");
		$wgOut->addHTML("<h2 class='nap_header'>$expandSpan " . wfMsg('nap_similarresults') . "</h2>");
		$wgOut->addHTML("<div class='nap_body section_text'>");
		$count = 0;
		$l = new LSearch();
		$hits  = $l->googleSearchResultTitles($title->getFullText(), 0, 5);
		if (sizeof($hits) > 0) {
			$html = "";
			foreach ($hits as $hit) {
				$t1 = $hit;
				$id = rand(0, 500);
				if ($t1 == null
					|| $t1->getFullURL() == $title->getFullURL()
					|| $t1->getNamespace() != NS_MAIN
					|| !$t1->exists())
				{
					continue;
				}
				$safe_title = htmlspecialchars(str_replace("'", "&#39;", $t1->getText()));

				$html .= "<tr><td>"
					. $this->skin->makeLinkObj($t1, wfMsg('howto', $t1->getText() ))
					. "</td><td style='text-align:right; width: 200px;'>[<a href='#action' onclick='nap_Merge(\"{$safe_title}\");'>" . wfMsg('nap_merge') . "</a>] "
					. " [<a href='#action' onclick='javascript:nap_Dupe(\"{$safe_title}\");'>" . wfMsg('nap_duplicate') . "</a>] "
					. " <span id='mr_$id'>[<a onclick='javascript:nap_MarkRelated($id, {$t1->getArticleID()}, {$title->getArticleID()});'>" . wfMsg('nap_related') . "</a>]</span> "
					. "</td></tr>";
				$count++;
			}
		}
		if ($count == 0) {
			$wgOut->addHTML(wfMsg('nap_no-related-topics'));
		} else {
			$wgOut->addHTML(wfMsg('nap_already-related-topics') . "<table style='width:100%;'>$html</table>");
		}

		$wgOut->addHTML(wfMsg('nap_othersearches', urlencode($title->getFullText()) ));
		$wgOut->addHTML("</div>");
		$wgOut->addHTML("</div>");

		/// COPYRIGHT CHECKER
		$cc_check = SpecialPage::getTitleFor( 'Copyrightchecker', $title->getText() );
		$wgOut->addHTML("<script type='text/javascript'>window.onload = nap_cCheck; var nap_cc_url = \"{$cc_check->getFullURL()}\";</script>");
		$wgOut->addHTML("<div class='nap_section minor_section'>");
		$wgOut->addHTML("<h2 class='nap_header'>$expandSpan " . wfMsg('nap_copyrightchecker') . "</h2>");
		$wgOut->addHTML("<div class='nap_body section_text'>");
		$wgOut->addHTML("<div id='nap_copyrightresults'><center><img src='/extensions/wikihow/rotate.gif' alt='loading...'/></center></div>");
		$wgOut->addHTML("<center><input type='button' class='button primary' onclick='nap_cCheck();' value='Check'/></center>");
		$wgOut->addHTML("</div>");
		$wgOut->addHTML("</div>");

		/// ARTICLE PREVIEW
		$editUrl =  Title::makeTitle(NS_SPECIAL, "QuickEdit")->getFullURL() . "?type=editform&target=" . urlencode($title->getFullText()) . "&fromnab=1";
		$wgOut->addHTML("<div class='nap_section minor_section'>");
		$wgOut->addHTML("<a name='article' id='anchor-article'></a>");
		$wgOut->addHTML("<h2 class='nap_header'>$expandSpan " . wfMsg('nap_articlepreview')
			. " - <a href=\"{$title->getFullURL()}\" target=\"new\">" . wfMsg('nap_articlelinktext')."</a> $externalLinkImg"
			. " - <a href=\"{$title->getEditURL()}\" target=\"new\">" . wfMsg('edit')."</a> $externalLinkImg"
			. " - <a href=\"{$title->getFullURL()}?action=history\" target=\"new\">" . wfMsg('history')."</a> $externalLinkImg"
			. " - <a href=\"{$title->getTalkPage()->getFullURL()}\" target=\"new\">" . wfMsg('discuss')."</a> $externalLinkImg"
			. "</h2>");
		$wgOut->addHTML("<div class='nap_body section_text'>");
		$wgOut->addHTML("<div id='article_contents' ondblclick='nap_editClick(\"$editUrl\");'>");
		$popts = $wgOut->parserOptions();
		$popts->setTidy(true);
		// $parserOutput = $wgOut->parse($rev->getText(), $title, $popts);
		$output = $wgParser->parse($rev->getText(), $title, $popts);
		$parserOutput = $output->getText();
		$magic = WikihowArticleHTML::grabTheMagic($rev->getText());
		$html = WikihowArticleHTML::processArticleHTML($parserOutput, array('no-ads' => true, 'ns' => $title->getNamespace(), 'magic-word' => $magic));
		$wgOut->addHTML($html);
		$wgOut->addHTML("</div>");
		$wgOut->addHTML("<center><input id='editButton' type='button' class='button primary' name='wpEdit' value='" . wfMsg('edit') .
			"' onclick='nap_editClick(\"$editUrl\");'/></center>");
		$wgOut->addHTML("</div>");
		$wgOut->addHTML("</div>");

		$wgOut->addHTML('<div style="clear: both;"></div>');

		/// DISCUSSION PREVIEW
		$talkPage = $title->getTalkPage();
		$wgOut->addHTML("<div class='nap_section minor_section'>");
		$wgOut->addHTML("<a name='talk' id='anchor-talk'></a>");
		$wgOut->addHTML("<h2 class='nap_header'>$expandSpan " . wfMsg('nap_discussion')
			. " - <a href=\"{$talkPage->getFullURL()}\" target=\"new\">" . wfMsg('nap_articlelinktext')."</a> $externalLinkImg"
			. "</h2>");
		$wgOut->addHTML("<div class='nap_body section_text'>");
		$wgOut->addHTML("<div id='disc_page'>");
		if ($talkPage->getArticleID() > 0) {
			$rp = Revision::newFromTitle($talkPage);
			$wgOut->addHTML($wgOut->parse($rp->getText()));
		} else {
			$wgOut->addHTML(wfMsg('nap_discussionnocontent'));
		}
		$wgOut->addHTML(PostComment::getForm(true, $talkPage, true));
		$wgOut->addHTML("</div>");
		$wgOut->addHTML("</div>");
		$wgOut->addHTML("</div>");

		/// USER INFORMATION
		$wgOut->addHTML("<div class='nap_section minor_section'>");
		$wgOut->addHTML("<a name='user' id='anchor-user'></a>");
		$used_templates = array();
		if ($ut_id > 0) {
			$res = $dbr->select('templatelinks', array('tl_title'), array('tl_from=' . $ut_id), __METHOD__);
			while($row = $dbr->fetchObject($res)) {
				$used_templates[] = strtolower($row->tl_title);
			}
			$dbr->freeResult($res);
		}
		$wgOut->addHTML("<h2 class='nap_header'>$expandSpan " . wfMsg('nap_userinfo')
			. " - <a href=\"{$user_talk->getFullURL()}\" target=\"new\">" . wfMsg('nap_articlelinktext')."</a> $externalLinkImg"
			. "</h2>");
		$wgOut->addHTML("<div class='nap_body section_text'>");
		$contribs = SpecialPage::getTitleFor( 'Contributions', $user->getName() );

		$regDateTxt = "";
		if ($user->getRegistration() > 0) {
			preg_match('/^(\d{4})(\d\d)(\d\d)(\d\d)(\d\d)(\d\d)$/D',$user->getRegistration(),$da);
			$uts = gmmktime((int)$da[4],(int)$da[5],(int)$da[6],
				(int)$da[2],(int)$da[3],(int)$da[1]);
			$regdate = gmdate('F j, Y', $uts);
			$regDateTxt = wfMsg('nap_regdatetext', $regdate) . ' ';
		}

		$key = 'nap_userinfodetails_anon';
		if ($user->getID() != 0) {
			$key = 'nap_userinfodetails';
		}
		$wgOut->addWikiText(
			wfMsg($key,
				$user->getName(),
				number_format(WikihowUser::getAuthorStats($first_user), 0, "", ","),
				$title->getFullText(),
				$regDateTxt)
		);

		if (WikihowUser::getAuthorStats($first_user) < 50) {
			if ($user_talk->getArticleId() == 0) {
				$wgOut->addHTML(wfMsg('nap_newwithouttalkpage'));
			} else {
				$rp = Revision::newFromTitle($user_talk);
				$xtra = "";
				if (strpos($_SERVER['HTTP_USER_AGENT'], "MSIE 8.0") === false)
					$xtra = "max-height: 300px; overflow: scroll;";
				$output = $wgParser->parse($rp->getText(), $user_talk, $popts);
				$parserOutput = $output->getText();
				$wgOut->addHTML("<div style='border: 1px solid #eee; {$xtra}'>" . $parserOutput . "</div>");
			}
		}

		if ($user_talk->getArticleId() != 0
			&& sizeof($used_templates) > 0)
		{
			$wgOut->addHTML('<br />' . wfMsg('nap_usertalktemplates', implode($used_templates, ", ")));
		}

		$wgOut->addHTML(PostComment::getForm(true, $user_talk, true));
		$wgOut->addHTML("</div>");
		$wgOut->addHTML("</div>");

		/// ACTION INFORMATION
		$maxrcid = $dbr->selectField('recentchanges', 'max(rc_id)', array('rc_cur_id=' . $aid), __METHOD__);
		$wgOut->addHTML("<div class='nap_section minor_section'>");
		$wgOut->addHTML("<a name='action' id='anchor-action'></a>");
		$wgOut->addHTML("<h2 class='nap_header'> " . wfMsg('nap_action') . "</h2>");
		$wgOut->addHTML("<div class='nap_body section_text'>");
		$wgOut->addHTML("<form action='{$this->me->getFullURL()}' name='nap_form' method='post' onsubmit='return checkNap();'>");
		$wgOut->addHTML("<input type='hidden' name='target' value='" . htmlspecialchars($title->getText()) . "'/>");
		$wgOut->addHTML("<input type='hidden' name='page' value='{$aid}'/>");
		$wgOut->addHTML("<input type='hidden' name='newbie' value='". $wgRequest->getVal('newbie', 0) . "'/>");
		$wgOut->addHTML("<input type='hidden' name='prevuser' value='" . $user->getName() . "'/>");
		$wgOut->addHTML("<input type='hidden' name='maxrcid' value='{$maxrcid}'/>");
		$wgOut->addHTML("<table>");
		$suggested = $dbr->selectField('suggested_titles', 'count(*)', array('st_title'=>$title->getDBKey()), __METHOD__);
		if ($suggested > 0) {
			$wgOut->addHTML("<tr><td valign='top'>" . wfMsg('nap_suggested_warning') . "</td></tr>");
		}

		$wgOut->addHTML("</table>");
		$wgOut->addHTML(wfMsg('nap_actiontemplates'));
		if ($wgUser->isAllowed( 'delete' )  || $wgUser->isAllowed( 'move' ) ) {
			$wgOut->addHTML(wfMsg('nap_actionmovedeleteheader'));
			if ($wgUser->isAllowed( 'move' )) {
				$wgOut->addHTML(wfMsg('nap_actionmove', htmlspecialchars($title->getText())));
			}
			if ($wgUser->isAllowed( 'delete' )) {
				$wgOut->addHTML(wfMsg('nap_actiondelete'));
			}
		}

		// BUTTONS
		$wgOut->addHTML("<input type='submit' value='" . wfMsg('nap_skip') . "' id='nap_skip' name='nap_skip' class='button secondary' />");
		if (!$locked)
			$wgOut->addHTML("<input type='submit' value='" . wfMsg('nap_markaspatrolled') . "' id='nap_submit' name='nap_submit' class='button primary' />");
		$wgOut->addHTML("</form>");
		$wgOut->addHTML("</div>");
		$wgOut->addHTML("</div>");

		$wgOut->addHTML(<<<END
<script type='text/javascript'>

var tabindex = 1;
for(i = 0; i < document.forms.length; i++) {
	for (j = 0; j < document.forms[i].elements.length; j++) {
		switch (document.forms[i].elements[j].type) {
			case 'submit':
			case 'text':
			case 'textarea':
			case 'checkbox':
			case 'button':
				document.forms[i].elements[j].tabIndex = tabindex++;
				break;
			default:
				break;
		}
	}
}

// Handlers for expand/contract arrows
(function ($) {
$('.nap_expand').click(function() {
	var thisSpan = $(this);
	var body = thisSpan.parent().next();
	var footer = body.next();
	if (body.css('display') != 'none') {
		footer.hide();
		body.css('overflow', 'hidden');
		var oldHeight = body.height();
		body.animate(
			{ height: 0 },
			200,
			'swing',
			function () {
				thisSpan.html('&#9658;');
				body
					.hide()
					.height(oldHeight);
			});
	} else {
		var oldHeight = body.height();
		body.height(0);
		body.animate(
			{ height: oldHeight },
			200,
			'swing',
			function () {
				thisSpan.html('&#9660;');
				footer.show();
				body.css('overflow', 'visible');
			});
	}
	return false;
});
})(jQuery);

</script>

END
);


	}

	/**
	 * Special page class entry point
	 */
	public function execute($par) {
		global $wgRequest, $wgUser, $wgOut;

		wfLoadExtensionMessages('Newarticleboost');
		$target = isset($par) ? $par : $wgRequest->getVal('target');

		if ($wgUser->isBlocked()) {
			$wgOut->blockedPage();
			return;
		}

		// set tidy on to avoid IE8 complaining about browser compatibility
		$opts = $wgOut->parserOptions();
		$opts->setTidy(true);
		$wgOut->parserOptions($opts);
		$wgOut->addMeta('X-UA-Compatible', 'IE=8');

		if ( !in_array( 'newarticlepatrol', $wgUser->getRights() ) ) {
			$wgOut->setArticleRelated(false);
			$wgOut->setRobotpolicy('noindex,nofollow');
			$wgOut->showErrorPage('nosuchspecialpage', 'nospecialpagetext');
			return;
		}

		$dbw = wfGetDB(DB_MASTER);
		$dbr = wfGetDB(DB_SLAVE);
		$wgOut->setHTMLTitle(wfMsg('nap_page_title'));
		$this->me = Title::makeTitle(NS_SPECIAL, "Newarticleboost");
		$this->can_newbie = in_array( 'newbienap', $wgUser->getRights() );
		$this->do_newbie = $wgRequest->getVal("newbie") == 1
			&& $this->can_newbie;

		$this->skin = $wgUser->getSkin();
		$wgOut->addHTML('<style type="text/css" media="all">/*<![CDATA[*/ @import "' . wfGetPad('/extensions/min/f/extensions/wikihow/nab/newarticleboost.css&' . self::REVISION) . '"; /*]]>*/</style>');
		$wgOut->addHTML('<script type="text/javascript">
			var gAutoSummaryText = "' . wfMsg('nap_autosummary') . '";
			var gChangesLost = "'.wfMsg('all-changes-lost').'";
			</script>');
		$wgOut->addHTML('<script type="text/javascript" src="' . wfGetPad('/extensions/min/?f=skins/common/clientscript.js,extensions/wikihow/nab/newarticleboost.js&' . self::REVISION) . '"></script>');

		if (!$target) {
			$this->displayNABList($dbw, $dbr);
		} elseif ($wgRequest->wasPosted()) {
			$this->doNABAction($dbw);
		} else {
			$this->displayNABConsole($dbw, $dbr, $target);
		}
	}

	/**
	 * Place the Rising-star-usertalk-msg on the user's talk page 
	 * and emails the user
	 */
	public function notifyUserOfRisingStar($title, $name) {
		global $wgUser, $wgLang;
		$user = $wgUser->getName();
		$real_name = User::whoIsReal($wgUser->getID());
		if ($real_name == "") {
			$real_name = $user;
		}

		$dateStr = $wgLang->timeanddate(wfTimestampNow());
		$wikitext = "";
		$article = "";

		$userObj = new User();
		$userObj->setName($name);
		$user_talk = $userObj->getTalkPage();

		$comment = '{{subst:Rising-star-usertalk-msg|[['.$title->getText().']]}}' . "\n";
		$formattedComment = wfMsg('postcomment_formatted_comment', $dateStr, $user, $real_name, $comment);

		if ($user_talk->getArticleId() > 0) {
			$rev = Revision::newFromTitle($user_talk);
			$wikitext = $rev->getText();
		}
		$article = new Article($user_talk);

		$wikitext .= "\n\n$formattedComment\n\n";

		$article->doEdit($wikitext, wfMsg('nab-rs-usertalk-editsummary'));

		// Send author email notification
		AuthorEmailNotification::notifyRisingStar($title->getText(), $name, $real_name, $user);
	}

}

/**
 * AJAX server-side code for the NAB status check submission from NAB.
 */
class NABStatus extends SpecialPage {

	public function __construct() {
		parent::__construct('NABStatus');
	}

	public function execute($par) {
		global $wgTitle, $wgOut, $wgRequest, $wgUser;

		$target = isset($par) ? $par : $wgRequest->getVal('target');

		$wgOut->setHTMLTitle('New Article Boost Status - wikiHow');

		$sk = $wgUser->getSkin();
		$dbr = wfGetDB(DB_SLAVE);

		$wgOut->addHTML('<style type="text/css" media="all">/*<![CDATA[*/ @import "' . wfGetPad('/extensions/min/f/extensions/wikihow/nab/newarticleboost.css&' . Newarticleboost::REVISION) . '"; /*]]>*/</style>');
		$wgOut->addHTML(wfMsg('nap_statusinfo'));
		$wgOut->addHTML("<br/><center>");
		$days = $wgRequest->getVal('days', 1);
		if ($days == 1) {
			$wgOut->addHTML(" [". wfMsg('nap_last1day') . "] ");
			$wgOut->addHTML(" [" . $sk->makeLinkObj($wgTitle, wfMsg('nap_last7day'), "days=7") . "] ");
			$wgOut->addHTML(" [" . $sk->makeLinkObj($wgTitle, wfMsg('nap_last30day'), "days=30") . "] ");
		} else if ($days == 7) {
			$wgOut->addHTML(" [" . $sk->makeLinkObj($wgTitle, wfMsg('nap_last1day'), "days=1") . "] ");
			$wgOut->addHTML(" [" . wfMsg('nap_last7day') . "] ");
			$wgOut->addHTML(" [" . $sk->makeLinkObj($wgTitle, wfMsg('nap_last30day'), "days=30") . "] ");
		} else if ($days == 30) {
			$wgOut->addHTML(" [" . $sk->makeLinkObj($wgTitle, wfMsg('nap_last1day'), "days=1") . "] ");
			$wgOut->addHTML(" [" . $sk->makeLinkObj($wgTitle, wfMsg('nap_last7day'), "days=7") . "] ");
			$wgOut->addHTML(" [" . wfMsg('nap_last30day') . "] ");

		}

		$days_ago = wfTimestamp(TS_MW, time() - 60 * 60 * 24 * $days);
		$boosted = $dbr->selectField(array('newarticlepatrol', 'page'),
			array('count(*)'),
			array('page_id=nap_page', 'page_is_redirect=0', 'nap_patrolled=1', "nap_timestamp_ci > '$days_ago'"),
			__METHOD__);
		$newarticles = $dbr->selectField(array('newarticlepatrol'),
			array('count(*)'),
			array("nap_timestamp > '$days_ago'"),
			__METHOD__);
		$na_boosted = $dbr->selectField(array('newarticlepatrol'),
			array('count(*)'),
			array("nap_timestamp > '$days_ago'", "nap_patrolled"=>1),
			__METHOD__);

		$boosted = number_format($boosted, 0, "", ",");
		$newarticles = number_format($newarticles, 0, "", ",");
		$na_boosted = number_format($na_boosted, 0, "", ",");
		$per_boosted = $newarticles > 0 ? number_format($na_boosted/ $newarticles * 100, 2) : 0;
		$wgOut->addHTML("<br/><br/><div>
				<table width='50%' align='center' class='status'>
					<tr>
						<td>" . wfMsg('nap_totalboosted') . "</td>
						<td>$boosted</td>
					</tr>
					<tr>
						<td>" . wfMsg('nap_numnewboosted') . "</td>
						<td>$na_boosted</td>
					</tr>
					 <tr>
						<td>" . wfMsg('nap_numarticles') . "</td>
						<td>$newarticles</td>
					</tr>
					<tr>
						<td>" . wfMsg('nap_perofnewbosted') . "</td>
						<td>$per_boosted%</td>
					</tr>
				</table>
				</div>");
		$wgOut->addHTML("</center>");

		$wgOut->addHTML("<br/>" . wfMsg('nap_userswhoboosted') . "<br/><br/><center>
			<table width='500px' align='center' class='status'>" );

		$total = $dbr->selectField('logging', 'count(*)',  array ('log_type'=>'nap', "log_timestamp>'$days_ago'"), __METHOD__);

		$sql = "SELECT log_user, count(*) AS C
				  FROM logging WHERE log_type = 'nap'
				    AND log_timestamp > '$days_ago'
				  GROUP BY log_user
				  ORDER BY C DESC
				  LIMIT 20";

		$res = $dbr->query($sql, __METHOD__);
		$index = 1;
		$wgOut->addHTML("<tr>
			<td></td>
				<td>User</td>
				<td  align='right'>" . wfMsg('nap_numboosted') . "</td>
				<td align='right'>" . wfMsg('nap_perboosted') . "</td>
				</tr>");
		while ( ($row = $dbr->fetchObject($res)) != null) {
			$user = User::newFromID($row->log_user);
			$percent = $total == 0 ? "0" : number_format($row->C / $total * 100, 0);
			$count = number_format($row->C, 0, "", ',');
			$log = $sk->makeLinkObj(Title::makeTitle( NS_SPECIAL, 'Log'), $count, 'type=nap&user=' .  $user->getName());
			$wgOut->addHTML("<tr>
				<td>$index</td>
				<td>" . $sk->makeLinkObj($user->getUserPage(), $user->getName()) . "</td>
				<td  align='right'>{$log}</td>
				<td align='right'> $percent % </td>
				</tr>
			");
			$index++;
		}
		$dbr->freeResult($res);
		$wgOut->addHTML("</table></center>");

	}

}

/**
 * AJAX server-side code for the Copyright check submission from NAB.
 */
class Copyrightchecker extends UnlistedSpecialPage {

	public function __construct() {
		parent::__construct('Copyrightchecker', '', false, true);
	}

	public function execute($par) {
		global $wgRequest, $wgOut, $IP;
		$target = isset($par) ? $par : $wgRequest->getVal('target');

		if (is_null($target)) {
			$wgOut->addHTML("<b>Error:</b> No parameter passed to Copyrightchecker.");
			return;
		}

		$query = $wgRequest->getVal('query');

		wfLoadExtensionMessages('Newarticleboost');

		$title = Title::newFromURL($target);
		$rev = Revision::newFromTitle($title);
		$wgOut->setArticleBodyOnly(true);

		if (!$query) {
			// Get the text and strip the steps header, any templates, 
			// flatten it to HTML and strip the tags
			if (!$rev) {
				echo "Revision for article not found by copyright check";
				return;
			}
			$wikitext = $rev->getText();
			$wikitext = preg_replace("/^==[ ]+" . wfMsg('steps') . "[ ]+==/mix", "", $wikitext);
			$wikitext = preg_replace("/{{[^}]*}}/im", "", $wikitext);
			$wikitext = WikihowArticleEditor::textify($wikitext);
			$parts = preg_split("@\.@", $wikitext);
			shuffle($parts);
			$queries = array();
			foreach ($parts as $p) {
				$p = trim($p);
				$words = split(" ", $p);
				if (sizeof($words) > 5) {
					if (sizeof($words) >  15) {
						$words = array_slice($words, 0, 15);
						$p = implode(" ", $words);
					}
					$queries[] = $p;
					if (sizeof($queries) == 2) {
						break;
					}
				}
			}
			$query = '"' . implode('" AND "',  $queries) . '"';
		}

		require_once(dirname(__FILE__) . '/GoogleAjaxSearch.class.php');
		$results = GoogleAjaxSearch::getGlobalWebResults($query, 8, null);

		// Filter out results from wikihow.com
		if (sizeof($results) > 0 && is_array($results)) {
			$newresults = array();
			for ($i = 0; $i < sizeof($results); $i++) {
				if (strpos($results[$i]['url'], "http://www.wikihow.com/") === 0
					|| strpos($results[$i]['url'], "http://m.wikihow.com/") === 0)
				{
					continue;
				}
				$newresults[] = $results[$i];
			}
			$results = $newresults;
		}

		// Process results
		if (sizeof($results) > 0 && is_array($results)) {
			$wgOut->addHTML(wfMsg("nap_copyrightlist", $query) . "<table width='100%'>");
			for ($i = 0; $i < 3 && $i < sizeof($results); $i++) {
				$match = $results[$i];
				$c = json_decode($match['content']);
				$wgOut->addHTML("<tr><td><a href='{$match['url']}' target='new'>{$match['title']}</a>
					<br/>$c
					<br/><font size='-2'>{$match['url']}</font></td><td style='width: 100px; text-align: right; vertical-align: top;'><a href='' onclick='return nap_copyVio(\"" . htmlspecialchars($match['url']) . "\");'>Copyvio</a></td></tr>");
			}
			$wgOut->addHTML("</table>");
		} else {
			$wgOut->addHTML(wfMsg('nap_nocopyrightfound', $query));
		}
	}
}


/**
 * AJAX server-side code for the "mark related" functionality from NAB.
 */
class Markrelated extends UnlistedSpecialPage {

	public function __construct() {
		parent::__construct('Markrelated', '', false, true);
	}

	// adds a related wikihow to the article t1 to t2
	public function addRelated($t1, $t2, $summary = "Adding related wikihow from NAB", $top = false, $linkedtext = null) {

#echo "putting a link in '{$t1->getText()}' to '{$t2->getText()}'\n\n";
		if ($linkedtext)
			$link = "*[[{$t2->getText()}|" . wfMsg('howto', $linkedtext) . "]]";
		else
			$link = "*[[{$t2->getText()}|" . wfMsg('howto', $t2->getText()) . "]]";
		$article = new Article($t1);
		$wikitext = $article->getContent(true);
		for ($i = 0; $i < 30; $i++) {
			$s = $article->getSection($wikitext, $i);
			if (preg_match("@^==[ ]*" . wfMsg('relatedwikihows') . "@m", $s)) {
				if (preg_match("@{$t2->getText()}@m", $s)) {
					$found = true;
					break;
				}
				if ($top)
					$s = preg_replace("@==\n@", "==\n$link\n", $s);
				else
					$s .= "\n{$link}\n";
				$wikitext = $article->replaceSection($i, $s);
				$found = true;
				break;
			} else if (preg_match("@^==[ ]*(" . wfMsg('sources') . ")@m", $s)) {
				// we have gone too far
				$s = "\n== " . wfMsg('relatedwikihows') . " ==\n{$link}\n\n" . $s;
				$wikitext = $article->replaceSection($i, $s);
				$found = true;
				break;
			}
		}
		if (!$found) {
			$wikitext .= "\n\n== " . wfMsg('relatedwikihows') . " ==\n{$link}\n";
		}
		if (!$article->doEdit($wikitext, $summary))
			echo "Didn't save\n";
	}

	public function execute($par) {
		global $wgRequest, $wgOut, $wgUser;

		if ( !in_array( 'newarticlepatrol', $wgUser->getRights() ) ) {
			$wgOut->setArticleRelated( false );
			$wgOut->setRobotpolicy( 'noindex,nofollow' );
			$wgOut->showErrorPage( 'nosuchspecialpage', 'nospecialpagetext' );
			return;
		}
		$wgOut->disable();
		$p1 = $wgRequest->getVal('p1');
		$p2 = $wgRequest->getVal('p2');
		$t1 = Title::newFromID($p1);
		$t2 = Title::newFromID($p2);
		$this->addRelated($t1, $t2);
		$this->addRelated($t2, $t1);
	}

}

/**
 * AJAX server-side code for the "mark related" functionality from NAB.
 */
class NABClean extends UnlistedSpecialPage {

	public function __construct() {
		parent::__construct('NABClean', '', false, true);
	}

	public function execute($par) {
		global $wgRequest, $wgOut, $wgMemc;
		$target = isset($par) ? $par : $wgRequest->getVal('page');
		$dbw = wfGetDB(DB_MASTER);
		$in_nab = $dbw->selectField('newarticlepatrol', 'count(*)', array('nap_page' => $target), __METHOD__);
		if ($in_nab) {
			$dbw->delete('newarticlepatrol', array('nap_page' => $target), __METHOD__);
			$wgMemc->delete( self::getNabbedCachekey($target) );
			$wgOut->addHTML('<p>Deleted from NAB.</p>');
		} else {
			$wgOut->addHTML('<p>Could not find in NAB!</p>');
		}
		$wgOut->addHTML('<p>Return to <a href="/Special:Newarticleboost">the NAB list</a>.</p>');
	}

}

