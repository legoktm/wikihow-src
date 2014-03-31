<?

define(IV_RESULTS_PER_PAGE, 10);

class Importvideo extends SpecialPage {

	// youtube, 5min, etc.
	public $mSource;

	public $mResponseData = array(), $mCurrentNode, $mResults, $mCurrentTag = array();

	function __construct($source = null) {
		parent::__construct( 'Importvideo' );
		$this->mSource = $source;
	}

	/**
	 *  Returns a title of a newly created article that needs a video
	 */
	function getNewArticleWithoutVideo(){
		global $wgRequest;
		global $wgCookiePrefix, $wgCookiePath, $wgCookieDomain, $wgCookieSecure;
		$t = null;
		$dbr = wfGetDB(DB_SLAVE);
		$vidns = NS_VIDEO;
		$skip= "";
		if ($wgRequest->getVal('skip') != null) {
			$skip = " AND nap_page < {$wgRequest->getInt('skip')}";
			setcookie( $wgCookiePrefix.'SkipNewVideo', $wgRequest->getVal('skip'), time() + 86400, $wgCookiePath, $wgCookieDomain, $wgCookieSecure );
		} else if ( isset( $_COOKIE["{$wgCookiePrefix}SkipNewVideo"] ) ) {
			$skip = " AND nap_page < " . intval($_COOKIE["{$wgCookiePrefix}SkipNewVideo"]);
		}
		$sql = "SELECT nap_page
				FROM newarticlepatrol
					LEFT JOIN templatelinks t1 ON t1.tl_from = nap_page and t1.tl_namespace = {$vidns}
					LEFT JOIN templatelinks t2 on t2.tl_from =  nap_page and t2.tl_title IN ('Nfd', 'Copyvio', 'Merge', 'Speedy')
					LEFT JOIN page on  nap_page = page_id
			WHERE nap_patrolled =1 AND t1.tl_title is NULL AND nap_page != 0  AND t2.tl_title is null AND page_is_redirect = 0 {$skip}
			ORDER BY nap_page desc LIMIT 1;";
		$res = $dbr->query($sql);
		if ($row = $dbr->fetchObject($res)) {
			$t = Title::newFromID($row->nap_page);
		}
		return $t;
	}

	/**
	 *  Returns an article from a specific category that requires a video
	 */
	function getTitleFromCategory($category) {
		$cat = Title::makeTitle(NS_CATGEGORY, $category);
		$t	 = null;
		$dbr = wfGetDB(DB_MASTER);
		$sql = "SELECT page_title
				FROM page
				LEFT JOIN templatelinks ON tl_from=page_id AND tl_namespace=" . NS_VIDEO . "
				LEFT JOIN categorylinks ON cl_from = page_id
				WHERE tl_title is NULL
					AND	cl_to = " . $dbr->addQuotes($cat->getDBKey()) . "
				ORDER BY rand() LIMIT 1;";
		$res = $dbr->query($sql);
		if ($row = $dbr->fetchObject($res))
			$t = Title::newFromText($row->page_title);
		return $t;
	}

