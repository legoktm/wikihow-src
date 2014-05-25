<?php

if ( !defined('MEDIAWIKI') ) exit;

$wgAutoloadClasses['ImageHooks'] = dirname( __FILE__ ) . '/ImageHooks.body.php';
$wgAutoloadClasses['PageHooks'] = dirname( __FILE__ ) . '/PageHooks.body.php';

$wgHooks['ImageConvertNoScale'][] = array('ImageHooks::onImageConvertNoScale');
$wgHooks['ImageConvertComplete'][] = array('ImageHooks::onImageConvertComplete');
$wgHooks['FileTransform'][] = array('ImageHooks::onFileTransform');
$wgHooks['BitmapDoTransformScalerParams'][] = array('ImageHooks::onBitmapDoTransformScalerParams');
$wgHooks['FileThumbName'][] = array('ImageHooks::onFileThumbName');
$wgHooks['ImageConvert'][] = array('ImageHooks::onImageConvert');
$wgHooks['ThumbnailBeforeProduceHTML'][] = array('ImageHooks::onThumbnailBeforeProduceHTML');
$wgHooks['ImageBeforeProduceHTML'][] = array('ImageHooks::onImageBeforeProduceHTML');
$wgHooks['ImageHandlerParseParamString'][] = array('ImageHooks::onImageHandlerParseParamString');
$wgHooks['TitleSquidURLs'][] = array('PageHooks::onTitleSquidURLs');

function getSearchKeyStopWords() {
	global $wgMemc;

	$cacheKey = wfMemcKey('stop_words');
	$cacheResult = $wgMemc->get($cacheKey);
	if ($cacheResult) {
		return $cacheResult;
	}

	$sql = "SELECT stop_words FROM stop_words limit 1";
	$stop_words = null;
	$db = wfGetDB(DB_SLAVE);
	$res = $db->query($sql, __METHOD__);
	if ( $db->numRows($res) ) {
		while ( $row = $db->fetchObject($res) ) {
			$stop_words = split(", ", $row->stop_words);
		}
	}
	$db->freeResult( $res );

	$s_index = array();
	if (is_array($stop_words)) {
		foreach ($stop_words as $s) {
			$s_index[$s] = "1";
		}
	}

	$wgMemc->set($cacheKey, $s_index);

	return $s_index;
}

function generateSearchKey($text) {
	$stopWords = getSearchKeyStopWords();

	$text = strtolower($text);
	$tokens = split(' ', $text);
	$ok_words = array();
	foreach ($tokens as $t) {
		if ($t == '' || isset($stopWords[$t]) ) continue;
		$ok_words[] = $t;
	}
	sort($ok_words);
	$key = join(' ', $ok_words);
	$key = trim($key);

	return $key;
}

function updateSearchIndex($new, $old) {
	$dbw = wfGetDB(DB_MASTER);
	if ($new != null
		&& ($new->getNamespace() == 0
			|| $new->getNamespace() == 16) )
	{
		$dbw->delete( 'title_search_key',
			array('tsk_title' => $new->getDBKey(),
				  'tsk_namespace' => $new->getNamespace()),
			__METHOD__ );

		$dbw->insert( 'title_search_key',
			array('tsk_title' => $new->getDBKey(),
				  'tsk_namespace' => $new->getNamespace(),
				  'tsk_key' => generateSearchKey($new->getText()) ),
			__METHOD__ );
	}

	if ($old != null) {
		$dbw->delete( 'title_search_key',
			array('tsk_title' => $old->getDBKey(),
				  'tsk_namespace' => $old->getNamespace()),
			__METHOD__ );
	}
}

function wfMarkUndoneEditAsPatrolled() {
	global $wgRequest;
	if ($wgRequest->getVal('wpUndoEdit', null) != null) {
		$oldid = $wgRequest->getVal('wpUndoEdit');
		// using db master to avoid db replication lag
		$dbr = wfGetDB(DB_MASTER);
		$rcid = $dbr->selectField('recentchanges', 'rc_id', array('rc_this_oldid' => $oldid) );
		RecentChange::markPatrolled($rcid);
		PatrolLog::record($rcid, false);
	}
	return true;
}

function wfTitleMoveComplete($title, &$newtitle, &$user, $oldid, $newid) {
	updateSearchIndex($title, $newtitle);
	return true;
}
$wgHooks['TitleMoveComplete'][] = array('wfTitleMoveComplete');

