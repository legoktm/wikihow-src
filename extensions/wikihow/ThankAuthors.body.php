<?php

class ThankAuthors extends UnlistedSpecialPage {

	function __construct() {
		parent::__construct('ThankAuthors');
	}

	function execute($par) {
		global $wgUser, $wgOut, $wgLang, $wgTitle, $wgMemc, $wgDBname;
		global $wgRequest, $wgSitename, $wgLanguageCode, $IP;
		global $wgScript, $wgFilterCallback, $wgScriptPath;

		$this->setHeaders();

		require_once("$IP/extensions/wikihow/EditPageWrapper.php");
		require_once("$IP/includes/EditPage.php");

		$target = isset($par) ? $par : $wgRequest->getVal('target');
		if (!$target) {
			$wgOut->addHTML("No target specified. In order to thank a group of authors, a page must be provided.");
			return;
		}

		$title = Title::newFromDBKey($target);
		$me = Title::makeTitle(NS_SPECIAL, "ThankAuthors");

		if (!$wgRequest->getVal('token')) {
			$sk = $wgUser->getSkin();
			$talk_page = $title->getTalkPage();

			$token = $this->getToken1();
			$thanks_msg = wfMsg('thank-you-kudos', $title->getFullURL(), wfMsg('howto', $title->getText()));

			// add the form HTML
			$wgOut->addHTML(<<<EOHTML
				<script type='text/javascript'>
					function submitThanks () {
						var message = $('#details').val();
						if(message == "") {
							alert("Please enter a message.");
							return false;
						}
						var url = '{$me->getFullURL()}?token=' + $('#token')[0].value + '&target=' + $('#target')[0].value + '&details=' + $('#details')[0].value;
						var form = $('#thanks_form');
						form.html($('#thanks_response').html());
						$.get(url);
						return true;
					}
				</script>

				<div id="thanks_response" style="display:none;">$thanks_msg</div>
				<div id="thanks_form"><div class="section_text">
EOHTML
				);
			$wgOut->addWikiText( wfMsg('enjoyed-reading-article',
				$title->getFullText(),
				$talk_page->getFullText() )
			);

			$wgOut->addHTML("<input id=\"target\" type=\"hidden\" name=\"target\" value=\"$target\"/>
				<input id=\"token\" type=\"hidden\" name=\"$token\" value=\"$token\"/>
				");


			$wgOut->addHTML ("<br />
				<textarea style='width:98%;' id=\"details\" rows=\"5\" cols=\"100\" name=\"details\"></textarea><br/>
				<br /><button onclick='submitThanks();' class='button primary'>" . wfMsg('submit') . "</button>
				</div></div>");
		} else {
			// this is a post, accept the POST data and create the 
			// Request article

			wfLoadExtensionMessages('PostComment');
			$wgOut->setArticleBodyOnly(true);

			$user = $wgUser->getName();
			$real_name = User::whoIsReal($wgUser->getID());
			if ($real_name == "") {
				$real_name = $user;
			}
			$dateStr = $wgLang->timeanddate(wfTimestampNow());
			$comment = $wgRequest->getVal("details");
			$text = $title->getFullText();

			wfDebug("STA: got text...");

			// filter out links
			$preg = "/[^\s]*\.[a-z][a-z][a-z]?[a-z]?/i";
			$matches = array();
			if (preg_match($preg, $comment, $matches) > 0 ) {
				$wgOut->addHTML(wfMsg('no_urls_in_kudos', $matches[0] )  );
				return;
			}

			$comment = strip_tags($comment);

			$formattedComment = wfMsg('postcomment_formatted_thanks', $dateStr, $user, $real_name, $comment, $text);

			wfDebug("STA: comment $formattedComment\n");
			wfDebug("STA: Checking blocks...");

			$tmp = "";
			if ( $wgUser->isBlocked() ) {
				$this->blockedIPpage();
				return;
			}
			if ( !$wgUser->getID() && $wgWhitelistEdit ) {
				$this->userNotLoggedInPage();
				return;
			}

			if ($target == "Spam-Blacklist") {
				$wgOut->readOnlyPage();
				return;
			}

			wfDebug("STA: checking read only\n");
			if ( wfReadOnly() ) {
				$wgOut->readOnlyPage();
				return;
			}
			wfDebug("STA: checking rate limiter\n");
			if ( $wgUser->pingLimiter('userkudos') ) {
				$wgOut->rateLimited();
				return;
			}

			wfDebug("STA: checking blacklist\n");

			if ($wgFilterCallback
				&& $wgFilterCallback($title, $comment, ""))
			{
				// Error messages or other handling should be
				// performed by the filter function
				return;
			}

			wfDebug("STA: checking tokens\n");

			$usertoken = $wgRequest->getVal('token');
			$token1 = $this->getToken1();
			$token2 = $this->getToken2();
			if ($usertoken != $token1 && $usertoken != $token2) {
				wfDebug ("STA: User kudos token doesn't match user: $usertoken token1: $token1 token2: $token2");
				return;
			}
			wfDebug("STA: going through contributors\n");

			$article = new Article($title);
			$contributors = $article->getContributors(0, 0, true);
			foreach ($contributors as $c) {
				$id = $c->getID();
				$u = $c;
				wfDebug("STA: going through contributors $u $id\n");
				if ($id == "0") continue; // forget the anon users.
				$t = Title::newFromText("User_kudos:" . $u);
				$a = new Article($t);
				$update = $t->getArticleID() > 0;
				$text = "";
				if ($update) {
					$text = $a->getContent(true);
					$text .= "\n\n" . $formattedComment;
					if ($wgFilterCallback
						&& $wgFilterCallback( $t, $text, $text) )
					{
						// Error messages or other handling should be
						// performed by the filter function
						return;
					}
				}
				if ($update) {
					$a->updateArticle($text, "", true, false, false, '', false);
				} else {
					$a->insertNewArticle($text, "", true, false, false, false, false);
				}
			}

			wfDebug("STA: done\n");
			$wgOut->addHTML("Done.");
			$wgOut->redirect('');
		}

	}

	function getToken1() {
		global $wgRequest, $wgUser;
		$d = substr(wfTimestampNow(), 0, 10);
		$s = $wgUser->getID() . $_SERVER['HTTP_X_FORWARDED_FOR'] . $_SERVER['REMOTE_ADDR'] . $wgRequest->getVal("target")  . $d;
		wfDebug("STA: generating token 1 ($s) " . md5($s) . "\n");
		return md5($s);
	}

	function getToken2() {
		global $wgRequest, $wgUser;
		$d = substr( wfTimestamp( TS_MW, time() - 3600 ), 0, 10);
		$s = $wgUser->getID() . $_SERVER['HTTP_X_FORWARDED_FOR'] . $_SERVER['REMOTE_ADDR'] . $wgRequest->getVal("target")  . $d;
		wfDebug("STA: generating token 2 ($s) " . md5($s) . "\n");
		return md5($s);
	}

}

