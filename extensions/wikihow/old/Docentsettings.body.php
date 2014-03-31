<?php
if ( ! defined( 'MEDIAWIKI' ) )
	die();
/**#@+
 * 
 * @package MediaWiki
 * @subpackage Extensions
 *
 * @link http://www.wikihow.com/WikiHow:Docentsettings-Extension Documentation
 *
 *
 * @author Travis Derouin <travis@wikihow.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 */

$wgExtensionCredits['specialpage'][] = array(
    'name' => 'Docentsettings',
    'author' => 'Travis <travis@wikihow.com>',
	'description' => 'Provides a way of administering docent settings',
);

$wgExtensionMessagesFiles['Docentsettings'] = dirname(__FILE__) . '/Docentsettings.i18n.php';

$wgSpecialPages['Docentsettings'] = 'Docentsettings';
$wgAutoloadClasses['Docentsettings'] = dirname( __FILE__ ) . '/Docentsettings.body.php';
 
class Docentsettings extends SpecialPage {

    function __construct() {
        parent::__construct( 'Docentsettings' );
    }


	function getcheckbox($name, $already_subscribed, $trail = "", $disable_name = true) {
		$option = "";
		$display = $name;
		$name = htmlspecialchars($name);
		$style = '';
		if (isset($already_subscribed[$display])) {
			$option = " checked disabled ";
			if ($disable_name) { 
				$name = "disbled_name";
				return "<input {$style} type='checkbox' name=\"{$name}\" {$option} > <i>{$display}</i> {$trail}";
			} else {
				$option = " checked ";
			}
		}
		return "<input {$style} type='checkbox' name=\"{$name}\" value=\"{$name}\" {$option} > {$display} {$trail}";
	}
	