function wfArticleSaveComplete($article, $user, $p2, $p3, $p5, $p6, $p7) {
	global $wgMemc;

	if ($article) {
		updateSearchIndex($article->getTitle(), null);
	}
	wfMarkUndoneEditAsPatrolled();

	// In WikiHowSkin.php we cache the info for the author line. we want to
	// remove this if that article was edited so that old info isn't cached.
	if ($article && class_exists('SkinWikihowskin')) {
		$cachekey = ArticleAuthors::getLoadAuthorsCachekey($article->getID());
		$wgMemc->delete($cachekey);
	}

	return true;
}
$wgHooks['ArticleSaveComplete'][] = array('wfArticleSaveComplete');

function wfUpdateCatInfoMask(&$article, &$user) {
	if ($article) {
		$title = $article->getTitle();
		if ($title && $title->getNamespace() == NS_MAIN) {
			$mask = Categoryhelper::getTitleCategoryMask($title);
			$dbw = wfGetDB(DB_MASTER);
			$dbw->update('page',
				array('page_catinfo' => $mask),
				array('page_id' => $article->getID()),
				__METHOD__);
		}
	}
	return true;
}
$wgHooks['ArticleSaveComplete'][] = array('wfUpdateCatInfoMask');

function wfUpdatePageFeaturedFurtherEditing($article, $user, $text, $summary, $flags) {
	if ($article) {
		$t = $article->getTitle();
		if (!$t || $t->getNamespace() != NS_MAIN) {
			return true;
		}
	}

	$templates = split("\n", wfMsgForContent('templates_further_editing'));
	$regexps = array();
	foreach ($templates as $template) {
		$template = trim($template);
		if ($template == "") continue;
		$regexps[] ='\{\{' . $template;
	}
	$re = "@" . implode("|", $regexps) . "@i";

	$updates = array();
	if (preg_match_all($re, $text, $matches)) {
		$updates['page_further_editing'] = 1;
	}
	else{
		$updates['page_further_editing'] = 0; //added this to remove the further_editing tag if its no longer needed
	}
	if (preg_match("@\{\{fa\}\}@i", $text)) {
		$updates['page_is_featured'] = 1;
	}
	if (sizeof($updates) > 0) {
		$dbw = wfGetDB(DB_MASTER);
		$dbw->update('page', $updates, array('page_id'=>$t->getArticleID()), __METHOD__);
	}
	return true;
}

$wgHooks['ArticleSaveComplete'][] = array('wfDeleteParentCategoryKey');

/*
* Delete the memcache key that stores the parent category breadcrumbs so that they will update
* on wikitext category changes
*/
function wfDeleteParentCategoryKey($article, $user, $text, $summary, $flags) {
	global $wgMemc;

	if ($article) {
		$t = $article->getTitle();
		if (!$t || $t->getNamespace() != NS_MAIN) {
			return true;
		}
		$key = wfMemcKey('parentcattree', $t->getArticleId());
		$wgMemc->delete($key);
	}
	return true;
}

$wgHooks['ArticleSaveComplete'][] = array('wfUpdatePageFeaturedFurtherEditing');

function wfSetPage404IfNotExists() {
	global $wgTitle, $wgOut, $wgLanguageCode;

	// Note: if namespace < 0, it's a virtual namespace like NS_SPECIAL
	// Check if image exists for foreign language images, because Title may not exist since image may only be on English
	if ($wgTitle && $wgTitle->getNamespace() >= 0 && !$wgTitle->exists() &&
	($wgLanguageCode =="en" || $wgTitle->getNamespace() != NS_IMAGE || !wfFindFile($wgTitle))
	) {
		$redirect = Misc::check404Redirect($wgTitle);
		if (!$redirect) {
			$wgOut->setStatusCode(404);
		} else {
			$wgOut->redirect('/' . $redirect, 301);
		}
	}
	return true;
}
$wgHooks['OutputPageBeforeHTML'][] = array('wfSetPage404IfNotExists');

// implemented in Misc.body.php
$wgHooks['JustBeforeOutputHTML'][] = array('Misc::setMobileLayoutHeader');
$wgHooks['JustBeforeOutputHTML'][] = array('Misc::addVarnishHeaders');

function wfAddCacheControlHeaders() {
	global $wgTitle, $wgRequest;

	if ($wgRequest && $wgTitle && $wgTitle->getText() == wfMsg('mainpage')) {
		$wgRequest->response()->header('X-T: MP');
	}

	return true;
}
$wgHooks['AddCacheControlHeaders'][] = array('wfAddCacheControlHeaders');

