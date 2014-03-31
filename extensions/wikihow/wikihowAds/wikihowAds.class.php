<?php

/**********************
 * 
 *  Here are all the ad units we have currently:
 *  intro: At the bottom of the intro
 *  0: Text ad after the first step
 *  1: Text ad after the last step
 *  2: Text ad in a section if there are no tips
 *  2a: Text ad at the end of the tips section
 *  4: Image ad in the right rail, only in INTERNATIONAL
 *	5: Docviewer: Image ad in sidebar on the samples page
 *  6: Docviewer2: Image ad at the top of the samples page
 *  7: Docviewer3: Text ads at the bottom of the samples page
 *  8: Linkunit2: Link unit in the right rail
 * 
 *********************/

if (!defined('MEDIAWIKI')) die();

class wikihowAds {
	
	static $mGlobalChannels = array();
	static $mGlobalComments = array();
	public static $mCategories = array();

	var $adsLoaded = false;
	var $ads;
	static $isABTest = null;
	static $hasAltMethods = false;
	
	function wikihowAds() {
		$this->ads = array();
	}
	
	public static function getSetup() {
		global $wgUser, $IP, $wgMemc;
		
		$isHHM = wikihowAds::isHHM();
		$isABTest = wikihowAds::isABTestArticle();
		$cachekey = wfMemcKey('ads_setup', intval($isHHM), intval($isABTest), WH_SITEREV);
		//$html = $wgMemc->get($cachekey);
		$html = null;
		if ($html === null) {
			$js = wfMessage('Wikihowads_setup', $isHHM, intVal($isABTest))->text();
			require_once("$IP/extensions/min/lib/JSMinPlus.php");
			$adsClass = file_get_contents("$IP/extensions/wikihow/wikihowAds/wikihowAds.js");
			$min = JSMinPlus::minify($adsClass . $js);
			$html = <<<EOHTML
<!-- MediaWiki:wikihowads_setup -->
<script type='text/javascript'>
<!--
$min
//-->
</script>
EOHTML;
			$wgMemc->set($cachekey, $html);
		}

		return $html;
	}

	public static function getAdUnitPlaceholder($num, $isLinkUnit = false, $postLoad = true) {
		global $wgSingleLoadAllAds, $wgEnableLateLoadingAds, $wgUser, $wgTitle;

		if(self::adExclusions($wgTitle))
			return "";

		if ( !self::isCombinedCall($num) ) {
			$unit = !$isLinkUnit ? self::getAdUnit($num) : self::getLinkUnit($num);
		} else {
			$unit = !$isLinkUnit ? self::getWikihowAdUnit($num) : self::getLinkUnit($num);
		}
		$adID = !$isLinkUnit ? 'au' . $num : 'lu' . $num;
		
		if($wgSingleLoadAllAds && !$isLinkUnit && $num != 4) {
			
		}

		static $postLoadTest = null;
		if (!$wgEnableLateLoadingAds) {
			$postLoad = false;
		}

		if ($postLoadTest == null) {
			$postLoadTest = mt_rand(1,2);
			if ($postLoadTest == 1)
				// no post load
				self::$mGlobalChannels[] = "2490795108";
			else
				// yes post load
				self::$mGlobalChannels[] = "7974857016";
		}

		return $unit;
	}
	
	/***
	 * 
	 * Generally our text ads are all combined,
	 * and image ads cannot be combined. Occasionally
	 * other ads besides image ads we don't want
	 * combine into one call
	 * 
	 ***/
	function isCombinedCall($ad) {
		global $wgLanguageCode;

		if($wgLanguageCode == "en") {
			return false;
		}
		$adString = strval($ad);
		switch($adString) {
			case "4":
			case "4b":
			case "4c":
			case "docviewer":
			case "docviewer2":
			case "docviewer2a":
			case "top":
			case "bottom":
				$ret =  false;
				break;
			default:
				$ret = true;
			
		}
		return $ret;
	}
	
	function adExclusions($title){
		if (!$title || !$title->exists()) {
			return false;
		}

		$msg = ConfigStorage::dbGetConfig('ad-exclude-list'); //popular companies
		$articles = split("\n", $msg);

		if(in_array($title->getArticleID(), $articles)) {
			return true;
		} else {
			return false;
		}
	}
	
	function getLinkUnit($num) {
		global $wgUser;
		$channels = self::getCustomGoogleChannels('linkunit' . $num, false);
		$s = wfMessage('linkunit' . $num, $channels[0])->text();
		$s = "<div class='wh_ad'>" . preg_replace('/\<[\/]?pre[^>]*>/', '', $s) . "</div>";
		return $s;
	}

	function getAdUnit($num) {
		global $wgUser, $wgLanguageCode, $wgTitle;
		if($wgLanguageCode == "en") {
			$tempTitle = $wgTitle->getText();
			if($tempTitle == "Get Caramel off Pots and Pans"){
				if(is_int($num) && $num == 4)
					$s = wfMessage('adunit_test')->text();
			}
			else {
				$channels = self::getCustomGoogleChannels('adunit' . $num, false);
				$s = wfMessage('adunit' . $num, $channels[0], self::$hasAltMethods)->text();
			}
		}
		else {
			$channels = self::getInternationalChannels();
			$s = wfMessage('adunit' . $num, $channels)->text();
		}

		//taking out wrapping <div class="wh_ad" b/c for current test we can't have that
		$s = "" . preg_replace('/\<[\/]?pre[^>]*>/', '', $s) . "";
		return $s;
	}
	
	function getWikihowAdUnit($num) {
		global $wgUser, $wgLanguageCode;
		if ($wgLanguageCode == "en") { 
			$channelArray = self::getCustomGoogleChannels('adunit' . $num, false);
			$channels = $channelArray[0];
		}
		else
			$channels = self::getInternationalChannels();
		
		$params = self::getCSIParameters($num);
		
		if($params['slot'] == null || $params['width'] == null || $params['height'] == null || $params['max_ads'] == null) {
			//we don't have the required information, so lets spit out an error message
			$tmpl = new EasyTemplate( dirname(__FILE__) );
			$tmpl->set_vars(array(
				'adId' => $num,
				'params' => $params,
			));
			$s = $tmpl->execute('wikihowError.tmpl.php');
		}
		else {
			$tmpl = new EasyTemplate( dirname(__FILE__) );
			$tmpl->set_vars(array(
				'adId' => $num,
				'channels' => $channels,
				'params' => $params,
			));

			if(wikihowAds::isABTestArticle()) {
				$s = $tmpl->execute('wikihowAdAsync.tmpl.php');
			} else {
				if ($wgLanguageCode == "en") {
					$s = $tmpl->execute('wikihowAdCSI.tmpl.php');
				}
				else {
					$tmpl->set_vars(array('adLabel' => wfMsg('ad_label')));
					$s = $tmpl->execute('wikihowAdCSIIntl.tmpl.php');
				}
			}

			//$s = "<div class='wh_ad'>" . $s . "</div>";
		}
		return $s;
	}
	
