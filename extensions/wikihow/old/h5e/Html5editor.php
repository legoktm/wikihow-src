<?

if (!defined('MEDIAWIKI')) die();

/**#@+
 * Uses new HTML 5 capabilities to allow article editing inline
 *
 * @package MediaWiki
 * @subpackage Extensions
 *
 * @link http://www.wikihow.com/WikiHow:Html5editor-Extension Documentation
 *
 *
 * @author Travis Derouin <travis@wikihow.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 */

// tweak this if you're developing
define('H5E_DEBUG', false);

$wgExtensionCredits['special'][] = array(
	'name' => 'Html5editor',
	'author' => 'Travis Derouin',
	'description' => 'Allows for editing of content with HTML5 features',
	'url' => 'http://www.wikihow.com/WikiHow:Html5editor-Extension',
);

#$wgExtensionMessagesFiles['Html5editor'] = dirname(__FILE__) . '/Html5editor.i18n.php';

$wgSpecialPages['Html5editor'] = 'Html5editor';
$wgAutoloadClasses['Html5editor'] = dirname( __FILE__ ) . '/Html5editor.body.php';
$wgExtensionMessagesFiles['Html5editor'] = dirname(__FILE__) . '/Html5editor.i18n.php';

$wgHooks['BeforePageDisplay'][] = array('Html5Setup');
$wgHooks['ParserBeforeStrip'][] = array('Html5WrapTemplates');
$wgHooks['SpamBlacklistFoundSpam'][] = array('Html5SetSpamMessage');

function Html5SetSpamMessage($match = false) {
	Html5editor::$spam_message = wfMsg( 'spamprotectiontext' );
	if ( $match ) {
		Html5editor::$spam_message .= wfMsgExt( 'spamprotectionmatch', 'parse', wfEscapeWikiText( $match ) );
	}
	return true;
}

function isHtml5Editable($editOK = false) {
	global $wgTitle, $wgRequest;

	$articleExists = $wgTitle->getArticleID() > 0;
	$action = $wgRequest->getVal('action', '');

	// TODO: during initial phase, articles are only edited with h5e if they
	// are being created
	$editable =
		(H5E_DEBUG || !$articleExists) // only non-existent articles for now
		&& (empty($action) || $action == 'view' ||
			($editOK && $action == 'edit'))
		&& $wgTitle->getFullText() != wfMsg('mainpage')
		&& $wgTitle->getNamespace() == NS_MAIN
		&& hasHtml5Browser();
	return $editable;
}

function hasHtml5Browser() {
	$userAgent = @$_SERVER['HTTP_USER_AGENT'];
	// TODO: during initial phase, only firefox
	if (!H5E_DEBUG) {
		$match = preg_match('@((firefox)/([0-9]+))@i', $userAgent, $m);
	} else {
		$match = preg_match('@((firefox)/([0-9]+)|(webkit)/([0-9]+)|(msie) ([0-9]+))@i', $userAgent, $m);
	}
	if ($match > 0) {
		if (count($m) >= 4 && $m[2]) { // firefox
			$match = (int)$m[3] >= 3;
		} elseif (count($m) >= 6 && $m[4]) { // webkit
			$match = (int)$m[5] >= 500;
		} elseif (count($m) >= 8 && $m[6]) { // msie
			$match = (int)$m[7] >= 8;
		}
	}
	return $match;
}

function Html5Setup() {
	global $wgOut, $wgRequest, $IP, $wgTitle;

	$articleExists = $wgTitle->getArticleID() > 0;

	$editable = isHtml5Editable();
	if ($editable) {
		wfLoadExtensionMessages('Html5editor');

		$imageUpload = Easyimageupload::getUploadBoxJS(false);
		$wgOut->addScript($imageUpload);

		$vars = array(
			'GOOGLE_SEARCH_API_KEY' => WH_GOOGLE_AJAX_IMAGE_SEARCH_API_KEY,
			'articleExists' => $articleExists,
		);
		EasyTemplate::set_path( dirname(__FILE__).'/' );
		$script = EasyTemplate::html('skin.tmpl.php', $vars);
		$wgOut->addScript($script);
	}

	return true;
}

function Html5SetupAddWrapperDiv($parser, $text) {
	$text = "<div id='bodycontents'>$text</div>";
	return true;
}

$wgIgnoreTemplates = array("SITENAME", "PLURAL", "sitename", "plural");

function HtmlWrapASinglePart($part) {
	global $wgIgnoreTemplates;
	$parts = preg_split("@({{[^}]*}})@", $part, 0, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
	$newcontent = "";
	//TODO: when an article is saved from advanced editor and regenerated this isn't called
	while ($x = array_shift($parts)) {
		if (strpos($x, "{{") === 0 && trim($x) != "") {
			$template = preg_replace("@\|.*|:.*@", "", $x);
			$template = preg_replace("@\{|\}@", "", $template);
			if (!in_array($template, $wgIgnoreTemplates)) {
				$newcontent .= "<span class='template'>{$x}</span>";
			} else {
				$newcontent .= $x;
			}
		} else {
			$newcontent .= "$x";
		}
	}
	return $newcontent;
}

#function Html5WrapTemplates(&$text) {
function Html5WrapTemplates(&$parser, &$text, &$stripstate) {

	// short circuit for non-article based parsing
	if (!preg_match("@^==[ ]*" . wfMsg('steps') . "@m", $text)) {
		return true;
 	}

	$nowikiparts = preg_split("@(<[/]?nowiki>)@im", $text, 0, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
	$newcontent = "";

	if (preg_match("@^<sup.*reference@", $text)) {
		return true;
	}

	while (sizeof($nowikiparts) > 0) {
		$n = array_shift($nowikiparts);
		if (strtolower($n) == "<nowiki>") {
			$n = array_shift($nowikiparts);
			$key = str_replace("{", "&123;", htmlspecialchars($n));
			$key = str_replace("}", "&125;", $n);
			$key = htmlspecialchars($key);
			$newcontent .= "<span class='nowiki' val=\"{$key}\"><nowiki>{$n}</nowiki></span>";
			array_shift($nowikiparts);
		} else {
			$newcontent .= HtmlWrapASinglePart($n);
		}
	}
	#echo $newcontent; exit;

	$text = $newcontent;
	#echo $content; exit;
	return true;
}

/**
 * Returns script to be placed in the head of the html doc for when the
 * edit buttons are pushed (so that they can force waiting until
 * the rest of the page has loaded).
 */
function Html5EditButtonBootstrap() {
	global $wgOut;
	EasyTemplate::set_path( dirname(__FILE__).'/' );
	$script = EasyTemplate::html('edit-bootstrap.tmpl.php');
	return $script;
}

/**
 * Show the new default edit page instead of the default "page not found".
 */
function Html5DefaultContent() {
	global $wgRequest, $wgOut;
	EasyTemplate::set_path( dirname(__FILE__).'/' );
	return EasyTemplate::html('new-article.tmpl.php');
}

