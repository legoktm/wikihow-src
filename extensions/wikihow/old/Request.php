<?php

class Request {
	function notifyRequest($titleObj, $actualTitleObj) {
		global $wgUser, $wgServer, $wgScriptPath, $wgScript;

		$dbkey = $titleObj->getDBKey();
		if ($actualTitleObj) {
			$dbkey = $actualTitleObj->getDBKey();
		}

		$author_name = "";
		if ($wgUser->getID() > 0) {
			$author_name = $wgUser->getRealName();
			if (!$author_name) {
				$author_name = $wgUser->getName();
			}
		}
		$subject = wfMsg('howto', $titleObj->getText());
		$text = wfMsg('howto', $titleObj->getText()) . "\n\n";

		if ($wgUser->getID() > 0) {
			$text = wfMsg('request_answered_email_by_logged_in_user',
				$titleObj->getText(),
				$wgServer . $wgScriptPath . "/" . $titleObj->getDBKey() ,
				$author_name,
				$wgServer . $wgScriptPath . "/User_talk:" . $wgUser->getName());
		} else {
			$text = wfMsg('request_answered_email',
				$titleObj->getText(),
				$wgServer . $wgScriptPath . "/" . $titleObj->getDBKey());
		}

		$dbr = wfGetDB(DB_READ);
		$res = $dbr->select( array('page', 'revision', 'user'),
			array('user_name', 'user_real_name', 'user_email'),
			array('rev_user=user_id',
				'page_namespace=' . NS_ARTICLE_REQUEST,
				'page_title' => $dbkey,
				'rev_page=page_id'),
			__METHOD__,
			array('ORDER BY' => 'rev_id', 'LIMIT' => 1));
		foreach ($res as $row) {
			$name = $row->user_real_name;
			if (!$name)
				$name = $row->user_name;

			$email = $row->user_email;
			if ($email) {
				$to = new MailAddress($email);
				$from = new MailAddress('"wikiHow" <support@wikihow.com>');
				$mailResult = userMailer( $to, $from,
					wfQuotedPrintable( $subject ),
					$text,
					false );
			}
		}
		$dbr->freeResult($res);
	}

	function getArticleRequestTop() {
		global $wgTitle, $wgArticle, $wgRequest, $wgUser;

		$s = "";
		$sk = $wgUser->getSkin();
		$action = $wgRequest->getVal("action");

		if ($wgTitle->getNamespace() == NS_ARTICLE_REQUEST && !$action && $wgTitle->getArticleID() > 0) {
			$askedBy = $wgArticle->getUserText();
			$authors = $wgArticle->getContributors(1);
			$real_name = User::whoIsReal($authors[0][0]);
			if ($real_name) {
				$askedBy = $real_name;
			} elseif ($authors[0][0] == 0) {
				$askedBy = wfMsg('user_anonymous');
			} else {
				$askedBy = $authors[0][1];
			}
			$dateAsked = date("F d, Y", wfTimestamp(TS_UNIX, $wgArticle->getTimestamp()));

			$s .= "<div class=\"article_inner\"><table>
			<tr>
			<td width=\"20%\" valign=\"top\">".wfMsg('request')."</td><td><b>" . wfMsg('howto', $wgTitle->getText()) . "</b></td>
			</tr>
			<tr>
			<td>".wfMsg('askedby')."</td><td>" . $askedBy . "</td>
			</tr>
			<tr>
			<td>".wfMsg('date').":</td>
				<td>" . $dateAsked . "</td>
			</tr>
			<tr>
			<td valign=\"middle\">".wfMsg('details')."</td>
				<td><b>	";
		}

		return $s;
	}

	function getArticleRequestBottom() {
		global $wgTitle, $wgStylePath, $wgScript, $wgScriptPath;
		global $wgLang, $wgTitle, $wgRequest;
		$s = "";

		$li = $wgLang->specialPage("Userlogin");
		$lo = $wgLang->specialPage("Userlogout");
		$rt = $wgTitle->getPrefixedURL();
		if ( 0 == strcasecmp( urlencode( $lo ), $rt ) ) {
			$q = "";
		} else {
			$q = "?returnto={$rt}";
		}

		$action = $wgRequest->getVal("action");

		if ($wgTitle->getNamespace() == NS_ARTICLE_REQUEST && !$action && $wgTitle->getArticleID() > 0) {
			$s .= "</td>
					</tr>
					<tr> ";
			$t = Title::makeTitle(NS_MAIN, $wgTitle->getText());
			if ($t->getArticleID() > 0) {
				$s .= "<td style=\"padding-left: 50px\" colspan=\"2\"><br/><br/>".wfMsg('answeredtopic', $t->getText(), $t->getFullURL()) . "</a>.";
			} else {
				$s .= "<td style=\"padding-left: 250px\" colspan=\"2\">
							<br/><br/>".wfMsg('canyouhelp')."<br/><br/>
							<img src=\"$wgStylePath/common/images/arrow.jpg\" align=\"middle\">&nbsp;&nbsp;<a href=\"$wgScript?title=" . $wgTitle->getDBKey() . "&action=edit&requested=" . $wgTitle->getDBKey() . "\">".wfMsg('write-howto')." " . $wgTitle->getText() . "</a><br/>
				<br/><!--<font size=-3>".wfMsg('requested-topic-removed')."</font><br/><br/>-->
							<img src=\"$wgStylePath/common/images/arrow.jpg\" align=\"middle\">&nbsp;&nbsp;<a href=\"$wgScriptPath/".$wgLang->getNsText(NS_SPECIAL).":EmailLink?target=" . $wgTitle->getPrefixedURL() . "&returnto=$rt\">".wfMsg('sendthisrequest')."</a>
							<br/><font size=-3>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;".wfMsg('knowanexprt')."</font><br/><br/>
							<img src=\"$wgStylePath/common/images/arrow.jpg\" align=\"middle\"> &nbsp;<a href=\"/Special:Createpage\">".wfMsg('writerelated')."</a> <br/>
							<font size=-3>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;".wfMsg('topicremains')."</font><br/><br/>
					<img src=\"$wgStylePath/common/images/arrow.jpg\" align=\"middle\"> &nbsp;<a href=\"$wgScriptPath/".wfMsg('about-wikihow-url')."\">".wfMsg('learnmoreaboutwikihow')."</a> <br/>";
			}

			$s .= "</td>
				</tr>
				</table></div>";
		}
		return $s;
	}
}

function notifyRequester($article, $user, $user, $text, $summary) {
	global $wgTitle, $wgRequest;
				$requested = $wgRequest->getVal('requested', null);
				if ($requested && $summary != "Request now answered.") {
					$actualTitleObj = Title::newFromDBKey("Request:" . $wgTitle->getDBKey());
					$actualkey = $wgTitle->getDBKey();
					if ($requested != $actualkey) {
						$ot = Title::newFromDBKey( "Request:" . $requested);
						$nt = Title::newFromDBKey( "Request:" . $actualkey);
						$error = $ot->moveTo( $nt );
						if ($error !== true) {
							echo $error;

						}
						$actualTitleObj = $nt;
					}
					Request::notifyRequest($wgTitle, $actualTitleObj);
					// strip categories
					$at = new Article($actualTitleObj);
					$text = $at->getContent(true);
					//echo $t->getFullText();
					$text = ereg_replace("[\[]+Category\:([- ]*[.]?[a-zA-Z0-9_/-?&%])*[]]+", "", $text);
					$text .= "[[Category:Answered Requests]]";
					$at->updateArticle($text, "Request now answered.", true, false);

				}
	return true;
}