// Add to the list of available JS vars on every page
function wfAddJSglobals(&$vars) {
	$vars['wgCDNbase'] = wfGetPad('');
	return true;
}
$wgHooks['MakeGlobalVariablesScript'][] = array('wfAddJSglobals');

//
// Hooks for managing 404 redirect system
//
function wfFix404AfterMove($oldTitle, $newTitle) {
	if ($oldTitle && $newTitle) {
		Misc::modify404Redirect($oldTitle->getArticleID(), null);
		Misc::modify404Redirect($newTitle->getArticleID(), $newTitle);
	}
	return true;
}
function wfFix404AfterDelete($wikiPage) {
	if ($wikiPage) {
		$pageid = $wikiPage->getId();
		if ($pageid > 0) {
			Misc::modify404Redirect($pageid, null);
		}
	}
	return true;
}
function wfFix404AfterInsert($article) {
	if ($article) {
		$title = $article->getTitle();
		if ($title) {
			Misc::modify404Redirect($article->getID(), $title);
		}
	}
	return true;
}
function wfFix404AfterUndelete($title) {
	if ($title) {
		$pageid = $title->getArticleID();
		Misc::modify404Redirect($pageid, $title);
	}
	return true;
}
$wgHooks['TitleMoveComplete'][] = array('wfFix404AfterMove');
$wgHooks['ArticleDelete'][] = array('wfFix404AfterDelete');
$wgHooks['ArticleInsertComplete'][] = array('wfFix404AfterInsert');
$wgHooks['ArticleUndelete'][] = array('wfFix404AfterUndelete');
$wgHooks['EditPageBeforeEditToolbar'][] = array('wfEditPageBeforeEditToolbar');

function wfEditPageBeforeEditToolbar(&$toolbar) {
	global $wgStylePath, $wgOut, $wgLanguageCode;

	$params = array(
		$image = $wgStylePath . '/owl/images/1x1_transparent.gif',
		// Note that we use the tip both for the ALT tag and the TITLE tag of the image.
		// Older browsers show a "speedtip" type message only for ALT.
		// Ideally these should be different, realistically they
		// probably don't need to be.
		$tip = 'Weave links',
		$open = '',
		$close = '',
		$sample = '',
		$cssId = 'weave_button',
	);
	$script = Xml::encodeJsCall( 'mw.toolbar.addButton', $params );
	$wgOut->addScript( Html::inlineScript( ResourceLoader::makeLoaderConditionalScript( $script ) ) );

	$params = array(
		$image = $wgStylePath . '/owl/images/1x1_transparent.gif',
		// Note that we use the tip both for the ALT tag and the TITLE tag of the image.
		// Older browsers show a "speedtip" type message only for ALT.
		// Ideally these should be different, realistically they
		// probably don't need to be.
		$tip = 'Add Image',
		$open = '',
		$close = '',
		$sample = '',
		$cssId = 'easyimageupload_button',
		$onclick = "easyImageUpload.doEIUModal('advanced');return false;",
	);
	$script = Xml::encodeJsCall( 'mw.toolbar.addButton', $params );
	$wgOut->addScript( Html::inlineScript( ResourceLoader::makeLoaderConditionalScript( $script ) ) );

	$wgOut->addJScode('advj');
	$wgOut->addJScode('eiuj');

	if (in_array($wgLanguageCode, array('en', 'de', 'es', 'pt'))){
		$popbox =  PopBox::getPopBoxJSAdvanced() . PopBox::getPopBoxCSS();
		$popbox_div = PopBox::getPopBoxDiv();
		$wgOut->addHTML($popbox_div . $popbox);
		$wgOut->addHTML(Easyimageupload::getUploadBoxJS(true));
	}

	return true;
}

function onDoEditSectionLink( $skin, $nt, $section, $tooltip, $result, $lang ) {
	$query = array();
	$query['action'] = "edit";
	$query['section'] = $section;

	//INTL: Edit section buttons need to be bigger for intl sites
	$editSectionButtonClass = "editsection";
	$customAttribs = array("class"=>$editSectionButtonClass, "onclick"=>"gatTrack(gatUser,\'Edit\',\'Edit_section\');");
	$customAttribs['title'] = wfMessage( 'editsectionhint' )->rawParams( htmlspecialchars( $tooltip ) )->escaped();

	$result = Linker::link( $nt, wfMessage('editsection')->text(), $customAttribs, $query, "known");

	return true;
}

$wgHooks['DoEditSectionLink'][] = array('onDoEditSectionLink');

