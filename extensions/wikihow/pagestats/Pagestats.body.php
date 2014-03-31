<?php

if (!defined('MEDIAWIKI')) die();

class Pagestats extends UnlistedSpecialPage {
	
	public function __construct() {
		parent::__construct('Pagestats');
	}

	public static function get30day($pageId, &$dbr) {
		global $wgMemc;
		
		//$key = "ps-30day-" . $pageId;
		//$val = $wgMemc->get($key);
		
		//if(!$val) {
			$val =  $dbr->selectField('pageview', 'pv_30day', array('pv_page' => $pageId));
			//$wgMemc->set($key, $val);
		//}
		
		return $val;
		
	}
	
	public static function get1day($pageId, &$dbr) {
		$val = $dbr->selectField('pageview', 'pv_1day', array('pv_page' => $pageId));
		
		return $val;
	}
	
	public static function update30day($pageId, $val) {
		global $wgMemc;
		
		$key = "ps-30day-" . $pageId;
		
		$wgMemc->set($key, $val);
	}

	public static function getTitusData($pageId) {
		global $IP, $wgLanguageCode;
		$url = WH_TITUS_API_HOST."/api.php?action=titus&subcmd=article&page_id=$pageId&language_code=$wgLanguageCode&format=json";
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_USERPWD, WH_DEV_ACCESS_AUTH);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_TIMEOUT, 5);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		$ret = curl_exec($ch);
		$curlErr = curl_error($ch);

		if ($curlErr) {
			$result['error'] = 'curl error: ' . $curlErr;
		} else {
			$result = json_decode($ret, FALSE);
		}
		return $result;
	}
	
    public static function getRatingReasonData($pageId, $type, &$dbr) {
        $val->total = $dbr->selectField('rating_reason', "count(*)", array("ratr_item" => $pageId, "ratr_type" => $type), __METHOD__);
        return $val;
    }

	public static function getRatingData($pageId, $tableName, $tablePrefix, &$dbr) {
		global $wgMemc;
		
		//$key = "ps-rating-" . $pageId;
		//$val = $wgMemc->get($key);
		
		//if(!$val) {
			$val->total = 0;
			$yes = 0;
		
			$res = $dbr->select($tableName, "{$tablePrefix}_rating as rating", array("{$tablePrefix}_page" => $pageId, "{$tablePrefix}_isdeleted" => 0), __METHOD__);
			while($row = $dbr->fetchObject($res)) {
				$val->total++;
				if($row->rating == 1)
					$yes++;
			}
			
			if($val->total > 0)
				$val->percentage = round($yes*1000/$val->total)/10;
			else
				$val->percentage = 0;

			
			//$wgMemc->set($key, $val);
			
		//}
			
		return $val;
	}
	
	function getFellowsTime($fellowEditTimestamp) {
		global $wgLang;
		$d = false;
		if (!$fellowEditTimestamp) {
			return false;
		}

		$ts = wfTimestamp( TS_MW, strtotime($fellowEditTimestamp));
		$hourMinute = $wgLang->sprintfDate("H:i", $ts);
		if ($hourMinute == "00:00") {
			$d = $wgLang->sprintfDate("j F Y", $ts);
		} else {
			$d = $wgLang->timeanddate($ts);
		}
		$result = "<p>" . wfMsg('ps-fellow-time') . " $d&nbsp;&nbsp;</p>";
		return $result;
	}


	public static function getPagestatData($pageId) {
		global $wgUser;

		$t = Title::newFromID($pageId);
		$skin = $wgUser->getSkin();
		$dbr = wfGetDB(DB_SLAVE);
		
		wfLoadExtensionMessages('Pagestats');
		
		$html = "<h3 style='margin-bottom:5px'>Staff-only data</h3>";
		$error = null;
		$titusData = self::getTitusData($pageId);
		if (!($titusData->titus) ) {
			$error = (string)json_encode($titusData);
			$html .= "<p>" . wfMsg('ps-error') . "</p>";
			$html .= "<hr style='margin:5px 0; '/>";
		} else {
			$titusData = $titusData->titus;
		}

		// pageview data
		$day30 = self::get30day($pageId, $dbr);
		$day1 = self::get1day($pageId, $dbr);
		$html .= "<p>{$day30} " . wfMsg('ps-pv-30day') . "</p>";
		$html .= "<p>{$day1} " . wfMsg('ps-pv-1day') . "</p>";

		if ($titusData) {
			// stu data
			$html .= "<hr style='margin:5px 0; '/>";
			$html .= "<p>" . wfMsg('ps-stu') . " {$titusData->ti_stu_10s_percentage_www}%&nbsp;&nbsp;{$titusData->ti_stu_3min_percentage_www}%&nbsp;&nbsp;{$titusData->ti_stu_10s_percentage_mobile}%</p>";
			$html .= "<p>" . wfMsg('ps-stu-views') . " {$titusData->ti_stu_views_www}&nbsp;&nbsp;{$titusData->ti_stu_views_mobile}</p>";
			if($t) {
				$html .= "<p><a href='#' class='clearstu'>Clear Stu</a></p>";
			}
		}
		
		// accuracy data
		$data = self::getRatingData($pageId, 'rating', 'rat', $dbr);
		$html .= "<hr style='margin:5px 0;' />";
		$html .= "<p>Accuracy: {$data->percentage}% of {$data->total} votes</p>";
		if($t) {
			$cl = Title::newFromText('Clearratings', NS_SPECIAL);
			$link = $skin->makeLinkObj($cl, 'Clear ratings', 'type=article&target='.$pageId);
			$html .= "<p>{$link}</p>";
		}

		$haveBabelfishData = false;
		$languageCode = null;
		if ($titusData) {
			$languageCode = $titusData->ti_language_code;
			// 10k yes/no data
			$html .= "<hr style='margin:5px 0; '/>";
			if($titusData->ti_is_top10k == 1) {
				$html .= "<p>" . wfMsg('ps-tk-list') . ' ' . $titusData->ti_top_list . '&nbsp;&nbsp;</p>' ;
				$html .= "<p>" . wfMsg('ps-tk-query') . " " . $titusData->ti_top10k . '&nbsp;&nbsp;</p>';
			}
			else {
				$html .= "<p>" . wfMsg('ps-tk-list') . ' none&nbsp;&nbsp;</p>' ;

			}
			// fellow data
			$html .= "<hr style='margin:5px 0; '/>";
			$html .= "<p>" . wfMsg('ps-fellow') . " ";
			$html .= $titusData->ti_last_fellow_edit? :"";
			$html .= "&nbsp;&nbsp;</p>";
			$html .= self::getFellowsTime($titusData->ti_last_fellow_edit_timestamp) ? :"";

			// babelfish rank
			$haveBabelfishData = true;
			$bfRank = $titusData->ti_babelfish_rank ?: "no data";
			$html .= "<hr style='margin:5px 0; '/>";
			$html .= "<p>" . wfMsg('ps-bfish') . ": {$bfRank}&nbsp;&nbsp;</p>";
		}

		// languages translated
		$lLinks = array();
		if ($languageCode) {
			try {
				$linksTo = TranslationLink::getLinksTo($languageCode, $pageId, true);
				foreach($linksTo as $link) {
					$href = $link->toURL;
					$lLinks[] = "<a href='".htmlspecialchars($href)."'>$link->toLang</a>";
				}
			} catch (DBQueryError $e) {
				$lLinks[] = "<p>".$e->getText()."</p>";
			}
		}

		// only print the line if we have not printed it above with babelfish data
		if (!$haveBabelfishData) {
			$html .= "<hr style='margin:5px 0;' />";
		}
		$html .= "<p>Translated: " . implode($lLinks, ',') . "</p>";

		// article id
		$html .= "<hr style='margin:5px 0;' />";
		$html .= "<p>Article Id: $pageId</p>";

		return array("body"=>$html, "error"=>$error);
	}

    public static function getSampleStatData($sampleTitle) {
		global $wgUser;
		$skin = $wgUser->getSkin();

		$html = "<h3 style='margin-bottom:5px'>Staff-only data</h3>";

		$dbr = wfGetDB(DB_SLAVE);

		$data = self::getRatingData($sampleTitle, 'ratesample', 'rats', $dbr);
		$html .= "<hr style='margin:5px 0;' />";
		$html .= "<p>Rating Accuracy: {$data->percentage}% of {$data->total} votes</p>";
		
        $cl = Title::newFromText('Clearratings', NS_SPECIAL);
        $link = $skin->makeLinkObj($cl, 'Clear ratings', 'type=sample&target='.$sampleTitle);
        $html .= "<p>{$link}</p>";

		$data = self::getRatingReasonData($sampleTitle, 'sample', $dbr);
		$html .= "<hr style='margin:5px 0;' />";
		$html .= "<p>Rating Reasons: {$data->total}</p>";
		
        $cl = SpecialPage::getTitleFor( 'AdminRatingReasons');
        $link = $skin->makeLinkObj($cl, 'View rating reasons', 'item='.$sampleTitle);
        $html .= "<p>{$link}</p>";

        $cl = SpecialPage::getTitleFor( 'AdminRemoveRatingReason', $sampleTitle);
        $link = $skin->makeLinkObj($cl, 'Clear rating reasons');
        $html .= "<p>{$link}</p>";
		
        return $html;
    }
	
	private static function addData(&$data) {
		$html = "";
		foreach($data as $key => $value) {
			$html .= "<tr><td style='font-weight:bold; padding-right:5px;'>" . $value . "</td><td>" . wfMsg("ps-" . $key) . "</td></tr>";
		}
		return $html;
	}

	public function execute() {
		global $wgRequest, $wgOut;
		$action = $wgRequest->getVal('action');
		if ($action == 'ajaxstats') {
            $wgOut->setArticleBodyOnly(true);
            $target = $wgRequest->getVal('target');

            $type = $wgRequest->getVal('type');
            if ($type == "article") {
                $title = !empty($target) ? Title::newFromURL($target) : null;
                if ($title && $title->exists()) {
                    $result = self::getPagestatData($title->getArticleID());
                    print json_encode($result);
                }
            } elseif ($type == "sample") {
                $title = !empty($target) ? Title::newFromText("sample/$target") : null;
                if ($title) {
                    $result = array(
                        'body' => self::getSampleStatData($target)
                    );
                    print json_encode($result);
                }
            }
		}
	}

	public static function getJSsnippet($type) {
?>
<script>
	function setupStaffWidgetClearStuLinks() {
		$('.clearstu').click(function(e) {
			e.preventDefault();
			var answer = confirm("reset all stu data for this page?");
			if (answer == false) {
				return;
			}
			var url = '/Special:Stu';
			var pagesList = window.location.origin + window.location.pathname;

			$.post(url, {
				"discard-threshold" : 0,
				"data-type": "summary",
				"action" : "reset",
				"pages-list": pagesList
				},
				function(result) {
					console.log(result);
				});
		});
	}

	if ($('#staff_stats_box').length) {
		$('#staff_stats_box').html('Loading...');
        var type = "<?php echo $type ?>";
        var target = (type == "sample") ? wgSampleName : wgTitle;

		getData = {'action':'ajaxstats', 'target':target, 'type':type};

		$.get('/Special:Pagestats', getData, function(data) {
				var result = (data && data['body']) ? data['body'] : 'Could not retrieve stats';
				$('#staff_stats_box').html(result);
				if (data && data['error']) {
					console.log(data['error']);
				}

				if ($('.clearstu').length) {
					setupStaffWidgetClearStuLinks();
				}
			}, 'json');
	}
</script>
<?
	}

}
