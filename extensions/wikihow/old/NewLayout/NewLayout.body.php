<?php

if (!defined('MEDIAWIKI')) die();

class NewLayout extends UnlistedSpecialPage {

	const ARTICLE_LAYOUT = '04';

	static $mLastEditedInfo = false;

	public function __construct() {
		parent::__construct('NewLayout');
		EasyTemplate::set_path( dirname(__FILE__) );
	}

	public function execute() {
		$this->go();
	}
	
	public static function removeSideBarCallback(&$showSideBar) {
		global $wgUser, $wgRequest, $wgTitle;
		
		if($wgRequest->getVal('oldid') != '' ||
			($wgRequest->getVal('action') != '' && $wgRequest->getVal('action') != 'view') ||
			$wgTitle->getNamespace != NS_MAIN )
			$showSideBar = true;
		elseif (NewLayout::isNewLayoutPage()) 
			$showSideBar = false;
		else	
			$showSideBar = true;
		return true;
	}
	
	public static function isUserAgentMobile() {
		if (class_exists('MobileWikihow')) {
			return MobileWikihow::isUserAgentMobile();
		} else {
			return false;
		}
	}
	
	public function header($isUserAgentMobile) {
		global $wgUser;
		
		$sk = $wgUser->getSkin();
		
		if (NewLayout::isNewLayoutPage()) {
			EasyTemplate::set_path( dirname(__FILE__));

			$headerVars = array(
				'form' => GoogSearch::getSearchBox("cse-search-box"),
			);

			echo EasyTemplate::html('header_'.self::ARTICLE_LAYOUT.'.tmpl.php', $headerVars);
		}
		return true;
	}
	
	function getLastEdited() {
		global $wgTitle, $wgUser;
		if (!$wgTitle || !($wgTitle->getNamespace() == NS_MAIN || $wgTitle->getNamespace() == NS_PROJECT)) {
			return '';
		}

		$row = self::getLastEditedInfo();

		$html = '';
		$u = User::newFromName($row->rev_user_text);
		if ($row && $row->rev_user != 0 && $u) {
			$ts = wfTimestamp(TS_UNIX, $row->rev_timestamp);
			$html = wfMsg('last_edited') . "<br/>";
			$editedByMsg = $wgUser->isAnon() ? 'last_edited_by_anon' : 'last_edited_by';
			$html .= wfMsg($editedByMsg, date("F j, Y", $ts), $u->getName() , $u->getUserPage()->getLocalURL());
		}
		return $html;
	}

	function getLastEditedInfo() {
		global $wgTitle;

		if (self::$mLastEditedInfo) {
			return self::$mLastEditedInfo;
		}
		$dbr = wfGetDB(DB_SLAVE);

		$bad = WikihowUser::getBotIDs();
		$bad[] = 0; // filter out anons too, as per Jack

		$row = $dbr->selectRow('revision', array('rev_user', 'rev_user_text', 'rev_timestamp'),
				array('rev_user NOT IN (' . $dbr->makeList($bad) . ")", "rev_page"=>$wgTitle->getArticleID()),
				__METHOD__,
				array("ORDER BY" => "rev_id DESC", "LIMIT"=>1)
			);

		$info->rev_user_text = $row->rev_user_text;
		$info->rev_user = $row->rev_user;
		$info->rev_timestamp = $row->rev_timestamp;

		self::$mLastEditedInfo = $info;
		return $info;
	}

	public static function getCSS() {
		return '<style type="text/css" media="all">/*<![CDATA[*/ @import "' . wfGetPad('/extensions/wikihow/NewLayout/Layout_04.css') . '?' . WH_SITEREV . '";  /*]]>*/</style>';
	}
	
	public function isNewLayoutPage() {
		global $wgTitle, $wgRequest, $wgOut, $wgUser;
		
		if($wgRequest->getVal('oldid') != '' ||
			($wgRequest->getVal('action') != '' && $wgRequest->getVal('action') != 'view') ||
			$wgTitle->getNamespace() != NS_MAIN)
			return false;
		
		if($wgUser->getID() != 0)
			return false;
		
		if(self::isUserAgentMobile())
			return false;
		
		$wikihowUrl = "http://www.wikihow.com/" . $wgTitle->getPartialURL();
		
		$msg = ConfigStorage::dbGetConfig('redesign_test'); 
		$articles = split("\n", $msg);
		if(in_array($wikihowUrl, $articles))
			return true;
		else
			return false;
	}
	
	public function go() {
		$this->displayHtml();
	}
	
	public function displayHtml() {
		global $IP, $wgTitle, $wgOut, $wgUser, $wgRequest, $wgContLanguageCode;
		global $wgLang, $wgContLang, $wgXhtmlDefaultNamespace;
		
		$sk = new SkinWikihowskin();

		$articleName = $wgTitle->getText();
		$partialUrl = $wgTitle->getPartialURL();
		$isMainPage = ( $articleName == wfMsg('mainpage') );
		$action = $wgRequest->getVal('action', 'view');
		//$lang = $this->getSiteLanguage();
		//$deviceOpts = $this->getDevice();
		$pageExists = $wgTitle->exists();

		$randomUrl = '/' . wfMsg('special-randomizer');
		$titleBar = wfMsg('pagetitle', $articleName);
		$canonicalUrl = 'http://' . $IP . '/' . $wgTitle->getPartialURL();
	
		$rtl = $wgContLang->isRTL() ? " dir='RTL'" : '';		
		$head_element = "<html xmlns:fb=\"https://www.facebook.com/2008/fbml\" xmlns=\"{$wgXhtmlDefaultNamespace}\" xml:lang=\"$wgContLanguageCode\" lang=\"$wgContLanguageCode\" $rtl>\n";
		

		$css = '/extensions/min/f/skins/WikiHow/new.css,extensions/wikihow/common/jquery-ui-themes/jquery-ui.css,extensions/wikihow/gallery/prettyPhoto-3.12/src/prettyPhoto.css,extensions/wikihow/NewLayout/Layout_03.css';
		if ($wgUser->getID() > 0) $css .= ',/skins/WikiHow/loggedin.css';
		$css .= '?'.WH_SITEREV;
		
		$css = wfGetPad($css);
		
		if ($wgIsDomainTest) {
			$base_href = '<base href="http://www.wikihow.com/" />';
		}
		else {
			$base_href = '';
		}
		
		$out = new OutputPage;
		$headlinks = $out->getHeadLinks();
		
		if (!$wgIsDomainTest) {
			$canonicalUrl = '<link rel="canonical" href="'.$wgTitle->getFullURL().'" />';
		}
		
		//get login/sign up stuff
		$login = "";

		$li = $wgLang->specialPage("Userlogin");
		$lo = $wgLang->specialPage("Userlogout");
		$rt = $wgTitle->getPrefixedURL();
		if ( 0 == strcasecmp( urlencode( $lo ), $rt ) ) {
			$q = "";
		} else {
			$q = "returnto={$rt}";
		}

		if ( $wgUser->getID() ) {
			$uname = $wgUser->getName();
			if (strlen($uname) > 16) { $uname = substr($uname,0,16) . "..."; }
			$login = wfMsg('welcome_back', $wgUser->getUserPage()->getFullURL(), $uname );

			if ($wgLanguageCode == 'en' && $wgUser->isFacebookUser())
				$login =  wfMsg('welcome_back_fb', $wgUser->getUserPage()->getFullURL() ,$wgUser->getName() );
		} else {
			$login =  wfMsg('signup_or_login', $q) . " " . wfMsg('facebook_connect_header', wfGetPad("/skins/WikiHow/images/facebook_share_icon.gif")) ;
		}

		if($wgUser->getID() > 0) { 
			$helplink = $sk->makeLinkObj (Title::makeTitle(NS_CATEGORY, wfMsg('help')) ,  wfMsg('help'));
			$logoutlink = $sk->makeLinkObj(Title::makeTitle(NS_SPECIAL, 'Userlogout'), wfMsg('logout'));
			$login .= " | " . $helplink . " | " . $logoutlink;
		}

		$headerVars = array(
			'title' => $titleBar,
			'head_element' => $head_element,
			'base_href' => $base_href,
			'globalvar_script' => Skin::makeGlobalVariablesScript( $this->data ),
			'css' => $css,
			'headlinks' => $headlinks,
			'canon' => $canonicalUrl,
			'headitems' => $wgOut->getHeadItems(),
			'login' => $login,
		);
		
		if($wgUser->getID() > 0) {
			$footer_links = wfMsgExt('site_footer_new', 'parse');
		}
		else {
			$footer_links = wfMsgExt('site_footer_new_anon', 'parse');
		}
		
		if($wgUser->getID() > 0 || $isMainPage) {
			$sub_foot = wfMsg('sub_footer_new', wfGetPad(), wfGetPad());
		}
		else {
			$sub_foot = wfMsg('sub_footer_new_anon', wfGetPad(), wfGetPad());
		}
		
		$footerVars = array(
			'footer_links' => $footer_links,
			'search' => GoogSearch::getSearchBox("cse-search-box-footer").'<br />',
			'cat_list' => $sk->getCategoryList(),
			'sub_foot' => $sub_foot,
			'footertail' => $this->getFooterTail(),
		);

		$article = $wgOut->getHTML();
		$wgOut->clearHTML();
		
		//parse that article text
		$article = call_user_func( 'self::parseArticle_'.self::ARTICLE_LAYOUT, $article );
		
		$wgOut->addHTML( EasyTemplate::html('header_'.self::ARTICLE_LAYOUT.'.tmpl.php', $headerVars) );
		$wgOut->addHTML($article);
		$wgOut->addHTML( EasyTemplate::html('footer_'.self::ARTICLE_LAYOUT.'.tmpl.php', $footerVars) );			
		
		print $wgOut->getHTML();
	
	}
	
