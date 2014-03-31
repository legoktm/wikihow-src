<?
class ThumbRank {
	const EXCEPTION_MALFORMED_WIKITEXT = "Malformed wikitext in section exception";
	const EXCEPTION_UNMATCHED_WIKITEXT = "Unable to match wikitext to html exception";

	var $r = null;
	var $wikitext = null;

	function __construct(&$r) {
		$this->r = $r;
		$this->wikitext = $r->getText();
	}

	public function reorder($saveWikitext = false) {
		$articleMap = $this->getArticleMap();
		$rankMap = $this->getRankMap();
		$newOrder = array();
		$sectionReplaced = false;
		
		// Iterate through the ranking by type (tips or warnings)
		foreach ($rankMap as $type => $map) {
			if (!empty($map)) {
				// Get corresponding wikitext from articleMap
				foreach ($map as $item) {
					$itemWikitext = $articleMap[$type][$item]['wikitext']; 
					if ($itemWikitext) {
						$newOrder[] = $articleMap[$type][$item]['wikitext'];
						unset($articleMap[$type][$item]);
					}
				}

				// Add remaining unrated items (ie tips/warnings without votes)
				foreach ($articleMap[$type] as $item) {
					$newOrder[] = $item['wikitext'];
				}

				// No point in replacing the section if only one tip or warning
				if (sizeof($newOrder) > 1) {
					$sectionReplaced = true;
					// Replace the wikitext section with the newly ordered tips or warnings
					$this->replaceSection($type, implode("\n", $newOrder));
				}
			}
			$newOrder = array();
		}

		if ($sectionReplaced && $saveWikitext) {
			$this->saveArticle();
		}
	}

	private function saveArticle() {
		$t = $this->r->getTitle();	
		$a = new Article($t);
		$a->doEdit($this->wikitext, 'reordering tips and warnings based on votes', EDIT_UPDATE | EDIT_MINOR);
	}

	private function getRankMap() {
		$id = $this->r->getTitle()->getArticleId();
		$sql = "SELECT tr_hash, ((tr_up + 1.9208) / (tr_up + tr_down) - 
                   1.96 * SQRT((tr_up * tr_down) / (tr_up + tr_down) + 0.9604) / 
                   (tr_up + tr_down)) / (1 + 3.8416 / (tr_up + tr_down)) 
       				AS rank, tr_type FROM thumb_ratings WHERE tr_page_id = $id
       				ORDER BY tr_type,rank DESC;";
		$dbr = wfGetDB(DB_SLAVE);
		$res = $dbr->query($sql, __METHOD__);
		$rank = array(wfMsg('tips') => array(), wfMsg('warnings') => array());
		foreach ($res as $row) {
			$row = get_object_vars($row);
			$type = $row['tr_type'] == ThumbRatings::RATING_TIP ? wfMsg('tips') : wfMsg('warnings');
			$rank[$type][] = $row['tr_hash'];
		}
		return $rank;
	}

	private function getArticleMap() {
		$html = $this->getNonMobileHtml($this->r);
		$xpath = $this->getXPath($html, $this->r);

		$types = array(ThumbRatings::RATING_TIP => wfMsg("tips"), ThumbRatings::RATING_WARNING => wfMsg("warnings"));
		$hashes = array();
		foreach ($types as $k => $type) {
			$nodes = $xpath->query('//div[@id="' . strtolower($type) . '"]/ul/li');
			$wikitext = $this->getWikitextArray($type, $this->wikitext);
			foreach ($nodes as $j => $node) {
				$hash = md5($node->innerHTML);
				if (empty($wikitext[$j])) {
					throw new Exception(self::EXCEPTION_UNMATCHED_WIKITEXT);
				}
				$hashes[$type][$hash] = array('hash' => $hash, 'html' => $node->innerHTML, 'wikitext' => $wikitext[$j]);
			}
		}
		return $hashes;
	}

	public static function getXPath(&$bodyHtml, &$r) {
		global $wgWikiHowSections, $IP, $wgTitle;

		$lang = MobileWikihow::getSiteLanguage();
		// munge steps first
		$opts = array(
			'no-ads' => true,
		);
		require_once("$IP/skins/WikiHowSkin.php");
		$oldTitle = $wgTitle;
		$wgTitle = $r->getTitle();

		$vars['bodyHtml'] = WikihowArticleHTML::postProcess($bodyHtml, $opts);
		$vars['lang'] = $lang;
		EasyTemplate::set_path(dirname(__FILE__).'/');
		$html = EasyTemplate::html('thumb_html.tmpl.php', $vars);

		require_once("$IP/extensions/wikihow/mobile/JSLikeHTMLElement.php");
		$doc = new DOMDocument('1.0', 'utf-8');
		$doc->registerNodeClass('DOMElement', 'JSLikeHTMLElement');
		$doc->strictErrorChecking = false;
		$doc->recover = true;
		@$doc->loadHTML($html);
		$doc->normalizeDocument();
		$xpath = new DOMXPath($doc);
		$wgTitle = $oldTitle;
		return $xpath;
	}

	private function getWikitextArray($type, &$wikitext) {
		$section = Wikitext::getSection($wikitext, $type, true);
		$tips = Wikitext::splitTips($section[0]);
		if (!empty($tips)) {
			// Special case:  If first tip/warning isn't a properly formed wikitext tip
			// ie: a tip/warning preceded with a '*', don't return a wikitext array
			// as the section is malformed
			if (preg_match('@^[^*]@', $tips[0])) {
				throw new Exception(self::EXCEPTION_MALFORMED_WIKITEXT . ": \"" . $tips[0] . "\"");
			}
			return $tips;
		}
	}

	public static function getNonMobileHtml(&$r) {
		global $wgOut, $wgParser, $wgTitle, $wgUser;

		$oldTitle = $wgTitle;
		$wgTitle = $r->getTitle();
	
		$popts = $wgOut->parserOptions();
		$popts->setTidy(true);
		$t = $r->getTitle();		
		$parser = new Parser;
		$html = $parser->parse($r->getText(), $t, $popts, true, true, $r->getId());
		$popts->setTidy(false);
	
		$wgTitle = $oldTitle;

		return $html->mText;
	}

	public function getMobileHtml() {
		global $wgOut, $wgParser, $IP, $wgTitle;
		require_once("$IP/extensions/wikihow/mobile/MobileHtmlBuilder.class.php");

		$r = $this->r;
		$t = $r->getTitle();
		$oldTitle = $wgTitle;
		$wgTitle = $t;

		$popts = $wgOut->parserOptions();
		$popts->setTidy(true);
		$wgParser->disableCache();
		$parser = new Parser;
		$html = $parser->parse($this->wikitext, $t, $popts, true, true, $r->getId());
		$html = $html->mText;
		$popts->setTidy(false);
		$m = new MobileArticleBuilder();
		$html = $m->createByHtml($r->getTitle(), $html);
		
		$wgTitle = $oldTitle;

		return $html;
	}

	private function replaceSection($sectionName, $wikitext) {
		global $wgParser;

		$section = Wikitext::getSection($this->wikitext, $sectionName, true);
		if (empty($section[0]))  {
			throw new Exception("Couldn't find '$sectionName' section");
		}
		$newTxt = "== $sectionName ==\n" . $wikitext;

		$this->wikitext = $wgParser->replaceSection($this->wikitext, $section[1], $newTxt);
	}

	public function getWikitext() {
		return $this->wikitext;
	}
}