	static public function getCSIParameters($adNum) {
		global $wgLanguageCode;
		
		$adSizes = array(
			"intro" =>		array("width" => 671, "height" => 120, "max_ads" => 2),
			"0" =>			array("width" => 629, "height" => 120, "max_ads" => 2),
			"1" =>			array("width" => 627, "height" => 180, "max_ads" => 3),
			"2" =>			array("width" => 607, "height" => 180, "max_ads" => 3),
			"2a" =>			array("width" => 607, "height" => 180, "max_ads" => 3),
			"7" =>			array("width" => 613, "height" => 120, "max_ads" => 2),
			"docviewer3" =>	array("width" => 621, "height" => 120, "max_ads" => 3)
		);
		
		$adSlots = array(
			"en" => array("intro" => "8579663774", "0" => "5205564977", "1" => "7008858971", "2" => "7274067370", "2a" => "2533130178", "7" => "4009863375", "docviewer3" => "3079259774"),
			"es" => array("intro" => "2950638973", "0" => "4427372174", "1" => "5904105372", "2" => "1334304979", "2a" => "8857571779", "7" => "7380838578"),
			"fr" => array("intro" => "7263087379", "0" => "8739820574", "1" => "1216553779", "2" => "5646753377", "2a" => "4170020176", "7" => "2693286977"),
			"it" => array("intro" => "5925954977", "0" => "7402688174", "1" => "1076952977", "2" => "4309620974", "2a" => "2832887770", "7" => "1356154575"),
			"nl" => array("intro" => "7123486573", "0" => "8600219770", "1" => "1076952977", "2" => "5507152571", "2a" => "4030419379", "7" => "2553686171"),
			"pt" => array("intro" => "4287771370", "0" => "5764504576", "1" => "7241237772", "2" => "4148170578", "2a" => "2671437375", "7" => "8717970978"),
			"de" => array("intro" => "7101636972", "0" => "8578370175", "1" => "1055103375", "2" => "5485302970", "2a" => "4008569778", "7" => "2531836571"),
			"hi" => array("intro" => "8460618972", "0" => "9937352170", "1" => "2414085379", "2" => "6844284972", "2a" => "5367551779", "7" => "3890818573"),
			"ru" => array("intro" => "9291645375", "0" => "7814912177", "1" => "4721844971", "2" => "1768378579", "2a" => "3245111774", "7" => "6338178978"),
			"zh" => array("intro" => "6399420978", "0" => "4922687771", "1" => "3306353770", "2" => "9352887376", "2a" => "1829620571", "7" => "9492488178"),
		);
		
		$adString = strval($adNum);
		$params = array();
		
		$params['width'] = $adSizes[$adString]['width'];
		$params['height'] = $adSizes[$adString]['height'];
		$params['max_ads'] = $adSizes[$adString]['max_ads'];
		$params['slot'] = $adSlots[$wgLanguageCode][$adString];
		
		return $params;
	}
	
	function getIatestAd() {
		global $wgTitle;
		
        if ($wgTitle->getNamespace() == NS_MAIN) {
			$titleUrl = $wgTitle->getFullURL();
			
			$msg = wfMessage('IAtest')->text();
			$articles = split("\n", $msg);
			foreach ($articles as $article) {
				if($article == $titleUrl){
					return wikihowAds::getAdUnitPlaceholder(4);
				}
			}
		}
	}
	