// AG - styling for logout page
function onUserLogoutComplete( &$user, &$injected_html, $oldName) {
	$injected_html.= "
	<style type='text/css'>
	#bodycontents pre {
		font-family: Helvetica, arial, sans-serif;
		-webkit-font-smoothing: antialiased;
		margin-top: 3px;
		margin-bottom: 25px;
	}
	</style>
	";

	return true;
}
$wgHooks['UserLogoutComplete'][] = array('onUserLogoutComplete');

$wgHooks['ParserClearState'][] = array('turnOffAutoTOC');
function turnOffAutoTOC($parser) {
	$parser->mShowToc = false;

	return true;
}

$wgHooks['HeadScriptsStartupScript'][] = array('headScriptsStartupScript');
function headScriptsStartupScript($outputPage, $scripts) {
	$resourceLoader = $outputPage->getResourceLoader();
	$module = $resourceLoader->getModule( 'startup' );
	$query = ResourceLoader::makeLoaderQuery(
			array(), // modules; not determined yet
			$outputPage->getLanguage()->getCode(),
			$outputPage->getSkin()->getSkinName(),
			null, // user ... null for startup module
			null, // version; not determined yet
			ResourceLoader::inDebugMode(), //debug
			"scripts", // only
			$outputPage->isPrintable(), //printable
			$outputPage->getRequest()->getBool( 'handheld' ),
			array() // extra query params
			);

	$context = new ResourceLoaderContext( $resourceLoader, new FauxRequest( $query ) );
	$scripts = Html::inlineScript($resourceLoader->makeModuleResponse( $context, array($module)));

	return true;
}

$wgHooks['NewDifferenceEngine'][] = array('onNewDifferenceEngine');
function onNewDifferenceEngine($title, &$oldId, &$newId, $old, $new) {
	if ($old === false && $oldId === 0) {
		$oldId = false;
	}
	return true;
}

$wgHooks['DifferenceEngineShowDiff'][] = array('onDifferenceEngineShowDiff');
function onDifferenceEngineShowDiff(&$differenceEngine) {
	$differenceEngine->getOutput()->addCSScode('diffc');
	return true;
}

$wgHooks['DifferenceEngineShowDiffPage'][] = array('onDifferenceEngineShowDiffPage');
function onDifferenceEngineShowDiffPage(&$out) {
	$out->addCSScode('diffc');
	return true;
}

$wgHooks['DifferenceEngineOldHeaderNoOldRev'][] = array('onDifferenceEngineOldHeaderNoOldRev');
function onDifferenceEngineOldHeaderNoOldRev(&$oldHeader) {
	// Scott 1/15/14: make sure we get 2 columns -- add header
	$oldHeader = wfMessage('diff_noprev')->plain();
	return true;
}


$wgHooks['DifferenceEngineOldHeader'][] = array('onDifferenceEngineOldHeader');
function onDifferenceEngineOldHeader($differenceEngine, &$oldHeader, $prevlink, $oldminor, $diffOnly, $ldel, $unhide) {
	global $wgLanguageCode;

	$oldRevisionHeader = $differenceEngine->getRevisionHeader( $differenceEngine->mOldRev, 'complete', 'old' );

	$oldDaysAgo = wfTimeAgo($differenceEngine->mOldRev->getTimestamp());

	//INTL: Avatar database data doesn't exist for sites other than English
	if ($wgLanguageCode == 'en') {
		$av = '<img src="' . Avatar::getAvatarURL($differenceEngine->mOldRev->getUserText()) . '" class="diff_avatar" />';
	}

	$oldHeader = '<div id="mw-diff-otitle1"><h4>' . $prevlink . $oldRevisionHeader . '</h4></div>' .
		'<div id="mw-diff-otitle2">' . $av . '<div id="mw-diff-oinfo">' .
		Linker::revUserTools( $differenceEngine->mOldRev, !$unhide ) .
		'<br /><div id="mw-diff-odaysago">' . $oldDaysAgo . '</div>' .
		'</div></div>' .
		'<div id="mw-diff-otitle3" class="rccomment">' . $oldminor .
		Linker::revComment( $differenceEngine->mOldRev, !$diffOnly, !$unhide ) . $ldel . '</div>';

	return true;
}

