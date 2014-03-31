<?

class ImportvideoYoutube extends Importvideo {

	function addResult($v) {
		//$id, $title, $author_id, $author_name, $keywords) {
		global $wgOut, $wgRequest, $wgImportVideoBadUsers;

		$id = str_replace("http://gdata.youtube.com/feeds/api/videos/", "", $v['ID']);
		$min = min(strlen($v['CONTENT']), 255);
		$snippet = substr($v['CONTENT'], 0, $min);
		if ($min == 255) $snippet .= "...";
		$views = number_format($v['VIEWCOUNT'], 0);

		$keywords = $v['MEDIA:KEYWORDS'];
		$title = $v['TITLE'];
		$author = $v['NAME'];
		$length = $v['LENGTH'];
		$rating = number_format($v['AVGRATERS'], 2);
		$numvotes = $v['NUMRATERS'];


		if ($v['YT:NOEMBED'] == 1 || in_array(strtolower($v['NAME']), $wgImportVideoBadUsers) )  {
			$importOption = wfMsg('importvideo_noimportpossible');
		} else {
			$importOption = "<div class='embed_button'><input class='button primary' type='button' value='" . wfMsg('importvideo_embedit') . "' onclick='importvideo(\"{$id}\"); gatTrack(\"Registered_Editing\",\"Import_video\",\"Editing_page\");'/></div>";
		}


		$wgOut->addHTML("
		<div class='video_result' style='width: 500px;'>
			<div style='font-size: 120%; font-weight: bold; margin-bottom:10px;'>Video: {$title}</div>
			<table width='100%'>
				<tr>
					<td style='text-align:center'>
						<object width='200' height='200'>
						<param name='movie' value='http://www.youtube.com/v/{$id}&hl=en'></param>
						<param name='wmode' value='transparent' />
						<embed src='http://www.youtube.com/v/{$id}&hl=en' type='application/x-shockwave-flash' wmode='transparent' width='425' height='350'</embed> </object>
					</td>
				</tr>
				<tr>
					<td>
						<b>" . wfMsg('importvideo_rating') . ": </b>{$rating}" . wfMsg('importvideo_votes', $numvotes ) . " <br/><br/>
						<b>" . wfMsg('importvideo_views') . ": </b>{$views}  <br/><br/>
						<b>" . wfMsg('importvideo_description') . ": </b>{$snippet}<br /><br />
						{$importOption}
					</td>
				</tr>
				");

		$wgOut->addHTML(" </table></div> ");

	}

	function getTopResults($target, $limit = 10, $query = null) {
		global $wgRequest;
		$orderby = $wgRequest->getVal('orderby', 'relevance');
		$start = $wgRequest->getVal('start', 1);

		// let them pass in text if they so desire
		if (!$target instanceof Title) {
			$t = Title::newFromText($target);
			$target = $t->getText();
		}

		if (!$query) {
			$query = wfMsg('howto', $target);
		}
		$vq = urlencode($query);
		if ($orderby =='howto')
			$url = "http://gdata.youtube.com/feeds/api/videos/-/Howto?vq=" . urlencode($target) . "&start-index={$start}&max-results=$limit&format=5";
		else
			$url = "http://gdata.youtube.com/feeds/api/videos?vq=$vq+-expertvillage+-ehow&orderby={$orderby}&start-index={$start}&max-results=$limit&format=5";

		$results = $this->getResults($url);
		$this->parseResults($results);
#print_r($this);
	}

	function loadVideoText($id) {
		$url = "http://gdata.youtube.com/feeds/api/videos/$id";
		$results = $this->getResults($url);
		$this->parseResults($results);
		$title = Title::makeTitle(NS_VIDEO, $target);
		$v = $this->mResults[0];
		$content = $this->urlCleaner($v['CONTENT']);
		$text = "{{Curatevideo|youtube|$id|{$v['TITLE']}|{$v['MEDIA:KEYWORDS']}|$content|{$v['MEDIA:CATEGORY']}|{$desc}}}
{{VideoDescription|{{{1}}} }}";
		return $text;
	}

	function execute ($par) {
		global $wgRequest, $wgOut;

		#wfLoadExtensionMessages('Importvideo');

		$target = isset( $par ) ? $par : $wgRequest->getVal( 'target' );

		if ($wgRequest->wasPosted()) {
			// IMPORTING THE VIDEO NOW
			$id = $wgRequest->getVal('video_id');
			$desc = $wgRequest->getVal('description');
			$target = $wgRequest->getVal('target');

			$text = $this->loadVideoText($id);
			if ($text == null) {
				$wgOut->addHTML(wfMessage('importvideo_error_geting_results')->text());
				return;
			}
			$v = $this->mResults[0];
			$author = $v['NAME'];
			$badauthors = split("\n", wfMessage('Block_Youtube_Accounts')->text());;
			if ( in_array( $author, $badauthors) ) {
				$wgOut->addHTML(wfMessage('importvideo_youtubeblocked', $author)->text());
				return;
			}
			$title = Title::makeTitle(NS_VIDEO, $target);
			$vid = Title::makeTitle(NS_VIDEO, $title->getText());
			$editSummary = wfMessage('importvideo_addingvideo_summary')->text();
			$this->updateVideoArticle($vid, $text, $editSummary);
			$this->updateMainArticle($target, $editSummary);
			return;
		}


		if ($target == '') {
			$wgOut->addHTML(wfMessage('importvideo_notarget')->text());
			return;
		}

		$orderby = $wgRequest->getVal('orderby', 'relevance');
		$wgOut->addHTML($this->getPostForm($target));
		$this->getTopResults($target, 10, $wgRequest->getVal('q'));
		$wgOut->addHTML(" <br/>
			" . wfMsg('importvideo_youtube_sortby') . " <select name='orderby' id='orderby' onchange='changeUrl();'>
				<OPTION value='relevance' " . ($orderby == 'relevance' ? "SELECTED" : "") . "> " . wfMSg('importvideo_youtubesort_rel') . "</OPTION>
				<OPTION value='howto' " . ($orderby == 'howto' ? "SELECTED" : "") . "> " . wfMsg('importvideo_youtubesort_howto') . "</OPTION>
				<OPTION value='rating' " . ($orderby == 'rating' ? "SELECTED" : "") . "> " . wfMsg('importvideo_youtubesort_rating') . "</OPTION>
				<OPTION value='published' " . ($orderby == 'published' ? "SELECTED" : "") . "> " . wfMsg('importvideo_youtubesort_rel') . "</OPTION>
				<OPTION value='viewCount' " . ($orderby == 'viewCount' ? "SELECTED" : "") . "> " . wfMsg('importvideo_youtubesort_views') . "</OPTION>
			</select>
			<br/><br/>
			");

		if ($this->mResults == null) {
			$wgOut->addHTML(wfMsg("importvideo_error_geting_results"));
			return;
		}



		#print_r($this->mResults);
		if (sizeof($this->mResults) == 0) {
			#$wgOut->addHTML(wfMsg('importvideo_noresults', $target) . htmlspecialchars($results) );
			$wgOut->addHTML(wfMsg('importvideo_noresults', $query));
			$wgOut->addHTML("</form>");
			return;
		}

		$wgOut->addHTML(wfMsg('importvideo_results', $query) );

		$resultsShown = false;
		foreach ($this->mResults as $v) {
			if (!$this->isValid($v['PUBLISHED'])) {
				continue;
			}
			$resultsShown = true;
			$id = str_replace("http://gdata.youtube.com/feeds/api/videos/", "", $v['ID']);
			$min = min(strlen($v['CONTENT']), 255);
			$snippet = substr($v['CONTENT'], 0, $min);
			if ($min == 255) $snippet .= "...";
			$views = number_format($v['VIEWCOUNT'], 0);
			$this->addResult($v);
		}

		if (!$resultsShown) {
			$wgOut->addHTML(wfMsg('importvideo_noresults', $query));
			$wgOut->addHTML("</form>");
			return;
		}

		$wgOut->addHTML("</form>");

		$num = $this->mResponseData['OPENSEARCH:TOTALRESULTS'];
		$wgOut->addHTML($this->getPreviousNextButtons($num));
	}

	function parseStartElement ($parser, $name, $attrs) {
		switch ($name) {
			case "MEDIA:THUMBNAIL":
				$this->mCurrentNode['MEDIA:THUMBNAIL'] = $attrs['URL'];
				break;
			case "YT:STATISTICS":
				$this->mCurrentNode['VIEWCOUNT'] = $attrs['VIEWCOUNT'];
				$this->mCurrentNode['FAVORITECOUNT'] = $attrs['FAVORITECOUNT'];
				break;
			case "GD:RATING":
				$this->mCurrentNode['NUMRATERS'] = $attrs['NUMRATERS'];
				$this->mCurrentNode['AVGRATERS'] = $attrs['AVERAGE'];
				break;
			case "YT:DURATION":
				$this->mCurrentNode['LENGTH'] = $attrs['SECONDS'];
				break;
			case "YT:NOEMBED":
				$this->mCurrentNode['YT:NOEMBED'] = 1;
		}
		if ($name == 'ENTRY') {
			$this->mCurrentNode = array();
		}
		$this->mCurrentTag = $name;
	}

	function parseEndElement ($parser, $name) {
		if ($name == "ENTRY") {
			$this->mResults[] = $this->mCurrentNode;
			$this->mCurrentNode = null;
		}

	}

}