	public static function getGlobalChannels() {
		global $wgTitle, $wgUser;

		self::$mGlobalChannels[] = "1640266093";
		self::$mGlobalComments[] = "page wide track";

        // track WRM articles in Google AdSense
		// but not if they're included in the
		// tech buckets above
        if ($wgTitle->getNamespace() == NS_MAIN) {
            $dbr = wfGetDB(DB_MASTER);
            $minrev = $dbr->selectField('revision', 'min(rev_id)', array('rev_page'=>$wgTitle->getArticleID()), __METHOD__);
			$details = $dbr->selectRow('revision', array('rev_user_text', 'rev_timestamp'), array('rev_id'=>$minrev), __METHOD__);
			$fe = $details->rev_user_text;

			//Tech buckets (no longer only WRM)
			$foundTech = false;
			$title = $wgTitle->getFullURL();
			$titleUrl = $wgTitle->getFullURL();
			$msg = ConfigStorage::dbGetConfig('T_bin1'); //popular companies
			$articles = split("\n", $msg);
			foreach ($articles as $article) {
				if($article == $title){
					$foundTech = true;
					$ts = $details->rev_timestamp;
					if (preg_match("@^201106@", $ts)){
						self::$mGlobalChannels[] = "5265927225";
					} else if (preg_match("@^201105@", $ts)){
						self::$mGlobalChannels[] = "2621163941";
					} else if (preg_match("@^201104@", $ts)){
						self::$mGlobalChannels[] = "6703830173";
					} else if (preg_match("@^201103@", $ts)){
						self::$mGlobalChannels[] = "7428198201";
					} else if (preg_match("@^201102@", $ts)){
						self::$mGlobalChannels[] = "6027428251";
					} else if (preg_match("@^201101@", $ts)){
						self::$mGlobalChannels[] = "3564919246";
					}
					break;
				}
			}

			if (!$foundTech) {
				$msg = ConfigStorage::dbGetConfig('T_bin2'); //startup companies
				$articles = split("\n", $msg);
				foreach ($articles as $article) {
					if($article == $title){
						$foundTech = true;
						$ts = $details->rev_timestamp;
						if (preg_match("@^201112@", $ts)){
							self::$mGlobalChannels[] = "4113109859";
						} else if (preg_match("@^201111@", $ts)){
							self::$mGlobalChannels[] = "1967209400";
						} else if (preg_match("@^201110@", $ts)){
							self::$mGlobalChannels[] = "0168911685";
						} else if (preg_match("@^201109@", $ts)){
							self::$mGlobalChannels[] = "5356416885";
						} else if (preg_match("@^201108@", $ts)){
							self::$mGlobalChannels[] = "3273638668";
						} else if (preg_match("@^201107@", $ts)){
							self::$mGlobalChannels[] = "9892808753";
						} else if (preg_match("@^201106@", $ts)){
							self::$mGlobalChannels[] = "3519312489";
						} else if (preg_match("@^201105@", $ts)){
							self::$mGlobalChannels[] = "2958013308";
						} else if (preg_match("@^201104@", $ts)){
							self::$mGlobalChannels[] = "2240499801";
						} else if (preg_match("@^201103@", $ts)){
							self::$mGlobalChannels[] = "9688666159";
						} else if (preg_match("@^201102@", $ts)){
							self::$mGlobalChannels[] = "2421515764";
						} else if (preg_match("@^201101@", $ts)){
							self::$mGlobalChannels[] = "8503617448";
						}
						break;
					}
				}
			}

            if ($fe == 'WRM' && !$foundTech) { //only care if we didn't put into a tech bucket
				self::$mGlobalComments[] = "wrm";
				$ts = $details->rev_timestamp;
				
				if (preg_match("@^201112@", $ts)){
					self::$mGlobalChannels[] = "6155290251";
				} else if (preg_match("@^201111@", $ts)){
					self::$mGlobalChannels[] = "6049972339";
				} else if (preg_match("@^201110@", $ts)){
					self::$mGlobalChannels[] = "0763990979";
				} else if (preg_match("@^201109@", $ts)){
					self::$mGlobalChannels[] = "4358291042";
				} else if (preg_match("@^201108@", $ts)){
					self::$mGlobalChannels[] = "0148835175";
				} else if (preg_match("@^201107@", $ts)){
					self::$mGlobalChannels[] = "2390612184";
				} else if (preg_match("@^201106@", $ts)){
					self::$mGlobalChannels[] = "1532661106";
				} else if (preg_match("@^201105@", $ts)){
					self::$mGlobalChannels[] = "6709519645";
				} else if (preg_match("@^201104@", $ts)){
					self::$mGlobalChannels[] = "8239478166";
				} else if (preg_match("@^201103@", $ts)){
					self::$mGlobalChannels[] = "1255784003";
				} else if (preg_match("@^201102@", $ts)){
					self::$mGlobalChannels[] = "7120312529";
				} else if (preg_match("@^201101@", $ts)){
					self::$mGlobalChannels[] = "7890650737";
				} else if (preg_match("@^201012@", $ts)){
					self::$mGlobalChannels[] = "9742218152";
				} else if(preg_match("@^201011@", $ts)){
					self::$mGlobalChannels[] = "8485440130";
				} else if(preg_match("@^201010@", $ts)){
					self::$mGlobalChannels[] = "7771792733";
				} else if(preg_match("@^201009@", $ts)) {
				   self::$mGlobalChannels[] = "8422911943";
				} else if (preg_match("@^201008@", $ts)) {
				   self::$mGlobalChannels[] = "3379176477";
				} 
            } else if (in_array($fe, array('Burntheelastic', 'CeeZee', 'Claricea', 'EssAy', 'JasonArton', 'Nperry302', 'Sugarcoat'))) {
                self::$mGlobalChannels[] = "8537392489";
                self::$mGlobalComments[] = "mt";
            } else {
                self::$mGlobalChannels[] = "5860073694";
                self::$mGlobalComments[] = "!wrm && !mt";
            }
			
			//Original WRM bucket
			$msg = ConfigStorage::dbGetConfig('Dec2010_bin0');
			$articles = split("\n", $msg);
			foreach ($articles as $article) {
				if($article == $titleUrl){
					self::$mGlobalChannels[] = "8110356115"; //original wrm channels
					break;
				}
			}
			

			//WRM buckets
			$found = false;
			$title = $wgTitle->getFullText();
			$msg = ConfigStorage::dbGetConfig('Dec2010_bin1');
			$articles = split("\n", $msg);
			foreach ($articles as $article) {
				if($article == $title){
					$found = true;
					self::$mGlobalChannels[] = "8052511407";
					break;
				}
			}
			if(!$found){
				$msg = ConfigStorage::dbGetConfig('Dec2010_bin2');
				$articles = split("\n", $msg);
				foreach ($articles as $article) {
					if($article == $title){
						$found = true;
						self::$mGlobalChannels[] = "8301953346";
						break;
					}
				}
			}
			if(!$found){
				$msg = ConfigStorage::dbGetConfig('Dec2010_bin3');
				$articles = split("\n", $msg);
				foreach ($articles as $article) {
					if($article == $title){
						$found = true;
						self::$mGlobalChannels[] = "7249784941";
						break;
					}
				}
			}
			if(!$found){
				$msg = ConfigStorage::dbGetConfig('Dec2010_bin4');
				$articles = split("\n", $msg);
				foreach ($articles as $article) {
					if($article == $title){
						$found = true;
						self::$mGlobalChannels[] = "8122486186";
						break;
					}
				}
			}
			if(!$found){
				$msg = ConfigStorage::dbGetConfig('Dec2010_bin5');
				$articles = split("\n", $msg);
				foreach ($articles as $article) {
					if($article == $title){
						$found = true;
						self::$mGlobalChannels[] = "8278846457";
						break;
					}
				}
			}
			if(!$found){
				$msg = ConfigStorage::dbGetConfig('Dec2010_bin6');
				$articles = split("\n", $msg);
				foreach ($articles as $article) {
					if($article == $title){
						$found = true;
						self::$mGlobalChannels[] = "1245159133";
						break;
					}
				}
			}
			if(!$found){
				$msg = ConfigStorage::dbGetConfig('Dec2010_bin7');
				$articles = split("\n", $msg);
				foreach ($articles as $article) {
					if($article == $title){
						$found = true;
						self::$mGlobalChannels[] = "7399043796";
						break;
					}
				}
			}
			if(!$found){
				$msg = ConfigStorage::dbGetConfig('Dec2010_bin8');
				$articles = split("\n", $msg);
				foreach ($articles as $article) {
					if($article == $title){
						$found = true;
						self::$mGlobalChannels[] = "6371049270";
						break;
					}
				}
			}
			if(!$found){
				$msg = ConfigStorage::dbGetConfig('Dec2010_bin9');
				$articles = split("\n", $msg);
				foreach ($articles as $article) {
					if($article == $title){
						$found = true;
						self::$mGlobalChannels[] = "9638019760"; //WRM Bucket: WRG-selected
						break;
					}
				}
			}

			$msg = ConfigStorage::dbGetConfig('Dec2010_e1');
			$articles = split("\n", $msg);
			foreach ($articles as $article) {
				if($article == $titleUrl){
					self::$mGlobalChannels[] = "8107511392"; //WRM Bucket: E1
					break;
				}
			}

			$msg = ConfigStorage::dbGetConfig('Dec2010_e2');
			$articles = split("\n", $msg);
			foreach ($articles as $article) {
				if($article == $titleUrl){
					self::$mGlobalChannels[] = "3119976353"; //WRM Bucket: E2
					break;
				}
			}
			
			$msg = ConfigStorage::dbGetConfig('DrawTest'); //drawing articles
			$articles = split("\n", $msg);
			foreach ($articles as $article) {
				if($article == $titleUrl){
					$ts = $details->rev_timestamp;
					self::$mGlobalChannels[] = "4881792894"; //WRM Bucket: E2
					break;
				}
			}

			if (self::$mCategories['Recipes'] != null) {
				self::$mGlobalChannels[] = "5820473342"; //Recipe articles
			}
			
			$msg = ConfigStorage::dbGetConfig('CS_a'); //content strategy A
			$articles = split("\n", $msg);
			foreach ($articles as $article) {
				if($article == $titleUrl){
					$ts = $details->rev_timestamp;
					self::$mGlobalChannels[] = "8989984079"; //Content Strategy A
					break;
				}
			}
			
			$msg = ConfigStorage::dbGetConfig('CS_b'); //content strategy B
			$articles = split("\n", $msg);
			foreach ($articles as $article) {
				if($article == $titleUrl){
					$ts = $details->rev_timestamp;
					self::$mGlobalChannels[] = "3833770891"; //Content Strategy B
					break;
				}
			}
			
			$msg = ConfigStorage::dbGetConfig('CS_c'); //content strategy C
			$articles = split("\n", $msg);
			foreach ($articles as $article) {
				if($article == $titleUrl){
					$ts = $details->rev_timestamp;
					self::$mGlobalChannels[] = "5080980738"; //Content Strategy C
					break;
				}
			}
			
			$msg = ConfigStorage::dbGetConfig('CS_d'); //content strategy D
			$articles = split("\n", $msg);
			foreach ($articles as $article) {
				if($article == $titleUrl){
					$ts = $details->rev_timestamp;
					self::$mGlobalChannels[] = "3747905129"; //Content Strategy D
					break;
				}
			}
			
			$msg = ConfigStorage::dbGetConfig('CS_e'); //content strategy E
			$articles = split("\n", $msg);
			foreach ($articles as $article) {
				if($article == $titleUrl){
					$ts = $details->rev_timestamp;
					self::$mGlobalChannels[] = "0499166168"; //Content Strategy E
					break;
				}
			}
			
			$msg = ConfigStorage::dbGetConfig('CS_f'); //content strategy F
			$articles = split("\n", $msg);
			foreach ($articles as $article) {
				if($article == $titleUrl){
					$ts = $details->rev_timestamp;
					self::$mGlobalChannels[] = "3782603124"; //Content Strategy F
					break;
				}
			}
			
			$msg = ConfigStorage::dbGetConfig('CS_g'); //content strategy G
			$articles = split("\n", $msg);
			foreach ($articles as $article) {
				if($article == $titleUrl){
					$ts = $details->rev_timestamp;
					self::$mGlobalChannels[] = "2169636267"; //Content Strategy G
					break;
				}
			}
			
			$msg = ConfigStorage::dbGetConfig('CS_h') . "\n" . wfMessage('CS_h1')->text(); //content strategy H
			$articles = split("\n", $msg);
			foreach ($articles as $article) {
				if($article == $titleUrl){
					$ts = $details->rev_timestamp;
					self::$mGlobalChannels[] = "6341255402"; //Content Strategy H
					break;
				}
			}
			
			$msg = ConfigStorage::dbGetConfig('CS_i'); //content strategy I
			$articles = split("\n", $msg);
			foreach ($articles as $article) {
				if($article == $titleUrl){
					$ts = $details->rev_timestamp;
					self::$mGlobalChannels[] = "5819170825"; //Content Strategy I
					break;
				}
			}
			
			$msg = ConfigStorage::dbGetConfig('CS_j'); //content strategy J
			$articles = split("\n", $msg);
			foreach ($articles as $article) {
				if($article == $titleUrl){
					$ts = $details->rev_timestamp;
					self::$mGlobalChannels[] = "7694072995"; //Content Strategy J
					break;
				}
			}
			
			$msg = ConfigStorage::dbGetConfig('CS_k'); //content strategy K
			$articles = split("\n", $msg);
			foreach ($articles as $article) {
				if($article == $titleUrl){
					$ts = $details->rev_timestamp;
					self::$mGlobalChannels[] = "5982569583"; //Content Strategy K
					break;
				}
			}
			
			$msg = ConfigStorage::dbGetConfig('CS_l'); //content strategy L
			$articles = split("\n", $msg);
			foreach ($articles as $article) {
				if($article == $titleUrl){
					$ts = $details->rev_timestamp;
					self::$mGlobalChannels[] = "7774283315"; //Content Strategy L
					break;
				}
			}
			
			$msg = ConfigStorage::dbGetConfig('CS_m'); //content strategy M
			$articles = split("\n", $msg);
			foreach ($articles as $article) {
				if($article == $titleUrl){
					$ts = $details->rev_timestamp;
					self::$mGlobalChannels[] = "6128624756"; //Content Strategy M
					break;
				}
			}
			
			$msg = ConfigStorage::dbGetConfig('CS_n'); //content strategy N
			$articles = split("\n", $msg);
			foreach ($articles as $article) {
				if($article == $titleUrl){
					$ts = $details->rev_timestamp;
					self::$mGlobalChannels[] = "2682008177"; //Content Strategy N
					break;
				}
			}
			
			$msg = ConfigStorage::dbGetConfig('CS_o'); //content strategy O
			$articles = split("\n", $msg);
			foreach ($articles as $article) {
				if($article == $titleUrl){
					$ts = $details->rev_timestamp;
					self::$mGlobalChannels[] = "4294279486"; //Content Strategy O
					break;
				}
			}
			
			$msg = ConfigStorage::dbGetConfig('CS_p'); //content strategy P
			$articles = split("\n", $msg);
			foreach ($articles as $article) {
				if($article == $titleUrl){
					$ts = $details->rev_timestamp;
					self::$mGlobalChannels[] = "8749396082"; //Content Strategy P
					break;
				}
			}
			
			$msg = ConfigStorage::dbGetConfig('CS_q'); //content strategy Q
			$articles = split("\n", $msg);
			foreach ($articles as $article) {
				if($article == $titleUrl){
					$ts = $details->rev_timestamp;
					self::$mGlobalChannels[] = "0856671147"; //Content Strategy Q
					break;
				}
			}
			
			$msg = ConfigStorage::dbGetConfig('CS_r'); //content strategy R
			$articles = split("\n", $msg);
			foreach ($articles as $article) {
				if($article == $titleUrl){
					$ts = $details->rev_timestamp;
					self::$mGlobalChannels[] = "4560446682"; //Content Strategy R
					break;
				}
			}
			
			$msg = ConfigStorage::dbGetConfig('CS_s'); //content strategy S
			$articles = split("\n", $msg);
			foreach ($articles as $article) {
				if($article == $titleUrl){
					$ts = $details->rev_timestamp;
					self::$mGlobalChannels[] = "3657316725"; //Content Strategy S
					break;
				}
			}
			
			$msg = ConfigStorage::dbGetConfig('CS_t'); //content strategy T
			$articles = split("\n", $msg);
			foreach ($articles as $article) {
				if($article == $titleUrl){
					$ts = $details->rev_timestamp;
					self::$mGlobalChannels[] = "9924756626"; //Content Strategy T
					break;
				}
			}
			
			$msg = ConfigStorage::dbGetConfig('CS_u'); //content strategy U
			$articles = split("\n", $msg);
			foreach ($articles as $article) {
				if($article == $titleUrl){
					$ts = $details->rev_timestamp;
					self::$mGlobalChannels[] = "8414472671"; //Content Strategy U
					break;
				}
			}
			
			$msg = ConfigStorage::dbGetConfig('WRM_2012Q1'); 
			$articles = split("\n", $msg);
			foreach ($articles as $article) {
				if($article == $titleUrl){
					$ts = $details->rev_timestamp;
					self::$mGlobalChannels[] = "4126436138"; 
					break;
				}
			}
			
			$msg = ConfigStorage::dbGetConfig('WRM_2012Q2'); 
			$articles = split("\n", $msg);
			foreach ($articles as $article) {
				if($article == $titleUrl){
					$ts = $details->rev_timestamp;
					self::$mGlobalChannels[] = "3130480452"; 
					break;
				}
			}
			
			$msg = ConfigStorage::dbGetConfig('WRM_2012Q3');
			$articles = split("\n", $msg);
			foreach ($articles as $article) {
				if($article == $titleUrl){
					$ts = $details->rev_timestamp;
					self::$mGlobalChannels[] = "5929918148";
					break;
				}
			}
			
			$msg = ConfigStorage::dbGetConfig('WRM_2012Q4'); 
			$articles = split("\n", $msg);
			foreach ($articles as $article) {
				if($article == $titleUrl){
					$ts = $details->rev_timestamp;
					self::$mGlobalChannels[] = "5980804200"; 
					break;
				}
			}
			
			$msg = ConfigStorage::dbGetConfig('WRM_2013Q1'); 
			$articles = split("\n", $msg);
			foreach ($articles as $article) {
				if($article == $titleUrl){
					$ts = $details->rev_timestamp;
					self::$mGlobalChannels[] = "2374803371"; 
					break;
				}
			}
			
			$msg = ConfigStorage::dbGetConfig('WRM_2013Q2'); 
			$articles = split("\n", $msg);
			foreach ($articles as $article) {
				if($article == $titleUrl){
					$ts = $details->rev_timestamp;
					self::$mGlobalChannels[] = "3851536574"; 
					break;
				}
			}
			
			$msg = ConfigStorage::dbGetConfig('WRM_2013Q3');
			$articles = split("\n", $msg);
			foreach ($articles as $article) {
				if($article == $titleUrl){
					$ts = $details->rev_timestamp;
					self::$mGlobalChannels[] = "5328269777";
					break;
				}
			}
			
			$msg = ConfigStorage::dbGetConfig('WRM_2013Q4'); 
			$articles = split("\n", $msg);
			foreach ($articles as $article) {
				if($article == $titleUrl){
					$ts = $details->rev_timestamp;
					self::$mGlobalChannels[] = "6805002974"; 
					break;
				}
			}
			
			if (wikihowAds::isHHM()) {
				self::$mGlobalChannels[] = "5905062452"; //is an HHM page
			}
			
			if(self::isRedesignControl()) {
				$ts = $details->rev_timestamp;
				self::$mGlobalChannels[] = "9595513032"; //test off
			}
			
			if(self::isRedesignTest()) {
				$ts = $details->rev_timestamp;
				self::$mGlobalChannels[] = "6754085577";  //redesign test
			}
			
        }
	}
	