$wgHooks['DifferenceEngineNewHeader'][] = array('onDifferenceEngineNewHeader');
function onDifferenceEngineNewHeader($differenceEngine, &$newHeader, $formattedRevisionTools, $nextlink, $rollback, $newminor, $diffOnly, $rdel, $unhide) {
	global $wgLanguageCode, $wgTitle;
	$user = $differenceEngine->getUser();

	$newRevisionHeader = $differenceEngine->getRevisionHeader( $differenceEngine->mNewRev, 'complete', 'new' ) . ' ' . implode( ' ', $formattedRevisionTools );

	$newDaysAgo = wfTimeAgo($differenceEngine->mNewRev->getTimestamp());

	//INTL: Avatar database data doesn't exist for sites other than English
	if ($wgLanguageCode == 'en') {
		$av = '<img src="' . Avatar::getAvatarURL($differenceEngine->mNewRev->getUserText()) . '" class="diff_avatar" />';
	}

	$thumbsHtml = "";
	$thumbHeader = "";
	$th_diff_div = "";
	if ($user->getId() != 0 && $wgTitle->getText() != "RCPatrol" && $wgTitle->getText() != "RCPatrolGuts" && $differenceEngine->mNewRev->getTitle()->getNamespace() == NS_MAIN) {
		$oldId = $differenceEngine->mNewRev->getPrevious();
		$oldId = $oldId ? $oldId->getId() : -1;
		// Only show thumbs up for diffs that look back one revision
		if (class_exists('ThumbsUp')) {
			if ($oldId == -1 || ($differenceEngine->mOldRev && $oldId == $differenceEngine->mOldRev->getId()))  {
				$params = array ('title' => $differenceEngine->mNewRev->getTitle(), 'new' => $differenceEngine->mNewid, 'old' => $oldId, 'vandal' => 0);
				$thumbsHtml = ThumbsUp::getThumbsUpButton($params, true);
				$th_diff_div = 'class="th_diff_div"';
			}
		}
	}

	$newHeader = '<div id="mw-diff-ntitle1" ' . $th_diff_div . '><h4 ' . $thumbHeader . '>' . $newRevisionHeader . $nextlink . '</h4></div>' .
		'<div id="mw-diff-ntitle2">' . $av . $thumbsHtml . '<div id="mw-diff-oinfo">'
		. Linker::revUserTools( $differenceEngine->mNewRev, !$unhide ) .
		" $rollback " .
		'<br /><div id="mw-diff-ndaysago">' . $newDaysAgo . '</div>' .
		"</div>" .
		'<div id="mw-diff-ntitle4">' . $differenceEngine->markPatrolledLink() . '</div>' .
		"</div>" .
		'<div id="mw-diff-ntitle3" class="rccomment">' . $newminor .
		Linker::revComment( $differenceEngine->mNewRev, !$diffOnly, !$unhide ) . $rdel . '</div>';

	return true;
}

$wgHooks['DifferenceEngineMarkPatrolledRCID'][] = array('onDifferenceEngineMarkPatrolledRCID');
function onDifferenceEngineMarkPatrolledRCID(&$rcid, $differenceEngine, $change, $user) {
	if ($rcid == 0) {
		if ( $change && $differenceEngine->mNewPage->quickUserCan( 'autopatrol', $user ) ) {
			$rcid = $change->getAttribute( 'rc_id' );
		}
	}
	return true;
}

$wgHooks['DifferenceEngineMarkPatrolledLink'][] = array('onDifferenceEngineMarkPatrolledLink');
function onDifferenceEngineMarkPatrolledLink($differenceEngine, &$markPatrolledLink, $rcid, $token) {
	// Reuben: Include RC patrol/browsing opts when patrolling or skipping
	$req = $differenceEngine->getContext()->getRequest();
	$browseParams = Misc::getRecentChangesBrowseParams($req);
	if ( !$browseParams['fromrc'] ) {
		$browseParams = array();
	}

	$nextToPatrol = ' <span class="patrolnextlink" style="display:none">' .
		htmlspecialchars( RCPatrol::getNextURLtoPatrol($rcid) ) .
		'</span>';

	$markPatrolledLink = $nextToPatrol .
		' <span class="patrollink">[' .
		Linker::linkKnown(
			$differenceEngine->mNewPage,
			$differenceEngine->msg( 'markaspatrolleddiff' )->escaped(),
			array(),
			array(
				'action' => 'markpatrolled',
				'rcid' => $rcid,
				'token' => $token,
			) + $browseParams
		) .
		'&nbsp;|&nbsp;' .
		Linker::linkKnown(
			$differenceEngine->mNewPage,
			$differenceEngine->msg( 'skip' )->escaped(),
			array('class' => 'patrolskip'),
			array(
				'action' => 'markpatrolled',
				'skip' => 1,
				'rcid' => $rcid,
				'token' => $token,
			) + $browseParams
		) .
		']</span>';

	return true;
}

