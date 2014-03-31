<?php
/**
 * Special handling for category description pages
 * Modelled after ImagePage.php
 *
 */

if( !defined( 'MEDIAWIKI' ) )
	die( 1 );

/**
 */
class WikihowUserPage extends Article {

	var $featuredArticles;
	var $user;
	var $isPageOwner;

	static function onArticleFromTitle($title, &$page) {
		
		if ($title && NS_USER == $title->getNamespace()) {
			global $wgOut;
			$wgOut->addJScode('pbj');
			$wgOut->addCSScode('pbc');			
			$page = new WikihowUserPage($title);
		}

		return true;
	}

	function view($u = null) {
		global $wgOut, $wgTitle, $wgUser, $wgRequest;

		$diff = $wgRequest->getVal( 'diff' );
		$rcid = $wgRequest->getVal( 'rcid' );
		$this->user = ($u) ? $u : User::newFromName($wgTitle->getDBKey());

		if ((!$u && $this->mTitle->getNamespace() != NS_USER) || !$this->user || 
			isset( $diff ) || isset( $rcid )) {
				return Article::view();
		}
		
		if($this->user->getID() == 0) {
			header('HTTP/1.1 404 Not Found');
			$wgOut->setRobotpolicy( 'noindex,nofollow' );
			$wgOut->showErrorPage( 'nosuchuser', 'Noarticletext_user' );
			return;
		}

		$this->isPageOwner = $wgUser->getID() == $this->user->getID();
		if( $this->user->isBlocked() && $this->isPageOwner ) {
			$wgOut->blockedPage();
			return;
		}

		$wgOut->setRobotpolicy( 'index,follow' );
		$skin = $this->getContext()->getSkin();

		//user settings
		$checkStats = ($this->user->getOption('profilebox_stats') == 1);
		$checkStartedEdited = ($this->user->getOption('profilebox_startedEdited') == 1);

		$profileStats = new ProfileStats($this->user);

		$badgeData = $profileStats->getBadges();
		$wgOut->addHTML(ProfileBox::getDisplayBadge($badgeData));

		if (!$u) {
			$skin->addWidget($this->getRCUserWidget());
		}
		
		if ($checkStats || $checkStartedEdited) $createdData = $profileStats->getArticlesCreated(0);
			
		//stats
		if ($checkStats) {
			$stats = ProfileBox::fetchStats("User:" . $this->user->getName());
			$wgOut->addHTML(ProfileBox::getStatsDisplay($stats, $this->user->getName(), count($createdData)));
		}
		
		//articles created
		if ($checkStartedEdited) {
			$wgOut->addHTML(ProfileBox::getDisplayCreatedData($createdData, 5));

			//thumbed up edits
			$thumbsData = $profileStats->fetchThumbsData(0);
			$wgOut->addHTML(ProfileBox::getDisplayThumbData($thumbsData, 5));
		}
		
		$this->mTitle = ($u) ? Title::newFromText('User:'.$this->user->getName()) : $wgTitle;
		$wp = new WikiPage($this->mTitle);
		$popts = $wgOut->parserOptions();
		$popts->setTidy(true);
		$content = $wp->getContent();
		if ($content) {
			$parserOutput = $content->getParserOutput($this->mTitle, null, $popts, false)->getText();
			$html = WikihowArticleHTML::processArticleHTML($parserOutput, array('no-ads' => true, 'ns' => NS_USER));
			$wgOut->addHTML($html);
		}
		
		//contributions and views
		$contributions = $profileStats->getContributions();
		$views = ProfileBox::getPageViews();
		$wgOut->addHTML(ProfileBox::getFinalStats($contributions, $views));
		
		if (!$u) {
			$wgOut->addHTML("<div class='clearall'></div>");
		}
	}

	function getRCUserWidget() {
		$html = $this->getUserWidgetData();

		return $html;
	}

	function getUserWidgetData() {
		if (!class_exists('RCWidget')) return '';

		$data = RCWidget::pullData($this->user->getID());

		$tmpl = new EasyTemplate( dirname(__FILE__) );
		$tmpl->set_vars(array(
			'elements' => $data
		));
		$html = $tmpl->execute('rcuserwidget.tmpl.php');

		return $html;
	}
}