	function getCustomGoogleChannels($type, $use_chikita_sky) {

		global $wgTitle, $wgLang, $IP, $wgUser;
		
		$channels = array();
		$comments = array();

		$ad = array();
		$ad['adunitintro'] 			= '0206790666';
		$ad['horizontal_search'] 	= '9965311755';
		$ad['rad_bottom'] 			= '0403699914';
		$ad['ad_section'] 			= '7604775144';
		$ad['rad_left'] 			= '3496690692';
		$ad['rad_left_custom']		= '3371204857';
		$ad['rad_video'] 			= '8650928363';
		$ad['skyscraper']			= '5907135026';
		$ad['vertical_search']		= '8241181057';
		$ad['embedded_ads']			= '5613791162';
		$ad['embedded_ads_top']		= '9198246414';
		$ad['embedded_ads_mid']		= '1183596086';
		$ad['embedded_ads_vid']		= '7812294912';
		$ad['side_ads_vid']			= '5407720054';
		$ad['adunit0']				= '2748203808';
		$ad['adunit1']				= '4065666674';
		$ad['adunit2']				= '7690275023';
		$ad['adunit2a']				= '9206048113';
		$ad['adunit3']				= '9884951390';
		$ad['adunit4']				= '7732285575';
		$ad['adunit4b']				= '0969350919';
		$ad['adunit4c']				= '8476920763';
		$ad['adunit5']				= '7950773090';
		$ad['adunit6']				= '';
		$ad['adunitdocviewer']		= '8359699501';
		$ad['adunitdocviewer3']		= '3068405775';
		$ad['adunit7']				= '8714426702';
		$ad['linkunit1']			= '2612765588';
		$ad['linkunit2']          	= '5047600031';
		$ad['linkunit3']            = '5464626340';
		$ad['adunittop']			= '7558104428';
		$ad['adunitbottom']			= '9368624199';

		$namespace = array();
		$namespace[NS_MAIN]             = '7122150828';
		$namespace[NS_TALK]             = '1042310409';
		$namespace[NS_USER]             = '2363423385';
		$namespace[NS_USER_TALK]        = '3096603178';
		$namespace[NS_PROJECT]          = '6343282066';
		$namespace[NS_PROJECT_TALK]     = '6343282066';
		$namespace[NS_IMAGE]            = '9759364975';
		$namespace[NS_IMAGE_TALK]       = '9759364975';
		$namespace[NS_MEDIAWIKI]        = '9174599168';
		$namespace[NS_MEDIAWIKI_TALK]   = '9174599168';
		$namespace[NS_TEMPLATE]         = '3822500466';
		$namespace[NS_TEMPLATE_TALK]    = '3822500466';
		$namespace[NS_HELP]             = '3948790425';
		$namespace[NS_HELP_TALK]        = '3948790425';
		$namespace[NS_CATEGORY]         = '2831745908';
		$namespace[NS_CATEGORY_TALK]    = '2831745908';
		$namespace[NS_USER_KUDOS]       = '3105174400';
		$namespace[NS_USER_KUDOS_TALK]  = '3105174400';

		$channels[] = $ad[$type];
		$comments[] = $type;

		if ($use_chikita_sky) {
			$channels[] = "7697985842";
			$comments[] = "chikita sky";
		} else {
			$channels[] = "7733764704";
			$comments[] = "google sky";
		}

		foreach (self::$mGlobalChannels as $c) {
			$channels[] = $c;
		}
		foreach (self::$mGlobalComments as $c) {
			$comments[] = $c;
		}

		// Video
		if ($wgTitle->getNamespace() ==  NS_SPECIAL && $wgTitle->getText() == "Video") {
			$channels[] = "9155858053";
			$comments[] = "video";
		}

		/* Elizabeth said this was not used and ok to comment as of 8/27/2012
		$fas = FeaturedArticles::getFeaturedArticles(3);
		foreach ($fas as $fa) {
			if ($fa[0] == $wgTitle->getFullURL()) {
				$comments[] = 'FA';
				$channels[] = '6235263906';
			}
		}*/

		// do the categories
		// Elizabeth said this is in used as of 8/27/2012
		$tree = Categoryhelper::getCurrentParentCategoryTree();
		$tree = Categoryhelper::flattenCategoryTree($tree);
		$tree = Categoryhelper::cleanUpCategoryTree($tree);

		$map = self::getCategoryChannelMap();
		foreach ($tree as $cat) {
			if (isset($map[$cat])) {
				$channels[] = $map[$cat];
				$comments[] = $cat;
			}
		}

		if ($wgTitle->getNamespace() == NS_SPECIAL)
			$channels[] = "9363314463";
		else
			$channels[] = $namespace[$wgTitle->getNamespace()];
		if ($wgTitle->getNamespace() == NS_MAIN) {
			$comments[] = "Main namespace";
		} else {
			$comments[] = $wgLang->getNsText($wgTitle->getNamespace());
		}

		// TEST CHANNELS
		//if ($wgTitle->getNamespace() == NS_MAIN && $id % 2 == 0) {
		if ($wgTitle->getNamespace() == NS_SPECIAL && $wgTitle->getText() == "Search") {
			$channels[]  = '8241181057';
			$comments[]  = 'Search page';
		}

		$result = array(implode("+", $channels), implode(", ", $comments));
		return $result;
	}
	