$wgHooks['DifferenceEngineGetRevisionHeader'][] = array('onDifferenceEngineGetRevisionHeader');
function onDifferenceEngineGetRevisionHeader($differenceEngine, &$header, $state, $rev) {
	if($state == 'new') {
		if($rev->isCurrent()) {
			$header = htmlspecialchars( wfMsg( 'currentrev' ) );
		}
		else {
			$header = wfMsgHTML( 'revisionasof', wfTimeAgo($differenceEngine->mNewRev->getTimestamp()) );
		}
	} elseif ($state == 'old') {
		$header = "Old Revision";
	}

	return true;
}

$wgHooks['DifferenceEngineRenderRevisionShowFinalPatrolLink'][] = array('onDifferenceEngineRenderRevisionShowFinalPatrolLink');
function onDifferenceEngineRenderRevisionShowFinalPatrolLink() {
	// we do not want to show this link right now
	return false;
}

$wgHooks['DifferenceEngineRenderRevisionAddParserOutput'][] = array('onDifferenceEngineRenderRevisionAddParserOutput');
function onDifferenceEngineRenderRevisionAddParserOutput($differenceEngine, &$out, $parserOutput, $wikiPage) {
	$magic = WikihowArticleHTML::grabTheMagic($differenceEngine->mNewRev->getText());
	$html = WikihowArticleHTML::processArticleHTML($parserOutput->getText(), array('ns' => $wikiPage->mTitle->getNamespace(), 'magic-word' => $magic));
	$out->addHTML( $html );
	return true;
}

// this is so we can display the diff for new articles [sc - 1/16/2014]
$wgHooks['DifferenceEngineShowEmptyOldContent'][] = array('onDifferenceEngineShowEmptyOldContent');
function onDifferenceEngineShowEmptyOldContent(&$differenceEngine) {
	$oldContent = ContentHandler::makeContent( '', $differenceEngine->getTitle() );
	$differenceEngine->mOldContent = $oldContent;
	return true;
}

// Reuben 1/14: this hook will get rid of [Mark as Patrolled] at bottom of page.
$wgHooks['ArticleShowPatrolFooter'][] = array('onArticleShowPatrolFooter');
function onArticleShowPatrolFooter() {
	return false;
}

// Reuben 3/20: Jenn, through Anna, asked that Special:WantedPages would only
// contain links from main namespace articles to redlinks in other main
// namespace articles. This hook accomplishes that.
$wgHooks['WantedPages::getQueryInfo'][] = array('onWantedPagesGetQueryInfo');
function onWantedPagesGetQueryInfo(&$specialPage, &$query) {
	$query['conds'] = array(
		'pg1.page_namespace IS NULL',
		"pl_namespace" => NS_MAIN,
		"pg2.page_namespace" => NS_MAIN
	);

	return true;
}

// ARG added this hook to remove the version from the startup module scripts
$wgHooks['ResourceLoaderStartupModuleQuery'][] = array('onResourceLoaderStartupModuleQuery');
function onResourceLoaderStartupModuleQuery(&$query) {
	unset($query['version']);
	return true;
}

// $wgHooks['BeforeOutputAltMethodTOC'][] = array('runAltMethodTOCTest');
// function runAltMethodTOCTest($title, $anchorlist, $bAfter) {
	// //only for English
	// global $wgLanguageCode;
	// if ($wgLanguageCode != 'en') return true;

	// //TEST 01?
	// //same alt methods, but after the contributor list
	// $article_ids = explode("\n",ConfigStorage::dbGetConfig('wikihow-methodtoc-test-01'));
	// if ($title && in_array($title->getArticleID(),$article_ids)) {
		// $anchorlist = preg_replace('@<a href(.*)</a>@mU','<a style="font-weight:bold;" href$1</a>',$anchorlist).
						// '<br /><br />';
		// $bAfter = true;
		// return true;
	// }

	// //TEST 02?
	// //Table of Contents style list after the contributor list
	// $article_ids = explode("\n",ConfigStorage::dbGetConfig('wikihow-methodtoc-test-02'));
	// if ($title && in_array($title->getArticleID(),$article_ids)) {
		// $anchorlist = '<span style="display:block;margin-bottom:.5em;">Table of Contents</span>'.
						// preg_replace('@</a>@','</a><br />',$anchorlist).
						// '<br />';
		// $bAfter = true;
		// return true;
	// }

	// return true;
// }
