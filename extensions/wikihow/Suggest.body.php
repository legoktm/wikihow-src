<?php

if ( !defined('MEDIAWIKI') ) exit;

class RequestTopic extends SpecialPage {

	function __construct() {
		parent::__construct( 'RequestTopic' );
	}

	function execute($par) {
		global $wgRequest, $wgUser, $wgOut;

		wfLoadExtensionMessages('RequestTopic');

		$pass_captcha = true;
		if ($wgRequest->wasPosted()) {
			$fc = new FancyCaptcha();
			$pass_captcha   = $fc->passCaptcha();
		}

		$wgOut->setPageTitle(wfMsg('suggest_header'));
		if ($wgRequest->wasPosted() && $pass_captcha) {
			$dbr = wfGetDB(DB_SLAVE);
			require_once('EditPageWrapper.php');
			$title = EditPageWrapper::formatTitle($wgRequest->getVal('suggest_topic'));
			$s = Title::newFromText($title);
			if (!$s) {
				$wgOut->addHTML("There was an error creating this title.");
				return;
			}
			// does the request exist as an article?
			if ($s->getArticleiD()) {
				$wgOut->addHTML(wfMsg('suggested_article_exists_title'));
				$wgOut->addHTML(wfMsg('suggested_article_exists_info', $s->getText(), $s->getFullURL()));
				return;
			}
			// does the request exist in the list of suggested titles?
			$email = $wgRequest->getVal('suggest_email');
			if (!$wgRequest->getCheck('suggest_email_me_check'))
				$email = '';

			$count = $dbr->selectField('suggested_titles', array('count(*)'), array('st_title' => $s->getDBKey()));
			$dbw = wfGetDB(DB_MASTER);
			if ($count == 0) {
			    $dbw->insert('suggested_titles',
				array('st_title'	=> $s->getDBKey(),
					'st_user'	=> $wgUser->getID(),
					'st_user_text'	=> $wgUser->getName(),
					'st_isrequest'	=> 1,
					'st_category'	=> $wgRequest->getVal('suggest_category'),
					'st_suggested'	=> wfTimestampNow(),
					'st_notify'		=> $email,
					'st_source'		=> 'req',
					'st_key'		=> generateSearchKey($title),
					'st_group'		=> rand(0, 4)
				)
			    );
			} elseif ($email) {
				// request exists lets add the user's email to the list of notifications
				$existing = $dbr->selectField('suggested_titles', array('st_notify'), array('st_title' => $s->getDBKey()));
				if ($existing)
					$email = "$existing, $email";
				$dbw->update('suggested_titles',
					array('st_notify' => $email),
					array('st_title' => $s->getDBKey()));
			}
			$wgOut->addCSScode('suggc');
			$wgOut->addHTML(wfMsg("suggest_confirmation_owl", $s->getFullURL(), $s->getText()));
			return;
		}

		$wgOut->setHTMLTitle('Requested Topics - wikiHow');
		$wgOut->setRobotPolicy('noindex,nofollow');

		$wgOut->addCSScode('suggc');
		$wgOut->addHTML(wfMsg('suggest_sub_header'));

		$wgOut->addHTML("<form action='/Special:RequestTopic' method='POST' onSubmit='return checkSTForm();' name='suggest_topic_form'>");
		$wgOut->addJScode('suggj');
		$wgOut->addScript("<script type='text/javascript'/>var gSelectCat = '" . wfMsg('suggest_please_select_cat') . "';
		var gEnterTitle = '" . wfMsg('suggest_please_enter_title') . "';
		var gEnterEmail  = '" . wfMsg('suggest_please_enter_email') . "';
		</script>");

		$fc = new FancyCaptcha();
		$cats = $this->getCategoryOptions();
		$wgOut->addHTML(wfMsg('suggest_input_form', $cats, $fc->getForm(),  $pass_captcha ? "" : wfMsg('suggest_captcha_failed'), $wgUser->getEmail()));
		//$wgOut->addHTML(wfMsg('suggest_notifications_form', $wgUser->getEmail()));
		//$wgOut->addHTML(wfMsg('suggest_submit_buttons'));
		$wgOut->addHTML("</form>");
	}

	function getCategoryOptions($default = "") {
		global $wgUser;

		// only do this for logged in users
		$t = Title::newFromDBKey("WikiHow:" . wfMsg('requestcategories') );
		$r = Revision::newFromTitle($t);
		if (!$r)
			return '';
		$cat_array = split("\n", $r->getText());
		$s = "";
		foreach ($cat_array as $line) {
			$line = trim($line);
			if ($line == "" || strpos($line, "[[") === 0) continue;
			$tokens = split(":", $line);
			$val = "";
			$val = trim($tokens[sizeof($tokens) - 1]);
			$s .= "<OPTION class='input_med' VALUE=\"" . $val . "\">" . $line . "</OPTION>\n";
		}
		$s = str_replace("\"$default\"", "\"$default\" SELECTED", $s);

		return $s;
	}

}

class ListRequestedTopics extends SpecialPage {

	function __construct() {
		parent::__construct( 'ListRequestedTopics' );
	}