	/**
	 * Processes a search for users who are looking for an article to
	 * add a video to
	 */
	function doSearch($target, $orderby, $query, $search) {
		global $wgOut, $wgRequest;
		$me = Title::makeTitle(NS_SPECIAL, "Importvideo");
		$wgOut->addHTML(wfMsg('importvideo_searchinstructions') .
			"<br/><br/><form action='{$me->getFullURL()}'>
					<input type='hidden' name='target' value='" . htmlspecialchars($target) . "'/>
					<input type='hidden' name='orderby' value='{$orderby}'/>
					<input type='hidden' name='popup' value='{$wgRequest->getVal('popup')}'/>
					<input type='hidden' name='q' value='" . htmlspecialchars($query) . "' >
					<input type='text' name='dosearch' value='" . ($search != "1" ? htmlspecialchars($search) : "") . "' size='40'/>
					<input type='submit' value='" . wfMsg('importvideo_search') . "'/>
				</form>
				<br/>");
		if ($search != "1") {
			$l = new LSearch();
			$results = $l->googleSearchResultTitles($search);
			$base_url = $me->getFullURL() . "?&q=" . urlencode($query) . "&source={$source}";
			if (sizeof($results) == 0) {
				$wgOut->addHTML(wfMsg('importvideo_noarticlehits'));
				return;
			}
			#output the results
			$wgOut->addHTML(wfMsg("importvideo_articlesearchresults") . "<ul>");
			foreach ($results as $t) {
			$wgOut->addHTML("<li><a href='" . $base_url . "&target=" . urlencode($t->getText()) . "'>"
					. wfMsg('howto', $t->getText() . "</a></li>"));
			}
			$wgOut->addHTML("</ul>");
		}
	}

	/**
	 * Maintain modes through URL parameters
	 */
	function getURLExtras() {
		global $wgRequest;
		$popup		= $wgRequest->getVal('popup') == 'true' ? "&popup=true" : "";
		$rand		= $wgRequest->getVal('new') || $wgRequest->getVal('wasnew')
						? "&wasnew=1" : "";
		$bycat		= $wgRequest->getVal('category') ? "&category=" . urlencode($wgRequest->getVal('category')) : "";
		$orderby	= $wgRequest->getVal('orderby') ? "&orderby=" . $wgRequest->getVal('orderby') : "";
		return $popup . $rand. $bycat . $orderby;
	}

	/**
	 *   The main function
	 */
	function execute($par) {
		global $wgRequest, $wgUser, $wgOut, $wgImportVideoSources;

		if ( $wgUser->isBlocked() ) {
			$wgOut->blockedPage();
			return;
		}

		wfLoadExtensionMessages('Importvideo');
		if ($wgRequest->getVal('popup') == 'true') {
			$wgOut->setArticleBodyOnly(true);
		}
		$this->setHeaders();
		$source = $this->mSource = $wgRequest->getVal('source', 'youtube');
		$target = isset($par) ? $par : $wgRequest->getVal('target');
		$query = $wgRequest->getVal('q');
		$me = Title::makeTitle(NS_SPECIAL, "Importvideo");
		$wasnew = $this->getRequest()->getVal('wasnew');

		// some sanity checks on the target
		if ($target && !$wasnew) {
			$title = Title::newFromURL($target);
			if (!$title || !$title->exists()) {
				$wgOut->addHTML("Error: target article does not exist.");
				return;
			} else {
				$article = new Article($title);
				$article->loadPageData();
				if ($article->mIsRedirect) {
					$wgOut->addHTML("Error: target article is a redirect.");
					return;
				}
			}
		}

		$wgOut->addHTML("<div id='importvideo'>");
		$wgOut->addHTML("<h2>".wfMsg('add_a_video')."</h2>");
		# changing target article feature
		$search = $wgRequest->getVal("dosearch", null);
		if ($search != null) {
			$this->doSearch($target, $orderby, $query, $search);
			return;
		}
		$sp = null;
		switch ($source) {
			case 'howcast':
				$sp = new ImportvideoHowcast($source);
				break;
			case 'youtube':
			default:
				$sp = new ImportvideoYoutube($source);
				break;
		}

		// handle special cases where user is adding a video to a new article or by category
		if ($wgRequest->getVal('new') || $wgRequest->getVal('wasnew')) {
			if ($wgRequest->getVal('new')) {
				$t = $this->getNewArticleWithoutVideo();
				$target = $t->getText();
			} else {
				$t = Title::newFromText($target);
			}
			$wgRequest->setVal('target', $target);
		} else if ($wgRequest->getVal('category') && $target == '') {
			$t = $this->getTitleFromCategory($wgRequest->getVal('category'));
			$target = $t->getText();
			$wgRequest->setVal('target', $target);
		}

		// construct base url to switch between sources
		$url = $me->getFullURL() . "?target=" . urlencode($target) . "&q=" . urlencode($query) . $this->getURLExtras() . "&source=";

		$title = Title::newFromText($target);
		if (!trim($target)) {
			$wgOut->addHTML("Error: no target specified.");
			return;
		}
		$target = $title->getText();

		//get the steps and intro to show to the user
		$r = Revision::newFromTitle($title);
		$text = "";
		if ($r)
			$text = $r->getText();
		$article = new Article($title);
		$extra  = $article->getSection($text, 0);
		$steps = "";
		for ($i = 1; $i < 3; $i++) {
			$xx = $article->getSection($text, $i);
			if (preg_match("/^==[ ]+" . wfMsg('steps') . "/", $xx)) {
				$steps = $xx;
				break;
			}
		}
		$extra = preg_replace("/{{[^}]*}}/", "", $extra);
		$extra = $wgOut->parse($extra);
		$steps = $wgOut->parse($steps);
		$cancel = "";

		$nextlink = "/Special:Importvideo?new=1&skip={$title->getArticleID()}";
		if ($wgRequest->getVal('category'))
			$nextlink = "/Special:Importvideo?category=" . urlencode($wgRequest->getVal('category'));

		if ($wgRequest->getVal('popup') != 'true') {
			$wgOut->addHTML("<div class='article_title'>
				" . wfMsg('importvideo_article') . "- <a href='{$title->getFullURL()}' target='new'>" . wfMsg('howto', $title->getText()) . "</a>");
			$wgOut->addHTML("<spanid='showhide' style='font-size: 80%; text-align:right; font-weight: normal;'>
					(<a href='{$nextlink}' accesskey='s'>next article</a> |
					<a href='$url&dosearch=1' accesskey='s'>" . wfMsg('importvideo_searchforarticle') . "</a> {$cancel} )
				</span>");
			if ($wgRequest->getVal('category')) {
				$wgOut->addHTML("You are adding videos to articles from the \"{$wgRequest->getVal('category')}\" category.
					(<a href='#'>change</a>)");
			}
			$wgOut->addHTML("</div>");

			$wgOut->addHTML("<div class='video_related wh_block'>
					<h2>Introduction</h2>
					{$extra}
					<br clear='all'/>
					<div id='showhide' style='font-size: 80%; text-align:right;'>
						<span id='showsteps'><a href='#' onclick='javascript:showhidesteps(); return false;'>" . wfMsg('importvideo_showsteps' ) . "</a></span>
						<span id='hidesteps' style='display: none;'><a href='#' onclick='javascript:showhidesteps(); return false;'>" . wfMsg('importvideo_hidesteps' ) . "</a></span>
					</div>
					<div id='stepsarea' style='display: none;'>
					{$steps}
					</div>
					<br clear='all'/>
				</div>
			");
		}
		$wgOut->addHTML("<script type='text/javascript' src='" . wfGetPad('/extensions/min/f/extensions/wikihow/video/importvideo.js?rev=') . WH_SITEREV . "'> </script>	");
		$wgOut->addHTML("<link rel='stylesheet' type='text/css' href='" . wfGetPad('/extensions/min/f/extensions/wikihow/video/importvideo.css?rev=') . WH_SITEREV . "' />");
		$wgOut->addHTML("<script type='text/javascript'>
			var isPopUp = " . ($wgRequest->getVal('popup') ?  "true" : "false") . ";
			</script>");

		if (!$wgRequest->wasPosted()) {
			$wgOut->addHTML(wfMsgWikiHtml('add_video_info'));
			# HEADER for import page
			$url = $me->getFullURL() . "?target=" . urlencode($target) . "&q=" . urlencode($query) . $this->getURLExtras(). "&source=";

			// refine form
			$orderby = $wgRequest->getVal('orderby', 'relevance');
			$wgOut->addHTML($this->refineForm($me, $target, $wgRequest->getVal('popup') == 'true', $query, $orderby));

			// sources tab
			$wgOut->addHTML("<ul id='importvideo_search_tabs'>");
			foreach ($wgImportVideoSources as $s) {
				$selected = ($s == $source) ? ' class="iv_selected"' : '';
				$wgOut->addHTML("<li$selected><a href='{$url}{$s}'>" . wfMsg('importvideo_source_' . $s) . "</a></li>");
			}
			$wgOut->addHTML("</ul>");

			$vt = Title::makeTitle(NS_VIDEO, $target);
			if ($vt->getArticleID() > 0 && $wgRequest->getVal('popup') != 'true') {
				$wgOut->addHTML("<div class='wh_block importvideo_main'>".wfMsgExt('importvideo_videoexists', 'parse', $vt->getFullText())."</div>");
			}
		}

		//special class just for pop-ups
		if ($wgRequest->getVal('popup')) $pop_class = 'importvideo_pop';

		$wgOut->addHTML("<div class='wh_block importvideo_main $pop_class'>");
		$sp->execute($par);
		$wgOut->addHTML("</div>");	//Bebeth: took out extra closing div
		$wgOut->addHTML("</div>");	//Scott: put a brand new extra closing div in (take that, Bebeth!)

	}

	function refineForm($me, $target, $popup, $query, $orderby = '') {
		global $wgRequest;
		$p 		= $popup ? "true" : "false";
		$rand 	= $wgRequest->getVal('new') || $wgRequest->getVal('wasnew')
					? "<input type='hidden' name='wasnew' value='1'/>" : "";
		$cat   	= $wgRequest->getVal('category') != ""
					? "<input type='hidden' name='category' value=\"" . htmlspecialchars($wgRequest->getVal('category')) . "\"/>" : "";
		if ($query == '') $query = $target;
		return "<div style='text-align:center; margin-top: 5px; padding: 3px;'>
			<form action='{$me->getFullURL()}' name='refineSearch' method='GET'>
			<input type='hidden' name='target' value=\"" . htmlspecialchars($target) . "\"/>
			<input type='hidden' name='popup' value='{$p}'/>
			{$rand}
			<input type='hidden' name='orderby' value='{$orderby}'/>
			<input type='hidden' name='source' value='{$this->mSource}'/>
			{$cat}
			<input type='text' name='q' value=\"" . htmlspecialchars($query) . "\" id='refinesearch_input' class='search_input' />
			<input type='submit' class='button' value='" . wfMsg('importvideo_refine') . "'/>
			</form></div>
			<br/>";
	}

	function getPostForm($target) {
		global $wgRequest;
		$me = Title::makeTitle(NS_SPECIAL, "Importvideo");
		$tar_es = htmlspecialchars($target);
		$query = $wgRequest->getVal('q');
		$popup = $wgRequest->getVal('popup') == "true" ?  "true" : "false" ;
		$rand = $wgRequest->getVal('new') || $wgRequest->getVal('wasnew')
					? "<input type='hidden' name='wasnew' value='1'/>" : "";
		$cat = $wgRequest->getVal('category') != ""
					? "<input type='hidden' name='category' value=\"" . htmlspecialchars($wgRequest->getVal('category')) . "\"/>" : "";
		return "<form method='POST' action='{$me->getFullURL()}' name='videouploadform' id='videouploadform'>
				<input type='hidden' name='description' value='' />
				<input type='hidden' name='url' id='url' value='/Special:Importvideo?{$_SERVER['QUERY_STRING']}'/>
				<input type='hidden' name='popup' value='{$wgRequest->getVal('popup')}'/>
				{$rand}
				{$cat}
				<input type='hidden' name='video_id' value=''/>
				<input type='hidden' name='target' value=\"{$tar_es}\"/>
				<input type='hidden' name='source' value='{$this->mSource}'/>   </form>
		";
	}

	function getPreviousNextButtons($maxResults = -1) {
		global $wgRequest;
		$query = $wgRequest->getVal('q');
		$start = $wgRequest->getVal('start', 1);
		$target = preg_replace('@ @','+',$wgRequest->getVal('target'));
		$me = Title::makeTitle(NS_SPECIAL, "Importvideo");

		// Previous, Next buttons if necessary
		$s = "<table width='100%'><tr><td>";
		$url = $me->getFullURL() . "?target=$target&source={$this->mSource}" . $this->getURLExtras();
		$perpage = 10;

		if ($start > 1) {
			$nstart = $start - $perpage;
			$nurl =  $url ."&start=" . $nstart . "&q=" . urlencode($query);
			$s .= "<a href='$nurl'>" . wfMsg('importvideo_previous_results', 10) . "</a>";
		}

		$s .= "</td><td align='right'>";
		// no point offering a next button if there are less than 10 results
		if (sizeof($this->mResults) >= IV_RESULTS_PER_PAGE) {
			if ($maxResults < 0 || $start + IV_RESULTS_PER_PAGE < $maxResults) {
				$nstart = $start + $perpage;
				$nurl = $url . "&start=" . $nstart . "&q=" . urlencode($query);
				$s .= "<a href='$nurl'>" . wfMsg('importvideo_next_results', 10) . "</a>";
			}
		}
		$s .= "</td></tr></table>";
		return $s;
	}

	function getResults($url) {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_TIMEOUT, 5);
		$contents = curl_exec($ch);
		if (curl_errno($ch)) {
			echo "curl error {$url}: " . curl_error($ch) . "\n";
		}
		curl_close($ch);
		return $contents;
	}

	function updateVideoArticle($title, $text, $editSummary) {
		$a = new Article($title);
		$a->doEdit($text, $editSummary);
		self::mark_video_as_patrolled($a->getId());
	}

	function mark_video_as_patrolled($article_id) {
		global $wgUser;

		$fname = 'Importvideo::mark_video_as_patrolled';
		wfProfileIn( $fname );

		$dbw = wfGetDB(DB_MASTER);
		$dbw->update('recentchanges',
			array('rc_patrolled'=>1),
			array('rc_namespace'=>NS_VIDEO, 'rc_cur_id'=>$article_id),
			"mark_video_as_patrolled",
			array("ORDER BY" => "rc_id DESC", "LIMIT"=>1));

		wfProfileOut( $fname );
	}

	function urlCleaner($url) {
	  $U = explode(' ',$url);

	  $W =array();
	  foreach ($U as $k => $u) {
		if (stristr($u,'http') || (count(explode('.',$u)) > 1)) {
		  unset($U[$k]);
		  return $this->urlCleaner( implode(' ',$U));
		}
	  }
	  return implode(' ',$U);
	}

	function updateMainArticle($target, $editSummary) {
		global $wgOut, $wgRequest;
		$title = Title::makeTitle(NS_MAIN, $target);
		$vid = Title::makeTitle(NS_VIDEO, $target);
		$r = Revision::newFromTitle($title);
		$update = true;
		if (!$r) {
			$update = false;
			$text = "";
		} else {
			$text = $r->getText();
		}

		$tag = "{{" . $vid->getFullText() . "|}}";
		if ($wgRequest->getVal('description') != '') {
			$tag = "{{" . $vid->getFullText() . "|" . $wgRequest->getVal('description') . "}}";
		}
		$newsection .= "\n\n== " . wfMsg('video') . " ==\n{$tag}\n\n";
		$a = new Article($title);

		$newtext = "";

		// Check for existing video section in the target article
		preg_match("/^==[ ]*" . wfMsg('video') . "/im", $text, $matches, PREG_OFFSET_CAPTURE);
		if (sizeof($matches) > 0 ) {
			// There is an existing video section, replace it
			$i = $matches[0][1];
			preg_match("/^==/im", $text, $matches, PREG_OFFSET_CAPTURE, $i+1);
			if (sizeof($matches) > 0) {
				$j = $matches[0][1];
				// == Video == was not the last section
				$newtext = trim(substr($text, 0, $i)) . $newsection . substr($text, $j, strlen($text));
			} else {
				// == Video == was the last section append it
				$newtext = trim($text) . $newsection;
			}
			// existing section, change it.
		} else {
			// There is not an existng video section, insert it after steps
			// This section could be cleaned up to handle it if there was an existing video section too I guess
			$arr = preg_split('/(^==[^=]*?==\s*?$)/m', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
			$found = false;
			for ($i =0 ; $i < sizeof($arr); $i++) {
				if (preg_match("/^==[ ]*" . wfMsg('steps') . "/", $arr[$i])) {
					$newtext .= $arr[$i];
					$i++;
					if ($i < sizeof($arr))
						$newtext .= $arr[$i];
					$newtext = trim($newtext) . $newsection;
					$found = true;
				} else {
					$newtext .= $arr[$i];
				}
			}
			if (!$found) {
				$arr = preg_split('/(^==[^=]*?==\s*?$)/m', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
				$newtext = "";
				$newtext = trim($arr[0]) . $newsection;
				for ($i =1 ; $i < sizeof($arr); $i++) {
					$newtext .= $arr[$i];
				}
			}
		}
		if ($newtext == "")
			$newtext = $newsection;
		$watch = $title->userIsWatching();
		if ($update)
			$a->updateArticle($newtext, $editSummary, false, $watch);
		else
			$a->insertNewArticle($newtext, $editSummary, false, $watch);

		if ($wgRequest->getVal("popup") == "true") {
			$wgOut->clearHTML();
			$wgOut->disable();
			echo "<script type='text/javascript'>
			function onLoad() {
				var e = document.getElementById('video_text');
				e.value = \"" . htmlspecialchars($tag) . "\";
				pv_Preview();
				var summary = document.getElementById('wpSummary');
				if (summary.value != '')
					summary.value += ',  " . ($update ? wfMsg('importvideo_changingvideo_summary') : $editSummary) . "';
				else
					summary.value = '" . ($update ? wfMsg('importvideo_changingvideo_summary') : $editSummary) . "';
				closeModal();
			}
			onLoad();
				</script>
				";
		}
		$me = Title::makeTitle(NS_SPECIAL, "Importvideo");
		if ($wgRequest->getVal('wasnew') || $wgRequest->getVal('new')) {
			// log it, we track when someone uploads a video for a new article
			$params = array($title->getArticleID());
			$log = new LogPage( 'vidsfornew', false );
			$log->addEntry('added', $title, 'added');

			$wgOut->redirect($me->getFullURL() . "?new=1&skip=" . $title->getArticleID());
			return;
		} else if ($wgRequest->getVal('category')) {
			// they added a video to a category, keep them in the category mode
			$wgOut->redirect($me->getFullURL() . "?category=" . urlencode($wgRequest->getVal('category')));
			return;
		}
	}

	/**
	 * Parser setup functions, subclasses over ride parseStartElement
	 * and parseEndElement
	 */
	function parseDefaultHandler ($parser, $data) {
		if ($this->mCurrentTag) {
			if (is_array($this->mCurrentNode)) {
				if (isset($this->mCurrentNode[$this->mCurrentTag])) {
					$this->mCurrentNode[$this->mCurrentTag] .= $data;
				} else {
					$this->mCurrentNode[$this->mCurrentTag] = $data;
				}
			} else {
				$this->mResponseData[$this->mCurrentTag] = $data;
			}
		}
	}

	function parseResults($results) {
		$xml_parser = xml_parser_create();
		xml_set_element_handler($xml_parser, array($this, "parseStartElement"), array($this, "parseEndElement"));
		xml_set_default_handler($xml_parser, array($this, "parseDefaultHandler"));
		xml_parse($xml_parser, $results);
		xml_parser_free($xml_parser);
	}

	function isValid(&$timestring) {
		global $wgUser;
		$userGroups = $wgUser->getGroups();
		// Staff, admin and nabbers can see all vids
		if (in_array('staff', $userGroups) || in_array('admin', $userGroups) || in_array('newarticlepatrol', $userGroups)) {
			return true;
		}

		$ret = true;
		$pub = strtotime($timestring);
		if ($pub) {
			$current = time();
			$diff = $current - $pub;
			// If published more than 30 days ago, it's valid
			$ret = $diff > 60 * 60 * 24 * 30 ? true : false;
		}
		return $ret;
	}
}

/**
 * This class is used to grab a description from the user when they
 * insert their video
 */
class ImportvideoPopup extends UnlistedSpecialPage {

	function __construct() {
		parent::__construct( 'ImportvideoPopup' );
	}

	function execute($par) {
		global $wgOut, $wgRequest;
		$wgOut->setArticleBodyOnly(true);
		wfLoadExtensionMessages('Importvideo');
		$wgOut->addHTML('<div style="margin-top:20px">');
		$wgOut->addWikiText(wfMsg('importvideo_add_desc_details'));
		if ($wgRequest->wasPosted()) {
			$iv = Title::makeTitle(NS_SPECIAL, "Importvideo");
			$wgOut->addHTML("<form method='POST' name='importvideofrompopup' action='{$iv->getLocalUrl()}'>");
			$vals = $wgRequest->getValues();
			foreach($vals as $key=>$val) {
				if ($key != "title") {
					$wgOut->addHTML("<input type='hidden' name='{$key}' value=\"" . htmlspecialchars($val) . "\"/>");
				}
			}
			$wgOut->addHTML(' <p><center><textarea id="importvideo_comment" name="description" style="width:520px; height: 50px;margin-top: 10px"></textarea></p>
				<br/>
				<p><input type="submit" class="button primary" value="' . wfMsg('importvideo_popup_add_desc') . '" />
				</center>
				</p>
			</div></form>');
		} else {
			$wgOut->addHTML('<br /><center><p><textarea id="importvideo_comment" style="width:550px; height: 50px;"></textarea></p>
				<br/><br/>
				<input type="button" class="button primary" value="' . wfMsg('importvideo_popup_add_desc') . '" onclick="throwdesc();" /> <a href="#" onclick="$(\'#dialog-box\').dialog(\'close\'); return false;" class="button">' . wfMsg('importvideo_popup_changearticle') . '</a>
			</center>
			</div></form>
			');
		}
	}

}

/**
 *  This page is used for processing ajax requests to show a video preview in the guided editor
 */
class Previewvideo extends UnlistedSpecialPage {
	function __construct() {
		parent::__construct( 'Previewvideo' );
	}

	function execute( $par ) {
		global $wgRequest, $wgParser, $wgUser, $wgOut;

		$wgOut->disable();

		$target = isset( $par ) ? $par : $wgRequest->getVal( 'target' );
		$vt = Title::newFromURL($target);
		if (!$vt) return;
		$t = Title::makeTitle(NS_MAIN, $vt->getText());

		# can we parse from the main naemspace article to include the comment?
		$r = Revision::newFromTitle($t);
		if (!$r) return;
		$text = $r->getText();

		preg_match("/{{Video:[^}]*}}/", $text, $matches);
		if (sizeof($matches) > 0) {
			$comment = preg_replace("/.*\|/", "", $matches[0]);
			$comment = preg_replace("/}}/", "", $comment);
		}

		$rv = Revision::newFromTitle($vt);
		if (!$rv) return;
		$text = $rv->getText();
		$text = str_replace("{{{1}}}", $comment, $text);
		$html = $wgOut->parse($text, true, true) ;
		echo $html;
	}
}

/**
 * This is a leaderboard for users who are adding videos to new articles
 */
class Newvideoboard extends SpecialPage {


	function __construct() {
		parent::__construct( 'Newvideoboard' );
	}

	function execute ($par) {
		global $wgRequest, $wgOut, $wgUser, $wgLang;
		$target = isset( $par ) ? $par : $wgRequest->getVal( 'target' );
		$sk = $wgUser->getSkin();
		$dbr = &wfGetDB(DB_SLAVE);

		$this->setHeaders();

		$wgOut->addCSScode('pcc');

		$me = Title::makeTitle(NS_SPECIAL, "Newvideoboard");
		$now = wfTimestamp(TS_UNIX);

		// allow the user to grab the local patrol count relative to their own timezone
		if ($wgRequest->getVal('window', 'day') == 'day') {
			$links = "[" . $sk->makeLinkObj($me, wfMsg('videoboard_week'), "window=week") . "] [" . wfMsg('videoboard_day'). "]";
			$date1 = substr(wfTimestamp(TS_MW, $now - 24*3600*7), 0, 8) . "000000";
			$date2 = substr(wfTimestamp(TS_MW, $now + 24*3600), 0, 8) . "000000";
		} else {
			$links = "[" . wfMsg('videoboard_week') . "] [" . $sk->makeLinkObj($me, wfMsg('videoboard_day'), "window=day") . "]";
			$date1 = substr(wfTimestamp(TS_MW), 0, 8) . "000000";
			$date2 = substr(wfTimestamp(TS_MW, $now + 24*3600), 0, 8) . "000000";
		}

		$wgOut->addHTML($links);
		$wgOut->addHTML("<br/><br/><table width='500px' align='center' class='status'>" );

		$sql = "select log_user, count(*) as C
				from logging where log_type='vidsfornew' and log_timestamp > '$date1' and log_timestamp < '$date2'
				group by log_user order by C desc limit 20;";
		$res = $dbr->query($sql);
		$index = 1;
		$wgOut->addHTML("<tr>
						   <td></td>
							<td>User</td>
							<td  align='right'>" . wfMsg('videoboard_numberofvidsadded') . "</td>
							</tr>");

		while ( ($row = $dbr->fetchObject($res)) != null) {
			$u = User::newFromID($row->log_user);
			$count = number_format($row->C, 0, "", ',');
			$class = "";
			if ($index % 2 == 1)
				$class = 'class="odd"';
			$log = $sk->makeLinkObj(Title::makeTitle( NS_SPECIAL, 'Log'), $count, 'type=vidsfornew&user=' .  $u->getName());
			$wgOut->addHTML("<tr $class>
				<td>$index</td>
				<td>" . $sk->makeLinkObj($u->getUserPage(), $u->getName()) . "</td>
				<td  align='right'>{$log}</td>
				</tr>
			");
			$index++;
		}

		$wgOut->addHTML("</table></center>");
		if ($wgUser->getOption('patrolcountlocal', "GMT") != "GMT")  {
			$wgOut->addHTML("<br/><br/><i><font size='-2'>" . wfMsgWikiHtml('patrolcount_viewlocal_info') . "</font></i>");
		}
	}
}