	function getInternationalChannels() {
		global $wgTitle, $wgUser;
		
		$channels = array();

		if ($wgTitle->getNamespace() == NS_MAIN) {
            $dbr = wfGetDB(DB_MASTER);
            $minrev = $dbr->selectField('revision', 'min(rev_id)', array('rev_page'=>$wgTitle->getArticleID()), __METHOD__);
			$details = $dbr->selectRow('revision', array('rev_user_text', 'rev_timestamp'), array('rev_id'=>$minrev), __METHOD__);
			$fe = $details->rev_user_text;
			
			$ts = $details->rev_timestamp;

            if (in_array($fe, array('Wilfredor', 'WikiHow Traduce')) ){ //spanish
               	$channels[] = "3957522669";
				if (preg_match("@^2011(01|02|03)@", $ts)) //2011 first quarter
					$channels[] = "6251979379";
				elseif (preg_match("@^2011(04|05|06)@", $ts)) //2011 second quarter
					$channels[] = "7728712578";
				elseif (preg_match("@^2011(07|08|09)@", $ts)) //2011 third quarter
					$channels[] = "9205445776";
				elseif (preg_match("@^2011(10|11|12)@", $ts)) //2011 fourth quarter
					$channels[] = "1682178973";
				elseif (preg_match("@^2012(01|02|03)@", $ts)) //2012 first quarter
					$channels[] = "1682178973";
				elseif (preg_match("@^2012(04|05|06)@", $ts)) //2012 second quarter
					$channels[] = "4635645374";
				elseif (preg_match("@^2012(07|08|09)@", $ts)) //2012 third quarter
					$channels[] = "6112378576";
				elseif (preg_match("@^2012(10|11|12)@", $ts)) //2012 fourth quarter
					$channels[] = "7589111773";
				elseif (preg_match("@^2013(01|02|03)@", $ts)) //2013 first quarter
					$channels[] = "9065844978";
				elseif (preg_match("@^2013(04|05|06)@", $ts)) //2013 second quarter
					$channels[] = "1542578170";
				elseif (preg_match("@^2013(07|08|09)@", $ts)) //2013 third quarter
					$channels[] = "3019311371";
				elseif (preg_match("@^2013(10|11|12)@", $ts)) //2013 fourth quarter
					$channels[] = "4496044575";
			} else if($fe == "WikiHow Übersetzungen"){ //german, DE
               	$channels[] = "6309209598";
				if (preg_match("@^2011(01|02|03)@", $ts)) //2011 first quarter
					$channels[] = "5972777772";
				elseif (preg_match("@^2011(04|05|06)@", $ts)) //2011 second quarter
					$channels[] = "7449510970";
				elseif (preg_match("@^2011(07|08|09)@", $ts)) //2011 third quarter
					$channels[] = "8926244177";
				elseif (preg_match("@^2011(10|11|12)@", $ts)) //2011 fourth quarter
					$channels[] = "1402977376";
				elseif (preg_match("@^2012(01|02|03)@", $ts)) //2012 first quarter
					$channels[] = "2879710572";
				elseif (preg_match("@^2012(04|05|06)@", $ts)) //2012 second quarter
					$channels[] = "4356443778";
				elseif (preg_match("@^2012(07|08|09)@", $ts)) //2012 third quarter
					$channels[] = "5833176975";
				elseif (preg_match("@^2012(10|11|12)@", $ts)) //2012 fourth quarter
					$channels[] = "7309910177";
				elseif (preg_match("@^2013(01|02|03)@", $ts)) //2013 first quarter
					$channels[] = "8786643374";
				elseif (preg_match("@^2013(04|05|06)@", $ts)) //2013 second quarter
					$channels[] = "1263376574";
				elseif (preg_match("@^2013(07|08|09)@", $ts)) //2013 third quarter
					$channels[] = "2740109778";
				elseif (preg_match("@^2013(10|11|12)@", $ts)) //2013 fourth quarter
					$channels[] = "4216842972";
				
            } else if($fe == "Traduções wikiHow"){ //PT
                $channels[] = "3705134139";
				if (preg_match("@^2012(01|02|03)@", $ts)) //2012 first quarter
					$channels[] = "5693576175";
				elseif (preg_match("@^2012(04|05|06)@", $ts)) //2012 second quarter
					$channels[] = "7170309370";
				elseif (preg_match("@^2012(07|08|09)@", $ts)) //2012 third quarter
					$channels[] = "8647042577";
				elseif (preg_match("@^2012(10|11|12)@", $ts)) //2012 fourth quarter
					$channels[] = "1123775770";
				elseif (preg_match("@^2013(01|02|03)@", $ts)) //2013 first quarter
					$channels[] = "2600508979";
				elseif (preg_match("@^2013(04|05|06)@", $ts)) //2013 second quarter
					$channels[] = "4077242175";
				elseif (preg_match("@^2013(07|08|09)@", $ts)) //2013 third quarter
					$channels[] = "5553975370";
				elseif (preg_match("@^2013(10|11|12)@", $ts)) //2013 fourth quarter
					$channels[] = "7030708574";
            } else if($fe == "WikiHow Traduction") { //french
				$channels[] = "9278407376";
				if (preg_match("@^2012(10|11|12)@", $ts)) //2012 fourth quarter
					$channels[] = "6891107778";
				elseif (preg_match("@^2013(01|02|03)@", $ts)) //2013 first quarter
					$channels[] = "8367840975";
				elseif (preg_match("@^2013(04|05|06)@", $ts)) //2013 second quarter
					$channels[] = "9844574173";
				elseif (preg_match("@^2013(07|08|09)@", $ts)) //2013 third quarter
					$channels[] = "2321307371";
				elseif (preg_match("@^2013(10|11|12)@", $ts)) //2013 fourth quarter
					$channels[] = "3798040579";
			} else if($fe == "WikiHow tradurre") { //italian
				$channels[] = "1323878288";
				if (preg_match("@^2012(10|11|12)@", $ts)) //2012 fourth quarter
					$channels[] = "8507441770";
				elseif (preg_match("@^2013(01|02|03)@", $ts)) //2013 first quarter
					$channels[] = "9984174979";
				elseif (preg_match("@^2013(04|05|06)@", $ts)) //2013 second quarter
					$channels[] = "2460908172";
				elseif (preg_match("@^2013(07|08|09)@", $ts)) //2013 third quarter
					$channels[] = "3937641371";
				elseif (preg_match("@^2013(10|11|12)@", $ts)) //2013 fourth quarter
					$channels[] = "5414374579";
			} else if($fe == "WikiHow vertalingen") { //Dutch, NL
				$channels[] = "6514064173";
				if (preg_match("@^2013(01|02|03)@", $ts)) //2013 first quarter
					$channels[] = "4807318578";
				elseif (preg_match("@^2013(04|05|06)@", $ts)) //2013 second quarter
					$channels[] = "6284051773";
				elseif (preg_match("@^2013(07|08|09)@", $ts)) //2013 third quarter
					$channels[] = "7760784972";
				elseif (preg_match("@^2013(10|11|12)@", $ts)) //2013 fourth quarter
					$channels[] = "9237518179";
			}  
			
		}
		
		$channelString = implode("+", $channels);
			
		return $channelString;
	}
	