	function execute($par) {
		global $wgRequest, $wgUser, $wgOut, $wgHooks;

		require_once('Leaderboard.body.php');

		$wgOut->setHTMLTitle('List Requested Topics - wikiHow');
		$wgOut->setRobotPolicy('noindex,nofollow');

		$this->setActiveWidget();
		$this->setTopAuthorWidget();
		$this->getNewArticlesWidget();

		wfLoadExtensionMessages('RequestTopic');
		list( $limit, $offset ) = wfCheckLimits();
		$dbr = wfGetDB(DB_SLAVE);

		$wgOut->addCSScode('suggc');
		$wgOut->addJScode('suggj');

		$wgHooks["pageTabs"][] = array("wfRequestedTopicsTabs");

		$category = $wgRequest->getVal('category');
		$st_search = $wgRequest->getVal('st_search');

		//heading with link
		$request = '<a href="/Special:RequestTopic" class="editsection">'.wfMsg('requesttopic').'</a>';
		$heading = $request.'<h2>'.wfMsg('suggested_list_topics_title').'</h2>';
						
		//add surpise button
		$heading .= "<a href='/Special:RecommendedArticles?surprise=1' class='button buttonright secondary' id='suggested_surprise'>".wfMsg('suggested_list_button_surprise')."</a>";
		$wgOut->addHTML($heading);
	
		if (!$st_search && !$category) {
			global $wgCategoryNames;
			
			//add search box
			$html = $this->getSearchBox();
			
			//add the cats (meow)
			$isColumned = false;
			$count = 0;
			$link = '/Special:ListRequestedTopics';
			$html .= '<div class="catboxes">'.
					'<div class="catbox_column">';
					
			foreach ($wgCategoryNames as $cat) {
				$cat_class = 'cat_'.strtolower(str_replace(' ','',$cat));
				$cat_class = preg_replace('/&/','and',$cat_class);
				$html .= '<div class="catbox '.$cat_class.'"><a href="'.$link.'?category='.urlencode($cat).'">'.$cat.'</a></div>';
				
				if ($count >= (count($wgCategoryNames)/2) && $isColumned == false) {
					$html .= '</div><div class="catbox_column">';
					$isColumned = true;
				}
				$count++;
			}
			
			$html .= '<div class="catbox_misc"><a href="'.$link.'?st_search=all">'.
					wfMsg('suggested_list_cat_all').'</a></div>'.
					'<div class="catbox_misc"><a href="'.$link.'?category=Other">'.
					wfMsg('suggested_list_cat_other').'</a></div>'.
					'</div></div>';
			
			$wgOut->addHTML($html);
			
		} else {
			if ($st_search && $st_search != "all") {
				$key = generateSearchKey($st_search);
				$sql = "SELECT st_title, st_user_text, st_user FROM suggested_titles WHERE st_used = 0 " .
					"AND st_category = " . $dbr->addQuotes($category) . " " .
					"AND st_key like " . $dbr->addQuotes("%" . str_replace(" ", "%", $key) . "%") . " ".
					"LIMIT $offset, $limit;";
			} else {
				$sql = "SELECT st_title, st_user_text, st_user FROM suggested_titles WHERE st_used= 0"
				. ($category ? " AND st_category = " . $dbr->addQuotes($category) : '')
				. " AND st_patrolled=1 ORDER BY st_suggested DESC LIMIT $offset, $limit";
			}

			$res = $dbr->query($sql, __METHOD__);
			$wgOut->addHTML($this->getSearchBox($key, $category));

			if ($dbr->numRows($res) > 0) {
				if ($key) {
					$col_header = 'Requests for <strong>"' . htmlentities($key) . '"</strong>';
				} elseif ($category) {
					$col_header = str_replace(" and ", " &amp; ", $category);
				} else {
					$col_header = wfMsg('suggested_list_all');
				}
				
				if ($category && $category != 'Other') {
					$cat_class = preg_replace('/&/','and',$category);
					$cat_class = 'cat_' . strtolower(preg_replace('@[^A-Za-z0-9]@', '', $cat_class));
					$cat_icon = '<div class="cat_icon '.$cat_class.'"></div>';
				}

				$wgOut->addHTML("<table class='suggested_titles_list wh_block'>");
				$wgOut->addHTML("<tr class='st_top_row'><th class='st_icon'>{$cat_icon}</th><th class='st_title'>{$col_header}</th><th>Requested By</th></tr>");
				
				$count = 0;
				foreach ($res as $row) {
					$t = Title::newFromDBKey($row->st_title);
					if (!$t) continue;
					$c = "";
					if ($count % 2 == 1) $c = "class='st_on'";
					if ($row->st_user == 0) {
						$wgOut->addHTML("<tr><td class='st_write'><a href='/Special:CreatePage/{$t->getPartialURL()}'>Write</td><td class='st_title'>{$t->getText()}</td><td class='st_requestor'>Anonymous</td>
							</tr>");
					} else {
						$u = User::newFromName($row->st_user_text);
						$wgOut->addHTML("<tr><td class='st_write'><a href='/Special:CreatePage/{$t->getPartialURL()}'>Write</td><td class='st_title'>{$t->getText()}</td><td class='st_requestor'><a href='{$u->getUserPage()->getFullURL()}'>{$u->getName()}</a>
							</tr>");
					}
					$count++;
				}
				$wgOut->addHTML("</table>");
				$key = $st_search;
				if ($offset != 0) {
					$url = $_SERVER['SCRIPT_URI'];
					if ($key)
						$url .= "?st_search=" . urlencode($key);
					elseif ($category)
						$url .= "?category=" . urlencode($category);

					$wgOut->addHTML("<a class='pagination' style='float: left;' href='" . $url . "&offset=" . (max($offset - $limit, 0)) . "'>Previous {$limit}</a>");
				}
				if ($count == $limit) {
					$url = $_SERVER['SCRIPT_URI'];
					if ($key)
						$url .= "?st_search=" . urlencode($key);
					elseif ($category)
						$url .= "?category=" . urlencode($category);

					$wgOut->addHTML("<a class='pagination' style='float: right;' href='" . $url . "&offset=" . ($offset + $limit) . "'>Next {$limit}</a>");
				}
				$wgOut->addHTML("<br class='clearall' />");
			} else {
				if ($key)
					$wgOut->addHTML(wfMsg('suggest_noresults', htmlentities($key)));
				else
					$wgOut->addHTML(wfMsg('suggest_noresults', htmlentities($category)));
			}
		}
	}

	function getSearchBox($searchTerm = "", $category = "") {
		if ($category) $width_style = 'style="width: '.(400-(strlen($category)*6)).'px;"';
	
		$search = '
			<form action="/Special:ListRequestedTopics" id="st_search_form">
			<input type="text" value="' . htmlentities($searchTerm) . '" name="st_search" id="st_search" class="search_input" '.$width_style.' />
			<input type="hidden" name="category" value="' . htmlentities($category) . '" />
			<input type="submit" value="Search ' . htmlentities($category) . '" class="button secondary" id="st_search_btn" style="margin-left:10px;" />
			</form>';
		return $search;
	}

	function getCategoryImage($category) {
		$parts = explode(' ', $category);
		$firstName = count($parts) ? strtolower($parts[0]) : '';
		$options = array(
			'arts', 'cars', 'computers', 'education', 'family', 'finance', 'food',
			'health', 'hobbies', 'holidays', 'home', 'personal', 'pets', 'philosophy',
			'relationships', 'sports', 'travel', 'wikihow', 'work', 'youth',
		);
		if (in_array($firstName, $options)) {
			$path = wfGetPad($path);
			$path = wfGetPad("/skins/WikiHow/images/category_icon_$firstName.png");
			$image = "<img src='{$path}' alt='{$category}' />";
		} else {
			$path = '';
			$image = '';
		}
		return $image;
	}

	function setActiveWidget() {
		global $wgUser;
		$html = "<div id='stactivewidget'>" . ListRequestedTopics::getActiveWidget() . "</div>";
		$skin = $wgUser->getSkin();
		$skin->addWidget($html);
	}

	function setTopAuthorWidget() {
		global $wgUser;
		$html = "<div id=''>" . ListRequestedTopics::getTopAuthorWidget() . "</div>";
		$skin = $wgUser->getSkin();
		$skin->addWidget($html);
	}

	// function getTabs($section) {
		// global $wgUser, $wgOut;
		// $sk = $wgUser->getSkin();

		// $tabs = '';
		// $articles = $topic = $cats = $recommended = 'wide';
		// if ($section == 'Topic') {
			// $topic .= ' on';
		// } elseif ($section == 'Recommended') {
			// $recommended .= ' on';
		// } elseif ($section == 'Articles') {
			// $articles .= ' on';
		// } elseif ($section == 'SuggestCategories') {
			// $cats .= " on";
		// }
		// $tabs .= '<a href="/Special:ListRequestedTopics" onmousedown="button_click(this);" class="' . $topic . '">Find a Topic</a>';
		// $tabs .= '<a href="/Special:RecommendedArticles" onmousedown="button_click(this);" class="' . $recommended . '">Recommended</a>';
		// $tabs .= '<a href="/Special:YourArticles" onmousedown="button_click(this);" class="' . $articles . '">Your Articles</a>';
		// $request = $sk->makeLinkObj(Title::makeTitle(NS_SPECIAL, 'RequestTopic'), wfMsg('requesttopic'));
		// $request = preg_replace('@^<a@', '<a class="notab" style="float:right; margin-right:10px; font-size:1em; width:auto; font-weight:normal;"', $request);
		// $tabs .= $request;

		// return $tabs;
	// }

	function getNewArticlesWidget() {
		global $wgUser;

		$skin = $wgUser->getSkin();
		$html = $skin->getNewArticlesBox();
		$skin->addWidget($html);
	}

	function getTopAuthorWidget() {
		global $wgUser;
		$startdate = strtotime('7 days ago');
		$starttimestamp = date('Ymd-G',$startdate) . '!' . floor(date('i',$startdate)/10) . '00000';
		$data = LeaderBoard::getArticlesWritten($starttimestamp);
		arsort($data);
		$html = "<h3>Top Authors - Last 7 Days</h3><table class='stleaders'>";

		$index = 1;

		$sk = $wgUser->getSkin();

		foreach ($data as $key => $value) {
			$u = new User();
			$value = number_format($value, 0, "", ',');
			$u->setName($key);
			if (($value > 0) && ($key != '')) {
				$class = "";
				if ($index % 2 == 1)
					$class = 'class="odd"';

				$img = Avatar::getPicture($u->getName(), true);
				if ($img == '') {
					$img = Avatar::getDefaultPicture();
				}

				$html .= "<tr $class>
					<td class='leader_image'>" . $img . "</td>
					<td class='leader_user'>" . $sk->makeLinkObj($u->getUserPage(), $u->getName()) . "</td>
					<td class='leader_count'><a href='/Special:Leaderboard/$target?action=articlelist&period=$period&lb_name=".$u->getName() ."' >$value</a> </td>
				</tr> ";
				$data[$key] = $value * -1;
				$index++;
			}
			if ($index > 6) break;
		}
		$html .= "</table>";

		return $html;
	}

	function getActiveWidget() {
		global $wgUser;

		$sk = $wgUser->getSkin();

		$html = "<h3>" . wfMsg('st_currentstats') . "</h3><table class='st_stats'>";

		$unw = number_format(ListRequestedTopics::getUnwrittenTopics(), 0, ".", ", ");

		if ($wgUser->getID() != 0) {
			$today = ListRequestedTopics::getArticlesWritten(false);
			$topicsToday = ListRequestedTopics::getTopicsSuggested(false);
			$alltime = ListRequestedTopics::getArticlesWritten(true);
			$topicsAlltime = ListRequestedTopics::getTopicsSuggested(true);
		} else {
			$today = $sk->makeLinkObj(Title::makeTitle(NS_SPECIAL, "Userlogin"), "Login");
			$topicsToday = "N/A";
			$alltime = "N/A";
			$topicsAlltime = "N/A";
		}


		$html .= "<tr class='dashed'><td>" . wfMsg('st_numunwritten') . "</td><td class='stcount'>{$unw}</tr>";
		$html .= "<tr><td>" . wfMsg('st_articleswrittentoday') . "</td><td class='stcount' id='patrolledcount'>{$today}</td></tr>";
		$html .= "<tr class='dashed'><td>" . wfMsg('st_articlessuggestedtoday') . "</td><td class='stcount' id='quickedits'>{$topicsToday}</td></tr>";
		$html .= "<tr><td>" . wfMsg('st_alltimewritten'). "</td><td class='stcount' id='alltime'>{$alltime}</td></tr>";
		$html .= "<tr class='dashed'><td>" . wfMsg('st_alltimesuggested'). "</td><td class='stcount'>{$topicsAlltime}</td></tr>";
		$html .= "</table><center>" . wfMsg('rcpatrolstats_activeupdate') . "</center>";
		return $html;
	}

	function getUnwrittenTopics() {
		$dbr = wfGetDB(DB_SLAVE);
		$count = $dbr->selectField('suggested_titles',
			array('count(*)'),
			array('st_used' => 0),
			__METHOD__);
		return $count;
	}

	function getArticlesWritten($alltime) {
		global $wgUser;
		$dbr = wfGetDB(DB_SLAVE);
		$conds = array('fe_user' => $wgUser->getID(), 'page_id = fe_page', 'page_namespace=0');
		if (!$alltime) {
			// just today
			$cutoff = wfTimestamp(TS_MW, time() - 24 * 3600);
			$conds[] = "fe_timestamp > '{$cutoff}'";
		}
		$count = $dbr->selectField( array('firstedit', 'page'),
			array('count(*)'),
			$conds,
			__METHOD__);

		return number_format($count, 0, ".", ", ");
	}

	function getTopicsSuggested($alltime) {
		global $wgUser;
		$dbr = wfGetDB(DB_SLAVE);
		$conds = array('fe_user' => $wgUser->getID(), 'fe_page=page_id', 'page_title=st_title', 'page_namespace=0');
		if (!$alltime) {
			// just today
			$cutoff = wfTimestamp(TS_MW, time() - 24 * 3600);
			$conds[] = "fe_timestamp > '{$cutoff}'";
		}
		$count = $dbr->selectField(array('firstedit', 'page' ,'suggested_titles'),
			array('count(*)'),
			$conds,
			__METHOD__);

		return number_format($count, 0, ".", ", ");
	}

}


class ManageSuggestedTopics extends SpecialPage {

	function __construct() {
		parent::__construct( 'ManageSuggestedTopics' );
	}

	function execute ($par) {
		global $wgRequest, $wgUser, $wgOut;

		if (!in_array( 'sysop', $wgUser->getGroups()) && !in_array( 'newarticlepatrol', $wgUser->getRights() ) ) {
			$wgOut->setArticleRelated( false );
			$wgOut->setRobotpolicy( 'noindex,nofollow' );
			$wgOut->showErrorPage( 'nosuchspecialpage', 'nospecialpagetext' );
			return;
        }

		wfLoadExtensionMessages('RequestTopic');
		list( $limit, $offset ) = wfCheckLimits();

		$wgOut->setPageTitle('Manage Suggested Topics');
		$wgOut->setHTMLTitle('Manage Suggested Topics - wikiHow');
		$wgOut->addJScode('winpj');
		$wgOut->addCSScode('winpc');
		$wgOut->setRobotPolicy('noindex,nofollow');

		$dbr = wfGetDB(DB_SLAVE);
		$wgOut->addCSScode('suggc');
		$wgOut->addJScode('suggj');

		if ($wgRequest->wasPosted()) {
			$accept = array();
			$reject = array();
			$updates = array();
			$newnames = array();
			$title_mst = Title::makeTitle(NS_SPECIAL, "ManageSuggstions");
			foreach ($wgRequest->getValues() as $key=>$value) {
				$id = str_replace("ar_", "", $key);
				if ($value == 'accept') {
					$accept[] = $id;
					self::LogManageSuggestion('added', $title_mst, $id);
				} elseif ($value == 'reject') {
					$reject[] = $id;
					self::LogManageSuggestion('removed', $title_mst, $id);
				} elseif (strpos($key, 'st_newname_') !== false) {
					$updates[str_replace('st_newname_', '', $key)] = $value;
					$newnames[str_replace('st_newname_', '', $key)] = $value;
				}
			}
			$dbw = wfGetDB(DB_MASTER);
			if (count($accept) > 0) {
				$dbw->update('suggested_titles', array('st_patrolled' => 1), array('st_id' => $accept), __METHOD__);
			}
			if (count($reject) > 0) {
				$dbw->delete('suggested_titles', array('st_id' => $reject), __METHOD__);
			}

			foreach ($updates as $u=>$v) {
				$t = Title::newFromText($v);
				if (!$t) continue;

				// renames occassionally cause conflicts with existing requests, that's a bummer
				if (isset($newnames[$u])) {
					$page = $dbr->selectField('page', array('page_id'), array('page_title' => $t->getDBKey()), __METHOD__);
					if ($page) {
						// wait, this article is already written, doh
						$notify = $dbr->selectField('suggested_titles', array('st_notify'), array('st_id' => $u), __METHOD__);
						if ($notify) {
                			$dbw->insert('suggested_notify', array('sn_page' => $page, 'sn_notify' => $notify, 'sn_timestamp' => wfTimestampNow(TS_MW)), __METHOD__);
						}
						$dbw->delete('suggested_titles', array('st_id' => $u), __METHOD__);
					}
					$id = $dbr->selectField('suggested_titles', array('st_id'), array('st_title' => $t->getDBKey()), __METHOD__);
					if ($id) {
						// well, it already exists... like the Highlander, there can be only one
						$notify = $dbr->selectField('suggested_titles', array('st_notify'), array('st_id' => $u), __METHOD__);
						if ($notify) {
							// append the notify to the existing
							$dbw->update('suggested_titles', array('st_notify = concat(st_notify, ' . $dbr->addQuotes("\n" . $notify) . ")"), array('st_id' => $id), __METHOD__);
						}
						// delete the old one
						$dbw->delete('suggested_titles', array('st_id' => $u), __METHOD__);
					}
				}
				$dbw->update('suggested_titles',
					array('st_title' => $t->getDBKey()),
					array('st_id' => $u),
					__METHOD__);
			}
			$wgOut->addHTML(count($accept) . " suggestions accepted, " . count($reject) . " suggestions rejected.");
		}
		$sql = "SELECT st_title, st_user_text, st_category, st_id
				FROM suggested_titles WHERE st_used=0
				AND st_patrolled=0 ORDER BY st_suggested DESC LIMIT $offset, $limit";
		$res = $dbr->query($sql, __METHOD__);
		$wgOut->addHTML("
				<form action='/Special:ManageSuggestedTopics' method='POST' name='suggested_topics_manage'>
				<table class='suggested_titles_list wh_block'>
				<tr class='st_top_row'>
				<td class='st_title'>Article request</td>
				<td>Category</td>
				<td>Edit Title</td>
				<td>Requestor</td>
				<td>Accept</td>
				<td>Reject</td>
			</tr>
			");
		$count = 0;
		foreach ($res as $row) {
			$t = Title::newFromDBKey($row->st_title);
			if (!$t) continue;
			$c = "";
			if ($count % 2 == 1) $c = "class='st_on'";
			$u = User::newFromName($row->st_user_text);

			$wgOut->addHTML("<tr $c>
					<input type='hidden' name='st_newname_{$row->st_id}' value=''/>
					<td class='st_title_m' id='st_display_id_{$row->st_id}'>{$t->getText()}</td>
					<td>{$row->st_category}</td>
					<td><a href='' onclick='javascript:editSuggestion({$row->st_id}); return false;'>Edit</a></td>
					" .  ($u ? "<td><a href='{$u->getUserPage()->getFullURL()}' target='new'>{$u->getName()}</a></td>"
							: "<td>{$row->st_user_text}</td>" ) .
					"<td class='st_radio'><input type='radio' name='ar_{$row->st_id}' value='accept'></td>
					<td class='st_radio'><input type='radio' name='ar_{$row->st_id}' value='reject'></td>
				</tr>");
			$count++;
		}
		$wgOut->addHTML("</table>
			<br/><br/>
			<table width='100%'><tr><td style='text-align:right;'><input type='submit' value='Submit' class='button secondary' /></td></tr></table>
			</form>
			");
	}
	
	//write a log message for the action just taken
	private function LogManageSuggestion($name, $title_mst, $suggest_id) {
		global $wgUser;
		
		//first, get the title this is talking about
		$dbr = wfGetDB(DB_SLAVE);
		$the_title = $dbr->selectField('suggested_titles','st_title',array('st_id' => $suggest_id));
		$page_title = Title::newFromText($the_title);
		
		if ($page_title) {
			//then log that sucker
			$log = new LogPage( 'suggestion', true );
			$mw_msg = ($name == 'added') ? 'managesuggestions_log_add' : 'managesuggestions_log_remove';
			$msg = wfMsg($mw_msg, $wgUser->getName(), $page_title);
			$log->addEntry($name, $title_mst, $msg);
		}
	}
}

class RenameSuggestion extends UnlistedSpecialPage {
    function __construct() {
        parent::__construct( 'RenameSuggestion' );
    }

    function execute($par) {
		global $wgOut, $wgRequest;
		$name = $wgRequest->getVal( 'name' );
		wfLoadExtensionMessages('RequestTopic');
		$wgOut->setArticleBodyOnly(true);
		$wgOut->addHTML(wfMsg('suggested_edit_title',$name));
    }

}

class RecommendedArticles extends SpecialPage {

	function __construct() {
        parent::__construct( 'RecommendedArticles' );
    }

	// for the two little boxes at the top.
	function getTopLevelSuggestions($map, $cats) {
		$dbr = wfGetDB(DB_SLAVE);
		$cat1 = $cats[0];
		$cat2 = sizeof($cats) > 1 ? $cats[1] : $cats[0];
		$top = array($cat1, $cat2);
		$suggests = array();
		$users = array();
		$catresults = array();

		$catarray = "(";
		for ($i = 0; $i < count($cats); $i++) {
			if ($i > 0) $catarray .= ",";
			$catarray .= "'{$map[$cats[$i]]}'";
		}
		$catarray .= ")";

		$randstr = wfRandom();
		$conds = array('st_used' => 0, 'st_traffic_volume' => 2, "st_random >= $randstr");

		if (count($cats) > 0)
			$conds[] = "st_category IN $catarray";
		$rows = $dbr->select('suggested_titles', 
			array('st_title', 'st_user', 'st_user_text', 'st_category'),
			$conds,
			__METHOD__,
			array('ORDER BY'=>'st_random', 'GROUP BY' => 'st_category'));

		if ($dbr->numRows($rows) == 0) {
			$conds = array('st_used=0', 'st_traffic_volume'=>2, "st_random >= $randstr");
			$rows = $dbr->select('suggested_titles', 
				array('st_title', 'st_user', 'st_user_text', 'st_category'),
				$conds,
				__METHOD__, 
				array('ORDER BY' => 'st_random', 'GROUP BY' => 'st_category'));
			for ($i = 0; $i < 2; $i++) {
				$row = $dbr->fetchRow($rows);
				$t = Title::makeTitle(NS_MAIN, $row['st_title']);
				$suggests[] = $t;
				$users[] = $row['st_user_text'];
				$userids[] = $row['st_user'];
				$catresults[] = $row['st_category'];
			}
		} elseif ($dbr->numRows($rows) == 1) {
			$row = $dbr->fetchRow($rows);
			$t = Title::makeTitle(NS_MAIN, $row['st_title']);
			$suggests[] = $t;
			$users[] = $row['st_user_text'];
			$userids[] = $row['st_user'];
			$catresults[] = $row['st_category'];

			$randstr = wfRandom();
			$conds = array('st_used=0', 'st_traffic_volume'=>2, "st_random >= $randstr", "st_category IN $catarray", "st_title != '" . $row['st_title'] . "'");
			$rows2 = $dbr->select('suggested_titles',
				array('st_title', 'st_user', 'st_user_text', 'st_category'),
				$conds,
				__METHOD__,
				array('ORDER BY'=>'st_random', 'GROUP BY' => 'st_category'));
			if ($dbr->numRows($rows2) >= 1) {
				$row = $dbr->fetchRow($rows2);
				$t = Title::makeTitle(NS_MAIN, $row['st_title']);
				$suggests[] = $t;
				$users[] = $row['st_user_text'];
				$userids[] = $row['st_user'];
				$catresults[] = $row['st_category'];
			} else {
				$conds = array('st_used=0', 'st_traffic_volume'=>2, "st_random >= $randstr");
				$rows = $dbr->select('suggested_titles',
					array('st_title', 'st_user', 'st_user_text', 'st_category'),
					$conds,
					__METHOD__,
					array('ORDER BY'=>'st_random', 'GROUP BY' => 'st_category'));
				$row = $dbr->fetchRow($rows);
				$t = Title::makeTitle(NS_MAIN, $row['st_title']);
				$suggests[] = $t;
				$users[] = $row['st_user_text'];
				$userids[] = $row['st_user'];
				$catresults[] = $row['st_category'];
			}

		} else {
			for ($i = 0; $i < 2; $i++) {
				$row = $dbr->fetchRow($rows);
				$t = Title::makeTitle(NS_MAIN, $row['st_title']);
				$suggests[] = $t;
				$users[] = $row['st_user_text'];
				$userids[] = $row['st_user'];
				$catresults[] = $row['st_category'];
			}
		}
	
		$s = '';
		for ($i = 0; $i < 2; $i++) {
			if ($i == 1) {
				//add 'or'
				$s .= '<div class="top_suggestion_or">OR</div>';
			}
			
			if ($userids[$i] > 0) {
				$u = User::newFromName($users[$i]);
				$user_line = "<a href='{$u->getUserPage()->getFullURL()}'>{$u->getName()}</a>";
			} else {
				$user_line = wfMsg('anonymous');
			}
			
			$s .= 	'<div class="top_suggestion_box">' .
					'<div class="category">'.$catresults[$i].'</div>' .
					'<div class="title">'.$suggests[$i]->getText().'</div>' .
					'<div class="requestor"><img src="' . Avatar::getAvatarURL($users[$i]) . '"/>' .
					'<a href="/Special:CreatePage/'.$suggests[$i]->getPartialURL().'" class="button secondary">Write</a>' .
					'Requested By<br />'.$user_line.'</div>' .
					'</div>'; 
		}
		$s .= '<br class="clearall" />';
		
		return $s;
	}

    function execute($par) {
		global $wgOut, $wgRequest, $wgUser, $wgTitle, $wgLanguageCode, $wgHooks;
		require_once('Leaderboard.body.php');

		if ($wgLanguageCode != 'en') {
			$wgOut->showErrorPage( 'nosuchspecialpage', 'nospecialpagetext' );
			return;
		}

		$map = SuggestCategories::getCatMap(true);
		$cats = SuggestCategories::getSubscribedCats();
		$dbr = wfGetDB(DB_SLAVE);
		wfLoadExtensionMessages('RecommendedArticles');
		$wgOut->setRobotPolicy('noindex,nofollow');
		$wgOut->setHTMLTitle('Manage Suggested Topics - wikiHow');

		$target = isset( $par ) ? $par : $wgRequest->getVal( 'target' );

		if ($target == 'TopRow') {
			$wgOut->setArticleBodyOnly(true);
			$wgOut->addHTML($this->getTopLevelSuggestions($map, $cats));
			return;
		}
		$wgOut->addCSScode('suggc');
		$wgOut->addJScode('suggj');

		ListRequestedTopics::setActiveWidget();
		ListRequestedTopics::setTopAuthorWidget();
		ListRequestedTopics::getNewArticlesWidget();

		$wgHooks["pageTabs"][] = array("wfRequestedTopicsTabs");

		//heading with link
		$request = '<a href="/Special:RequestTopic" class="editsection">'.wfMsg('requesttopic').'</a>';
		$heading = $request.'<h2>'.wfMsg('suggestedarticles_header').'</h2>';
						
		$wgOut->addHTML($heading);

		$suggestions = "";

		if (count($cats) > 0) {
			foreach ($cats as $key) {
				$cat = $map[$key];
				$suggestionsArray = array();

				// grab some suggestions
				$randstr = wfRandom();
				$headerDone = false;
				$suggCount = 0;
				// grab 2 suggested articles that are NOT by ANON
				$resUser = $dbr->select('suggested_titles', array('st_title', 'st_user', 'st_user_text'),
					array('st_category' => $cat, 'st_used=0', "st_user > 0"),
					__METHOD__,
					array("ORDER BY" => "st_random", "LIMIT"=>2)
				);
				foreach ($resUser as $userRow) {
					$randSpot = mt_rand(0, 4);
					while (!empty($suggestionsArray[$randSpot]))
						$randSpot = mt_rand(0, 4);
					$suggestionsArray[$randSpot]->title = $userRow->st_title;
					$suggestionsArray[$randSpot]->user = $userRow->st_user;
					$suggCount++;
				}

				$res = $dbr->select('suggested_titles', array('st_title', 'st_user', 'st_user_text'),
					array('st_category' => $cat, 'st_used' => 0, 'st_traffic_volume' => 2, "st_random >= $randstr"),
					__METHOD__,
					array("ORDER BY" => "st_random", "LIMIT"=>5)
				);
				if ($dbr->numRows($res) > 0) {
					foreach ($res as $row) {
						if ($suggCount >= 5)
							break;
						$randSpot = mt_rand(0, 4);
						while (!empty($suggestionsArray[$randSpot]))
							$randSpot = mt_rand(0, 4);
						$suggestionsArray[$randSpot]->title = $row->st_title;
						$suggestionsArray[$randSpot]->user = $row->st_user;
						$suggCount++;
					}
				}
				
				if ($cat != 'Other') {
					$cat_class = 'cat_'.strtolower(str_replace(' ','',$cat));
					$cat_class = preg_replace('/&/','and',$cat_class);
					$cat_icon = '<div class="cat_icon '.$cat_class.'"></div>';
				}
				else {
					$cat_icon = '';
				}

				if ($suggCount > 0) {
					$suggestions .= "<table class='suggested_titles_list wh_block'>";
					$suggestions .= "<tr class='st_top_row'><th class='st_icon'>{$cat_icon}</th><th class='st_title'><strong>{$cat}</strong></th><th>Requested By</th></tr>";
					
					require_once('EditPageWrapper.php');
					foreach($suggestionsArray as $suggestion) {
						if (!empty($suggestionsArray)) {
							$t = Title::newFromText(EditPageWrapper::formatTitle($suggestion->title));
							if ($suggestion->user > 0) {
								$u = User::newFromId($suggestion->user);
								$u = "<a href='{$u->getUserPage()->getFullURL()}'>{$u->getName()}</a>";
							}
							else
								$u = "Anonymous";
							$suggestions .= "<tr><td class='st_write'><a href='/Special:CreatePage/{$t->getPartialURL()}'>Write</td><td class='st_title'>{$t->getText()}</td><td class='st_requestor'>{$u}</td></tr>";

						}
					}

					$suggestions .= "</table>";
				}
			}
		}

		if ($wgRequest->getInt('surprise') == 1 || $suggestions == "")
			$wgOut->addHTML("<div id='top_suggestions'>" . $this->getTopLevelSuggestions($map, $cats) . "</div>");

		$wgOut->addHTML("<br class='clearall' /><div id='suggested_surprise_big'><a href='/Special:RecommendedArticles?surprise=1' class='button secondary'>".wfMsg('suggested_list_button_surprise')."</a></div><br class='clearall' />");
			
		if (sizeof($cats) == 0) {
			$wgOut->addHTML(wfMsg('suggested_nocats'));
			$wgOut->addHTML("<a href='#' id='choose_cats'>Choose which categories to display</a>");
			return;
		}

		if ($wgUser->getID() > 0) {
			$wgOut->addHTML($suggestions);
			$wgOut->addHTML("<a href='#' id='choose_cats'>Choose which categories to display</a>");
		} else {
			$rt = $wgTitle->getPrefixedURL();
			$q = "returnto={$rt}";
			$wgOut->addHTML(wfMsg('recommend_anon', $q));
		}
    }

}

class YourArticles extends SpecialPage {

    function __construct() {
        parent::__construct( 'YourArticles' );
    }

	function getAuthors($t) {
		$dbr = wfGetDB(DB_SLAVE);
		$authors = array();
        $res = $dbr->select('revision',
            array('rev_user', 'rev_user_text'),
            array('rev_page'=> $t->getArticleID()),
            __METHOD__,
            array('ORDER BY' => 'rev_timestamp')
        );
		foreach ($res as $row) {
            if ($row->rev_user == 0) {
               $authors['anonymous'] = 1;
            } elseif (!isset($this->mAuthors[$row->user_text])) {
               $authors[$row->rev_user_text] = 1;
            }
        }
		return array_reverse($authors);
	}

    function execute($par) {
		global $wgOut, $wgUser, $wgTitle, $wgLanguageCode, $wgHooks;

		if ($wgLanguageCode != 'en') {
			$wgOut->showErrorPage( 'nosuchspecialpage', 'nospecialpagetext' );
			$wgOut->setRobotPolicy('noindex,nofollow');
			return;
		}

		require_once('Leaderboard.body.php');

		wfLoadExtensionMessages('RequestTopic');
		$wgOut->addCSScode('suggc');
		$wgOut->addJScode('suggj');

		ListRequestedTopics::setActiveWidget();
		ListRequestedTopics::setTopAuthorWidget();
		ListRequestedTopics::getNewArticlesWidget();

		$wgHooks["pageTabs"][] = array("wfRequestedTopicsTabs");

		$wgOut->setHTMLTitle('Articles Started By You - wikiHow');
		$wgOut->setRobotPolicy('noindex,nofollow');

		//heading with link
		$request = '<a href="/Special:RequestTopic" class="editsection">'.wfMsg('requesttopic').'</a>';
		$heading = $request.'<h2>'.wfMsg('your_articles_header').'</h2>';
						
		//add surpise button
		$heading .= "<a href='/Special:RecommendedArticles?surprise=1' class='button buttonright secondary' id='suggested_surprise'>".wfMsg('suggested_list_button_surprise')."</a><br /><br /><br />";
		$wgOut->addHTML($heading);

		if ($wgUser->getID() > 0) {

			$dbr = wfGetDB(DB_SLAVE);
			$res = $dbr->query("select * from firstedit left join page on fe_page=page_id
					left join suggested_titles on page_title=st_title and page_namespace= 0 where fe_user={$wgUser->getID()} and page_id is not NULL order by st_category");

			if ($dbr->numRows($res) == 0) {
				$wgOut->addHTML(wfMsg("yourarticles_none"));
				return;
			}

			$last_cat = "-";

			// group it by categories
			// sometimes st_category is not set, so we have to grab the top category
			// from the title object of the target article
			$articles = array();
			while ($row = $dbr->fetchObject($res)) {
				$t = Title::makeTitle(NS_MAIN, $row->page_title);
				$cat = $row->st_category;
				if ($cat == '') {
					$str = Categoryhelper::getTopCategory($t);
					if ($str != '')  {
						$title = Title::makeTitle(NS_CATEGORY, $str);
						$cat = $title->getText();
					} else {
						$cat = "Other";
					}
				}
				if (!isset($articles[$cat]))
					$articles[$cat] = array();
				$articles[$cat][] = $row;
			}
			
			foreach ($articles as $cat=>$article_array) {
				$image = ListRequestedTopics::getCategoryImage($cat);
				$style = "";
				if ($image == "") {
					$style = "style='padding-left:67px;'";
				}
				 
				$wgOut->addHTML('<h2>'.$cat.'</h2><div class="wh_block"><table class="suggested_titles_list">');
				
				foreach ($article_array as $row) {
					$t = Title::makeTitle(NS_MAIN, $row->page_title);
					$ago = wfTimeAgo($row->page_touched);
					$authors = array_keys($this->getAuthors($t));
					$a_out = array();
					for ($i = 0; $i < 2 && sizeof($authors) > 0; $i++) {
						$a = array_shift($authors);
						if ($a == 'anonymous')  {
							$a_out[] = "Anonymous"; // duh
						} else {
							$u = User::newFromName($a);
							if (!$u) {
								echo "{$a} broke";
								exit;
							}
							$a_out[] = "<a href='{$u->getUserPage()->getFullURL()}'>{$u->getName()}</a>";
						}
					}
					$skin = $wgUser->getSkin();
					$img = SkinWikihowskin::getGalleryImage($t, 46, 35);
					$wgOut->addHTML("<tr><td class='article_image'><img src='{$img}' alt='' width='46' height='35' /></td>"
						 . "<td><h3><a href='{$t->getFullURL()}' class='title'>" . wfMsg('howto', $t->getFullText()) . "</a></h3>"
						. "<p class='meta_info'>Authored by: <a href='{$wgUser->getUserPage()->getFullURL()}'>You</a></p>"
						. "<p class='meta_info'>Edits by: " . implode(", ", $a_out) . " (<a href='{$t->getFullURL()}?action=credits'>see all</a>)</p>"
						. "<p class='meta_info'>Last updated {$ago}</p>"
						. "</td>"
						. "<td class='view_count'>" . number_format($row->page_counter, 0, "", ",") . "</td></tr>"
					);
				}
				$wgOut->addHTML('</table></div>');
			}
		} else {
			$rt = $wgTitle->getPrefixedURL();
			$q = "returnto={$rt}";
			$wgOut->addHTML( wfMsg('yourarticles_anon', $q) );
		}
    }

}

class SuggestCategories extends UnlistedSpecialPage {

	function __construct() {
		parent::__construct( 'SuggestCategories' );
	}

	// returns a set of keys for the top level categories
	function getCatMap($associative=false) {
		// get it? cat-map? instead of cat-nap? hahah.
		$cat_title = Title::makeTitle(NS_PROJECT, "Categories");
		$rev = Revision::newFromTitle($cat_title);
		$text = preg_replace("@\*\*.*@im", "", $rev->getText());
		$text = preg_replace("@\n[\n]*@im", "\n", $text);
		$lines = split("\n", $text);
		$map = array();
		foreach ($lines as $l) {
			if (strpos($l, "*") === false) continue;
			$cat = trim(preg_replace("@\*@", "", $l));
			if ($associative) {
				$key = strtolower(str_replace(" ", "-", $cat));
				$map[$key] = $cat;
			} else {
				$map[] = $cat;
			}
		}
		return $map;
	}

	function getSubscribedCats() {
		global $wgUser;
		$dbr = wfGetDB(DB_SLAVE);
		$row = $dbr->selectRow('suggest_cats', array('*'), array('sc_user'=>$wgUser->getID()));
		if ($row) {
			$field = $row->sc_cats;
			if ($field == '')
				return array();
			$cats = preg_split("@,@", $field, 0, PREG_SPLIT_NO_EMPTY);
			return $cats;
		}
		$catmap = self::getCatMap();

		foreach ($catmap as $cat) {
			$cats[] = strtolower(str_replace(" ", "-", $cat));
		}

		// meow!
		return $cats;
	}

	function execute($par) {
		global $wgOut, $wgRequest, $wgUser;

		$dbr = wfGetDB(DB_SLAVE);

		// just getting cats?
		if ($wgRequest->getVal('getusercats')) {
			$catmap = self::getCatMap();
			$cats = self::getSubscribedCats();

			$wgOut->setArticleBodyOnly(true);

			if ((count($catmap) == count($cats)) or (empty($cats))) {
				$wgOut->addHTML('All');
				return;
			}

			foreach ($catmap  as $cat) {
				$key = strtolower(str_replace(" ", "-", $cat));
				$safekey = str_replace("&", "and", $key);

				// hack for the ampersand in our db
				$checkkey = ($safekey == 'cars-and-other-vehicles' ? 'cars-&-other-vehicles' : $safekey);

				// are we selecting it?
				if ($cats && in_array($checkkey, $cats)) {
					$usercats[] = $cat;
				}
			}
			$wgOut->addHTML(implode($usercats, ", "));

			return;
		}

		// process any postings, saving the categories
		if ($wgRequest->wasPosted()) {
			$field = preg_replace("@ @", "", $wgRequest->getVal('cats'));
			// hack for ampersand in "cars & other vehicles" category
			$field = str_replace('cars-and-other-vehicles','cars-&-other-vehicles',$field);

			$cats = preg_split("@,@", $field, 0, PREG_SPLIT_NO_EMPTY);
			$cats = array_unique($cats);
			sort($cats);
			$dbw = wfGetDB(DB_MASTER);
			$sql = "INSERT INTO suggest_cats VALUES(" .$wgUser->getID() . ", " . $dbw->addQuotes(implode($cats, ","))
				. ") ON DUPLICATE KEY UPDATE sc_cats = " . $dbw->addQuotes(implode($cats, ","));
			$dbw->query($sql);
			$wgOut->addHTML("<br/><br/>Categories updated.<br/><br/>");

			$type = $wgRequest->getVal('type');
			if ($type) {
				$wgOut->redirect('/Special:EditFinder/'.urlencode($type));
			} else {
				$wgOut->redirect('/Special:RecommendedArticles');
			}
		}

		$wgOut->setArticleBodyOnly(true);

		$catmap = self::getCatMap();
		$cats = self::getSubscribedCats();

		$hiddencats = implode($cats, ",");
		$hiddencats = str_replace("&","and",$hiddencats);

		// get top categories
		$theHTML .= "<form method='post' action='/Special:SuggestCategories' id='suggest_cats' name='suggest_cats'><input type='hidden' name='cats' value='" . $hiddencats . "'/>";
		$theHTML .= "<table width='100%' class='categorytopics selecttopics'><tr>";
		$index = 0;
		$select_count = 0;
		foreach ($catmap  as $cat) {
			$key = strtolower(str_replace(" ", "-", $cat));
			$safekey = str_replace("&", "and", $key);
			// hack for the ampersand in our db
			($safekey == 'cars-and-other-vehicles') ? $checkkey = 'cars-&-other-vehicles' :	$checkkey = $safekey;

			// are we selecting it?
			if ($cats && in_array($checkkey, $cats)) {
				$c = "chosen";
				$s = "checked='checked'";
				$select_count++;
			}
			else {
				$c = "not_chosen";
				$s = "";
			}

			$theHTML .= "<td id='{$safekey}' class='{$c} categorylink'><a class=''><input type='checkbox' id='check_{$safekey}' {$s} />" .  ListRequestedTopics::getCategoryImage($cat) . "<br />{$cat}</a></td>";
			$index++;
			if ($index % 6 == 0)
				$theHTML .= "</tr><tr>";

		}
		$actual_count = $index;
		if ($index % 6 <= 5) {
			while ($index % 6 != 0) {
				$theHTML .= "<td></td>";
				$index++;
			}
		}

		$theHTML .= '</tr></table> '
					.'<a style="float: right; background-position: 0% 0pt;" class="button primary" onclick="document.suggest_cats.submit();" id="the_save_button">' . wfMsg('save') . '</a>';

		// selected all?
		$s = $select_count == $actual_count ? "checked='checked'" : "";
		// add checkbox at the top
		$theHTML = "<input type='checkbox' id='check_all_cats' ".$s." /> <label for='check_all_cats'>All categories</label>".$theHTML."</form>";

		$wgOut->addHTML($theHTML);
	}

}