	public function parseArticle_01($article) {
		global $wgWikiHowSections, $wgTitle, $wgUser;
		
		$ads = $wgUser->getID() == 0;
		
		$sk = new SkinWikihowskin();
		
		$sectionMap = array(
			wfMsg('Intro') => 'intro',
			wfMsg('Ingredients') => 'ingredients',
			wfMsg('Steps') => 'steps',
			wfMsg('Video') => 'video',
			wfMsg('Tips') => 'tips',
			wfMsg('Warnings') => 'warnings',
			wfMsg('relatedwikihows') => 'relatedwikihows',
			wfMsg('sourcescitations') => 'sources',
			wfMsg('thingsyoullneed') => 'thingsyoullneed',
		);		
		
		foreach ($wgWikiHowSections as $section) {
			$reverse_msgs[wfMsg($section)] = $section;
		}

		$parts = preg_split("@(<h2.*</h2>)@im", $article, 0, PREG_SPLIT_DELIM_CAPTURE);
		$body= '';
		$section_menu = '';
		$intro_img = '';
		for ($i = 0; $i < sizeof($parts); $i++) {
			if ($i == 0) {
				//intro
				preg_match("/Image:(.*)\">/", $parts[$i], $matches);
				if (count($matches) > 0) {
					$img = $matches[1];
					$img = preg_replace('@%27@',"'",$img);
					$image = Title::makeTitle(NS_IMAGE, $img);

					if ($image) {
						$file = wfFindFile($image);

						if ($file) {
							$thumb = $file->getThumbnail(200, -1, true, true);
							$intro_img = '<a href="'.$image->getFullUrl().'"><img border="0" width="200" class="mwimage101" src="'.wfGetPad($thumb->getUrl()).'" alt="" /></a>';
						}
					}
				}
				if ($intro_img == '') {
						$intro_img = '<img border="0" width="200" class="mwimage101" src="'.wfGetPad('/skins/WikiHow/images/wikihow_sq_200.png').'" alt="" />';
				}
				
				$r = Revision::newFromTitle($wgTitle);
				$intro_text = Wikitext::getIntro($r->getText());
				$intro_text = trim(Wikitext::flatten($intro_text));
				
				$body .= '<br /><div id="color_div"></div><br />';
				
				$body .= '<div id="article_intro">'.$intro_text.'</div>';
				
				if ($ads) {
					$body .= '<div class="ad_noimage intro_ad">' . wikihowAds::getAdUnitPlaceholder('intro') . '</div>';
				}
				
				
				$section_menu .= '<li><a href="#">Summary</a></li>';
			}			
			else if (stripos($parts[$i], "<h2") === 0 && $i < sizeof($parts) - 1) {
				preg_match("@<span>.*</span>@", $parts[$i], $matches);
				$rev = "";
				if (sizeof($matches) > 0) {
					$h2 =  trim(strip_tags($matches[0]));
					$rev = isset($reverse_msgs[$h2]) ? $reverse_msgs[$h2] : "";
				}
				
				if ($rev !== 'steps') {
					$body .= $parts[$i];
				}
				
				$i++;
				if ($rev == "steps") {
					$body .= "\n<div id=\"steps\" class='editable'>{$parts[$i]}</div>\n";
				} else if ($rev != "") {
					$body .= "\n<div id=\"{$rev}\" class='article_inner editable'>{$parts[$i]}</div>\n";
				} else {
					$body .= "\n<div class='article_inner editable'>{$parts[$i]}</div>\n";
				}
				
				$section_menu .= '<li><a href="#'.$rev.'">'.$h2.'</a></li>';
			} 
			else {
				$body .= $parts[$i];
			}

		}

		$punct = "!\.\?\:"; # valid ways of ending a sentence for bolding
		$i = strpos($body, '<div id="steps"');
		if ($i !== false) $j = strpos($body, '<div id=', $i+5);
		if ($j === false) $j = strlen($body);
		if ($j !== false && $i !== false) {
			$steps = substr($body, $i, $j - $i);
			$parts = preg_split("@(<[/]?ul>|<[/]?ol>|<[/]?li>)@im", $steps, 0, PREG_SPLIT_DELIM_CAPTURE  | PREG_SPLIT_NO_EMPTY);
			$numsteps = preg_match_all('/<li>/m',$steps, $matches );
			$level = 0;
			$steps = "";
			$upper_tag = "";
			$levelstack = array();
			$tagstack = array();
			$current_tag = "";
			$current_li = 1;
			$donefirst = false; // used for ads to tell when we've put the ad after the first step

			if ($numsteps < 100) {

				while ($p = array_shift($parts)) {
					switch (strtolower($p)) {
						case "<ol>":
							$level++;
							if ($level == 1)  {
								$p = '<ol class="steps_list">';
								$upper_tag = "ol";
							} else {
								$p = "&nbsp;<div class='listbody'>{$p}";
							}
							if ($current_tag != "")
								$tagstack[] = $current_tag;
							$current_tag = "ol";
							$levelstack[] = $current_li;
							$current_li = 1;
							break;
						case "<ul>":
							if ($current_tag != "")
								$tagstack[] = $current_tag;
							$current_tag = "ul";
							$levelstack[] = $current_li;
							$level++;
							break;
						case "</ol>":
						case "</ul>":
							$level--;
							if ($level == 0) $upper_tag = "";
							$current_tag = array_pop($tagstack);
							$current_li = array_pop($levelstack);
							break;
						case "<li>":
							$closecount = 0;
							if ($level == 1 && $upper_tag == "ol") {
								$li_number = $current_li++;
								//$p = '<li>'. str_pad($li_number,2,'0',STR_PAD_LEFT);
								$p = '<li>';
								# this is where things get interesting. Want to make first sentence bold!
								# but we need to handle cases where there are tags in the first sentence
								# split based on HTML tags
								$next = array_shift($parts);
								$htmlparts = preg_split("@(<[^>]*>)@im", $next,
									0, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
								$dummy = 0;
								$incaption = false;
								$apply_b = false;
								$the_big_step = $next;
								while ($x = array_shift($htmlparts)) {
									# if it's a tag, just append it and keep going
									if (preg_match("@(<[^>]*>)@im", $x)) {
										//tag
										$p .= $x;
										if ($x == "<span class='caption'>")
											$incaption = true;
										else if ($x == "</span>" && $incaption)
											$incaption = false;
										continue;
									}
									# put the closing </b> in if we hit the end of the sentence
									if (!$incaption) {
										if (!$apply_b && trim($x) != "") {
											$p .= '<p class="step_head"><span>'. str_pad($li_number,2,'0',STR_PAD_LEFT).'</span>';
											$apply_b = true;
										}
										if ($apply_b) {
											$x = preg_replace("@([{$punct}])@im", "$1</p>", $x, 1, &$closecount);
										}
									}
									$p .= $x;
										
									if ($closecount > 0) {
										break;
									} else {
										#echo "\n\n-----$x----\n\n";
									}
									$dummy++;
								}
																
								# get anything left over
								$p .= implode("", $htmlparts);
								
								if ($closecount == 0) $p .= "</b>"; // close the bold tag if we didn't already
								if ($level == 1 && $current_li == 2 && $ads && !$donefirst) {
									$p .= '<br class="clearall" />'.wikihowAds::getAdUnitPlaceholder(0);
									$donefirst = true;
								}								

							}
							break;
						case "</li>":
							$p = "<div class='clearall'></div>{$p}"; //changed BR to DIV b/c IE doesn't work with the BR clear tag
							break;
					} // switch
					$steps .= $p;
				} // while
			} else {
				$steps = substr($body, $i, $j - $i);
				$steps = "<div id='steps_notmunged'>\n" . $steps . "\n</div>\n";
			}
			
						
			// we have to put the final_li in the last OL LI step, so reverse the walk of the tokens
			$parts = preg_split("@(<[/]?ul>|<[/]?ol>|<[/]?li>)@im", $steps, 0, PREG_SPLIT_DELIM_CAPTURE);
			$parts = array_reverse($parts);
			$steps = "";
			$level = 0;
			$gotit = false;
			$donelast = false;
			foreach ($parts as $p) {
				$lp = strtolower($p);
				if ($lp == "</ol>" ) {
					$level++;
					$gotit= false;
				}else if($lp == "</ul>" ){
					$level++;
				} else if (strpos($lp, "<li") !== false && $level == 1 && !$gotit) {
					/// last OL step list fucker
					//$p = preg_replace("@<li[^>]*>@i", '<li class="steps_li final_li">', $p);
					
					$gotit = true;
				} else if (strpos($lp, "<ul") !== false ){
					$level--;
				} else if (strpos($lp, "<ol") !== false ) {
					$level--;
				} else if ($lp == "</li>" && !$donelast) {
					// ads after the last step
					if ($ads){
						if(substr($body, $j) == ""){
							$p = "<script>missing_last_ads = true;</script>" . wikihowAds::getAdUnitPlaceholder(1) . $p;
							$no_third_ad = true;
						}
						else {
							$p = '<br />'.wikihowAds::getAdUnitPlaceholder(2) . $p;
						}
					}
					$donelast = true;
				}
				$steps = $p . $steps;
			}

			$body = substr($body, 0, $i) . $steps . substr($body, $j);
			
		} /// if numsteps == 100?
		
				/// ads below tips, walk the sections and put them after the tips
		if ($ads) {
			$foundtips = false;
			$anchorTag = "";
			foreach ($wgWikiHowSections as $s) {
				$isAtEnd = false;
				if ($s == "ingredients" || $s == "steps")
					continue; // we skip these two top sections
				$i = strpos($body, '<div id="' . $s. '"');
			    if ($i !== false) {
					$j = strpos($body, '<h2>', $i + strlen($s));
				} else {
					continue; // we didnt' find this section
				}
	    		if ($j === false){
					$j = strlen($body); // go to the end
					$isAtEnd = true;
				}
	    		if ($j !== false && $i !== false) {
	        		$section  = substr($body, $i, $j - $i);
					if ($s == "video") {
						// special case for video
						$newsection = "<div id='video'><center>{$section}</center></div>";
						$body = str_replace($section, $newsection, $body);
						continue;
					} else if ($s == "tips") {
						//tip ad is now at the bottom of the tips section
						//need to account for the possibility of no sections below this and therefor
						//no anchor tag
						if($isAtEnd)
							$anchorTag = "<p></p>";
						$body = str_replace($section, $section . $anchorTag . wikihowAds::getAdUnitPlaceholder('2a') .'<p><br /></p>' , $body);
						$foundtips = true;
						break;
					} else {
						$foundtips = true;
						if($isAtEnd)
							$anchorTag = "<p></p>";
						$body = str_replace($section, $section . $anchorTag . wikihowAds::getAdUnitPlaceholder(2) , $body);
						break;
					}
				}
			}
			if (!$foundtips && !$no_third_ad) { //must be the video section
				//need to put in the empty <p> tag since all the other sections have them for the anchor tags.
				$body .= "<p class='video_spacing'></p>" . wikihowAds::getAdUnitPlaceholder(2);
			}
		}

		$catlinks = $sk->getCategoryLinks($false);
		$authors = $sk->getAuthorFooter();
		if ($authors != "" || is_array($this->data['language_urls']) || $catlinks != "") {
		
		//k, now grab the bottom stuff
		$article_bottom .= '<br />'.wfGetSuggestedTitles($wgTitle).'<br />
							<h2 class="section_head" id="article_info_header">'.wfMsg('article_info').'</h2>
							<div id="article_info" class="article_inner">
								<p>'.self::getLastEdited().'</p>
								<p>'. wfMsg('categories') . ':<br/>'.$catlinks.'</p>
								<p>'.$authors.'</p>
							</div><!--end article_info-->';
		}

		if( $wgUser->getID() == 0 && !$isMainPage && $action != 'edit' && $wgTitle->getNamespace() == NS_MAIN) {
			$article_bottom .= '<div class="final_ad">'. wikihowAds::getAdUnitPlaceholder(7). '</div>';
		}
		$article_bottom .= '
						<div id="final_question">
								'.$userstats.'
								<p><b>'.$sk->pageStats().'</b></p>
								<div id="page_rating">'.RateArticle::showForm().'</div>
								<p></p>
					   </div>  <!--end last_question-->
					</div> <!-- article -->';
					
		//share buttons
		$url = urlencode($wgServer . "/" . $wgTitle->getPrefixedURL());
		$fb = '<div class="like_button"><fb:like href="' . $url . '" send="false" layout="button_count" width="86" show_faces="false"></fb:like></div>';
		$gp1 = '<div class="gplus1_button"><g:plusone size="medium" callback="plusone_vote"></g:plusone></div>';

//		$fb_share = '<div class="like_button like_tools"><fb:like href="' . $url . '" send="false" layout="button_count" width="86" show_faces="false"></fb:like></div>';
		$tb_admin = '<div class="admin_state"><a href="http://twitter.com/share" data-lang="' . $wgLanguageCode . '" style="display:none; background-image: none; color: #ffffff;" class="twitter-share-button" data-count="horizontal" data-via="wikiHow" data-text="How to ' . htmlspecialchars($wgTitle->getText()) . '" data-related="JackHerrick:Founder of wikiHow">Tweet</a></div>';
		$tb = '<a href="http://twitter.com/share" data-lang="' . $wgLanguageCode . '" style="display:none; background-image: none; color: #ffffff;" class="twitter-share-button" data-count="horizontal" data-via="wikiHow" data-text="How to ' . htmlspecialchars($wgTitle->getText()) . '" data-related="JackHerrick:Founder of wikiHow">Tweet</a>';	
		
		$the_buttons = '<div id="share_buttons_top">'.$fb;
		if ($wgUser->isSysop() && $wgTitle->userCan('delete')) {
			$the_buttons .= $tb_admin;
		}
		else {
			$the_buttons .= $tb;
		}
		$the_buttons .= $gp1.'</div>';
		
   
		$title = '<h1>How to '.$wgTitle->getText().'</h1>';
		$edited = $sk->getAuthorHeader();
		$section_menu = '<ul>'.$section_menu.'</ul>';

		$sidebar = '<div id="sidenav">'.$intro_img.$section_menu.'</div>';		
		$main = '<div id="article_main">'.$title.$the_buttons.$edited.$body.$article_bottom.'</div>';	
		$article = '<div id="article_layout_'.self::ARTICLE_LAYOUT.'">'.$sidebar.$main.'</div>';
		
		return $article;
	}
	
	public function parseArticle_02($article) {
		global $wgWikiHowSections, $wgTitle, $wgUser;
		
		$ads = $wgUser->getID() == 0;
		
		$sk = new SkinWikihowskin();
		
		$sectionMap = array(
			wfMsg('Intro') => 'intro',
			wfMsg('Ingredients') => 'ingredients',
			wfMsg('Steps') => 'steps',
			wfMsg('Video') => 'video',
			wfMsg('Tips') => 'tips',
			wfMsg('Warnings') => 'warnings',
			wfMsg('relatedwikihows') => 'relatedwikihows',
			wfMsg('sourcescitations') => 'sources',
			wfMsg('thingsyoullneed') => 'thingsyoullneed',
		);		
		
		foreach ($wgWikiHowSections as $section) {
			$reverse_msgs[wfMsg($section)] = $section;
		}

		$parts = preg_split("@(<h2.*</h2>)@im", $article, 0, PREG_SPLIT_DELIM_CAPTURE);
		$body= '';
		$intro_img = '';
		for ($i = 0; $i < sizeof($parts); $i++) {
			if ($i == 0) {
				//intro
				preg_match("/Image:(.*)\">/", $parts[$i], $matches);
				if (count($matches) > 0) {
					$img = $matches[1];
					$img = preg_replace('@%27@',"'",$img);
					$image = Title::makeTitle(NS_IMAGE, $img);

					if ($image) {
						$file = wfFindFile($image);

						if ($file) {
							$thumb = $file->getThumbnail(200, -1, true, true);
							$intro_img = '<a href="'.$image->getFullUrl().'"><img border="0" width="200" class="mwimage101" src="'.wfGetPad($thumb->getUrl()).'" alt="" /></a>';
						}
					}
				}
				if ($intro_img == '') {
						$intro_img = '<img border="0" width="200" class="mwimage101" src="'.wfGetPad('/skins/WikiHow/images/wikihow_sq_200.png').'" alt="" />';
				}
				
				$r = Revision::newFromTitle($wgTitle);
				$intro_text = Wikitext::getIntro($r->getText());
				$intro_text = trim(Wikitext::flatten($intro_text));
				
				$body .= '<br /><div id="color_div"></div><br />';
				
				$body .= '<div id="article_intro">'.$intro_text.'</div>';
				
				if ($ads) {
					$body .= '<div class="ad_noimage intro_ad">' . wikihowAds::getAdUnitPlaceholder('intro') . '</div>';
				}
			}			
			else if (stripos($parts[$i], "<h2") === 0 && $i < sizeof($parts) - 1) {
				preg_match("@<span>.*</span>@", $parts[$i], $matches);
				$rev = "";
				if (sizeof($matches) > 0) {
					$h2 =  trim(strip_tags($matches[0]));
					$rev = isset($reverse_msgs[$h2]) ? $reverse_msgs[$h2] : "";
				}
				
				if ($rev !== 'steps') {
					$body .= $parts[$i];
				}
				
				$i++;
				if ($rev == "steps") {
					$body .= "\n<div id=\"steps\" class='editable'>{$parts[$i]}</div>\n";
				} else if ($rev != "") {
					$body .= "\n<div id=\"{$rev}\" class='article_inner editable'>{$parts[$i]}</div>\n";
				} else {
					$body .= "\n<div class='article_inner editable'>{$parts[$i]}</div>\n";
				}
			} 
			else {
				$body .= $parts[$i];
			}

		}

		$punct = "!\.\?\:"; # valid ways of ending a sentence for bolding
		$i = strpos($body, '<div id="steps"');
		if ($i !== false) $j = strpos($body, '<div id=', $i+5);
		if ($j === false) $j = strlen($body);
		if ($j !== false && $i !== false) {
			$steps = substr($body, $i, $j - $i);
			$parts = preg_split("@(<[/]?ul>|<[/]?ol>|<[/]?li>)@im", $steps, 0, PREG_SPLIT_DELIM_CAPTURE  | PREG_SPLIT_NO_EMPTY);
			$numsteps = preg_match_all('/<li>/m',$steps, $matches );
			$level = 0;
			$steps = "";
			$upper_tag = "";
			$levelstack = array();
			$tagstack = array();
			$current_tag = "";
			$current_li = 1;
			$donefirst = false; // used for ads to tell when we've put the ad after the first step

			if ($numsteps < 100) {

				while ($p = array_shift($parts)) {
					switch (strtolower($p)) {
						case "<ol>":
							$level++;
							if ($level == 1)  {
								$p = '<ol class="steps_list">';
								$upper_tag = "ol";
							} else {
								$p = "&nbsp;<div class='listbody'>{$p}";
							}
							if ($current_tag != "")
								$tagstack[] = $current_tag;
							$current_tag = "ol";
							$levelstack[] = $current_li;
							$current_li = 1;
							break;
						case "<ul>":
							if ($current_tag != "")
								$tagstack[] = $current_tag;
							$current_tag = "ul";
							$levelstack[] = $current_li;
							$level++;
							break;
						case "</ol>":
							$p .= '<div id="steps_end"></div>';
						case "</ul>":
							$level--;
							if ($level == 0) $upper_tag = "";
							$current_tag = array_pop($tagstack);
							$current_li = array_pop($levelstack);
							break;
						case "<li>":
							$closecount = 0;
							if ($level == 1 && $upper_tag == "ol") {
								$li_number = $current_li++;
								//$p = '<li>'. str_pad($li_number,2,'0',STR_PAD_LEFT);
								$p = '<li>';
								# this is where things get interesting. Want to make first sentence bold!
								# but we need to handle cases where there are tags in the first sentence
								# split based on HTML tags
								$next = array_shift($parts);
								$htmlparts = preg_split("@(<[^>]*>)@im", $next,
									0, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
								$dummy = 0;
								$incaption = false;
								$apply_b = false;
								while ($x = array_shift($htmlparts)) {
									# if it's a tag, just append it and keep going
									if (preg_match("@(<[^>]*>)@im", $x)) {
										//tag
										$p .= $x;
										if ($x == "<span class='caption'>")
											$incaption = true;
										else if ($x == "</span>" && $incaption)
											$incaption = false;
										continue;
									}
									# put the closing </b> in if we hit the end of the sentence
									if (!$incaption) {
										if (!$apply_b && trim($x) != "") {
											$p .= '<p class="step_head"><span>'. str_pad($li_number,2,'0',STR_PAD_LEFT).'</span>';
											$apply_b = true;
										}
										if ($apply_b) {
											$x = preg_replace("@([{$punct}])@im", "$1</p>", $x, 1, &$closecount);
										}
									}
									$p .= $x;
										
									if ($closecount > 0) {
										break;
									} else {
										#echo "\n\n-----$x----\n\n";
									}
									$dummy++;
								}
																
								# get anything left over
								$p .= implode("", $htmlparts);
								
								if ($closecount == 0) $p .= "</b>"; // close the bold tag if we didn't already
								if ($level == 1 && $current_li == 2 && $ads && !$donefirst) {
									$p .= '<br class="clearall" />'.wikihowAds::getAdUnitPlaceholder(0);
									$donefirst = true;
								}

							}
							break;
						case "</li>":
							$p = "<div class='clearall'></div>{$p}"; //changed BR to DIV b/c IE doesn't work with the BR clear tag
							break;
					} // switch
					$steps .= $p;
				} // while
			} else {
				$steps = substr($body, $i, $j - $i);
				$steps = "<div id='steps_notmunged'>\n" . $steps . "\n</div>\n";
			}
			
						
			// we have to put the final_li in the last OL LI step, so reverse the walk of the tokens
			$parts = preg_split("@(<[/]?ul>|<[/]?ol>|<[/]?li>)@im", $steps, 0, PREG_SPLIT_DELIM_CAPTURE);
			$parts = array_reverse($parts);
			$steps = "";
			$level = 0;
			$gotit = false;
			$donelast = false;
			foreach ($parts as $p) {
				$lp = strtolower($p);
				if ($lp == "</ol>" ) {
					$level++;
					$gotit= false;
				}else if($lp == "</ul>" ){
					$level++;
				} else if (strpos($lp, "<li") !== false && $level == 1 && !$gotit) {
					/// last OL step list fucker
					//$p = preg_replace("@<li[^>]*>@i", '<li class="steps_li final_li">', $p);
					
					$gotit = true;
				} else if (strpos($lp, "<ul") !== false ){
					$level--;
				} else if (strpos($lp, "<ol") !== false ) {
					$level--;
				} else if ($lp == "</li>" && !$donelast) {
					// ads after the last step
					if ($ads){
						if(substr($body, $j) == ""){
							$p = "<script>missing_last_ads = true;</script>" . wikihowAds::getAdUnitPlaceholder(1) . $p;
							$no_third_ad = true;
						}
						else {
							$p = '<br />'.wikihowAds::getAdUnitPlaceholder(2) . $p;
						}
					}
					$donelast = true;
				}
				$steps = $p . $steps;
			}

			$body = substr($body, 0, $i) . $steps . substr($body, $j);
			
		} /// if numsteps == 100?
		
				/// ads below tips, walk the sections and put them after the tips
		if ($ads) {
			$foundtips = false;
			$anchorTag = "";
			foreach ($wgWikiHowSections as $s) {
				$isAtEnd = false;
				if ($s == "ingredients" || $s == "steps")
					continue; // we skip these two top sections
				$i = strpos($body, '<div id="' . $s. '"');
			    if ($i !== false) {
					$j = strpos($body, '<h2>', $i + strlen($s));
				} else {
					continue; // we didnt' find this section
				}
	    		if ($j === false){
					$j = strlen($body); // go to the end
					$isAtEnd = true;
				}
	    		if ($j !== false && $i !== false) {
	        		$section  = substr($body, $i, $j - $i);
					if ($s == "video") {
						// special case for video
						$newsection = "<div id='video'><center>{$section}</center></div>";
						$body = str_replace($section, $newsection, $body);
						continue;
					} else if ($s == "tips") {
						//tip ad is now at the bottom of the tips section
						//need to account for the possibility of no sections below this and therefor
						//no anchor tag
						if($isAtEnd)
							$anchorTag = "<p></p>";
						$body = str_replace($section, $section . $anchorTag . wikihowAds::getAdUnitPlaceholder('2a') .'<p><br /></p>' , $body);
						$foundtips = true;
						break;
					} else {
						$foundtips = true;
						if($isAtEnd)
							$anchorTag = "<p></p>";
						$body = str_replace($section, $section . $anchorTag . wikihowAds::getAdUnitPlaceholder(2) , $body);
						break;
					}
				}
			}
			if (!$foundtips && !$no_third_ad) { //must be the video section
				//need to put in the empty <p> tag since all the other sections have them for the anchor tags.
				$body .= "<p class='video_spacing'></p>" . wikihowAds::getAdUnitPlaceholder(2);
			}
		}

		$catlinks = $sk->getCategoryLinks($false);
		$authors = $sk->getAuthorFooter();
		if ($authors != "" || is_array($this->data['language_urls']) || $catlinks != "") {
		
		//k, now grab the bottom stuff
		$article_bottom .= '<br />'.wfGetSuggestedTitles($wgTitle).'<br />
							<h2 class="section_head" id="article_info_header">'.wfMsg('article_info').'</h2>
							<div id="article_info" class="article_inner">
								<p>'.self::getLastEdited().'</p>
								<p>'. wfMsg('categories') . ':<br/>'.$catlinks.'</p>
								<p>'.$authors.'</p>
							</div><!--end article_info-->';
		}

		if( $wgUser->getID() == 0 && !$isMainPage && $action != 'edit' && $wgTitle->getNamespace() == NS_MAIN) {
			$article_bottom .= '<div class="final_ad">'. wikihowAds::getAdUnitPlaceholder(7). '</div>';
		}
		$article_bottom .= '
						<div id="final_question">
								'.$userstats.'
								<p><b>'.$sk->pageStats().'</b></p>
								<div id="page_rating">'.RateArticle::showForm().'</div>
								<p></p>
					   </div>  <!--end last_question-->
					</div> <!-- article -->';
					
		//share buttons
		$url = urlencode($wgServer . "/" . $wgTitle->getPrefixedURL());
		$fb = '<div class="like_button"><fb:like href="' . $url . '" send="false" layout="button_count" width="86" show_faces="false"></fb:like></div>';
		$gp1 = '<div class="gplus1_button"><g:plusone size="medium" callback="plusone_vote"></g:plusone></div>';

//		$fb_share = '<div class="like_button like_tools"><fb:like href="' . $url . '" send="false" layout="button_count" width="86" show_faces="false"></fb:like></div>';
		$tb_admin = '<div class="admin_state"><a href="http://twitter.com/share" data-lang="' . $wgLanguageCode . '" style="display:none; background-image: none; color: #ffffff;" class="twitter-share-button" data-count="horizontal" data-via="wikiHow" data-text="How to ' . htmlspecialchars($wgTitle->getText()) . '" data-related="JackHerrick:Founder of wikiHow">Tweet</a></div>';
		$tb = '<a href="http://twitter.com/share" data-lang="' . $wgLanguageCode . '" style="display:none; background-image: none; color: #ffffff;" class="twitter-share-button" data-count="horizontal" data-via="wikiHow" data-text="How to ' . htmlspecialchars($wgTitle->getText()) . '" data-related="JackHerrick:Founder of wikiHow">Tweet</a>';	
		
		$the_buttons = '<div id="share_buttons_top">'.$fb;
		if ($wgUser->isSysop() && $wgTitle->userCan('delete')) {
			$the_buttons .= $tb_admin;
		}
		else {
			$the_buttons .= $tb;
		}
		$the_buttons .= $gp1.'</div>';
		
   
		$title = '<h1>How to '.$wgTitle->getText().'</h1>';
		$edited = $sk->getAuthorHeader();

		$sidebar = '<div id="sidenav"><div id="showslideshow"></div><div id="pp_big_space">'.$intro_img.'</div></div>';		
		$main = '<div id="article_main">'.$title.$the_buttons.$edited.$body.$article_bottom.'</div>';	
		$article = '<div id="article_layout_'.self::ARTICLE_LAYOUT.'">'.$sidebar.$main.'</div>';
		
		return $article;
	}
	
	
	public function getFooterTail() {
		global $wgUser, $wgTitle, $wgRequest;
		
		$sk = new SkinWikihowskin();
		
		//$footertail = WikiHowTemplate::getPostLoadedAdsHTML();
		
		$trackData = array();
		// Data analysis tracker

		if (class_exists('CTALinks') && /*CTALinks::isArticlePageTarget() &&*/ trim(wfMsgForContent('data_analysis_feature')) == "on" && !CTALinks::isLoggedIn() && $wgTitle->getNamespace() == NS_MAIN ) {
			// Ads test for logged out users on article pages
			$footertail .= wfMsg('data_analysis');
		}

		$footertail .= wfMsg('client_data_analysis');

		// Intro image on/off
		if (class_exists('CTALinks') && CTALinks::isArticlePageTarget()) {
			//$trackData[] = ($sk->hasIntroImage()) ? "introimg:yes" : "introimg:no";
		}

		// Account type
		global $wgCookiePrefix;
		if (isset($_COOKIE[$wgCookiePrefix . 'acctTypeA'])) {
			// cookie value is "<userid>|<acct class>"
			$cookieVal =  explode("|", $_COOKIE[$wgCookiePrefix . 'acctTypeA']);
			// Only track if user is logged in with same account the cookie was created for
			if ($wgUser->getID() == $cookieVal[0]) {
				$trackData[] = "accttype:class{$cookieVal[1]}";
			}
		}

		// Another Cohort test. Only track cohorts after they return from initial account creation session
		if (isset($_COOKIE[$wgCookiePrefix . 'acctTypeB']) && !isset($_COOKIE[$wgCookiePrefix . 'acctSes'])) {
			// cookie value is "<userid>|<acct class>"
			$cookieVal =  explode("|", $_COOKIE[$wgCookiePrefix . 'acctTypeB']);
			// Only track if user is logged in with same account the cookie was created for
			if ($wgUser->getID() == $cookieVal[0]) {
				$trackData[] = "acctret:{$cookieVal[1]}";
			}
		}

		// Logged in/out
		$trackData[] = ($wgUser->getId() > 0) ? "usertype:loggedin" : "usertype:loggedout";

		$nsURLs = array(NS_USER => "/User", NS_USER_TALK => "/User_talk", NS_IMAGE => "/Image");
		$gaqPage = $nsURLs[$wgTitle->getNamespace()];
		$trackUrl = sizeof($gaqPage) ? $gaqPage : $wgTitle->getFullUrl();
		$trackUrl = str_replace("$wgServer$wgScriptPath", "", $trackUrl);
		$trackUrl = str_replace("http://www.wikihow.com", "", $trackUrl);
		$trackUrl .= '::';
		$trackUrl .= "," . implode(",", $trackData) . ",";
		
		$footertail .= "
<script type=\"text/javascript\">

  var _gaq = _gaq || [];
  _gaq.push(['_setAccount', 'UA-2375655-1']);
  _gaq.push(['_setDomainName', '.wikihow.com']);
  _gaq.push(['_trackPageview']);
  _gaq.push(['_trackPageLoadTime']);

  (function() {
    var ga = document.createElement('script'); ga.type = 'text/javascript'; ga.async = true;
    ga.src = \"" . wfGetPad('/extensions/min/f/skins/common/ga.js') . '?rev=' . WH_SITEREV . "\";
    var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(ga, s);
  })();

</script>
<!-- Google Analytics Event Track -->
<? //merged with other JS above: <script type=\"text/javascript\" src=\"".wfGetPad('/extensions/min/f/skins/WikiHow/gaWHTracker.js')."\"></script>?>
<script type=\"text/javascript\">
if (typeof Event =='undefined' || typeof Event.observe == 'undefined') {
	jQuery(window).load(gatStartObservers);
} else {
	Event.observe(window, 'load', gatStartObservers);
}
</script>
<!-- END Google Analytics Event Track -->";

		if (class_exists('CTALinks') && trim(wfMsgForContent('cta_feature')) == "on") {
			$footertail .= CTALinks::getGoogleControlTrackingScript();
			$footertail .= CTALinks::getGoogleConversionScript();
		}

		$footertail .= '<!-- LOAD EVENT LISTENERS -->';
		
		/*if ($wgTitle->getPrefixedURL() == wfMsg('mainpage') && $wgLanguageCode == 'en') {
			$footertail .= "
				<script type=\"text/javascript\">
				if (typeof Event =='undefined' || typeof Event.observe == 'undefined') {
					jQuery(window).load(initSA);
				} else {
					Event.observe(window, 'load', initSA);
				}
				</script>";
		}*/

		$footertail .= "<!-- LOAD EVENT LISTENERS ALL PAGES -->
		<div id='img-box'></div>";

		/*if (class_exists('CTALinks') && trim(wfMsgForContent('cta_feature')) == "on") {
			$footertail .= CTALinks::getBlankCTA();
		}*/

		// QuickBounce test
		/*if (false && $sk->isQuickBounceUrl('ryo_urls')) {

		$footertail .= '<!-- Begin W3Counter Secure Tracking Code -->
		<script type="text/javascript" src="https://www.w3counter.com/securetracker.js"></script>
		<script type="text/javascript">
		w3counter(55901);
		</script>
		<noscript>
		<div><a href="http://www.w3counter.com"><img src="https://www.w3counter.com/tracker.php?id=55901" style="border: 0" alt="W3Counter" /></a></div>
		</noscript>
		<!-- End W3Counter Secure Tracking Code-->';

		}*/

		//$footertail .= '</body>';
		
		/*if (($wgRequest->getVal("action") == "edit" || $wgRequest->getVal("action") == "submit2") && $wgRequest->getVal('advanced', null) != 'true') {
			$footertail .= "<script type=\"text/javascript\">
			if (document.getElementById('steps') && document.getElementById('wpTextbox1') == null) {
					InstallAC(document.editform,document.editform.q,document.editform.btnG,\"./".$wgLang->getNsText(NS_SPECIAL).":TitleSearch"."\",\"en\");
			}
			</script>";
		}*/
		
		return $footertail;
	}
	
	function parseArticle_03($article) {
		global $wgTitle, $wgUser, $wgRequest, $wgServer, $wgLang, $wgArticle, $wgParser, $wgOut, $IP;
		
		$article = self::mungeSteps($article);
		$sk = $wgUser->getSkin();

		$url = urlencode($wgServer . "/" . $wgTitle->getPrefixedURL());
		$img = urlencode(WikihowShare::getPinterestImage($wgTitle));
		$desc = urlencode(wfMsg('howto', $wgTitle->getText()) . WikihowShare::getPinterestTitleInfo($this->getContext()));
				
		$fb = '<div class="like_button"><fb:like href="' . $url . '" send="false" layout="button_count" width="100" show_faces="false"></fb:like></div>';
		$gp1 = '<div class="gplus1_button"><g:plusone size="medium" callback="plusone_vote"></g:plusone></div>';

		$pinterest = '<div id="pinterest"><a href="http://pinterest.com/pin/create/button/?url=' . $url . '&media=' . $img . '&description=' . $desc . '" class="pin-it-button" count-layout="horizontal">Pin It</a></div>';

		// German includes "how to " in the title text
		$howto = wfMsg('howto', htmlspecialchars($wgTitle->getText()));
		$tb = '<div class="admin_state"><a href="http://twitter.com/share" data-lang="' . $wgLanguageCode . '" style="display:none; background-image: none; color: #ffffff;" class="twitter-share-button" data-count="horizontal" data-via="wikiHow" data-text="' . $howto . '" data-related="JackHerrick:Founder of wikiHow">Tweet</a></div>';
		
		$article = str_replace('<div class="corner top_right"></div>', '<div class="corner top_right">&nbsp;</div>', $article);
		$article = str_replace('<div class="corner top_left"></div>', '<div class="corner top_left">&nbsp;</div>', $article);
		$article = str_replace('<div class="corner bottom_right"></div>', '<div class="corner bottom_right">&nbsp;</div>', $article);
		$article = str_replace('<div class="corner bottom_left"></div>', '<div class="corner bottom_left">&nbsp;</div>', $article);
		$article = str_replace("<div class='corner top_right'></div>", "<div class='corner top_right'>&nbsp;</div>", $article);
		$article = str_replace("<div class='corner top_left'></div>", "<div class='corner top_left'>&nbsp;</div>", $article);
		$article = str_replace("<div class='corner bottom_right'></div>", "<div class='corner bottom_right'>&nbsp;</div>", $article);
		$article = str_replace("<div class='corner bottom_left'></div>", "<div class='corner bottom_left'>&nbsp;</div>", $article);
		$article = str_replace('<div style="clear:both"></div>', '<div style="clear:both">&nbsp;</div>', $article);
		$article = str_replace("’", "'", $article);
		
		$introImage = "";
		require_once("$IP/extensions/wikihow/mobile/JSLikeHTMLElement.php");
		$doc = new DOMDocument('1.0', 'utf-8');
		$doc->registerNodeClass('DOMElement', 'JSLikeHTMLElement');
		$doc->strictErrorChecking = false;
		$doc->recover = true;
		@$doc->loadHTML($article);
		$doc->normalizeDocument();
		$xpath = new DOMXPath($doc);
		
		//removing the featured article star
		$nodes = $xpath->query('//div[@id="featurestar"]');
		foreach($nodes as $node) {
			$node->parentNode->removeChild($node->nextSibling->nextSibling);
			$node->parentNode->removeChild($node);
			break;
		}
		
		$nodes = $xpath->query('//div[@class="rounders"]');
		
		foreach ($nodes as $node) {
			$style = $node->getAttribute("style");
			$start = strpos($style, "width:");
			$end = strpos($style, "px", $start);
			$width = intval(substr($style, $start + 6, $start + 6 - $end));
			$newWidth = $width + 21;
			$style = substr($style, 0, $start + 6) . $newWidth . substr($style, $end);
			
			$start = strpos($style, "height:");
			$end = strpos($style, "px", $start);
			$height = intval(substr($style, $start + 7, $start + 7 - $end));
			$newheight = $height + 19;
			$style = substr($style, 0, $start + 7) . $newHeight . substr($style, $end);
			
			$node->setAttribute("style", $style);
			$childNode = $node->firstChild;
			$node->removeChild($childNode);
			$newNode = $doc->createElement("div");
			$newNode->setAttribute('class', 'top');
			$node->appendChild($newNode);
			$newNode2 = $doc->createElement("div");
			$newNode2->setAttribute('class', 'bottom');
			$newNode->appendChild($newNode2);
			$newNode3 = $doc->createElement("div");
			$newNode3->setAttribute('class', 'left');
			$newNode2->appendChild($newNode3);
			$newNode4 = $doc->createElement("div");
			$newNode4->setAttribute('class', 'right');
			$newNode3->appendChild($newNode4);
			$newNode4->appendChild($childNode);
		}
		
		
		//grabbing the intro image
		/*$nodes = $xpath->query('//div[@class="mwimg"]');
		foreach ($nodes as $node) {
			$introImage = "<div class='mwimg'>" . $node->innerHTML . "</div>";
			$node->parentNode->removeChild($node);
			break;
		}*/
		
		$nodes = $xpath->query('//ol[@class="steps_list_2"]/li/div[@class="mwimg"]');
		foreach($nodes as $node) {
			$checkNode = $xpath->evaluate($node->parentNode->getNodePath() . '/div[@class="check"]')->item(0);
			$node->parentNode->removeChild($node);
			$checkNode->parentNode->insertBefore($node, $checkNode->nextSibling);
		}
		
		$article = $doc->saveHTML();
		
		$article = str_replace('<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.0 Transitional//EN" "http://www.w3.org/TR/REC-html40/loose.dtd">
<html><body>', "", $article);
		$article = str_replace('</body></html>', "", $article);

		//$share =  $fb . $gp1 . $pinterest;
		
		$mainVars = array(
			'wgTitle' => $wgTitle,
			'wgUser' => $wgUser,
			'article' => $article,
			'sk' => $sk,
			'wgRequest' => $wgRequest,
			'share' => $share,
			'wgLang' => $wgLang,
			'wgArticle' => $wgArticle,
			'introImage' => $introImage,
			'navigation' => self::getNavigation()
		);
		return EasyTemplate::html('main_'.self::ARTICLE_LAYOUT.'.tmpl.php', $mainVars);
	}
	
	public function getNavigation() {
		global $wgUser, $wgTitle;
		
		$sk = $wgUser->getSkin();
		
		// QWER links for everyone on all pages
		$cp = Title::makeTitle(NS_PROJECT, "Community-Portal");
		$cptab = Title::makeTitle(NS_PROJECT, "Community");

		$helplink = $sk->makeLinkObj (Title::makeTitle(NS_CATEGORY, wfMsg('help')) ,  wfMsg('help'));
		$logoutlink = $sk->makeLinkObj(Title::makeTitle(NS_SPECIAL, 'Userlogout'), wfMsg('logout'));
		$forumlink = "<a href='$wgForumLink'>" . wfMsg('forums') . "</a>";
		$tourlink = "";
		if ($wgLanguageCode =='en')
			$tourlink = $sk->makeLinkObj(Title::makeTitle(NS_PROJECT, "Tour"), wfMsg('wikihow_tour')) ;
		$splink = "";

		if($wgUser->getID() != 0)
			$splink = "<li>" . $sk->makeLinkObj(Title::makeTitle(NS_SPECIAL, "Specialpages"), wfMsg('specialpages')) . "</li>";

		$rclink = $sk->makeLinkObj(Title::makeTitle(NS_SPECIAL, "Recentchanges"), wfMsg('recentchanges'));
		$requestlink = $sk->makeLinkObj(Title::makeTitle(NS_SPECIAL, "RequestTopic"), wfMsg('requesttopic'));
		$listrequestlink = $sk->makeLinkObj( Title::makeTitle(NS_SPECIAL, "ListRequestedTopics"), wfMsg('listrequtestedtopics'));
		$rsslink = "<a href='" . $wgServer . "/feed.rss'>" . wfMsg('rss') . "</a>";
		$rplink = $sk->makeLinkObj(Title::makeTitle(NS_SPECIAL, "Randompage"), wfMsg('randompage') ) ;
		
		//For logged out only
		if($wgUser->getID() == 0){
			$loginlink =  "<li>" . wfMsg('Anon_login', $q) . "</li>";
			$cplink = "<li>" . $sk->makeLinkObj ($cptab, wfMsg('communityportal') ) . "</li>";
		}
		else{
			$rcpatrollink = "<li>" . $sk->makeLinkObj(Title::makeTitle(NS_SPECIAL, "RCPatrol"), wfMsg('RCPatrol')) . "</li>";
			if (class_exists('IntroImageAdder')) {
			$imagepicklink = "<li>" . $sk->makeLinkObj(Title::makeTitle(NS_PROJECT, "IntroImageAdderStartPage"), wfMsg('IntroImageAdder')) . "</li>";
			}
			if ($wgLanguageCode == 'en') {
				$moreideaslink = "<li><a href='/Special:CommunityDashboard'>" . wfMsg('more-ideas') . "</a></li>";
				$categorypickerlink = "<li>" . $sk->makeLinkObj(Title::makeTitle(NS_SPECIAL, "Categorizer"), wfMsg('UncategorizedPages')) . "</li>";
			} else {
				$moreideaslink = "<li><a href='/Contribute-to-wikiHow'>" . wfMsg('more-ideas') . "</a></li>";
				$categorypickerlink = "<li>" . $sk->makeLinkObj(Title::makeTitle(NS_SPECIAL, "Uncategorizedpages"), wfMsg('UncategorizedPages')) . "</li>";
			}
		}
		
		$editlink = "<li>" . " <a href='" . $wgTitle->escapeLocalURL($sk->editUrlOptions()) . "'>" . wfMsg('edit-this-article') . "</a>" . "</li>";
		$createLink = $sk->makeLinkObj(Title::makeTitle(NS_SPECIAL, "CreatePage"), wfMsg('Write-an-article'));
		
		$navigation = "
		<div class='sidebox_shell'>
        <div class='sidebox' id='side_nav'>
            	<h3 id='navigation_list_title' >
			<a href=\"#\" onclick=\"return sidenav_toggle('navigation_list',this);\" id='href_navigation_list'>" . wfMsg('navlist_collapse') . "</a>
			<span onclick=\"return sidenav_toggle('navigation_list',this);\" style=\"cursor:pointer;\"> " . wfMsg('navigation') . "</span></h3>
            <ul id='navigation_list' style='margin-top: 0;'>
				<li> {$createLink}</li>
				{$editlink}";

				$navigation .= "<li> {$requestlink}</li><li> {$listrequestlink}</li>";

				$navigation .= "
				{$imagepicklink}
				{$rcpatrollink}
				{$categorypickerlink}
				{$moreideaslink}
				{$loginlink}
            </ul>

			<h3>
			<a href=\"#\" onclick=\"return sidenav_toggle('visit_list',this);\" id='href_visit_list'>" . wfMsg('navlist_expand') . "</a>
			<span onclick=\"return sidenav_toggle('visit_list',this);\" style=\"cursor:pointer;\"> " . wfMsg('places_to_visit') . "</span></h3>
				<ul id='visit_list' style='display:none;'>
					<li> {$rclink}</li>
					<li> {$forumlink}</li>
					{$cplink}
					{$splink}
				</ul>";

			if ($wgTitle->getNamespace() == NS_MAIN && $isLoggedIn
			  && $wgTitle->userCanEdit() && !$isMainPage)  {
				$navigation .= "<h3>
				<a href=\"#\" onclick=\"return sidenav_toggle('editing_list',this);\" id='href_editing_list'>" . wfMsg('navlist_expand') . "</a>
				<span onclick=\"return sidenav_toggle('editing_list',this);\" style=\"cursor:pointer;\"> " . wfMsg('editing_tools') . "</span></h3>
					<ul id='editing_list' style='display:none;'>
						{$videolink}
						{$mralink}
						{$statslink}
						{$wlhlink}
					</ul>";
			}

			if($wgUser->getID() > 0 && ($wgTitle->getNamespace() == NS_IMAGE || $wgTitle->getNamespace() == NS_TEMPLATE || $wgTitle->getNamespace() == NS_TALK || $wgTitle->getNamespace() == NS_PROJECT)){
				$navigation .= "<h3>
				<a href=\"#\" onclick=\"return sidenav_toggle('editing_list',this);\" id='href_editing_list'>" . wfMsg('navlist_expand') . "</a>
				<span onclick=\"return sidenav_toggle('editing_list',this);\" style=\"cursor:pointer;\"> " . wfMsg('editing_tools') . "</span></h3>
					<ul id='editing_list' style='display:none;'>
						{$wlhlink}
					</ul>";
			}


			if($wgUser->getID() > 0){

				$navigation .= "<h3><a href=\"#\" onclick=\"return sidenav_toggle('my_pages_list',this);\" id='href_my_pages_list'>" . wfMsg('navlist_expand') . "</a>
			<span onclick=\"return sidenav_toggle('my_pages_list',this);\" style=\"cursor:pointer;\"> " . wfMsg('my_pages') . "</span></h3>
				<ul id='my_pages_list' style='display:none;'>
				<li> " . $sk->makeLinkObj(Title::makeTitle(NS_SPECIAL, 'Mytalk'), wfMsg('mytalkpage') ). "</li>
				<li> " . $sk->makeLinkObj(Title::makeTitle(NS_SPECIAL, 'Mypage'), wfMsg('myauthorpage') ). "</li>
				<li> " . $sk->makeLinkObj(Title::makeTitle(NS_SPECIAL, 'Watchlist'), wfMsg('watchlist') ). "</li>
				<li> " . $sk->makeLinkObj(Title::makeTitle(NS_SPECIAL, 'Drafts'), wfMsg('mydrafts') ). "</li>
				<li> " . $sk->makeLinkObj(SpecialPage::getTitleFor('Mypages', 'Contributions'),  wfMsg ('mycontris')) . "</li>
				<li> " . $sk->makeLinkObj(SpecialPage::getTitleFor('Mypages', 'Fanmail'),  wfMsg ('myfanmail')) . "</li>
				<li> " . $sk->makeLinkObj(Title::makeTitle(NS_SPECIAL, 'Preferences'), wfMsg('mypreferences') ). "</li>
				<li> " . $sk->makeLinkObj(Title::makeTitle(NS_SPECIAL, 'Userlogout'), wfMsg('logout') ) . "</li>
				</ul>";
			}

			$navigation .= "   {$userlinks}";

			$navigation .= "</div>
			<div class='sidebar_bottom_fold'></div>
		</div>
		";
			
		return $navigation;
	}
	
	public static function mungeSteps($body, $opts = array()) {
		global $wgWikiHowSections, $wgTitle, $wgUser;
		$ads = $wgUser->getID() == 0 && !@$opts['no-ads'];
		$parts = preg_split("@(<h2.*</h2>)@im", $body, 0, PREG_SPLIT_DELIM_CAPTURE);
		$reverse_msgs = array();
		$no_third_ad = false;
		foreach ($wgWikiHowSections as $section) {
			$reverse_msgs[wfMsg($section)] = $section;
		}
		$charcount = strlen($body);
		$body= "";
		for ($i = 0; $i < sizeof($parts); $i++) {
			if ($i == 0) {

				if ($body == "") {
					// if there is no alt tag for the intro image, so it to be the title of the page
					preg_match("@<img.*mwimage101[^>]*>@", $parts[$i], $matches);
					if (sizeof($matches) > 0) {
						$m = $matches[0];
						$newm = str_replace('alt=""', 'alt="' . htmlspecialchars($wgTitle->getText()) . '"', $m);
						if ($m != $newm) {
							$parts[$i] = str_replace($m, $newm, $parts[$i]);
						}
						
					}
					
					// done alt test
					$anchorPos = stripos($parts[$i], "<a name=");
					if($anchorPos > 0 && $ads){
						$content = substr($parts[$i], 0, $anchorPos);
						$count = preg_match_all('@</p>@', $parts[$i], $matches);
						
						if($count == 1) //this intro only has one paragraph tag
							$class = 'low';
						else {
							$endVar = "<p><br /></p>\n<p>";
							$end = substr($content, -1*strlen($endVar));

							if($end == $endVar) {
								$class = 'high'; //this intro has two paragraphs at the end, move ads higher
							}
							else{
								$class = 'mid'; //this intro has no extra paragraphs at the end.
							}
						}
						
						
						if(stripos($parts[$i], "mwimg") != false){
							$body = "<div class='article_inner editable'>" . $content . "<div class='ad_image " . $class . "'>" . wikihowAds::getAdUnitPlaceholder('intro') . "</div>" . substr($parts[$i], $anchorPos) ."<br class='clearall' /></div>\n";
						}else{
							$body = "<div class='article_inner editable'>" . $content . "<div class='ad_noimage " . $class . "'>" . wikihowAds::getAdUnitPlaceholder('intro') . "</div>" . substr($parts[$i], $anchorPos) ."</div>\n";
						}
					}
					elseif($anchorPos == 0 && $ads){
						$body = "<div class='article_inner editable'>{$parts[$i]}" . wikihowAds::getAdUnitPlaceholder('intro') . "<br class='clearall' /></div>\n";
					}
					else
						$body = "<div class='article_inner editable'>{$parts[$i]}<br class='clearall' /></div>\n";
				}
				continue;
			}
			
			if (stripos($parts[$i], "<h2") === 0 && $i < sizeof($parts) - 1) {
				preg_match("@<span>.*</span>@", $parts[$i], $matches);
				$rev = "";
				if (sizeof($matches) > 0) {
					$h2 =  trim(strip_tags($matches[0]));
					$rev = isset($reverse_msgs[$h2]) ? $reverse_msgs[$h2] : "";
				}
				
				$body .= $parts[$i];
				
				$i++;
				if ($rev == "steps") {
					
						$recipe_tag = "'";
					
					$body .= "\n<div id=\"steps\" class='editable{$recipe_tag}>{$parts[$i]}</div>\n";
				} elseif ($rev != "") {
					$body .= "\n<div id=\"{$rev}\" class='article_inner editable'>{$parts[$i]}</div>\n";
				} else {
					$body .= "\n<div class='article_inner editable'>{$parts[$i]}</div>\n";
				}
			} else {
				$body .= $parts[$i];
			}
		}
		
		#echo $body; exit;
		$punct = "!\.\?\:"; # valid ways of ending a sentence for bolding
		$i = strpos($body, '<div id="steps"');
		if ($i !== false) $j = strpos($body, '<div id=', $i+5);
		if ($j === false) $j = strlen($body);
		if ($j !== false && $i !== false) {
			$steps = substr($body, $i, $j - $i);
			$parts = preg_split("@(<[/]?ul>|<[/]?ol>|<[/]?li>)@im", $steps, 0, PREG_SPLIT_DELIM_CAPTURE  | PREG_SPLIT_NO_EMPTY);
			$numsteps = preg_match_all('/<li>/m',$steps, $matches );
			$level = 0;
			$steps = "";
			$upper_tag = "";
			// for the redesign we need some extra formatting for the OL, etc
	#print_r($parts); exit;
			$levelstack = array();
			$tagstack = array();
			$current_tag = "";
			$current_li = 1;
			$donefirst = false; // used for ads to tell when we've put the ad after the first step
			$bImgFound = false;
			$the_last_picture = '';
			$final_pic = array();
			$alt_link = array();
			
			#foreach ($parts as $p) {
			//XX Limit steps to 100 or it will timeout

			if ($numsteps < 300) {

				while ($p = array_shift($parts)) {
					switch (strtolower($p)) {
						case "<ol>":
							$level++;
							if ($level == 1)  {
								$p = '<ol class="steps_list_2">';
								$upper_tag = "ol";
							} else {
								$p = "&nbsp;<div class='listbody'>{$p}";
							}
							if ($current_tag != "")
								$tagstack[] = $current_tag;
							$current_tag = "ol";
							$levelstack[] = $current_li;
							$current_li = 1;
							break;
						case "<ul>":
							if ($current_tag != "")
								$tagstack[] = $current_tag;
							$current_tag = "ul";
							$levelstack[] = $current_li;
							$level++;
							break;
						case "</ol>":
						case "</ul>":
							$level--;
							if ($level == 0) $upper_tag = "";
							$current_tag = array_pop($tagstack);
							$current_li = array_pop($levelstack);
							break;
						case "<li>":
							$closecount = 0;
							if ($level == 1 && $upper_tag == "ol") {
								$li_number = $current_li++;
								$p = '<li><div class="step_num">' . $li_number . '</div><div class="check"><div>&#x2713;</div></div>';
									
								
								# this is where things get interesting. Want to make first sentence bold!
								# but we need to handle cases where there are tags in the first sentence
								# split based on HTML tags
								$next = array_shift($parts);
								
								$htmlparts = preg_split("@(<[^>]*>)@im", $next,
									0, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
								$dummy = 0;
								$incaption = false;
								$apply_b = false;
								$the_big_step = $next;
								while ($x = array_shift($htmlparts)) {
									# if it's a tag, just append it and keep going
									if (preg_match("@(<[^>]*>)@im", $x)) {
										//tag
										$p .= $x;
										if ($x == "<span class='caption'>")
											$incaption = true;
										elseif ($x == "</span>" && $incaption)
											$incaption = false;
										continue;
									}
									# put the closing </b> in if we hit the end of the sentence
									if (!$incaption) {
										if (!$apply_b && trim($x) != "") {
											$p .= "<b class='whb'>";
											$apply_b = true;
										}
										if ($apply_b) {
											$x = preg_replace("@([{$punct}])@im", "$1</b><br /><br />", $x, 1, $closecount);
										}
									}
									$p .= $x;
										
									if ($closecount > 0) {
										break;
									} else {
										#echo "\n\n-----$x----\n\n";
									}
									$dummy++;
								}
								
								# get anything left over
								$p .= implode("", $htmlparts);
								
								if ($closecount == 0) $p .= "</b>"; // close the bold tag if we didn't already
								if ($level == 1 && $current_li == 2 && $ads && !$donefirst) {
									$p .= wikihowAds::getAdUnitPlaceholder(0);
									$donefirst = true;
								}

							} elseif ($current_tag == "ol") {
								//$p = '<li><div class="step_num">'. $current_li++ . '</div>';
							}
							break;
						case "</li>":
							$p = "<div class='clearall'></div>{$p}"; //changed BR to DIV b/c IE doesn't work with the BR clear tag
							break;
					} // switch
					$steps .= $p;
				} // while
			} else {
				$steps = substr($body, $i, $j - $i);
				$steps = "<div id='steps_notmunged'>\n" . $steps . "\n</div>\n";
			}						
						
			// we have to put the final_li in the last OL LI step, so reverse the walk of the tokens
			$parts = preg_split("@(<[/]?ul>|<[/]?ol>|<[/]?li>)@im", $steps, 0, PREG_SPLIT_DELIM_CAPTURE);
			$parts = array_reverse($parts);
			$steps = "";
			$level = 0;
			$gotit = false;
			$donelast = false;
			foreach ($parts as $p) {
				$lp = strtolower($p);
				if ($lp == "</ol>" ) {
					$level++;
					$gotit= false;
				} elseif ($lp == "</ul>") {
					$level++;
				} elseif (strpos($lp, "<li") !== false && $level == 1 && !$gotit) {
					/// last OL step list fucker
					$p = preg_replace("@<li[^>]*>@i", '<li class="steps_li final_li">', $p);
					$gotit = true;
				} elseif (strpos($lp, "<ul") !== false) {
					$level--;
				} elseif (strpos($lp, "<ol") !== false) {
					$level--;
				} elseif ($lp == "</li>" && !$donelast) {
					// ads after the last step
					if ($ads){
						if(substr($body, $j) == ""){
							$p = "<script>missing_last_ads = true;</script>" . wikihowAds::getAdUnitPlaceholder(1) . $p;
							$no_third_ad = true;
						}
						else {
							$p = wikihowAds::getAdUnitPlaceholder(1) . $p;
						}
					}
					$donelast = true;
				}
				$steps = $p . $steps;
			}
			
			$body = substr($body, 0, $i) . $steps . substr($body, $j);
			
		} /// if numsteps == 100?
		
		/// ads below tips, walk the sections and put them after the tips
		if ($ads) {
			$foundtips = false;
			$anchorTag = "";
			foreach ($wgWikiHowSections as $s) {
				$isAtEnd = false;
				if ($s == "ingredients" || $s == "steps")
					continue; // we skip these two top sections
				$i = strpos($body, '<div id="' . $s. '"');
			    if ($i !== false) {
					$j = strpos($body, '<h2>', $i + strlen($s));
				} else {
					continue; // we didnt' find this section
				}
	    		if ($j === false){
					$j = strlen($body); // go to the end
					$isAtEnd = true;
				}
	    		if ($j !== false && $i !== false) {
	        		$section  = substr($body, $i, $j - $i);
					if ($s == "video") {
						// special case for video
						$newsection = "<div id='video'><center>{$section}</center></div>";
						$body = str_replace($section, $newsection, $body);
						continue;
					} elseif ($s == "tips") {
						//tip ad is now at the bottom of the tips section
						//need to account for the possibility of no sections below this and therefor
						//no anchor tag
						if($isAtEnd)
							$anchorTag = "<p></p>";
						
						$index = strripos($section, "</div>");
						$body = str_replace($section, substr($section, 0, $index) . wikihowAds::getAdUnitPlaceholder('2a') . "</div>" . $anchorTag , $body);
						$foundtips = true;
						break;
					} else {
						$foundtips = true;
						if($isAtEnd)
							$anchorTag = "<p></p>";
						
						$index = strripos($section, "</div>");
						$body = str_replace($section, substr($section, 0, $index) . wikihowAds::getAdUnitPlaceholder(2) . "</div>" . $anchorTag , $body);
						break;
					}
				}
			}
			if (!$foundtips && !$no_third_ad) { //must be the video section
				//need to put in the empty <p> tag since all the other sections have them for the anchor tags.
				$body .= "<p class='video_spacing'></p>" . wikihowAds::getAdUnitPlaceholder(2);
			}

		}	

		return $body;
	}
	
}