	function isRedesignControl() {
		global $wgTitle;
		
		$wikihowUrl = "http://www.wikihow.com/" . $wgTitle->getPartialURL();
		
		$msg = ConfigStorage::dbGetConfig('redesign_control'); 
		$articles = split("\n", $msg);
		if(in_array($wikihowUrl, $articles))
			return true;
		else
			return false;
	}
	
	function isRedesignTest() {
		global $wgTitle;
		
		$wikihowUrl = "http://www.wikihow.com/" . $wgTitle->getPartialURL();
		
		$msg = ConfigStorage::dbGetConfig('redesign_test'); 
		$articles = split("\n", $msg);
		if(in_array($wikihowUrl, $articles))
			return true;
		else
			return false;
	}
	
	function isJSTest() {
		global $wgTitle;
		
		$msg = wfMessage('Js_control')->text(); //JS test
		$articles = split("\n", $msg);
		
		if(in_array($wgTitle->getDBkey(), $articles) ) 
			return true;
		else
			return false;
		
	}
	
	function isJSControl() {
		global $wgTitle;
		
		$msg = wfMessage('Js_test')->text(); //JS test
		$articles = split("\n", $msg);
		
		if(in_array($wgTitle->getDBkey(), $articles) ) 
			return true;
		else
			return false;
	}
	