    function execute ($par) {
		global $wgRequest, $wgUser, $wgOut, $wgLang, $wgServer;
	
		$dbr = wfGetDB(DB_SLAVE);
		$me = Title::makeTitle(NS_SPECIAL, "Docentsettings");
	
		if ($wgRequest->wasPosted()) {
			$new = array();
			$new_key = array();
			foreach($wgRequest->getValues() as $key=>$value) {
				if ($value && ($key != 'title')) {
					$t = Title::makeTitle(NS_CATEGORY, $value);
					if ($t->getArticleID() == 0) {
						$wgOut->addHTML("Error: Unable to add category to settings for <b>$key</b>, please post this problem to <a href='http://www.wikihow.com/forum/viewforum.php?f=6'>Tech feedback</a>");
					} else {
						$new[] = $t;
						$new_key[$t->getDBKEy()] = 1;
					}
				}
			}
	
			$dbw = wfGetDB(DB_MASTER);
	
	//** UPDATE THE MAILMAN SETTINGS **//
			$old_key = array();
			$res = $dbr->select('docentcategories', array('dc_to'), array('dc_user' => $wgUser->getID() ));
			while ($row = $dbr->fetchObject($res)) {
				$old_key[$row->dc_to] = 1;
			}
			$dbr->freeResult($res);	
		
			$remove = array();
			foreach ($old_key as $key=>$value) {
				if (!isset($new_key[$key])) $remove[] = $key;
			}	
			$add = array();
			foreach ($new_key as $key=>$value) {
				if (!isset($old_key[$key])) $add[] = $key;
			}	
	
			foreach($add as $a) {
				$t = Title::makeTitle(NS_CATEGORY, $a);
				$dbw->delete('mailman_unsubscribe', array('mm_user' => $wgUser->getID(), 'mm_list' => $t->getDBKey(), 'mm_done=0'));
				$dbw->insert('mailman_subscribe', array('mm_user' => $wgUser->getID(), 'mm_list' => $t->getDBKey()));
			}
			foreach($remove as $a) {
				$t = Title::makeTitle(NS_CATEGORY, $a);
				$dbw->delete('mailman_subscribe', array('mm_user' => $wgUser->getID(), 'mm_list' => $t->getDBKey(), 'mm_done=0'));
				$dbw->insert('mailman_unsubscribe', array('mm_user' => $wgUser->getID(), 'mm_list' => $t->getDBKey()));
			}
	//** UPDATE THE MAILMAN SETTINGS **//
	
			$dbw->delete('docentcategories', array('dc_user' => $wgUser->getID() ));
		
			foreach($new as $t) {
				$dbw->insert('docentcategories', array('dc_user' => $wgUser->getID(), 'dc_to' => $t->getDBKey()));
			}
		}
		$wgOut->addHTML(wfMsg('docentsettings_info') . "<br/><br/>");
		$cats = Categoryhelper::getCategoryDropDownTree();
		$count = 0;

		$wgOut->addHTML("	
				<form method='POST' action='{$me->getFullURL()}'>
				<style type='text/css'>
					table.docentsettings {
					}
					table.docentsettings td {
						border: 1px solid #ccc;
						padding: 10px;
						font-size: 90%;
						vertical-align: top;
					}
				</style>
			");
		// check the referrer
		$refer = $_SERVER['HTTP_REFERER'];
		if ($refer && strpos($refer, $wgServer . "/Category") === 0) {
			$refer = str_replace($wgServer . "/", "", $refer);
			$t = Title::newFromURL($refer);
            if ($t && !isset($already_subscribed[$t->getText()])) {
            	$already_subscribed[$t->getText()] = 1;
        		$wgOut->addHTML("Add this category: <table width='100%' class='docentsettings'><tr>");
            	$wgOut->addHTML("<td>" . $this->getcheckbox($t->getText(), $already_subscribed, "", false) . "</td>");
            	$wgOut->addHTML("</tr><tr>");
        		$wgOut->addHTML("</tr></table><br/>");
			}
        }
	
		$wgOut->addHTML("Here are the catgories you are currently subscribed to. To remove a category, uncheck it in this section.<br/><br/>
	  			<table width='100%' class='docentsettings'><tr>
			");
	
		$count = 0; 	
		$res = $dbr->select('docentcategories', array('dc_to'), array('dc_user' => $wgUser->getID() ));
		$already_subscribed = array();
		while ($row = $dbr->fetchObject($res)) {
			$t = Title::makeTitle(NS_CATEGORY, $row->dc_to);
			$already_subscribed[$t->getText()] = 1;
			$wgOut->addHTML("<td>" . $this->getcheckbox($t->getText(), $already_subscribed, "", false) . "</td>");
			if ($count % 2 == 1) 
				$wgOut->addHTML("</tr><tr>");
			$count++;
		}
		$dbr->freeResult($res);
		$wgOut->addHTML("</tr><table>");
	

	
		$templates = wfMsgForContent('docentsettings_categories_to_ignore');
	    $t_arr = split("\n", str_replace(" ", "-", str_replace(" \n", "\n", $templates)));
	    $templates = "'" . implode("','", $t_arr) . "'";
			$sql = "select cl_to, count(*) as C 
				from revision left join page on rev_page = page_id and page_namespace= 0 left join categorylinks on cl_from=page_id 
				where rev_user={$wgUser->getID()} and cl_to is not null and cl_to NOT IN ({$templates})
				group by cl_to having  C > 3 order by C desc limit 20;";
		$res = $dbr->query($sql);
		$wgOut->addHTML("
			<br/>Recommendations: Here are the categories you have edited most on wikiHow:<br/><br/><table width='100%' class='docentsettings'><tr>");
		$count = 0;
		while ($row = $dbr->fetchObject($res)) {
			$t = Title::makeTitle(NS_CATEGEORY, $row->cl_to);
			$wgOut->addHTML("<td> " . $this->getcheckbox($t->getText(), $already_subscribed, "({$row->C})") . "</td>");
			if ($count % 2 == 1) 
				$wgOut->addHTML("</tr><tr>");
			$count++;
		}
		$wgOut->addHTML("</tr></table><br/>
				<script type='text/javascript'>
	                function showhide(id) {
	                    var box = document.getElementById(id);
	                    var style = box.getAttribute('style');
	                    if (style == 'display: none;')
	                        box.setAttribute('style', 'display: inline;');
	                    else
	                        box.setAttribute('style', 'display: none;');
	                
	                }
	            </script>
				All categories:<br/><br/>
				<table width='100%' class='docentsettings'>            
			");
		$dbr->freeResult($res);
		foreach ($cats as $key=>$subcat) {
			if ($key == "") continue;
			$float = 'float: left;';
			if ($count % 2 == 0) $wgOut->addHTML("<tr>");
			$id = strtolower(str_replace(" ", "_", $key));
			$wgOut->addHTML("<td> " . $this->getcheckbox($key, $already_subscribed, ""));
			$wgOut->addHTML("<br/><br/>");
			if (sizeof($subcat) > 0) {
				$wgOut->addHTML("
					<div style='font-size: 80%'><a onclick='javascript:showhide(\"subcats_{$id}\");'>Show/Hide Subcategories</a></div>
				<br/><div id='subcats_{$id}' style='display:none;'>");
				foreach($subcat as $s) {
					$s = substr($s, 2);
					$i = 10 + 10 * substr_count($s, "*");
					$s = str_replace("*", "", $s);	
					$wgOut->addHTML("<div style='margin-bottom: 5px; width: 260px; border: 1px solid #eee; padding: 3px; padding-left: {$i}px;'>" . $this->getcheckbox($s, $already_subscribed, "") . " <br/></div>");
				}
				$wgOut->addHTML("</div>");
			}
			$wgOut->addHTML("</td>");
			if ($count % 2 == 1) $wgOut->addHTML("</tr>");
			$count++;
		}
		$wgOut->addHTML("</table>
			<input type='submit' style='font-weight: bold; font-size: 110%' accesskey='s' value='" . wfMsg('submit') ."' />
			</form>");
	}

	function getDocentsForCategory($title) {
		$html = "";
		$results = array();
		$dbr = wfGetDB(DB_SLAVE);
		$res = $dbr->select( array('docentcategories', 'user'), 
				array('user_id', 'user_name', 'user_real_name'), 
				array('dc_user=user_id', 'dc_to' => $title->getDBKey())
			);
		while ($row = $dbr->fetchObject($res)) {
			$u = new User();
			$u->setName($row->user_name);
			$u->setRealName($row->user_real_name);
			$results[] = $u;
		}
		$html = "<div id='docents'>";
		if (sizeof($results) > 0) {
			$html .= "<h2><span id='docent_header'>" . wfMsg('docents') . "</span></h2><p>\n";
			$first = true;
			foreach($results as $u) {
				$display = $u->getRealName() == "" ? $u->getName() : $u->getRealName();
				if ($first) {
					$first = false;
				} else {
					$html .= "<strong>&bull;</strong>";
				}
				$html .= " <a href='" . $u->getUserPage()->getFullURL() . "'>{$display}</a>\n";
			}
			$html .= "</p>";
		} else {
			$html .= "<h2><span id='docent_header'>" . wfMsg('docents') . "</span></h2><p>\n";
			$html .= wfMsg('no_docents');
		}

		$html .= "<div id='become_docent'><span>+</span>" . wfMsg('become_a_docent') . "</div></div>";
		return $html;
	}
}	
