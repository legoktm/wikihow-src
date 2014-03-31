<?php

if ( !defined( 'MEDIAWIKI' ) ) {
    exit(1);
}

$wgExtensionCredits['specialpage'][] = array(
    'name' => 'QG',
    'author' => 'Travis <travis@wikihow.com>',
    'description' => 'Provides a way of reviewing a set of edits separate from RC Patrol, such as removal of stub templates.',
);

$dir = dirname(__FILE__) . '/';

$wgSpecialPages['QG'] = 'QG';
$wgSpecialPages['QC'] = 'QG';
$wgAutoloadClasses['QG'] = $dir . 'QC.body.php';


$wgQCRules = array(
	"QCRuleTemplateChange" => "ArticleSaveComplete"
);

foreach ($wgQCRules as $rule=>$hook) {
	$wgAutoloadClasses[$rule] = $dir . 'QC.body.php';
}

# Internationalisation file
$wgExtensionMessagesFiles['QG'] = $dir . 'QC.i18n.php';

$wgChangedTemplatesToQC = array("stub", "format", "cleanup", "copyedit");
$wgTemplateChangedVotesRequired = array(
	"removed" => array("yes"=>1, "no"=>2), 
	"added" => array("yes"=>1, "no"=>2)
);

$wgAutoloadClasses["QCRule"] = $dir . 'QC.body.php';
$wgAutoloadClasses["QCRCPatrol"] = $dir . 'QC.body.php';
$wgAutoloadClasses["QCRuleIntroImage"] = $dir . 'QC.body.php';
$wgAutoloadClasses["QCRuleTip"] = $dir . 'QC.body.php';

$wgQCIntroImageVotesRequired = array ("yes"=>2, "no"=>2); 
$wgQCVideoChangeVotesRequired = array ("yes"=>2, "no"=>1); 
$wgQCRCPatrolVotesRequired = array ("yes"=>1, "no"=>1); 


$wgHooks["ArticleSaveComplete"][] = "wfCheckQC";
$wgHooks["MarkPatrolledBatchComplete"][] = array("wfCheckQCPatrols");

function wfCheckQC(&$article, &$user, $text, $summary, $minoredit, $watchthis, $sectionanchor, &$flags, $revision) {
	global $wgChangedTemplatesToQC;

	// if an article becomes a redirect, vanquish all previous qc entries
	if (preg_match("@^#REDIRECT@", $text)) {
		QCRule::markAllAsPatrolled($article->getTitle());
		return true;
	}	

	// check for bots
	$bots = WikihowUser::getBotIDs();
	if (in_array($user->getID(),$bots)) {
		return true;
	}

	// ignore reverted edits
	if (preg_match("@Reverted edits by@", $summary)) {
		return true; 
	}
	
	// check for intro image change, reverts are ok for this one
	// $l = new QCRuleIntroImage($revision, $article); 
	// $l->process();	

	// do the templates
	foreach ($wgChangedTemplatesToQC as $t) {
		wfDebug("QC: About to process template change $t\n");
		$l = new QCRuleTemplateChange($t, $revision, $article); 
		$l->process();	
	}

	// check for video changes 
	$l = new QCRuleVideoChange($revision, $article); 
	$l->process();	

	return true;
}

function wfCheckQCPatrols(&$article, &$rcids, &$user) {
	if ($article && $article->getTitle() && $article->getTitle()->getNamespace() == NS_MAIN) {
			$l = new QCRCPatrol($article, $rcids); // 
			$l->process();
	}
	return true; 
}

//$wgQCRulesToCheck = array("ChangedTemplate/Stub", "ChangedTemplate/Format", "ChangedTemplate/Cleanup", "ChangedTemplate/Copyedit", "ChangedIntroImage", "ChangedVideo", "RCPatrol"); 
$wgQCRulesToCheck = array("ChangedVideo", "RCPatrol", "NewTip");

$wgAvailableRights[] = 'qc';
$wgGroupPermissions['staff']['qc'] = true;

// Log page definitions
$wgLogTypes[]              = 'qc';
$wgLogNames['qc']          = 'qclogpage';
$wgLogHeaders['qc']        = 'qclogtext';

$wgHooks['ArticleDelete'][] = array("wfClearQCOnDelete"); 

function wfClearQCOnDelete($wikiPage) {
	try {	
		$dbw = wfGetDB(DB_MASTER); 
		$id = $wikiPage->getId();
		$dbw->delete("qc", array("qc_page"=>$id));
	} catch (Exception $e) {}
	return true;
}