	function isMtv() {
		global $wgTitle;
		
		$titleText = $wgTitle->getDBkey();
		
		if($titleText == "Confess-to-an-Online-Lover-That-You-Are-Hiding-a-Secret")
			return true;
		return false;
	}
	
	function getMtv() {
		$s = "";
			
		$s = "<div class='wh_ad'><div class='side_ad'>"; 
		$s .= "<a href='http://mtvcasting.wufoo.com/forms/mtvs-online-relationship-show-now-casting' target='_blank'>";
		$s .= "<img src='" . wfGetPad('/skins/WikiHow/images/mtv_ad.jpg?1') . "' alt='MTV' /></a>";
		$s .= "</div></div>";
		
		return $s;
	}

	public static function getCategoryChannelMap() {
		global $wgMemc;
		$key = wfMemcKey('googlechannel', 'category', 'tree');
		$tree = $wgMemc->get( $key );
		if (!$tree) {
			$tree = array();
			$content = wfMessage('category_ad_channel_map')->inContentLanguage()->text();
			preg_match_all("/^#.*/im", $content, $matches);
			foreach ($matches[0] as $match) {
				$match = str_replace("#", "", $match);
				$cats = split(",", $match);
				$channel= trim(array_pop($cats));
				foreach($cats as $c) {
					$c = trim($c);
					if (isset($tree[$c]))
						$tree[$c] .= ",$channel";
					else
						$tree[$c] = $channel;
				}
			}
			$wgMemc->set($key, $tree, time() + 3600);
		}
		return $tree;

	}
	
	function getCategoryAd() {
		global $wgUser, $wgLanguageCode, $wgTitle;

        if(self::adExclusions($wgTitle))
            return "";
		
		$categories = array(
			'Arts-and-Entertainment' =>			array('/10095428/IMAGE_RR_ARTS_ENTER',			'div-gpt-ad-1358462597978-0'),
			'Health' =>							array('/10095428/IMAGE_RR_HEALTH',				'div-gpt-ad-1358462906741-8'),
			'Relationships' =>					array('/10095428/IMAGE_RR_RELATIONSHIPS',		'div-gpt-ad-1358462906741-16'),
			'Cars-&-Other-Vehicles' =>			array('/10095428/IMAGE_RR_CARS_VEHICLES',		'div-gpt-ad-1358462906741-1'),
			'Personal-Care-and-Style' =>		array('/10095428/IMAGE_RR_PERSONAL_STYLE',		'div-gpt-ad-1358462906741-13'),
			'Computers-and-Electronics' =>		array('/10095428/IMAGE_RR_COMP_ELECTRO',		'div-gpt-ad-1358462906741-2'),
			'Pets-and-Animals' =>				array('/10095428/IMAGE_RR_PETS_ANIMALS',		'div-gpt-ad-1358462906741-14'),
			'Education-and-Communications' =>	array('/10095428/IMAGE_RR_EDUCATION_COMM',		'div-gpt-ad-1358462906741-3'),
			'Philosophy-and-Religion' =>		array('/10095428/IMAGE_RR_PHIL_RELIGION',		'div-gpt-ad-1358462906741-15'),
			'Family-Life' =>					array('/10095428/IMAGE_RR_FAMILY_LIFE',			'div-gpt-ad-1358462906741-5'),
			'Finance-and-Business' =>			array('/10095428/IMAGE_RR_FINANCE_BIZ_LEGAL',	'div-gpt-ad-1358462906741-6'),
			'Sports-and-Fitness' =>				array('/10095428/IMAGE_RR_SPORTS_FITNESS',		'div-gpt-ad-1358462906741-17'),
			'Food-and-Entertaining' =>			array('/10095428/IMAGE_RR_FOOD_ENTERTAIN',		'div-gpt-ad-1358462906741-7'),
			'Travel' =>							array('/10095428/IMAGE_RR_TRAVEL',				'div-gpt-ad-1358462906741-18'),
			'Hobbies-and-Crafts' =>				array('/10095428/IMAGE_RR_HOBBIES_CRAFTS',		'div-gpt-ad-1358470416956-0'),
			'Work-World' =>						array('/10095428/IMAGE_RR_WORK_WORLD',			'div-gpt-ad-1358462906741-20'),
			'Home-and-Garden' =>				array('/10095428/IMAGE_RR_HOME_GARDEN',			'div-gpt-ad-1358462906741-11'),
			'Holidays-and-Traditions' =>		array('/10095428/IMAGE_RR_HOLIDAY_TRADIT',		'div-gpt-ad-1358462906741-10'),
			'Other' =>							array('/10095428/IMAGE_RR_OTHER',				'div-gpt-ad-1358462906741-12'),
			'Youth' =>							array('/10095428/IMAGE_RR_YOUTH',				'div-gpt-ad-1358462537340-0'),
			'WikiHow' =>						array('/10095428/IMAGE_RR_WIKIHOW',				'div-gpt-ad-1358462906741-19'),
		);

		$params = $categories['Other']; // default category
		foreach ($categories as $category => $par) {
			if (self::$mCategories[$category] != null) {
				$params = $par;
				break;
			}
		}
		
		if($wgLanguageCode == "en") 
			$s = wfMessage('adunit-image-rightrail', $params[0], $params[1])->text();

		else
			$s = "<div class='side_ad'>" . wfMessage('adunit4')->text() . "</div>";

		$s = "<div class='wh_ad'>" . preg_replace('/\<[\/]?pre[^>]*>/', '', $s) . "</div>";
		
		return $s;
	}
	
