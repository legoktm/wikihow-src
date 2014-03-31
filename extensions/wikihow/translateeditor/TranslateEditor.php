<?php
if ( ! defined( 'MEDIAWIKI' ) )
	die();
    
$wgExtensionCredits['specialpage'][] = array(
	'name' => 'TranslateEditor',
	'author' => 'Gershon Bialer',
	'description' => 'Translators will have a special editor with language link' 
);

$wgSpecialPages['TranslateEditor'] = 'TranslateEditor';
$wgAutoloadClasses['TranslateEditor'] = dirname( __FILE__ ) . '/TranslateEditor.body.php';
$wgHooks['CustomEditor'][] = 'TranslateEditor::onCustomEdit';
$wgHooks['ArticleSave'][] = 'TranslateEditor::onSave';
$wgHooks['ArticleSaveComplete'][] = 'TranslateEditor::onSaveComplete';
$wgHooks['ShouldUseDrafts'][] = 'TranslateEditor::shouldUseDrafts';