	function isHHM() {
		global $wgTitle, $wgUser;

		if ( $wgTitle->getNamespace() == NS_CATEGORY && $wgTitle->getPartialURL() == "Home-and-Garden") {
			return true;
		}
		else {
			if (self::$mCategories['Home-and-Garden'] != null) {
				return true;
			}
		}
		
		return false;
	}
	
	function getHhmAd() {
		$s = "";

		if(wikihowAds::isHHM()) {
			$catString = "diy.misc";
			$catNumber = "4777";
			
			$s = wfMessage('adunit-hhm', $catString, $catNumber)->text();
			$s = "<div class='wh_ad'>" . preg_replace('/\<[\/]?pre[^>]*>/', '', $s) . "</div>";
		}
		
		return $s;
	}
	
	function getAdUnitInterstitial($show = true) {
		$slot = '6356699771';
	
		$tmpl = new EasyTemplate( dirname(__FILE__) );
		$tmpl->set_vars(array(
			'slot' => $slot,
		));
		$s = $tmpl->execute('wikiHowAdInterstitial.tmpl.php');

		if (!$show) $hideit_style = ' style="display: none;"';
		
		$s = "<div class='wh_ad wh_ad_interstitial'$hideit_style>$s</div>";	
	
		return $s;
	}
	
	/******
	 * 
	 * Function return true if the current
	 * page is even eligible for having ads.
	 * Currently the requirements are:
	 * 1. User is logged out
	 * 2. User is on a Article, Image or Category page
	 * 3. Current page is NOT an index.php
	 * 4. Is not the main page
	 * 5. Action is not edit
	 * 
	 * Exceptions
	 * 1. Special:Categorylisting
	 * 
	 * In order to turn off ads all together,
	 * simply return false at the start of this
	 * function.
	 * 
	 ******/
	function isEligibleForAds() {
		global $wgUser, $wgTitle, $wgRequest, $wgLanguageCode;
		
		if(!$wgTitle) //don't want to check if it exists, b/c there are a few special pages that should show ads, and they don't "exist"
			return false;
		
		if($wgLanguageCode == "hi")
			return false;
		
		$isEligible = true;
		if($wgUser->getID() != 0)
			return false;
		
		$namespace = $wgTitle->getNamespace();
		if($namespace != NS_MAIN && $namespace != NS_IMAGE && $namespace != NS_CATEGORY)
			$isEligible = false;
		
		if($wgTitle && preg_match("@^/index\.php@", @$_SERVER["REQUEST_URI"]))
			$isEligible = false;
		
		$action = $wgRequest->getVal('action', 'view');
		if($action == 'edit')
			$isEligible = false;
		
		//check if its the main page
		if ($wgTitle
			&& $namespace == NS_MAIN
			&& $wgTitle->getText() == wfMessage('mainpage')->text()
			&& $action == 'view')
		{
			$isEligible = false;
		}

		//now some special exceptions
		$titleText = $wgTitle->getText();
		if ($namespace == NS_SPECIAL && 
			(0 === strpos($titleText, "Categorylisting") ||
			0 === strpos($titleText, "DocViewer") ||
			0 == strpos($titleText, "Quizzes"))) {
			$isEligible = true;
		}
		
		//check to see if the page is indexed, if its not, then it shouldn't show ads
		$indexed = RobotPolicy::isIndexable($wgTitle);
		if(!$indexed)
			$isEligible = false;
		
		return $isEligible;
		
	}

	protected static function isABTestArticle() {
		global $wgTitle;

		$isTest = false;
		if ($wgTitle && !preg_match("@^DocViewer$@", $wgTitle->getText())) {
			if (is_null(wikihowAds::$isABTest)) {
				// Turn on new ad test for all articles
				$wikihowUrl = "http://www.wikihow.com/" . $wgTitle->getPartialURL();

				$msg = ConfigStorage::dbGetConfig('beta_test');
				$articles = split("\n", $msg);
				if(in_array($wikihowUrl, $articles))
					wikihowAds::$isABTest = true;
			}
			$isTest = wikihowAds::$isABTest;
		}
		return $isTest;
	}

	function setCategories() {
		global $wgUser;

		$tree = Categoryhelper::getCurrentParentCategoryTree();
		if ($tree != null) {
			foreach($tree as $key => $path) {
				$catString = str_replace("Category:", "", $key);
				self::$mCategories[$catString] = $catString;

				$subtree = Categoryhelper::flattenCategoryTree($path);
				for ($i = 0; $i < count($subtree); $i++) {
					$catString = str_replace("Category:", "", $subtree[$i]);
					self::$mCategories[$catString] = $catString;
				}
			}
		}
	}

	static function setAltMethods($hasAlts) {
		self::$hasAltMethods = $hasAlts;
	}

	function getJump() {
		global $wgLanguageCode, $wgTitle;

		if($wgLanguageCode == "en") {
			$msg = ConfigStorage::dbGetConfig('ad_jump'); //articles that need the jump
			$articles = split("\n", $msg);
				if(in_array($wgTitle->getArticleID(), $articles)) {
					return wfMsg('ad_jump');
				}
		}
		return "";
	}


}

